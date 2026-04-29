<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Policies;

use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Authorization policy for SUMIT transactions (OfficeGuyTransaction).
 * Uses capability-based checks (isStaff, isClient, isAdmin, isSuperAdmin) so the host auth model is not referenced.
 *
 * @see PHASE4.md
 */
class OfficeGuyTransactionPolicy
{
    public function viewAny(object $user): bool
    {
        if (method_exists($user, 'isStaff') && $user->isStaff()) {
            return true;
        }

        return method_exists($user, 'isClient') && (bool) $user->isClient();
    }

    public function view(object $user, OfficeGuyTransaction $transaction): bool
    {
        if (method_exists($user, 'isStaff') && $user->isStaff()) {
            return true;
        }

        if (method_exists($user, 'isClient') && $user->isClient()) {
            if (property_exists($transaction, 'client_id') && $transaction->client_id !== null) {
                $clientId = $user->client_id ?? null;
                if ($clientId !== null && (int) $transaction->client_id === (int) $clientId) {
                    return true;
                }
            }

            return (string) $transaction->customer_id === (string) ($user->sumit_customer_id ?? '')
                || (int) $transaction->order_id === (int) ($user->id ?? 0);
        }

        if (method_exists($user, 'isReseller') && $user->isReseller()) {
            $client = $transaction->client ?? null;

            return $client !== null && isset($client->created_by) && (int) $client->created_by === (int) ($user->id ?? 0);
        }

        return false;
    }

    public function create(object $user): bool
    {
        return method_exists($user, 'isStaff') && $user->isStaff();
    }

    public function update(object $user, OfficeGuyTransaction $transaction): bool
    {
        return method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    public function delete(object $user, OfficeGuyTransaction $transaction): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    public function restore(object $user, OfficeGuyTransaction $transaction): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    public function forceDelete(object $user, OfficeGuyTransaction $transaction): bool
    {
        return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin();
    }

    public function refund(object $user, OfficeGuyTransaction $transaction): bool
    {
        return method_exists($user, 'isAdmin') && $user->isAdmin();
    }

    public function import(object $user): bool
    {
        return method_exists($user, 'isAdmin') && $user->isAdmin();
    }
}
