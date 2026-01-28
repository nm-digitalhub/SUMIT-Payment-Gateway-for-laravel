<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Jobs;

use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use OfficeGuy\LaravelSumitGateway\Models\CrmEntity;
use OfficeGuy\LaravelSumitGateway\Services\DebtService;

class CheckSumitDebtJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public function __construct(public ?int $entityId = null) {}

    public function handle(DebtService $debtService): void
    {
        $query = CrmEntity::query()
            ->whereNotNull('sumit_customer_id');

        if ($this->entityId) {
            $query->where('id', $this->entityId);
        }

        $query->chunkById(50, function ($entities) use ($debtService): void {
            foreach ($entities as $entity) {
                try {
                    $balance = $debtService->getCustomerBalanceById((int) $entity->sumit_customer_id);

                    if (! $balance || ($balance['debt'] ?? 0) <= 0) {
                        $this->resetAttempts((int) $entity->sumit_customer_id);

                        continue;
                    }

                    if (! $this->shouldSend($entity->id, (int) $entity->sumit_customer_id)) {
                        continue;
                    }

                    $email = $entity->email ?? $entity->client?->email;
                    $phone = $entity->phone ?? $entity->mobile ?? $entity->client?->phone;

                    $debtService->sendPaymentLink(
                        (int) $entity->sumit_customer_id,
                        $email,
                        $phone
                    );

                    $this->markSent($entity->id, (int) $entity->sumit_customer_id);

                } catch (\Throwable $e) {
                    Log::warning('Debt job failed for entity', [
                        'entity_id' => $entity->id,
                        'sumit_customer_id' => $entity->sumit_customer_id,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    protected function shouldSend(?int $entityId, int $sumitCustomerId): bool
    {
        $settings = config('officeguy.collection', []);
        $maxAttempts = (int) ($settings['max_attempts'] ?? 3);
        $reminderDays = array_values(array_filter(array_map(trim(...), explode(',', $settings['reminder_days'] ?? '0,3,7')), strlen(...)));
        $reminderDays = array_map(intval(...), $reminderDays);
        if ($reminderDays === []) {
            $reminderDays = [0, 3, 7];
        }

        $row = DB::table('officeguy_debt_attempts')
            ->where('sumit_customer_id', $sumitCustomerId)
            ->orderByDesc('id')
            ->first();

        $attempts = $row->attempts ?? 0;
        $lastSent = $row->last_sent_at ? Carbon::parse($row->last_sent_at) : null;

        if ($attempts >= $maxAttempts) {
            return false;
        }

        $index = min($attempts, count($reminderDays) - 1);
        $days = $reminderDays[$index] ?? 0;

        return ! ($lastSent && $lastSent->diffInDays(now()) < $days);
    }

    protected function markSent(?int $entityId, int $sumitCustomerId): void
    {
        DB::table('officeguy_debt_attempts')->updateOrInsert(
            [
                'sumit_customer_id' => $sumitCustomerId,
            ],
            [
                'crm_entity_id' => $entityId,
                'attempts' => DB::raw('attempts + 1'),
                'last_sent_at' => now(),
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
    }

    protected function resetAttempts(int $sumitCustomerId): void
    {
        DB::table('officeguy_debt_attempts')
            ->where('sumit_customer_id', $sumitCustomerId)
            ->update([
                'attempts' => 0,
                'last_sent_at' => null,
                'updated_at' => now(),
            ]);
    }
}
