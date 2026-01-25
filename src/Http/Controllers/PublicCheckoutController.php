<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;
use OfficeGuy\LaravelSumitGateway\Actions\PrepareCheckoutIntentAction;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Http\Requests\CheckoutRequest;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\CheckoutIntentResolver;
use OfficeGuy\LaravelSumitGateway\Services\CheckoutViewResolver;
use OfficeGuy\LaravelSumitGateway\Services\OfficeGuyApi;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
use OfficeGuy\LaravelSumitGateway\Services\SecureSuccessUrlGenerator;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;
use OfficeGuy\LaravelSumitGateway\Support\ModelPayableWrapper;
use OfficeGuy\LaravelSumitGateway\Support\OrderResolver;

/**
 * Public Checkout Page Controller
 *
 * Provides a public checkout page for any Payable model.
 * This allows developers to link any model implementing Payable
 * to a customizable checkout experience.
 */
class PublicCheckoutController extends Controller
{
    /**
     * Get the settings service instance.
     */
    protected function settings(): SettingsService
    {
        return app(SettingsService::class);
    }

    /**
     * Check if public checkout is enabled (via Admin Panel or config).
     */
    protected function isEnabled(): bool
    {
        $defaultValue = config('officeguy.routes.enable_public_checkout', false);

        return (bool) $this->settings()->get('enable_public_checkout', $defaultValue);
    }

