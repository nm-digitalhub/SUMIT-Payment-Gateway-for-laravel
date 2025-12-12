<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use OfficeGuy\LaravelSumitGateway\Contracts\Payable;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyToken;
use OfficeGuy\LaravelSumitGateway\Services\PaymentService;
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

        return view('officeguy::pages.checkout', [
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
     * @param Request $request
     * @param string|int $id
     * @return \Illuminate\Http\RedirectResponse|\Illuminate\Http\JsonResponse
     */
    public function process(Request $request, string|int $id)
    {
        // Check if feature is enabled
        if (!$this->isEnabled()) {
            abort(404, __('Public checkout is not enabled'));
        }

        $payable = $this->resolvePayable($request, $id);

        if (!$payable) {
            abort(404, __('Order not found'));
        }

        $user = auth()->user();
        $client = $user?->client;

        $rules = [
            'customer_name' => 'required|string|max:255',
            'customer_email' => 'required|email|max:255',
            'customer_phone' => 'required|string|max:50',
            'payment_method' => 'required|in:card,bit',
            'payments_count' => 'nullable|integer|min:1|max:36',
            'payment_token' => 'nullable|string',
            'save_card' => 'nullable|boolean',
            'customer_company' => 'nullable|string|max:255',
            'customer_vat' => 'nullable|string|max:50',
            'customer_address' => 'nullable|string|max:255',
            'customer_address2' => 'nullable|string|max:255',
            'customer_city' => 'nullable|string|max:120',
            'customer_state' => 'nullable|string|max:120',
            'customer_country' => 'nullable|string|max:2',
            'customer_postal' => 'nullable|string|max:20',
            'citizen_id' => 'nullable|string|max:50',
            // Guest registration fields
            'password' => 'nullable|string|confirmed|min:8',
            'terms' => 'nullable|accepted',
        ];

        // Require address fields if missing in profile
        if (empty($client?->client_address)) {
            $rules['customer_address'] = 'required|string|max:255';
        }
        if (empty($client?->client_city)) {
            $rules['customer_city'] = 'required|string|max:120';
        }
        if (empty($client?->client_country)) {
            $rules['customer_country'] = 'required|string|max:2';
        }
        if (empty($client?->client_postal_code)) {
            $rules['customer_postal'] = 'required|string|max:20';
        }

        $validated = $request->validate($rules);

        // Handle guest registration
        if (!$user && !empty($validated['password'])) {
            // Check if terms were accepted
            if (empty($validated['terms'])) {
                return back()->withErrors(['terms' => __('You must accept the Terms & Conditions to create an account')])->withInput();
            }

            // Check if email already exists
            if (\App\Models\User::where('email', $validated['customer_email'])->exists()) {
                return back()->withErrors(['customer_email' => __('This email is already registered. Please login instead.')])->withInput();
            }

            // Parse name into first_name and last_name
            $nameParts = explode(' ', trim($validated['customer_name']), 2);
            $firstName = $nameParts[0] ?? '';
            $lastName = $nameParts[1] ?? '';

            // Create new user
            $user = \App\Models\User::create([
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
                \Illuminate\Support\Facades\Log::warning('Failed to send welcome notification', [
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

        // Handle card payment
        return $this->processCardPayment($payable, $validated, $paymentsCount, $request);
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
     * Get saved payment tokens for the current user.
     *
     * @return \Illuminate\Support\Collection
     */
    protected function getSavedTokens()
    {
        if (!auth()->check() || !$this->settings()->get('support_tokens', false)) {
            return collect();
        }

        return OfficeGuyToken::where('owner_type', get_class(auth()->user()))
            ->where('owner_id', auth()->id())
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
    protected function processCardPayment(Payable $payable, array $validated, int $paymentsCount, Request $request)
    {
        $pciMode = config('officeguy.pci', config('officeguy.pci_mode', 'no'));
        $redirectMode = $pciMode === 'redirect';

        // Prepare extra parameters for redirect mode
        $extra = [];
        if ($redirectMode) {
            $extra['RedirectURL'] = route(config('officeguy.routes.success', 'checkout.success'), [
                'order' => $payable->getPayableId()
            ]);
            $extra['CancelRedirectURL'] = route(config('officeguy.routes.failed', 'checkout.failed'), [
                'order' => $payable->getPayableId()
            ]);
        }

        // Check for existing token
        $token = null;
        $tokenId = $validated['payment_token'] ?? null;
        if ($tokenId && $tokenId !== 'new') {
            $token = OfficeGuyToken::find($tokenId);
        }

        // Process the charge
        $result = PaymentService::processCharge(
            $payable,
            $paymentsCount,
            false, // recurring
            $redirectMode,
            $token,
            $extra
        );

        if ($result['success'] === true) {
            // Handle redirect flow
            if ($redirectMode && isset($result['redirect_url'])) {
                return redirect()->away($result['redirect_url']);
            }

            // Save token if requested
            if (($validated['save_card'] ?? false) && auth()->check() && isset($result['response']['Data']['Token'])) {
                $this->saveCardToken($result['response']['Data'], auth()->user());
            }

            return redirect()->route(
                config('officeguy.routes.success', 'checkout.success'),
                ['order' => $payable->getPayableId()]
            )->with('success', __('Payment completed successfully'));
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
     * @param mixed $user
     * @return void
     */
    protected function saveCardToken(array $data, $user): void
    {
        // Try to use createFromApiResponse if CardToken is present
        if (isset($data['CardToken'])) {
            try {
                OfficeGuyToken::createFromApiResponse($user, ['Data' => $data]);
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
            'owner_type' => get_class($user),
            'owner_id' => $user->getKey(),
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
}
