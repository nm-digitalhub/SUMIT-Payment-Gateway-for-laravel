<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

/**
 * WebhookService - Sends custom webhook notifications for payment events
 *
 * This service allows developers to receive notifications for various events
 * without creating custom Listeners. Configure webhook URLs in the Admin Panel.
 * All events are logged to the database for monitoring and automation.
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
     * @param array $options Additional options (transaction_id, document_id, etc.)
     * @return bool Whether the webhook was sent successfully
     */
    public function send(string $event, array $payload, array $options = []): bool
    {
        $url = $this->settings->get("webhook_{$event}");

        // Create webhook event record
        $webhookEvent = WebhookEvent::createEvent($event, $payload, array_merge($options, [
            'webhook_url' => $url,
        ]));

        if (empty($url)) {
            // No URL configured - mark as sent (no action needed)
            $webhookEvent->markAsSent(0, ['message' => 'No webhook URL configured']);
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
                $webhookEvent->markAsSent($response->status(), [
                    'body' => $response->body(),
                ]);

                $this->log('info', "Webhook sent successfully", [
                    'event' => $event,
                    'url' => $url,
                    'status' => $response->status(),
                ]);
                return true;
            }

            $webhookEvent->markAsFailed(
                "HTTP {$response->status()}: " . substr($response->body(), 0, 500),
                $response->status()
            );

            $this->log('warning', "Webhook request failed", [
                'event' => $event,
                'url' => $url,
                'status' => $response->status(),
                'response' => $response->body(),
            ]);
            return false;
        } catch (\Exception $e) {
            $webhookEvent->markAsFailed($e->getMessage());

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
    public function sendPaymentCompleted(array $data, array $options = []): bool
    {
        return $this->send('payment_completed', $data, $options);
    }

    /**
     * Send payment failed webhook.
     */
    public function sendPaymentFailed(array $data, array $options = []): bool
    {
        return $this->send('payment_failed', $data, $options);
    }

    /**
     * Send document created webhook.
     */
    public function sendDocumentCreated(array $data, array $options = []): bool
    {
        return $this->send('document_created', $data, $options);
    }

    /**
     * Send subscription created webhook.
     */
    public function sendSubscriptionCreated(array $data, array $options = []): bool
    {
        return $this->send('subscription_created', $data, $options);
    }

    /**
     * Send subscription charged webhook.
     */
    public function sendSubscriptionCharged(array $data, array $options = []): bool
    {
        return $this->send('subscription_charged', $data, $options);
    }

    /**
     * Send Bit payment completed webhook.
     */
    public function sendBitPaymentCompleted(array $data, array $options = []): bool
    {
        return $this->send('bit_payment_completed', $data, $options);
    }

    /**
     * Send stock synced webhook.
     */
    public function sendStockSynced(array $data, array $options = []): bool
    {
        return $this->send('stock_synced', $data, $options);
    }

    /**
     * Retry failed webhook events.
     *
     * @param int $limit Maximum number of events to retry
     * @return int Number of events processed
     */
    public function retryFailedEvents(int $limit = 100): int
    {
        $events = WebhookEvent::readyForRetry()
            ->limit($limit)
            ->get();

        $processed = 0;

        foreach ($events as $event) {
            $success = $this->send($event->event_type, $event->payload ?? []);
            
            if ($success) {
                $event->markAsSent(200);
            } else {
                $event->scheduleRetry();
            }
            
            $processed++;
        }

        return $processed;
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