    /**
     * Display the public checkout page for a given payable model.
     *
     * @param Request $request
     * @param string|int $id The payable model ID
     * @return View
     */
    public function show(Request $request, string|int $id): View
    {
        // Check if feature is enabled
        if (!$this->isEnabled()) {
            abort(404, __('Public checkout is not enabled'));
        }

        $payable = $this->resolvePayable($request, $id);

        if (!$payable) {
            abort(404, __('Order not found'));
        }

        $amount = $payable->getPayableAmount();
        $currency = $payable->getPayableCurrency();

        // Prefill from query params -> payable -> client -> authenticated user
        $user = auth()->user();
        if (!$user && class_exists(\Filament\Facades\Filament::class)) {
            $user = \Filament\Facades\Filament::auth()->user();
        }
        $client = $user?->client;

        $prefillName = $request->query('name')
            ?? $payable->getCustomerName()
            ?: ($client->name ?? null)
            ?: ($user ? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')) ?: $user->name : null);

        $prefillEmail = $request->query('email')
            ?? $payable->getCustomerEmail()
            ?: ($client->email ?? null)
            ?: ($user->email ?? null);

        $prefillPhone = $request->query('phone')
            ?? $payable->getCustomerPhone()
            ?: ($client->phone ?? null)
            ?: ($user->phone ?? null);

        $prefillCitizenId = $request->query('id')
            ?? $user?->id_number
            ?? $user?->vat_number
            ?? $client?->id_number
            ?? $client?->vat_number
            ?? null;

        $prefillCompany = $client?->company ?? $user?->company;
        $prefillVat = $client?->vat_number ?? $user?->vat_number;
        $prefillAddress = $client?->client_address ?? $client?->address ?? $user?->address;
        $prefillAddress2 = $client?->client_address2 ?? null;
        $prefillCity = $client?->client_city ?? $client?->city ?? $user?->city;
        $prefillState = $client?->client_state ?? $client?->state ?? $user?->state;
        $prefillCountry = $client?->client_country ?? $client?->country ?? $user?->country ?? 'IL';
        $prefillPostal = $client?->client_postal_code ?? $client?->postal_code ?? $user?->postal_code;

        // Resolve dynamic checkout template based on PayableType
        $resolver = app(CheckoutViewResolver::class);
        $view = $resolver->resolve($payable);

        return view($view, [
            'payable' => $payable,
            'settings' => $this->getSettings(),
            'maxPayments' => PaymentService::getMaximumPayments($amount),
            'bitEnabled' => (bool) $this->settings()->get('bit_enabled', false),
            'supportTokens' => (bool) $this->settings()->get('support_tokens', false),
            'savedTokens' => $this->getSavedTokens(),
            'currency' => $currency,
            'currencySymbol' => $this->getCurrencySymbol($currency),
            'checkoutUrl' => route('officeguy.public.checkout.process', ['id' => $id]),
            'prefillName' => $prefillName,
            'prefillEmail' => $prefillEmail,
            'prefillPhone' => $prefillPhone,
            'prefillCitizenId' => $prefillCitizenId,
            'prefillCompany' => $prefillCompany,
            'prefillVat' => $prefillVat,
            'prefillAddress' => $prefillAddress,
            'prefillAddress2' => $prefillAddress2,
            'prefillCity' => $prefillCity,
            'prefillState' => $prefillState,
            'prefillCountry' => $prefillCountry,
            'prefillPostal' => $prefillPostal,
        ]);
    }

    /**
     * Process the checkout form submission.
     *
     * @param CheckoutRequest $request
     * @param string|int $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function process(CheckoutRequest $request, string|int $id)
    {
        // DEBUG: Log checkout attempt
        Log::info('🛒 Checkout process started', [
            'payable_id' => $id,
            'has_og_token' => $request->has('og-token'),
            'og_token_value' => $request->input('og-token') ? '***' . substr($request->input('og-token'), -4) : null,
            'payment_token' => $request->input('payment_token'),
            'payment_method' => $request->input('payment_method'),
            'accept_terms' => $request->input('accept_terms'),
            'all_keys' => array_keys($request->all()),
        ]);

        // Check if feature is enabled
        if (!$this->isEnabled()) {
            abort(404, __('Public checkout is not enabled'));
        }

        $payable = $this->resolvePayable($request, $id);

        if (!$payable) {
            abort(404, __('Order not found'));
        }

        // ✅ Set payable for conditional validation in CheckoutRequest
        $request->setPayable($payable);

        // ✅ Validation already done by CheckoutRequest type-hint
        // Access validated data via: $request->validated()

        $user = auth()->user();
        $client = $user?->client;

        // Handle guest registration (unchanged - Phase 3)
        $validated = $request->validated();
        if (!$user && !empty($validated['password'])) {
            // Check if terms were accepted
            if (empty($validated['accept_terms'])) {
                return back()->withErrors(['accept_terms' => __('You must accept the Terms & Conditions to create an account')])->withInput();
            }

            // Resolve user model from container binding
            $userModel = app('officeguy.customer_model') ?? \App\Models\Client::class;

            // Check if email already exists
            if ($userModel::where('email', $validated['customer_email'])->exists()) {
                return back()->withErrors(['customer_email' => __('This email is already registered. Please login instead.')])->withInput();
            }

            // Parse name into first_name and last_name
            $nameParts = explode(' ', trim($validated['customer_name']), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            // Create new user
            $user = $userModel::create([
                'name' => $validated['customer_name'],
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $validated['customer_email'],
                'phone' => $validated['customer_phone'],
                'company' => $validated['customer_company'] ?? null,
                'address' => $validated['customer_address'] ?? null,
                'address2' => $validated['customer_address2'] ?? null,
                'city' => $validated['customer_city'] ?? null,
                'state' => $validated['customer_state'] ?? null,
                'country' => $validated['customer_country'] ?? 'IL',
                'postal_code' => $validated['customer_postal'] ?? null,
                'vat_number' => $validated['customer_vat'] ?? null,
                'id_number' => $validated['citizen_id'] ?? null,
                'password' => \Illuminate\Support\Facades\Hash::make($validated['password']),
                'email_verified_at' => now(), // Auto-verify email
                'newsletter_subscribed' => false,
            ]);

            // Fire Registered event
            event(new \Illuminate\Auth\Events\Registered($user));

            // Send welcome notification
            try {
                $user->notify(new \App\Notifications\WelcomeNotification);
            } catch (\Exception $e) {
                Log::warning('Failed to send welcome notification', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
            }

            // Log the user in
            \Illuminate\Support\Facades\Auth::login($user);

            // Refresh user and client references
            $user = auth()->user();
            $client = $user?->client;
        }

        $paymentsCount = max(1, (int) ($validated['payments_count'] ?? 1));
        $paymentMethod = $validated['payment_method'];

$pciMode = config('officeguy.pci', config('officeguy.pci_mode', 'no'));

// Extract payment_token for validation (handle empty string as "new")
$paymentToken = $validated['payment_token'] ?? null;

if (
    $paymentMethod === 'card'
    && (empty($paymentToken) || $paymentToken === 'new')
    && $pciMode !== 'redirect'
    && !$request->filled('og-token')
) {
    return back()
        ->withInput()
        ->withErrors([
            'payment' => __('Card token was not generated. Please try again.')
        ]);
}

        // Handle Bit payment
        if ($paymentMethod === 'bit') {
            return $this->processBitPayment($payable, $validated);
        }

        // Persist profile data if missing
        $dirty = false;
        if ($client) {
            if (empty($client->client_name) && !empty($validated['customer_name'])) {
                $client->client_name = $validated['customer_name'];
                $dirty = true;
            }
            if (empty($client->client_email) && !empty($validated['customer_email'])) {
                $client->client_email = $validated['customer_email'];
                $dirty = true;
            }
            if (empty($client->client_phone) && !empty($validated['customer_phone'])) {
                $client->client_phone = $validated['customer_phone'];
                $dirty = true;
            }
            if (empty($client->id_number) && !empty($validated['citizen_id'] ?? null)) {
                $client->id_number = $validated['citizen_id'];
                $dirty = true;
            }
            if (empty($client->company) && !empty($validated['customer_company'] ?? null)) {
                $client->company = $validated['customer_company'];
                $dirty = true;
            }
            if (empty($client->vat_number) && !empty($validated['customer_vat'] ?? null)) {
                $client->vat_number = $validated['customer_vat'];
                $dirty = true;
            }
            if (empty($client->client_address) && !empty($validated['customer_address'] ?? null)) {
                $client->client_address = $validated['customer_address'];
                $dirty = true;
            }
            if (empty($client->client_address2) && !empty($validated['customer_address2'] ?? null)) {
                $client->client_address2 = $validated['customer_address2'];
                $dirty = true;
            }
            if (empty($client->client_city) && !empty($validated['customer_city'] ?? null)) {
                $client->client_city = $validated['customer_city'];
                $dirty = true;
            }
            if (empty($client->client_state) && !empty($validated['customer_state'] ?? null)) {
                $client->client_state = $validated['customer_state'];
                $dirty = true;
            }
            if (empty($client->client_country) && !empty($validated['customer_country'] ?? null)) {
                $client->client_country = $validated['customer_country'];
                $dirty = true;
            }
            if (empty($client->client_postal_code) && !empty($validated['customer_postal'] ?? null)) {
                $client->client_postal_code = $validated['customer_postal'];
                $dirty = true;
            }
            if ($dirty) {
                $client->save();
            }
        }

        // ✅ NEW: Prepare checkout intent + service data
        // NOW customer data is complete (after guest user creation and profile updates)
        $intent = app(PrepareCheckoutIntentAction::class)->execute($request, $payable);
        $resolvedIntent = CheckoutIntentResolver::resolve($intent);

        // Handle card payment
        return $this->processCardPayment($payable, $validated, $paymentsCount, $request, $resolvedIntent);
    }

    /**
     * Resolve the payable model from the request.
     *
     * @param Request $request
     * @param string|int $id
     * @return Payable|null
     */
    protected function resolvePayable(Request $request, string|int $id): ?Payable
    {
        // Check for custom resolver in the request (allows per-route customization)
        $customResolver = $request->route('resolver');
        if ($customResolver && is_callable($customResolver)) {
            $resolved = call_user_func($customResolver, $id);
            if ($resolved instanceof Payable) {
                return $resolved;
            }
            // Wrap non-Payable model with field mapping
            if ($resolved instanceof \Illuminate\Database\Eloquent\Model) {
                return ModelPayableWrapper::wrap($resolved);
            }
        }

        // Check for model configured in Admin Panel settings
        $payableModel = $this->settings()->get('payable_model');
        if ($payableModel && class_exists($payableModel)) {
            $model = $payableModel::find($id);
            if ($model) {
                // If model implements Payable, use it directly
                if ($model instanceof Payable) {
                    return $model;
                }
                // Otherwise, wrap it with field mapping from Admin Panel
                return ModelPayableWrapper::wrap($model);
            }
        }

        // Fall back to default order resolver
        return OrderResolver::resolve($id);
    }

    /**
     * Get saved payment tokens for the current client.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getSavedTokens()
    {
        if (!auth()->check() || !$this->settings()->get('support_tokens', false)) {
            return collect();
        }

        $client = auth()->user()->client;

        if (!$client) {
            return collect();
        }

        return OfficeGuyToken::where('owner_type', 'client')
            ->where('owner_id', $client->id)
            ->where('gateway_id', 'officeguy')
            ->whereNull('deleted_at')
            ->get();
    }

    /**
     * Get the settings array for the view.
     *
     * @return array
     */
    protected function getSettings(): array
    {
        $settings = $this->settings();

        return [
            'pci_mode' => $settings->get('pci', $settings->get('pci_mode', 'no')),
            'cvv_mode' => $settings->get('cvv', 'required'),
            'citizen_id_mode' => $settings->get('citizen_id', 'required'),
            'company_id' => $settings->get('company_id', ''),
            'public_key' => $settings->get('public_key', ''),
        ];
    }

    /**
     * Get the currency symbol for a given currency code.
     *
     * @param string $currency
     * @return string
     */
    protected function getCurrencySymbol(string $currency): string
    {
        $symbols = [
            'ILS' => '₪',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'CAD' => 'C$',
            'AUD' => 'A$',
            'CHF' => 'CHF ',
            'JPY' => '¥',
        ];

        return $symbols[$currency] ?? $currency . ' ';
    }

    /**
     * Process a card payment.
     *
     * @param Payable $payable
     * @param array $validated
     * @param int $paymentsCount
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function processCardPayment(
        Payable $payable,
        array $validated,
        int $paymentsCount,
        Request $request,
        \OfficeGuy\LaravelSumitGateway\DataTransferObjects\ResolvedPaymentIntent $resolvedIntent
    )
    {
        // 🛡️ IDEMPOTENCY PROTECTION: Prevent double-charging on page refresh
        $existingTransaction = \OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction::where('order_id', $payable->getPayableId())
            ->where('status', 'completed')
            ->first();

        if ($existingTransaction) {
            \Illuminate\Support\Facades\Log::info('🛡️ Prevented duplicate charge - transaction already completed', [
                'order_id' => $payable->getPayableId(),
                'existing_transaction_id' => $existingTransaction->id,
                'existing_payment_id' => $existingTransaction->payment_id,
                'amount' => $existingTransaction->amount,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return $this->redirectSuccess($payable, __('This order has already been paid'));
        }

        // 🐛 DEBUG: Log incoming payment data
        \Log::info('🎯 [PublicCheckoutController] processCardPayment called', [
            'pci_mode' => $resolvedIntent->pciMode,
            'has_og_token_in_request' => $request->has('og-token'),
            'has_og_token_in_validated' => isset($validated['og-token']),
            'og_token_value' => $request->input('og-token') ?: 'EMPTY/NULL',
            'payment_method' => $validated['payment_method'] ?? null,
            'all_request_keys' => array_keys($request->all()),
        ]);

        // Process the charge
        $result = PaymentService::processResolvedIntent($resolvedIntent);

        if ($result['success'] === true) {
            // Handle redirect flow
            if ($resolvedIntent->redirectMode && isset($result['redirect_url'])) {
                return redirect()->away($result['redirect_url']);
            }

            // Save token if requested
            $client = auth()->user()?->client;
            if (($validated['save_card'] ?? false) && $client && isset($result['response']['Data']['Token'])) {
                $this->saveCardToken($result['response']['Data'], $client);
            }

            return $this->redirectSuccess($payable, __('Payment completed successfully'));
        }

        $errorMessage = $result['message'] ?? __('Payment failed. Please try again.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => false,
                'message' => $errorMessage,
            ], 422);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $errorMessage);
    }

    /**
     * Process a Bit payment.
     *
     * @param Payable $payable
     * @param array $validated
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    protected function processBitPayment(Payable $payable, array $validated)
    {
        // Bit payments are handled via redirect using BitPaymentService::processOrder
        $successUrl = route(config('officeguy.routes.success', 'checkout.success'), [
            'order' => $payable->getPayableId()
        ]);
        $cancelUrl = route(config('officeguy.routes.failed', 'checkout.failed'), [
            'order' => $payable->getPayableId()
        ]);
        $webhookUrl = route('officeguy.webhook.bit');

        $result = \OfficeGuy\LaravelSumitGateway\Services\BitPaymentService::processOrder(
            $payable,
            $successUrl,
            $cancelUrl,
            $webhookUrl
        );

        if ($result['success'] && isset($result['redirect_url'])) {
            return redirect()->away($result['redirect_url']);
        }

        return redirect()->back()
            ->withInput()
            ->with('error', $result['message'] ?? __('Could not initiate Bit payment'));
    }

    /**
     * Save a card token for future use.
     *
     * @param array $data
     * @param mixed $client
     * @return void
     */
    protected function saveCardToken(array $data, $client): void
    {
        // Try to use createFromApiResponse if CardToken is present
        if (isset($data['CardToken'])) {
            try {
                OfficeGuyToken::createFromApiResponse($client, ['Data' => $data]);
                return;
            } catch (\RuntimeException $e) {
                // Fall through to manual creation
            }
        }

        // Fallback to manual creation for other token formats
        $token = $data['Token'] ?? $data['CardToken'] ?? null;
        if (!$token) {
            return;
        }

        OfficeGuyToken::create([
            'owner_type' => 'client',
            'owner_id' => $client->getKey(),
            'gateway_id' => 'officeguy',
            'token' => $token,
            'last_four' => substr($data['CardNumber'] ?? $data['CardPattern'] ?? '', -4),
            'card_type' => $data['CardType'] ?? $data['Brand'] ?? null,
            'expiry_month' => isset($data['ExpirationMonth'])
                ? str_pad((string) $data['ExpirationMonth'], 2, '0', STR_PAD_LEFT)
                : null,
            'expiry_year' => isset($data['ExpirationYear'])
                ? (string) $data['ExpirationYear']
                : null,
        ]);
    }

    /**
     * Display checkout page for Package model (hosting/domain/SSL).
     *
     * @param Request $request
     * @param string|int $id Package ID
     * @return View
     */
    public function showPackage(Request $request, string|int $id): View
    {
        // Set resolver for Package model
        $request->route()->setParameter('resolver', function($id) {
            $modelClass = 'App\\Models\\Package';
            if (class_exists($modelClass)) {
                return $modelClass::find($id);
            }
            return null;
        });

        return $this->show($request, $id);
    }

    /**
     * Process payment for Package model.
     *
     * @param Request $request
     * @param string|int $id Package ID
     * @return mixed
     */
    public function processPackage(Request $request, string|int $id)
    {
        // Set resolver for Package model
        $request->route()->setParameter('resolver', function($id) {
            $modelClass = 'App\\Models\\Package';
            if (class_exists($modelClass)) {
                return $modelClass::find($id);
            }
            return null;
        });

        return $this->process($request, $id);
    }

    /**
     * Display checkout page for MayaNetEsimProduct model.
     *
     * @param Request $request
     * @param string|int $id eSIM Product ID
     * @return View
     */
    public function showEsim(Request $request, string|int $id): View
    {
        // Set resolver for eSIM model
        $request->route()->setParameter('resolver', function($id) {
            $modelClass = 'App\\Models\\MayaNetEsimProduct';
            if (class_exists($modelClass)) {
                return $modelClass::find($id);
            }
            return null;
        });

        return $this->show($request, $id);
    }

    /**
     * Process payment for MayaNetEsimProduct model.
     *
     * @param Request $request
     * @param string|int $id eSIM Product ID
     * @return mixed
     */
    public function processEsim(Request $request, string|int $id)
    {
        // Set resolver for eSIM model
        $request->route()->setParameter('resolver', function($id) {
            $modelClass = 'App\\Models\\MayaNetEsimProduct';
            if (class_exists($modelClass)) {
                return $modelClass::find($id);
            }
            return null;
        });

        return $this->process($request, $id);
    }

    /**
     * Redirect to success page using secure URL generation
     *
     * @param Payable|null $payable The Payable entity (Order, Invoice, etc.)
     * @param string $message Success message
     * @return \Illuminate\Http\RedirectResponse
     */
    protected function redirectSuccess(?Payable $payable, string $message = 'Payment completed successfully')
    {
        // If payable entity is available and secure success is enabled, generate secure URL
        if ($payable) {
            $generator = app(SecureSuccessUrlGenerator::class);

            if ($generator->isEnabled()) {
                $secureUrl = $generator->generate($payable);

                OfficeGuyApi::writeToLog(
                    'Redirecting to secure success page with token for payable #' . $payable->getPayableId(),
                    'debug'
                );

                return redirect()->away($secureUrl);
            }
        }

        // Fallback: Legacy redirect (if secure URL is disabled or payable unavailable)
        OfficeGuyApi::writeToLog(
            'Using legacy success redirect for payable #' . ($payable ? $payable->getPayableId() : 'unknown'),
            'debug'
        );

        $route = config('officeguy.routes.success', 'checkout.success');

        if ($route && Route::getRoutes()->getByName($route)) {
            return redirect()->route($route, ['order' => $payable ? $payable->getPayableId() : null])
                ->with('success', $message);
        }

        return redirect()->to(url('/'))->with('success', $message);
    }
}
