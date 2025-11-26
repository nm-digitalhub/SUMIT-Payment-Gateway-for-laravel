<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * WebhookService - Sends custom webhook notifications for payment events
 *
 * This service allows developers to receive notifications for various events
 * without creating custom Listeners. Configure webhook URLs in the Admin Panel.
 */
class WebhookService
{
    protected SettingsService $settings;

    public function __construct(SettingsService $settings)
    {
        $this->settings = $settings;
    }

    /**
     * Send a webhook notification for the given event.
     *
     * @param string $event The event name (e.g., 'payment_completed')
     * @param array $payload The data to send
     * @return bool Whether the webhook was sent successfully
     */
    public function send(string $event, array $payload): bool
    {
        $url = $this->settings->get("webhook_{$event}");

        if (empty($url)) {
            return false;
        }

        try {
            $payload = array_merge($payload, [
                'event' => $event,
                'timestamp' => now()->toIso8601String(),
            ]);

            $signature = $this->generateSignature($payload);

            $response = Http::timeout(30)
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-Webhook-Event' => $event,
                    'X-Webhook-Signature' => $signature,
                    'X-Webhook-Timestamp' => $payload['timestamp'],
                ])
                ->post($url, $payload);

            if ($response->successful()) {
                $this->log('info', "Webhook sent successfully", [
                    'event' => $event,
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return true;
            }

            $this->log('warning', "Webhook request failed", [
                'event' => $event,
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->log('error', "Webhook error: {$e->getMessage()}", [
                'event' => $event,
                'url' => $url,
                'exception' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Generate HMAC signature for webhook payload.
     *
     * @param array $payload
     * @return string
     */
    protected function generateSignature(array $payload): string
    {
        $secret = $this->settings->get('webhook_secret', '');

        if (empty($secret)) {
            return '';
        }

        return hash_hmac('sha256', json_encode($payload), $secret);
    }

    /**
     * Verify a webhook signature.
     *
     * @param string $signature The signature from the request header
     * @param array $payload The payload to verify
     * @return bool
     */
    public static function verifySignature(string $signature, array $payload, string $secret): bool
    {
        if (empty($secret) || empty($signature)) {
            return false;
        }

        $expectedSignature = hash_hmac('sha256', json_encode($payload), $secret);
        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Send payment completed webhook.
     */
    public function sendPaymentCompleted(array $data): bool
    {
        return $this->send('payment_completed', $data);
    }

    /**
     * Send payment failed webhook.
     */
    public function sendPaymentFailed(array $data): bool
    {
        return $this->send('payment_failed', $data);
    }

    /**
     * Send document created webhook.
     */
    public function sendDocumentCreated(array $data): bool
    {
        return $this->send('document_created', $data);
    }

    /**
     * Send subscription created webhook.
     */
    public function sendSubscriptionCreated(array $data): bool
    {
        return $this->send('subscription_created', $data);
    }

    /**
     * Send subscription charged webhook.
     */
    public function sendSubscriptionCharged(array $data): bool
    {
        return $this->send('subscription_charged', $data);
    }

    /**
     * Send Bit payment completed webhook.
     */
    public function sendBitPaymentCompleted(array $data): bool
    {
        return $this->send('bit_payment_completed', $data);
    }

    /**
     * Send stock synced webhook.
     */
    public function sendStockSynced(array $data): bool
    {
        return $this->send('stock_synced', $data);
    }

    /**
     * Log webhook activity.
     */
    protected function log(string $level, string $message, array $context = []): void
    {
        if ($this->settings->get('logging', false)) {
            $channel = $this->settings->get('log_channel', 'stack');
            Log::channel($channel)->$level("[SUMIT Webhook] {$message}", $context);
        }
    }
}
