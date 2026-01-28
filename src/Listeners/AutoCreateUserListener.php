<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Listeners;

use App\Mail\GuestWelcomeWithPasswordMail;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use OfficeGuy\LaravelSumitGateway\Events\PaymentCompleted;

/**
 * Auto-Create User Listener
 *
 * Listens to PaymentCompleted event and automatically creates a User account
 * for guest users after successful payment.
 *
 * Features:
 * - Creates User with temporary password (12 chars, 7 days expiry)
 * - Creates Client record linked to User
 * - Sends welcome email with login credentials
 * - Links Order to User and Client
 * - Handles existing users gracefully
 *
 * @version 1.14.0
 */
class AutoCreateUserListener
{
    /**
     * Handle the PaymentCompleted event.
     */
    public function handle(PaymentCompleted $event): void
    {
        // Check if feature is enabled
        if (! config('officeguy.auto_create_guest_user', true)) {
            return;
        }

        try {
            // 1. Get the Order/Payable
            $order = $this->resolveOrder($event->orderId);

            if (! $order) {
                Log::warning('AutoCreateUser: Order not found', [
                    'order_id' => $event->orderId,
                ]);

                return;
            }

            // 2. Check if guest user (user_id is null)
            if ($order->user_id !== null) {
                // User already exists, skip
                Log::debug('AutoCreateUser: Order already has user, skipping', [
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                ]);

                return;
            }

            // 3. Check if email is provided
            if (empty($order->client_email)) {
                Log::warning('AutoCreateUser: No email in order', [
                    'order_id' => $order->id,
                ]);

                return;
            }

            // 4. Check if user already exists with this email
            $existingUser = User::where('email', $order->client_email)->first();

            if ($existingUser) {
                // Link order to existing user
                $this->linkOrderToExistingUser($order, $existingUser);

                return;
            }

            // 5. Create new user
            $user = $this->createUserFromOrder($order);

            // 6. Generate temporary password
            $temporaryPassword = $this->generateTemporaryPassword($user);

            // 7. Send email with temporary password
            $this->sendWelcomeEmail($user, $temporaryPassword, $order);

            // 8. Create Client record
            $client = Client::createFromUser($user);

            // 9. Link order to user and client
            $order->update([
                'user_id' => $user->id,
                'client_id' => $client->id,
            ]);

            // 10. Log success
            Log::info('AutoCreateUser: User created successfully', [
                'order_id' => $order->id,
                'user_id' => $user->id,
                'client_id' => $client->id,
                'email' => $user->email,
                'temporary_password_expires_at' => $user->temporary_password_expires_at,
            ]);

        } catch (\Exception $e) {
            Log::error('AutoCreateUser: Failed to create user', [
                'order_id' => $event->orderId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    /**
     * Resolve the order from orderId.
     *
     * @return mixed|null
     */
    protected function resolveOrder(string | int $orderId)
    {
        // Try to find Order by ID
        $orderClass = config('officeguy.order.model', \App\Models\Order::class);

        if (class_exists($orderClass)) {
            return $orderClass::find($orderId);
        }

        return null;
    }

    /**
     * Link order to existing user.
     *
     * @param  mixed  $order
     */
    protected function linkOrderToExistingUser($order, User $user): void
    {
        $client = $user->client;

        if (! $client) {
            $client = Client::createFromUser($user);
        }

        $order->update([
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);

        Log::info('AutoCreateUser: Linked order to existing user', [
            'order_id' => $order->id,
            'user_id' => $user->id,
            'client_id' => $client->id,
        ]);
    }

    /**
     * Create user from order data.
     *
     * @param  mixed  $order
     */
    protected function createUserFromOrder($order): User
    {
        // Parse name into first_name and last_name
        $fullName = $order->client_name ?? $order->billing_name ?? 'Guest User';
        $nameParts = explode(' ', trim($fullName), 2);
        $firstName = $nameParts[0] ?? '';
        $lastName = $nameParts[1] ?? '';

        // Ensure country is 2-char ISO code
        $country = $order->billing_country ?? 'IL';
        if (strlen($country) > 2) {
            $country = 'IL'; // Default to Israel if invalid
        }

        // Get expiry days from config
        $expiryDays = (int) config('officeguy.guest_password_expiry_days', 7);

        return User::create([
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
            'vat_number' => null, // Not available in order
            'id_number' => null, // Not available in order
            'password' => '', // Will be set by generateTemporaryPassword
            'role' => \App\Enums\UserRole::CLIENT,
            'email_verified_at' => now(),
            'has_temporary_password' => true,
            'temporary_password_expires_at' => now()->addDays($expiryDays),
            'temporary_password_created_by' => null, // System-generated
        ]);
    }

    /**
     * Generate temporary password for user.
     *
     * @return string The plain text temporary password
     */
    protected function generateTemporaryPassword(User $user): string
    {
        // Generate random 12-character password
        $temporaryPassword = Str::random(12);

        // Update user with hashed password
        $user->update([
            'password' => Hash::make($temporaryPassword),
        ]);

        return $temporaryPassword;
    }

    /**
     * Send welcome email with temporary password.
     *
     * @param  mixed  $order
     */
    protected function sendWelcomeEmail(User $user, string $password, $order): void
    {
        try {
            Mail::to($user->email)->queue(
                new GuestWelcomeWithPasswordMail($user, $password, $order)
            );

            Log::info('AutoCreateUser: Welcome email queued', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);
        } catch (\Exception $e) {
            Log::error('AutoCreateUser: Failed to send welcome email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            // Don't throw - user was created successfully, email failure is non-critical
        }
    }
}
