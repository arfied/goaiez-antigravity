<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Business;
use App\Services\Billing\Dunning;
use App\Support\QueueBackoff;
use App\Support\Tenancy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * One tick of one tenant's dunning schedule (T137 SL-11, decisions 2133, 2142).
 *
 * ⛔ **THE MISSING HALF OF ROW 22 SLICE D, AND ITS ABSENCE WAS NOT A GAP IN
 * COVERAGE — IT WAS THE ENGINE HAVING NO STARTER** (2590). `Dunning::advance()`
 * is the whole retry and escalation path and the only caller of
 * `Subscriptions::suspendForNonPayment()`, and until this class existed nothing
 * in `app/` called it: no job, no command, no line in `routes/console.php`
 * beside the eleven that are scheduled there. So a declined payment wrote
 * attempt 1, stored a `next_attempt_at` **nothing read**, and stopped —
 * permanently, silently, with every test green, because every test called
 * `advance()` by hand. Meanwhile Authorize.Net terminates a suspended
 * subscription before its next run date on its own clock, so the divergence was
 * not "our schedule is slow", it was "the customer has no subscription and our
 * row says `past_due`".
 *
 * ## Not an `AutopilotJob`, and the reason is the same as `BuildTenantExportJob`'s
 *
 * That class opts out because `28` §3.7 says exporting is never gated; this one
 * opts out because `AutopilotJob::handle()` skips on `TenantPause` and
 * `TenantSuspension`, and **both of those would make the suspension arm
 * unreachable for exactly the tenants most likely to be in them** (2591). A
 * tenant who pauses their automations and then stops paying would keep the
 * product for ever; a tenant under a compliance suspension still owes money, and
 * their ARB subscription is still being terminated by the vendor while our
 * schedule sits frozen. Neither is a gate anybody chose — they would be
 * inherited, from a base class written for the 142-automation catalog, and the
 * inheriting is invisible in a diff. That is `CLAUDE.md`'s decision 398 shape
 * with the outer guard on the wrong side of the thing it hides.
 *
 * ⚠️ **WHAT IS NOT INHERITED IS RE-STATED HERE RATHER THAN DROPPED.** Tenancy is
 * established below, the kill switch is checked below, the backoff ladder is
 * below, and the idempotency is structural and lives in
 * {@see Dunning::advanceIfDue()}. What is genuinely gone is the
 * `automation_runs` row: dunning is a billing schedule counted from
 * `dunning_attempts`, and 2133 is explicit that the count may not be taken from
 * a table written for a reader. Every tick that does anything appends an
 * attempt row, which is the exhaustive record here.
 *
 * ## It spends no money, so CONFIRM does not attach
 *
 * `CLAUDE.md` reserves CONFIRM for three things, one of which is "anything that
 * spends money". **This path charges nothing** — 2143 settles that a dunning
 * "retry" on this gateway is a prompt rather than a charge, because charging the
 * stored profile directly would take the money without reinstating the
 * subscription. The only vendor call `advance()` makes is
 * `ARBGetSubscriptionStatusRequest`, a read. ⚠️ **If that ever changes, this
 * class is the wrong place for the charge and CONFIRM is the reason** (2597):
 * an unattended queue worker cannot obtain a confirmation, so a charging tick
 * would have to become a request for one.
 */
final class AdvanceDunningScheduleJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /**
     * The automation key this job answers to for the platform kill switch.
     *
     * ⚠️ **IT REUSES `AutopilotJob`'s CONFIG-ONLY SWITCH RATHER THAN ADDING A
     * SECOND ONE** (2598). `AutopilotJob::killSwitchThrownFor()` is static and
     * its own docblock invites this use. Two kill switches is how a kill switch
     * ends up half honoured, and the mechanism wanted here is the same one:
     * config rather than the database, because the moment an operator most wants
     * to stop this is the moment something is badly wrong.
     */
    public const string AUTOMATION_KEY = 'billing.dunning_tick';

    /**
     * Three attempts, the ladder every job in this application uses.
     *
     * A retry cannot double-escalate: `advanceIfDue()` re-reads the head under
     * its lock and the attempt it would write is already there with its
     * `next_attempt_at` in the future.
     */
    public int $tries = 3;

    public function backoff(): array
    {
        return QueueBackoff::ladder(QueueBackoff::fromSetting('queue.backoff.standard_seconds'));
    }

    public function __construct(
        public readonly int $businessId,
    ) {}

    public function handle(Dunning $dunning): void
    {
        // A job dispatched from the scheduler inherits no tenant context at all
        // — `AutopilotJob`'s reasoning, restated because this does not extend it.
        Tenancy::set($this->businessId);

        if (AutopilotJob::killSwitchThrownFor(self::AUTOMATION_KEY)) {
            /*
             * ⚠️ THE COST OF THROWING IT IS WORTH NAMING RATHER THAN LEAVING
             * PLEASANT. Nothing here is lost — `next_attempt_at` stays in the
             * past and the next tick after the switch is cleared picks the same
             * schedule up. But Authorize.Net's own termination clock does not
             * stop, so a long halt spends grace period the tenant will not get
             * back. It still fails in the right direction: the failure of
             * stopping is a tenant keeping the product longer, and the failure
             * of not stopping is taking it away during an incident.
             */
            return;
        }

        // Null when the business has been deleted between the sweep and the
        // worker, which is ordinary rather than an error — `28` §9.5's deletion
        // runs on its own schedule and does not coordinate with this one.
        $business = Business::query()->find($this->businessId);

        if (! $business instanceof Business) {
            return;
        }

        $dunning->advanceIfDue($business);
    }
}
