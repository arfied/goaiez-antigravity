<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Config\DefaultsRegistry;
use App\Services\Sms\TenantNumbers;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Bring numbers back out of the park once they are cold — decision 2686, and
 * the second half of doc 51 §5.5.
 *
 * `TenantNumbers::releaseFromTenant()` parks a departed tenant's number: state
 * `retired`, role `shared_pool`, no business. This is the only thing that ever
 * takes one back out, and what it hands back is a number in exactly the shape
 * `TenantNumbers::freeFromPool()` looks for — so the next signup can be given
 * it.
 *
 * ⛔ **WITHOUT THIS COMMAND THE PARK IS A GRAVE.** A number would be taken off
 * the departed tenant correctly and never assigned to anybody again, which is
 * the same "out of the pool for ever" outcome 2686 exists to fix, reached by a
 * different route. The release is the reader for `phone_numbers.retired_at`,
 * which has had a writer since the column was created and no reader at all.
 *
 * ## Not an `AutopilotJob`, and the reason is the tenant
 *
 * ⚠️ Every sweep beside this one in `routes/console.php` enumerates tenants and
 * dispatches per-tenant work. **A parked number belongs to nobody by
 * definition** — that is the whole point of the park — so there is no tenant to
 * establish, no per-tenant kill switch to read, and nothing for
 * `AutopilotJob::killSwitchThrownFor()` to be asked about. It is a platform
 * operation on platform rows, like `messaging:watch-platform-complaint-rate`.
 *
 * ⚠️ **IT IS IDEMPOTENT AND ORDER-INDEPENDENT.** The query selects on
 * `retired_at` against the clock, so a second run the same minute finds the same
 * rows already moved on and does nothing; a run missed for a week returns
 * everything that came due meanwhile.
 */
#[Signature('numbers:return-parked')]
#[Description('Return numbers whose park has expired to the assignable pool (decision 2686)')]
final class ReturnParkedNumbers extends Command
{
    public function handle(TenantNumbers $numbers, DefaultsRegistry $registry): int
    {
        // ⚠️ `int()`, so the manifest seed is what an unconfigured install gets
        // — 2861's lesson, one service over: `intOr(…, 0)` here would fall back
        // to zero, and `returnParkedNumbersToPool()` reads a non-positive park
        // as "do nothing", so the sweep would silently never return anything.
        $parkDays = $registry->int('numbers.release_park_days');

        if ($parkDays <= 0) {
            // ⛔ Stated out loud rather than passed over, on
            // `WatchPlatformComplaintRate`'s argument: a scheduled task printing
            // "nothing to do" is how a switched-off mechanism stays switched off
            // for a year. Here the consequence is a pool that quietly stops
            // refilling while every signup starts failing on an exhausted one.
            $this->warn(
                'numbers.release_park_days is not set, so no parked number will ever return to the '
                .'pool. Set it (decision 2686 says 90) or the pool drains one departed tenant at a time.'
            );

            return self::SUCCESS;
        }

        $returned = $numbers->returnParkedNumbersToPool($parkDays);

        $this->info("Returned {$returned} parked number(s) to the assignable pool after {$parkDays} days.");

        return self::SUCCESS;
    }
}
