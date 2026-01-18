<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use App\Models\User;
use App\Notifications\PackageUpdateAvailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * GitHub Webhook Controller.
 *
 * Handles webhooks from GitHub, specifically:
 * - Dependabot Pull Requests (package updates)
 * - Security alerts
 * - Release notifications
 *
 * Integrates with Laravel notification system to alert admins about:
 * - New package versions available
 * - Security vulnerabilities detected
 * - Automated PRs created by Dependabot
 *
 * @see .github/dependabot.yml
 * @see \App\Notifications\PackageUpdateAvailable
 */
class GithubWebhookController extends Controller
{
    /**
     * Handle Dependabot pull request webhook.
     *
     * This endpoint receives GitHub webhooks when Dependabot creates a PR
     * for package updates. It parses the PR information and sends notifications
     * to all admin users.
     */
    public function handleDependabotPr(Request $request): JsonResponse
    {
        // Verify GitHub webhook signature
        if (! $this->verifyGithubSignature($request)) {
            Log::warning('GitHub webhook signature verification failed', [
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Log webhook receipt
        Log::info('GitHub webhook received', [
            'event' => $request->header('X-GitHub-Event'),
            'delivery' => $request->header('X-GitHub-Delivery'),
        ]);

        $payload = $request->json()->all();

        // Only process opened pull requests
        if (! isset($payload['action']) || $payload['action'] !== 'opened') {
            return response()->json(['status' => 'ignored', 'reason' => 'not_a_pr_open_event']);
        }

        // Only process Dependabot PRs
        if (! $this->isDependabotPr($payload)) {
            return response()->json(['status' => 'ignored', 'reason' => 'not_dependabot']);
        }

        try {
            // Extract package update information
            $updateInfo = $this->extractUpdateInfo($payload);

            if (! $updateInfo) {
                Log::warning('Failed to extract package update info from Dependabot PR', [
                    'pr_title' => $payload['pull_request']['title'] ?? 'unknown',
                ]);

                return response()->json(['status' => 'ignored', 'reason' => 'invalid_pr_format']);
            }

            // Send notification to admins
            $this->notifyAdmins($updateInfo);

            Log::info('Dependabot PR notification sent', [
                'package' => $updateInfo['package'],
                'version' => "{$updateInfo['current_version']} → {$updateInfo['new_version']}",
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Notification sent to admins',
                'package' => $updateInfo['package'],
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to process Dependabot webhook', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Handle GitHub security advisory webhook.
     *
     * Receives notifications about security vulnerabilities in dependencies.
     */
    public function handleSecurityAdvisory(Request $request): JsonResponse
    {
        if (! $this->verifyGithubSignature($request)) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $payload = $request->json()->all();

        try {
            // Extract security advisory information
            $advisory = $payload['security_advisory'] ?? null;

            if (! $advisory) {
                return response()->json(['status' => 'ignored', 'reason' => 'no_advisory']);
            }

            // Get affected package
            $package = $advisory['package']['name'] ?? 'unknown';
            $severity = $advisory['severity'] ?? 'unknown';

            // Notify admins
            User::role('admin')->each(function ($user) use ($package, $severity, $advisory) {
                $user->notify(
                    PackageUpdateAvailable::security(
                        package: $package,
                        currentVersion: 'current',
                        newVersion: 'latest',
                        prUrl: $advisory['html_url'] ?? '#',
                        severity: $severity
                    )
                );
            });

            Log::warning('Security advisory received', [
                'package' => $package,
                'severity' => $severity,
                'advisory_id' => $advisory['ghsa_id'] ?? 'unknown',
            ]);

            return response()->json(['status' => 'success']);

        } catch (\Exception $e) {
            Log::error('Failed to process security advisory webhook', [
                'error' => $e->getMessage(),
            ]);

            return response()->json(['status' => 'error'], 500);
        }
    }

    /**
     * Verify GitHub webhook signature using HMAC.
     *
     * @see https://docs.github.com/en/webhooks/using-webhooks/validating-webhook-deliveries
     */
    protected function verifyGithubSignature(Request $request): bool
    {
        $signature = $request->header('X-Hub-Signature-256');

        if (! $signature) {
            return false;
        }

        $secret = config('officeguy.github.webhook_secret');

        if (! $secret) {
            Log::warning('GitHub webhook secret not configured');

            return false;
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256='.hash_hmac('sha256', $payload, $secret);

        return hash_equals($expectedSignature, $signature);
    }

    /**
     * Check if PR is from Dependabot.
     */
    protected function isDependabotPr(array $payload): bool
    {
        $user = $payload['pull_request']['user']['login'] ?? '';

        return Str::contains($user, ['dependabot', 'dependabot[bot]']);
    }

    /**
     * Extract package update information from PR title.
     *
     * Dependabot PR titles follow the format:
     * "Bump {package} from {old_version} to {new_version}"
     * or
     * "Bump {package} from {old_version} to {new_version} in {directory}"
     */
    protected function extractUpdateInfo(array $payload): ?array
    {
        $title = $payload['pull_request']['title'] ?? '';
        $prUrl = $payload['pull_request']['html_url'] ?? '#';
        $body = $payload['pull_request']['body'] ?? '';

        // Try to match Dependabot PR title pattern
        // Pattern 1: "Bump package from 1.0.0 to 2.0.0"
        if (preg_match('/^Bump (.+?) from (.+?) to (.+?)( in .+)?$/', $title, $matches)) {
            return [
                'package' => trim($matches[1]),
                'current_version' => trim($matches[2]),
                'new_version' => trim($matches[3]),
                'pr_url' => $prUrl,
                'changelog' => $this->extractChangelog($body),
                'is_security' => $this->isSecurityUpdate($body),
            ];
        }

        // Pattern 2: "chore(deps): bump package from 1.0.0 to 2.0.0"
        if (preg_match('/bump (.+?) from (.+?) to (.+?)( in .+)?$/i', $title, $matches)) {
            return [
                'package' => trim($matches[1]),
                'current_version' => trim($matches[2]),
                'new_version' => trim($matches[3]),
                'pr_url' => $prUrl,
                'changelog' => $this->extractChangelog($body),
                'is_security' => $this->isSecurityUpdate($body),
            ];
        }

        return null;
    }

    /**
     * Extract changelog from PR body.
     */
    protected function extractChangelog(string $body): ?string
    {
        // Look for release notes or changelog section
        if (preg_match('/## Release notes(.+?)(?=##|$)/s', $body, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        if (preg_match('/## Changelog(.+?)(?=##|$)/s', $body, $matches)) {
            return trim(strip_tags($matches[1]));
        }

        // Return first 200 characters if no specific section found
        $cleaned = trim(strip_tags($body));

        return Str::limit($cleaned, 200);
    }

    /**
     * Check if update is security-related.
     */
    protected function isSecurityUpdate(string $body): bool
    {
        return Str::contains(Str::lower($body), [
            'security',
            'vulnerability',
            'cve-',
            'ghsa-',
        ]);
    }

    /**
     * Send notification to all admin users.
     */
    protected function notifyAdmins(array $updateInfo): void
    {
        // Get all users with admin role
        $admins = User::role('admin')->get();

        if ($admins->isEmpty()) {
            Log::warning('No admin users found to notify about package update', [
                'package' => $updateInfo['package'],
            ]);

            return;
        }

        // Send notification to each admin
        foreach ($admins as $admin) {
            try {
                // Use special constructor for SUMIT package
                if ($updateInfo['package'] === 'officeguy/laravel-sumit-gateway') {
                    $admin->notify(
                        PackageUpdateAvailable::sumitUpdate(
                            currentVersion: $updateInfo['current_version'],
                            newVersion: $updateInfo['new_version'],
                            prUrl: $updateInfo['pr_url'],
                            changelog: $updateInfo['changelog']
                        )
                    );
                } elseif ($updateInfo['is_security']) {
                    // Security update
                    $admin->notify(
                        PackageUpdateAvailable::security(
                            package: $updateInfo['package'],
                            currentVersion: $updateInfo['current_version'],
                            newVersion: $updateInfo['new_version'],
                            prUrl: $updateInfo['pr_url'],
                            severity: 'high'
                        )
                    );
                } else {
                    // Regular update
                    $admin->notify(new PackageUpdateAvailable(
                        package: $updateInfo['package'],
                        currentVersion: $updateInfo['current_version'],
                        newVersion: $updateInfo['new_version'],
                        prUrl: $updateInfo['pr_url'],
                        changelog: $updateInfo['changelog'],
                        isSecurity: $updateInfo['is_security']
                    ));
                }
            } catch (\Exception $e) {
                Log::error('Failed to send notification to admin', [
                    'admin_id' => $admin->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Package update notifications sent to admins', [
            'package' => $updateInfo['package'],
            'admin_count' => $admins->count(),
        ]);
    }
}
