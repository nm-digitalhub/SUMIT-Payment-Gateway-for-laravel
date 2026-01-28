<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Middleware;

use Illuminate\Support\Facades\Log;
use Saloon\Http\PendingRequest;
use Saloon\Http\Response;

/**
 * Logging Middleware
 *
 * Logs all SUMIT API requests and responses for debugging.
 * Only active when config('officeguy.logging') === true
 */
class LoggingMiddleware
{
    /**
     * Handle request and response logging
     */
    public function __invoke(PendingRequest $pendingRequest): void
    {
        $channel = config('officeguy.log_channel', 'stack');

        // Log outgoing request
        Log::channel($channel)->debug('SUMIT Request', [
            'url' => $pendingRequest->getUrl(),
            'method' => $pendingRequest->getMethod()->value,
            'headers' => $pendingRequest->headers()->all(),
            'body' => $pendingRequest->body()->all(),
        ]);

        // Hook into response to log it
        $pendingRequest->middleware()->onResponse(
            function (Response $response) use ($channel, $pendingRequest): void {
                Log::channel($channel)->debug('SUMIT Response', [
                    'url' => $pendingRequest->getUrl(),
                    'status' => $response->status(),
                    'headers' => $response->headers(),
                    'body' => $response->json(),
                    'duration_ms' => $response->getPsrResponse()->getHeaderLine('X-Response-Time') ?? 'N/A',
                ]);
            }
        );

        // Log errors if they occur
        $pendingRequest->middleware()->onFailure(
            function (\Exception $exception, ?Response $response = null) use ($channel, $pendingRequest): void {
                Log::channel($channel)->error('SUMIT Request Failed', [
                    'url' => $pendingRequest->getUrl(),
                    'exception' => $exception->getMessage(),
                    'status' => $response?->status(),
                    'body' => $response?->json(),
                ]);
            }
        );
    }
}
