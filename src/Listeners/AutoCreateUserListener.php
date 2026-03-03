<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use OfficeGuy\LaravelSumitGateway\Events\GuestUserCreated;
use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Auto-Create User Listener (Phase 4: config-only, no host references)
 *
 * Listens to PaymentCompleted and creates a guest user/customer using config:
 * - officeguy.order.model: order model class
 * - officeguy.guest_user_model or officeguy.staff_model: user model class
 * - officeguy.customer_model (container): customer model class
 * - officeguy.guest_user_role: role value (string, no enum)
 *
 * Fires GuestUserCreated so the host can send welcome email.
 */
class AutoCreateUserListener
{
    public function handle(PaymentCompleted $event): void
    {
        if (! config('officeguy.auto_create_guest_user', true)) {
            return;
        }

        try {
            $order = $this->resolveOrder($event->orderId);
            if (! $order) {
                Log::warning('AutoCreateUser: Order not found', ['order_id' => $event->orderId]);

                return;
            }

            if ($order->user_id !== null) {
                Log::debug('AutoCreateUser: Order already has user, skipping', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                ]);

                return;
            }

            if (empty($order->client_email)) {
                Log::warning('AutoCreateUser: No email in order', ['order_id' => $order->id]);

                return;
            }

            $userModelClass = config('officeguy.guest_user_model') ?: config('officeguy.staff_model');
            if (! $userModelClass || ! class_exists($userModelClass)) {
                Log::warning('AutoCreateUser: User model not configured (officeguy.guest_user_model / staff_model)');

                return;
            }

            $existingUser = $userModelClass::where('email', $order->client_email)->first();
            if ($existingUser) {
                $this->linkOrderToExistingUser($order, $existingUser);

                return;
            }

            $user = $this->createUserFromOrder($order, $userModelClass);
            if (! $user) {
                return;
            }

            $temporaryPassword = $this->generateTemporaryPassword($user);
            $client = $this->createOrGetCustomer($user);
            if ($client) {
                $order->update([
                    'user_id' => $user->id,
                    'client_id' => $client->id,
                ]);
            } else {
                $order->update(['user_id' => $user->id]);
            }

            event(new GuestUserCreated($user, $temporaryPassword, $order));

            Log::info('AutoCreateUser: User created', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('AutoCreateUser: Failed', [
                'order_id' => $event->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    protected function resolveOrder(string | int $orderId): ?object
    {
        $orderClass = config('officeguy.order.model');
        if (! $orderClass || ! class_exists($orderClass)) {
            return null;
        }

        return $orderClass::find($orderId);
    }

    protected function linkOrderToExistingUser(object $order, object $user): void
    {
        $customerModel = app('officeguy.customer_model');
        $client = $user->client ?? null;
        if (! $client && $customerModel && method_exists($customerModel, 'createFromUser')) {
            $client = $customerModel::createFromUser($user);
        }
        $order->update([
            'user_id' => $user->id,
            'client_id' => $client->id ?? null,
        ]);
        Log::info('AutoCreateUser: Linked order to existing user', [
            'order_id' => $order->id,
            'user_id' => $user->id,
        ]);
    }

    protected function createUserFromOrder(object $order, string $userModelClass): ?object
    {
        $fullName = $order->client_name ?? $order->billing_name ?? 'Guest User';
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';
        $country = $order->billing_country ?? 'IL';
        if (strlen($country) > 2) {
            $country = 'IL';
        }
        $expiryDays = (int) config('officeguy.guest_password_expiry_days', 7);

        $attrs = [
            'name' => $fullName,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $order->client_email,
            'phone' => $order->client_phone ?? $order->billing_phone,
            'company' => $order->billing_name ?? null,
            'address' => $order->billing_address ?? null,
            'city' => $order->billing_city ?? null,
            'state' => $order->billing_state ?? null,
            'country' => $country,
            'postal_code' => $order->billing_zip ?? null,
            'vat_number' => null,
            'id_number' => null,
            'password' => '',
            'email_verified_at' => now(),
            'has_temporary_password' => true,
            'temporary_password_expires_at' => now()->addDays($expiryDays),
            'temporary_password_created_by' => null,
        ];
        if (property_exists($userModelClass, 'role') || in_array('role', $userModelClass::getFillable())) {
            $attrs['role'] = config('officeguy.guest_user_role', 'client');
        }

        return $userModelClass::create($attrs);
    }

    protected function generateTemporaryPassword(object $user): string
    {
        $password = Str::random(12);
        $user->update(['password' => Hash::make($password)]);

        return $password;
    }

    /**
     * Create customer from user if customer model supports createFromUser; otherwise return null.
     */
    protected function createOrGetCustomer(object $user): ?object
    {
        $customerModel = app('officeguy.customer_model');
        if (! $customerModel || ! method_exists($customerModel, 'createFromUser')) {
            return null;
        }

        return $customerModel::createFromUser($user);
    }
}
