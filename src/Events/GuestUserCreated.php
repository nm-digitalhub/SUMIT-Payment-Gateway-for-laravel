<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Events;

/**
 * Fired when a guest user was created after payment (e.g. by AutoCreateUserListener).
 * Host application should listen to send welcome email or perform other onboarding.
 *
 * @see PHASE4.md
 */
class GuestUserCreated
{
    public function __construct(
        public object $user,
        public string $temporaryPassword,
        public object $order
    ) {}
}
