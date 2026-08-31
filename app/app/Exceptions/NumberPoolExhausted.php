<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Services\Sms\NumberSelector;
use App\Services\Sms\TenantNumbers;
use RuntimeException;

/**
 * Thrown when a tenant asked for their dedicated number and the platform had none left.
 *
 * ⛔ **THIS ABORTS A REGISTRATION, WHICH IS THE POINT RATHER THAN A SIDE
 * EFFECT.** {@see TenantNumbers::claimForTenant()} is called from inside
 * `CreateNewUser`'s transaction, so throwing here rolls the whole signup back and
 * the person is told to try again. That is deliberately harsher than provisioning
 * them without a number: tenant dedicated number allocation makes the number the *only* thing that names a
 * tenant on an inbound event, so a tenant with no number has an unanswerable
 * HELP, a STOP that cannot be attributed to their list, and a missed-call
 * text-back that would go out from somebody else's number. **A tenant provisioned
 * into that state is not partially set up, they are quietly broken** — which is
 * the same argument `TenantProvisioner`'s own docblock makes about a user with no
 * business, and it is why this fails closed instead of degrading.
 *
 * ⚠️ **IT CANNOT FIRE ON A PLATFORM THAT IS NOT RUNNING DEDICATED NUMBERS YET, AND THAT IS
 * CHECKED RATHER THAN ASSUMED.** An installation with no assignable inventory at
 * all — every environment before an operator runs `sms:load-number-pool` — is the
 * bootstrap case, and refusing every registration there would be an outage caused
 * by a feature nobody has switched on. `claimForTenant()` distinguishes the two
 * exactly the way {@see NumberSelector} already distinguishes
 * its own two nulls: *"no inventory yet"* is not *"the inventory ran out"*.
 *
 * ⚠️ **THE MESSAGE NAMES THE COMMAND THAT FIXES IT.** Whoever meets this is
 * reading a failed registration at an hour when nobody planned to think about
 * number inventory.
 */
final class NumberPoolExhausted extends RuntimeException
{
    private function __construct(string $message)
    {
        parent::__construct($message);
    }

    /**
     * Every number in the assignable pool already belongs to a tenant.
     */
    public static function noFreeNumber(int $assigned): self
    {
        return new self(
            "The platform number pool is empty: all {$assigned} assignable number(s) already belong to "
            .'a tenant, and dedicated number allocation gives every tenant their own. Signup is refused rather than '
            .'completed without one — a tenant with no number cannot be resolved from an inbound '
            .'HELP, STOP or voice event, so nothing they receive can be routed back to them. '
            .'Load more numbers with `php artisan sms:load-number-pool +1512…` and retry.'
        );
    }
}
