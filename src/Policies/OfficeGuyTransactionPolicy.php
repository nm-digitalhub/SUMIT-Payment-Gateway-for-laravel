<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Policies;

use App\Enums\UserRole;
use App\Models\User;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyTransaction;

/**
 * Authorization policy for SUMIT transactions (OfficeGuyTransaction)
 * Mirrors existing TransactionPolicy semantics for staff/client/admin roles.
 */
class OfficeGuyTransactionPolicy
{
    public function viewAny(User $user): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        return (bool) $user->isClient();
    }

    public function view(User $user, OfficeGuyTransaction $transaction): bool
    {
        if ($user->isStaff()) {
            return true;
        }

        if ($user->role === UserRole::CLIENT) {
            if (! (property_exists($transaction, 'client_id') && ! is_null($transaction->client_id))) {
                // Fallbacks: SUMIT customer_id or order_id ↔ user id
                return (string) $transaction->customer_id === (string) $user->sumit_customer_id
                    || (int) $transaction->order_id === (int) $user->id;
            }
            if (is_null($user->client_id)) {
                // Fallbacks: SUMIT customer_id or order_id ↔ user id
                return (string) $transaction->customer_id === (string) $user->sumit_customer_id
                    || (int) $transaction->order_id === (int) $user->id;
            }
            if ((int) $transaction->client_id === (int) $user->client_id) {
                return true;
            }

            // Fallbacks: SUMIT customer_id or order_id ↔ user id
            return (string) $transaction->customer_id === (string) $user->sumit_customer_id
                || (int) $transaction->order_id === (int) $user->id;
        }

        if ($user->role === UserRole::RESELLER) {
            return $transaction->client?->created_by === $user->id;
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isStaff();
    }

    public function update(User $user, OfficeGuyTransaction $transaction): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, OfficeGuyTransaction $transaction): bool
    {
        return $user->isSuperAdmin();
    }

    public function restore(User $user, OfficeGuyTransaction $transaction): bool
    {
        return $user->isSuperAdmin();
    }

    public function forceDelete(User $user, OfficeGuyTransaction $transaction): bool
    {
        return $user->isSuperAdmin();
    }

    public function refund(User $user, OfficeGuyTransaction $transaction): bool
    {
        return $user->isAdmin();
    }

    public function import(User $user): bool
    {
        return $user->isAdmin();
    }
}
