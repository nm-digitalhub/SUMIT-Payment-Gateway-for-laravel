<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use OfficeGuy\LaravelSumitGateway\BackoffStrategy\ExponentialBackoffStrategy;
use OfficeGuy\LaravelSumitGateway\Jobs\SendWebhookJob;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;
use OfficeGuy\LaravelSumitGateway\Services\SettingsService;

/**
 * Webhook Call - Fluent Builder for Sending Webhooks
 *
 * Inspired by Spatie's laravel-webhook-server package.
 * Provides a clean API for configuring and dispatching webhook requests.
 *
 * @example
 * WebhookCall::create()
 *     ->event('payment_completed')
 *     ->url('https://example.com/webhook')
 *     ->payload(['order_id' => 123])
 *     ->dispatch();
 */
class WebhookCall
{
    protected string $uuid;
    protected string $event = '';
    protected string $url = '';
    protected array $payload = [];
    protected array $headers = [];
    protected string $secret = '';
    protected int $tries = 3;
    protected int $timeout = 30;
    protected string $backoffStrategy = ExponentialBackoffStrategy::class;
    protected array $meta = [];
    protected bool $signWebhook = true;
    protected bool $throwExceptionOnFailure = false;
    protected ?int $webhookEventId = null;

    public function __construct()
    {
        $this->uuid = (string) Str::uuid();

        // Load defaults from config
        $this->tries = config('officeguy.webhooks.tries', 3);
        $this->timeout = config('officeguy.webhooks.timeout_in_seconds', 30);
    }

    /**
     * Create a new WebhookCall instance.
     */
    public static function create(): static
    {
        return new static();
    }

    /**
     * Set the event name.
     */
    public function event(string $event): static
    {
        $this->event = $event;

        return $this;
    }

    /**
     * Set the webhook URL.
     */
    public function url(string $url): static
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Set the payload data.
     */
    public function payload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    /**
     * Set the signing secret.
     */
    public function useSecret(string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * Add custom headers.
     */
    public function withHeaders(array $headers): static
    {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Set maximum retry attempts.
     */
    public function maximumTries(int $tries): static
    {
        $this->tries = $tries;

        return $this;
    }

    /**
     * Set request timeout in seconds.
     */
    public function timeoutInSeconds(int $timeout): static
    {
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Set the backoff strategy class.
     */
    public function useBackoffStrategy(string $backoffStrategyClass): static
    {
        $this->backoffStrategy = $backoffStrategyClass;

        return $this;
    }

    /**
     * Attach metadata (not sent with request, but included in events).
     */
    public function meta(array $meta): static
    {
        $this->meta = array_merge($this->meta, $meta);

        return $this;
    }

    /**
     * Disable webhook signing.
     */
    public function doNotSign(): static
    {
        $this->signWebhook = false;

        return $this;
    }

    /**
     * Throw exception on final failure instead of silently deleting job.
     */
    public function throwExceptionOnFailure(): static
    {
        $this->throwExceptionOnFailure = true;

        return $this;
    }

    /**
     * Automatically configure URL and secret from settings for a specific event.
     */
    public function useSettingsForEvent(string $event): static
    {
        /** @var SettingsService $settings */
        $settings = app(SettingsService::class);

        $this->event = $event;
        $this->url = $settings->get("webhook_{$event}", '');
        $this->secret = $settings->get('webhook_secret', '');

        return $this;
    }

    /**
     * Dispatch the webhook asynchronously via queue.
     */
    public function dispatch(): void
    {
        $this->prepareForDispatch();

        $job = new SendWebhookJob(
            $this->uuid,
            $this->event,
            $this->url,
            $this->preparePayload(),
            $this->getAllHeaders(),
            $this->secret,
            $this->backoffStrategy,
            $this->meta,
            $this->webhookEventId
        );

        $job->tries = $this->tries;
        $job->timeout = $this->timeout;
        $job->throwExceptionOnFailure = $this->throwExceptionOnFailure;

        dispatch($job);
    }

    /**
     * Dispatch the webhook synchronously (immediate execution).
     */
    public function dispatchSync(): bool
    {
        $this->prepareForDispatch();

        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->getAllHeaders())
                ->post($this->url, $this->preparePayload());

            if ($response->successful()) {
                if ($this->webhookEventId) {
                    WebhookEvent::find($this->webhookEventId)?->markAsSent($response->status(), [
                        'body' => $response->body(),
                    ]);
                }

                return true;
            }

            if ($this->webhookEventId) {
                WebhookEvent::find($this->webhookEventId)?->markAsFailed(
                    "HTTP {$response->status()}: " . substr($response->body(), 0, 500),
                    $response->status()
                );
            }

            return false;
        } catch (\Exception $e) {
            if ($this->webhookEventId) {
                WebhookEvent::find($this->webhookEventId)?->markAsFailed($e->getMessage());
            }

            return false;
        }
    }

    /**
     * Dispatch the webhook asynchronously if condition is true.
     */
    public function dispatchIf(bool $condition): void
    {
        if ($condition) {
            $this->dispatch();
        }
    }

    /**
     * Dispatch the webhook synchronously if condition is true.
     */
    public function dispatchSyncIf(bool $condition): bool
    {
        if ($condition) {
            return $this->dispatchSync();
        }

        return false;
    }

    /**
     * Prepare payload with event and timestamp.
     */
    protected function preparePayload(): array
    {
        return array_merge($this->payload, [
            'event' => $this->event,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Get all headers including signature.
     */
    protected function getAllHeaders(): array
    {
        $headers = array_merge([
            'Content-Type' => 'application/json',
            'X-Webhook-Event' => $this->event,
            'X-Webhook-Timestamp' => now()->toIso8601String(),
            'X-Webhook-UUID' => $this->uuid,
        ], $this->headers);

        if ($this->signWebhook && !empty($this->secret)) {
            $headers['X-Webhook-Signature'] = $this->generateSignature();
        }

        return $headers;
    }

    /**
     * Generate HMAC signature for payload.
     */
    protected function generateSignature(): string
    {
        return hash_hmac('sha256', json_encode($this->preparePayload()), $this->secret);
    }

    /**
     * Prepare for dispatch - validate and create WebhookEvent record.
     */
    protected function prepareForDispatch(): void
    {
        if (empty($this->url)) {
            throw new \InvalidArgumentException('Webhook URL is required');
        }

        if (empty($this->event)) {
            throw new \InvalidArgumentException('Event name is required');
        }

        // Create WebhookEvent record for tracking
        $webhookEvent = WebhookEvent::createEvent($this->event, $this->preparePayload(), [
            'webhook_url' => $this->url,
            'uuid' => $this->uuid,
        ]);

        $this->webhookEventId = $webhookEvent->id;
    }

    /**
     * Get the UUID for this webhook call.
     */
    public function getUuid(): string
    {
        return $this->uuid;
    }
}
