<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Sms\BrandRegistrations;
use App\Services\Sms\TenantNumbers;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use InvalidArgumentException;

/**
 * Records the number a tenant brought on their own 10DLC brand — decision
 * 3310's second precondition, and the caller
 * {@see TenantNumbers::adoptOwnNumber()} would otherwise not have.
 *
 * ⛔ **WITHOUT THIS, `phone_numbers.lane = 'tenant'` HAS NO WRITER AND THE
 * SECOND PRECONDITION IS A PERMANENTLY-FALSE PREDICATE.**
 * `TenantNumbers::assign()` writes `platform` for every pool number and says why
 * (2101), so the column that separates *our* number from *their* number only
 * ever held one of its two values. A guard reading it would refuse every
 * broadcast for ever, on a green suite — `CLAUDE.md`'s writerless-control shape
 * with the failure pointing the safe way, which is exactly how it survives
 * review.
 *
 * ## The swap this performs is not reversible by re-running it
 *
 * ⚠️ **THE TENANT'S POOL NUMBER IS PARKED.** Dedicated number allocation is one number per tenant, so
 * adopting their own releases ours — see `TenantNumbers::adoptOwnNumber()` for
 * why keeping both would break the reverse lookup every inbound STOP, HELP and
 * delivery receipt depends on. The parked number answers STOP and HELP at
 * platform level throughout its `numbers.release_park_days` window and then
 * returns to the assignable pool (2686), so this is not destructive — but it is
 * not undone by running the command again either, and the confirmation below is
 * why it asks.
 *
 * ⚠️ **THE APPROVED FILING IS REQUIRED HERE AS AN ORDERING CONVENIENCE, NOT AS
 * THE CONTAINMENT.** The real check is `BroadcastPreconditions`, which asks about
 * the brand and the number **independently, at send time**, so that neither one
 * can hide the other's failure — 398's shape. If this refusal were the only
 * thing holding the rule, deleting the send-time brand check would leave the
 * suite green.
 */
#[Signature('sms:adopt-own-number
    {business : The business id bringing their own number}
    {number : The E.164 number they own, registered under their own 10DLC brand}
    {--provider-id= : The vendor\'s own identifier for the number, if known}')]
#[Description('Record a number a tenant brought on their own 10DLC brand, replacing the pool number')]
final class AdoptTenantOwnNumber extends Command
{
    /** Who the record names for an Ops action taken on a vendor console. */
    private const string ACTOR = 'system:own-number-adoption';

    public function handle(TenantNumbers $numbers, BrandRegistrations $registrations): int
    {
        $businessId = (int) $this->argument('business');
        $e164 = trim((string) $this->argument('number'));

        if ($businessId <= 0 || $e164 === '') {
            $this->components->error('Usage: sms:adopt-own-number 42 +15551234567');

            return self::FAILURE;
        }

        return Tenancy::actingAs($businessId, function () use ($numbers, $registrations, $businessId, $e164): int {
            if (! $registrations->isApproved()) {
                $this->components->error(
                    "Business {$businessId} has no approved 10DLC filing of its own, so a number "
                    .'recorded here would be their own line under our brand — which is neither of the '
                    .'things 3310 asks for. Record the approval first: sms:brand-registration '
                    .$businessId.' approve.'
                );

                return self::FAILURE;
            }

            $held = $numbers->forBusiness($businessId)?->e164;
            $own = $numbers->ownBrandNumberFor($businessId);

            if ($own === null && $held !== null && ! $this->confirmSwap($held, $e164)) {
                return self::FAILURE;
            }

            $providerId = trim((string) $this->option('provider-id'));

            try {
                $number = $numbers->adoptOwnNumber(
                    $businessId,
                    $e164,
                    $providerId === '' ? null : $providerId,
                    self::ACTOR,
                );
            } catch (InvalidArgumentException $e) {
                // Every refusal in that method names which of the three it is —
                // ours, somebody else's, or the one we gave them — because an
                // operator sent to look for the wrong one finds nothing.
                $this->components->error($e->getMessage());

                return self::FAILURE;
            }

            $this->components->info(sprintf(
                'Business %d now sends on %s, their own number on their own 10DLC brand (%s).',
                $businessId,
                $number->e164,
                $number->state->value,
            ));

            $this->components->warn(
                'This is two of three. A broadcast also needs a purchased credit balance — the '
                .'monthly allotment may not pay for one (3309) — and all three are checked again at '
                .'send time, per recipient.'
            );

            return self::SUCCESS;
        });
    }

    /**
     * ⚠️ **ASKED BECAUSE THE OLD NUMBER STOPS BEING THEIRS.** Not a CONFIRM in
     * the compliance sense — this spends no money and changes no GBP identity —
     * but an operator typing a digit wrong would park a live tenant's number and
     * hand them one they do not own, and the parked one is out of the pool for
     * ninety days. `--no-interaction` answers yes, which is what a deploy step
     * needs and is why the destructive half is bounded rather than permanent.
     */
    private function confirmSwap(string $held, string $adopting): bool
    {
        if ($this->confirm("{$held} is this tenant's number today. Park it and send on {$adopting} instead?", true)) {
            return true;
        }

        $this->components->info('Nothing changed.');

        return false;
    }
}
