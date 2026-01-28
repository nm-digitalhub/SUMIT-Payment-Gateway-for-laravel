<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\BackoffStrategy\BackoffStrategyInterface;
use OfficeGuy\LaravelSumitGateway\Events\FinalWebhookCallFailedEvent;
use OfficeGuy\LaravelSumitGateway\Events\WebhookCallFailedEvent;
use OfficeGuy\LaravelSumitGateway\Events\WebhookCallSucceededEvent;
use OfficeGuy\LaravelSumitGateway\Models\WebhookEvent;

/**
 * Send Webhook Job
 *
 * Queueable job that sends webhook requests with automatic retry logic.
 * Based on Spatie's CallWebhookJob implementation.
 */
class SendWebhookJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    public bool $throwExceptionOnFailure = false;

    public function __construct(
        public readonly string $uuid,
        public readonly string $event,
        public readonly string $url,
        public readonly array $payload,
        public readonly array $headers,
        public readonly string $secret,
        public readonly string $backoffStrategyClass,
        public readonly array $meta = [],
        public readonly ?int $webhookEventId = null
    ) {
        // Tries and timeout will be set from config in the WebhookCall builder
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            $response = Http::timeout($this->timeout)
                ->withHeaders($this->headers)
                ->post($this->url, $this->payload);

            if ($response->successful()) {
                $this->handleSuccess($response->status(), $response->body());
            } else {
                $this->handleFailure($response->status(), $response->body());
            }
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }

    /**
     * Handle successful webhook delivery.
     */
    protected function handleSuccess(int $statusCode, string $responseBody): void
    {
        // Update WebhookEvent record
        if ($this->webhookEventId) {
            WebhookEvent::find($this->webhookEventId)?->markAsSent($statusCode, [
                'body' => $responseBody,
            ]);
        }

        // Fire success event
        WebhookCallSucceededEvent::dispatch(
            $this->uuid,
            $this->event,
            $this->url,
            $this->payload,
            $this->headers,
            $this->attempts(),
            $statusCode,
            $responseBody,
            $this->meta
        );

        $this->logInfo('Webhook delivered successfully', [
            'status' => $statusCode,
            'attempt' => $this->attempts(),
        ]);
    }

    /**
     * Handle failed webhook delivery (non-2xx response).
     */
    protected function handleFailure(int $statusCode, string $responseBody): void
    {
        $errorMessage = "HTTP {$statusCode}: " . substr($responseBody, 0, 500);

        // Fire failed event
        WebhookCallFailedEvent::dispatch(
            $this->uuid,
            $this->event,
            $this->url,
            $this->payload,
            $this->headers,
            $this->attempts(),
            $statusCode,
            $errorMessage,
            $this->meta
        );

        $this->logWarning('Webhook delivery failed', [
            'status' => $statusCode,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
        ]);

        // Check if this is the last attempt
        if ($this->attempts() >= $this->tries) {
            $this->handleFinalFailure($errorMessage);
        } else {
            $this->scheduleRetry();
        }
    }

    /**
     * Handle exception during webhook delivery.
     */
    protected function handleException(\Throwable $e): void
    {
        $errorMessage = $e->getMessage();

        // Update WebhookEvent record
        if ($this->webhookEventId) {
            WebhookEvent::find($this->webhookEventId)?->markAsFailed($errorMessage);
        }

        // Fire failed event
        WebhookCallFailedEvent::dispatch(
            $this->uuid,
            $this->event,
            $this->url,
            $this->payload,
            $this->headers,
            $this->attempts(),
            null,
            $errorMessage,
            $this->meta
        );

        $this->logError('Webhook exception', [
            'error' => $errorMessage,
            'attempt' => $this->attempts(),
            'max_tries' => $this->tries,
        ]);

        // Check if this is the last attempt
        if ($this->attempts() >= $this->tries) {
            $this->handleFinalFailure($errorMessage);
        } else {
            $this->scheduleRetry();
        }
    }

    /**
     * Handle final failure after all retries exhausted.
     */
    protected function handleFinalFailure(string $errorMessage): void
    {
        // Update WebhookEvent record
        if ($this->webhookEventId) {
            WebhookEvent::find($this->webhookEventId)?->markAsFailed($errorMessage);
        }

        // Fire final failure event
        FinalWebhookCallFailedEvent::dispatch(
            $this->uuid,
            $this->event,
            $this->url,
            $this->payload,
            $this->headers,
            $this->attempts(),
            $errorMessage,
            $this->meta
        );

        $this->logError('Webhook permanently failed after all retries', [
            'total_attempts' => $this->attempts(),
            'error' => $errorMessage,
        ]);

        if ($this->throwExceptionOnFailure) {
            $this->fail(new \Exception("Webhook failed after {$this->attempts()} attempts: {$errorMessage}"));
        } else {
            $this->delete();
        }
    }

    /**
     * Schedule retry with exponential backoff.
     */
    protected function scheduleRetry(): void
    {
        /** @var BackoffStrategyInterface $strategy */
        $strategy = app($this->backoffStrategyClass);
        $waitSeconds = $strategy->waitInSecondsAfterAttempt($this->attempts());

        $this->logInfo('Scheduling webhook retry', [
            'attempt' => $this->attempts(),
            'next_attempt' => $this->attempts() + 1,
            'wait_seconds' => $waitSeconds,
        ]);

        $this->release($waitSeconds);
    }

    /**
     * Log info message.
     */
    protected function logInfo(string $message, array $context = []): void
    {
        Log::info("[SUMIT Webhook] {$message}", array_merge([
            'uuid' => $this->uuid,
            'event' => $this->event,
            'url' => $this->url,
        ], $context));
    }

    /**
     * Log warning message.
     */
    protected function logWarning(string $message, array $context = []): void
    {
        Log::warning("[SUMIT Webhook] {$message}", array_merge([
            'uuid' => $this->uuid,
            'event' => $this->event,
            'url' => $this->url,
        ], $context));
    }

    /**
     * Log error message.
     */
    protected function logError(string $message, array $context = []): void
    {
        Log::error("[SUMIT Webhook] {$message}", array_merge([
            'uuid' => $this->uuid,
            'event' => $this->event,
            'url' => $this->url,
        ], $context));
    }

    /**
     * Get the tags for the job (for Horizon).
     */
    public function tags(): array
    {
        return [
            'sumit-webhook',
            "event:{$this->event}",
            "uuid:{$this->uuid}",
        ];
    }
}
