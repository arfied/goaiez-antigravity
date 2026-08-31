<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\AutopilotActionType;
use App\Models\Business;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Support\Tenancy;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Suspend — the one place this application stops a tenant *for cause*, and the
 * one place it lets them start again.
 *
 * `28` §9.5: *"Suspend (compliance/abuse): all sending + actuation off, login
 * shows a plain status page with the support path; billing unaffected until
 * resolved. `super_admin`/`ops_admin`."*
 *
 * ## ⚠️ It is not a bigger pause, and every difference is deliberate
 *
 * `BUILD-PLAN` §2.8 wrote the distinction down before either half existed:
 * *"different trigger, different actor, and a login that shows a status page,
 * because an owner who can resume their own suspension has not been
 * suspended."* Concretely, against {@see TenantPause}:
 *
 * | | Pause | Suspend |
 * | --- | --- | --- |
 * | Who | the owner, or support for them | us, for cause |
 * | Reason | optional (825) | **required**, and a CHECK says so |
 * | Who lifts it | the owner, from `/account` | `super_admin` / `ops_admin` |
 * | The owner's own screens | work | replaced by a status page |
 * | The destination hand-off | keeps working (821) | **stops** |
 *
 * The two are independent state, not one switch with two labels, because both
 * can be true at once and each has to survive the other ending — an owner who
 * paused themselves last week must still be paused when a suspension is lifted.
 *
 * ## ⚠️ How wide a suspension is, which was this slice's central judgement
 *
 * §9.5's words are *"all sending + actuation off"* — the same words §9.5 uses of
 * the pause. So the starting point is decision 821's line, unchanged: **a
 * suspension stops us acting; it does not stop customers talking.**
 * `/f/{slug}` keeps accepting feedback, and triage still opens for an unhappy
 * customer. Two reasons, and the second one is suspension's own:
 *
 *   1. 821's, intact — the flagship install is a QR code on a table, and a
 *      submission that 404s loses that person's words permanently with nothing
 *      recording that they tried. The people at the table did nothing.
 *   2. **A compliance stop is the worst possible moment to stop keeping
 *      records.** What customers were saying during the period under
 *      investigation is precisely the evidence a complaint is answered with,
 *      and refusing intake destroys it.
 *
 * ⚠️ **ONE THING IS WIDER THAN THE PAUSE, AND ONLY ONE: THE DESTINATION
 * HAND-OFF.** Decision 821 keeps an already-routed review's buttons live during
 * a pause — *"the offer is ours to withhold; the tap is theirs to make"* —
 * because the owner chose the pause and nobody has said the offer was improper.
 * A suspension is exactly the finding that it may have been: review gating and
 * fake-review solicitation are the abuses this control exists for, and the
 * hand-off is the actuation that carries a third-party platform's terms. So
 * `ReviewInvites::eligible()` refuses while a tenant is suspended, and that is
 * the single behavioural difference. It is verified by mutation in both
 * directions — widening it to cover the pause reddens `TenantPauseTest`.
 *
 * ⚠️ **AND INVITES ARE DEFERRED, NEVER DESTROYED.** Decisions 890–896: the
 * pause empties `routed_destinations`, `route()` is once-only, and
 * `ReviewInvites` treats that snapshot as the authority — so suppressing the
 * invite half without `invite_deferred_at` *destroys* the invite rather than
 * postponing it, silently, for every review submitted during the window. A
 * suspension suppresses the same half and therefore carries the same marker;
 * `reviews:reinvite` skips a suspended tenant whole, exactly as it skips a
 * paused one, so the deferrals survive the suspension rather than expiring
 * inside it.
 *
 * ## ⛔ A suspension does NOT release the tenant's number, and 2686 left this to
 * be argued
 *
 * 2686 rules on deletion. Suspension is the harder case precisely because it is
 * **reversible**, and every reason to park a number cuts the other way here:
 *
 *   - **The number is the tenant's identity to their own customers.** Releasing
 *     it and lifting the suspension a week later would hand them a *different*
 *     number, so every customer holding the old one texts a number that belongs
 *     to nobody — and, after the park, possibly to somebody else. A control
 *     designed to be lifted must not do something that cannot be.
 *   - **Attribution survives.** `TenantNumbers::tenantFor()` keeps resolving the
 *     number to the suspended tenant, so an inbound STOP suppresses against
 *     **their** customer list, which is the correct list. Releasing would send
 *     it down the platform path instead — right for a departed tenant, wrong for
 *     one who is back on Monday.
 *   - **Nothing is at risk meanwhile.** A suspended tenant cannot send:
 *     `RunCampaignJob::stopCondition()` asks this class before every recipient
 *     and `ReviewInvites::eligible()` refuses outright. A held number that
 *     cannot originate anything is not an exposure.
 *
 * ⚠️ **WHAT IT DOES COST IS POOL CAPACITY**, recorded rather than dismissed: an
 * indefinitely suspended tenant holds a number nobody else can be given. That is
 * a capacity question with a capacity answer — buy more numbers, or lift or
 * delete the account — and answering it by recycling a suspended tenant's number
 * would trade a cost for a compliance failure. **A suspension that has become
 * permanent should become a deletion**, and that path already parks.
 *
 * ## ⚠️ A tripwire for row 4
 *
 * Triage stays open under a suspension, which is right today because opening a
 * triage conversation writes a row and sends nothing — there is no sender in
 * `app/` at all (551). **The day one exists it must ask this class before it
 * sends**, or "all sending off" stops being true for the one control whose
 * whole purpose is to make it true. `ConsentService::permit()` is the natural
 * place, because it is already the single chokepoint every send passes through.
 *
 * ## The tenant boundary, answered rather than avoided
 *
 * `businesses` is `ENABLE`+`FORCE`d on `app.business_id`, and internal staff
 * have no tenant — decision 569's wall, refused four times and most recently at
 * 800. This does not widen it: support opens an account **by reference**
 * through `AccountDirectory` and every write here runs inside
 * `Tenancy::actingAs()`, which satisfies `tenant_isolation`'s USING and WITH
 * CHECK without a third policy. {@see self::assertIsTenant()} is what makes
 * that a requirement rather than a convention.
 */
final class TenantSuspension
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ActivityService $activity,
    ) {}

    /**
     * Stop this account, for cause.
     *
     * Idempotent and quietly so, {@see TenantPause::pause()}'s reasoning: a
     * second press must not rewrite when the suspension began, because that is
     * the fact a dispute turns on. ⚠️ **A second call with a different reason is
     * therefore silently ignored** — amending a reason is not a thing this
     * class does, and pretending otherwise would let the record drift from what
     * was decided at the time. Lift and re-apply if the finding changes.
     *
     * @param  string  $actor  `audit_log`'s vocabulary — `user:14`, `support:9`.
     * @param  string  $reason  Required, and the database agrees. See below.
     *
     * @throws InvalidArgumentException when the reason is empty, or the business
     *                                  is not the tenant in context
     */
    public function suspend(Business $business, string $actor, string $reason): void
    {
        $this->assertIsTenant($business);

        $reason = $this->normaliseReason($reason);

        // ⚠️ REFUSED HERE AS WELL AS BY `businesses_suspension_is_attributed`,
        // and both layers are load-bearing for decision 216's reason. The CHECK
        // catches the repair script that reaches neither the enum nor the
        // service; this catches the operator, and gives them a sentence they
        // can act on instead of a SQLSTATE. `28` §9.5's "typed reasons" is the
        // requirement, and a suspension nobody can explain is the state a
        // compliance review would most want and least be able to reconstruct.
        if ($reason === null) {
            throw new InvalidArgumentException(
                'A suspension needs a reason. `28` §9.5 requires a typed one, and the '
                .'database refuses a suspension without it.'
            );
        }

        if ($this->isSuspended($business)) {
            return;
        }

        DB::transaction(function () use ($business, $actor, $reason): void {
            // forceFill, because these columns are guarded on the model: this
            // service is the only writer and a request body carrying
            // `suspended_at` must not be one. See Business::$guarded.
            $business->forceFill([
                'suspended_at' => now(),
                'suspended_by' => $actor,
                'suspension_reason' => $reason,
            ])->save();

            $this->audit->record('tenant.suspended', $actor, $business, [
                'reason' => $reason,
            ]);

            // ⚠️ THE FEED ENTRY IS NOT THE STATUS PAGE AND DOES NOT REPLACE IT.
            // The owner cannot reach their feed while this is in force — that
            // is what the status page is — so this entry is what they read
            // *afterwards*, when they are trying to reconstruct what happened
            // and when. `28` §9.4's transparency rule applied to the one action
            // in this application that the owner cannot undo.
            //
            // No location id: a suspension is the whole account, and filing it
            // under one location of several would put it somewhere the owner
            // did not choose.
            //
            // ⚠️ THE REASON IS NOT IN THE FEED. It is an internal note written
            // for an auditor, unreviewed, and possibly repeating a third
            // party's complaint — see render() on the status page, where the
            // same judgement is made about the same string.
            $this->activity->record(AutopilotActionType::TenantSuspended);
        });
    }

    /**
     * Let this account start again.
     *
     * `28` §9.5's counterpart to Suspend, and **not** §9.5's *Restore*, which
     * is Delete's counterpart and has nothing to restore from — see the slice's
     * decisions for why building it would be decision 272's shape.
     *
     * ⚠️ **LIFTING A SUSPENSION DOES NOT START THE ACCOUNT.** If the owner had
     * paused themselves before we suspended them, they are still paused
     * afterwards, and that is the entire reason the two controls do not share
     * columns. An implementation that cleared both would silently start a
     * business the owner had deliberately stopped.
     *
     * Clears all three columns together, because the CHECK requires it — a lift
     * that left `suspended_by` behind is refused by the database, which is the
     * constraint doing its job on the path most likely to forget.
     */
    public function lift(Business $business, string $actor): void
    {
        $this->assertIsTenant($business);

        if (! $this->isSuspended($business)) {
            return;
        }

        $suspendedBy = $business->suspended_by;
        $reason = $business->suspension_reason;

        DB::transaction(function () use ($business, $actor, $suspendedBy, $reason): void {
            $business->forceFill([
                'suspended_at' => null,
                'suspended_by' => null,
                'suspension_reason' => null,
            ])->save();

            // Both sides on the row, {@see TenantPause::resume()}'s reasoning:
            // "who lifted it" and "who applied it" are different questions and
            // the second is the one a review asks. The reason travels too,
            // because clearing the column is the only place it stops being
            // readable — `audit_log` is where it has to survive.
            $this->audit->record('tenant.suspension_lifted', $actor, $business, array_filter([
                'suspended_by' => $suspendedBy,
                'reason' => $reason,
            ]));

            $this->activity->record(AutopilotActionType::TenantSuspensionLifted);
        });
    }

    /**
     * Is this account stopped by us?
     *
     * Reads the column rather than a cached flag, {@see TenantPause::isPaused()}'s
     * reasoning: this is checked before every side effect, and a stale answer is
     * a side effect that happened after a compliance decision said it must not.
     */
    public function isSuspended(Business $business): bool
    {
        return $business->suspended_at !== null;
    }

    /**
     * Is the tenant in context stopped by us?
     *
     * For callers that hold a tenant but not the model — `AutopilotJob` runs
     * from a queue payload and `ReviewRouter` from a location. Returns **true**
     * when no business row can be read, which is fail-closed on purpose and for
     * {@see TenantPause::isCurrentTenantPaused()}'s reason: the two ways that
     * happens are a deleted business and a tenant that is not established, and
     * acting for a tenant in either case is worse than not acting.
     */
    public function isCurrentTenantSuspended(): bool
    {
        $business = Business::query()->find(Tenancy::idOrFail());

        return $business === null || $this->isSuspended($business);
    }

    /**
     * Why this account is stopped, or null when it is not.
     *
     * ⚠️ **AN INTERNAL NOTE, AND EVERY CALLER HAS TO DECIDE WHETHER THE OWNER
     * MAY SEE IT.** Two do, and they answer differently: the support console
     * shows it, because the next agent to pick up the account needs the finding
     * the last one wrote; the owner's status page does not, because unreviewed
     * prose written for an auditor is not a statement this company wants to
     * make to a customer, and it may repeat a third party's complaint verbatim.
     */
    public function reasonFor(Business $business): ?string
    {
        return $business->suspension_reason;
    }

    /**
     * Who stopped this account, in `audit_log`'s vocabulary, or null.
     */
    public function suspendedBy(Business $business): ?string
    {
        return $business->suspended_by;
    }

    /**
     * When it was stopped, or null.
     */
    public function suspendedAt(Business $business): ?CarbonInterface
    {
        return $business->suspended_at;
    }

    /**
     * ⚠️ THE CROSS-TENANT GUARD, HERE RATHER THAN IN EACH CALLER, and it is
     * doing more work than {@see TenantPause}'s namesake. Suspension is only
     * ever applied by somebody acting on an account that is not their own, so
     * *every* call reaches this class with the possibility of the wrong
     * business — and a suspension written onto the wrong one is a working
     * business stopped dead by a compliance finding about somebody else, which
     * they cannot undo and cannot explain.
     */
    private function assertIsTenant(Business $business): void
    {
        if ((int) $business->id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'Suspension acts on the tenant in context. Wrap the call in Tenancy::actingAs().'
            );
        }
    }

    /**
     * Bounded, and empty means absent.
     *
     * {@see TenantPause::normaliseReason()}'s rules — 500 characters because it
     * lands in `audit_log`, which is append-only forever, and whitespace-only
     * collapses to null so "no reason given" has one representation. Here that
     * null is a refusal rather than a stored absence, because a suspension
     * cannot have one.
     */
    private function normaliseReason(string $reason): ?string
    {
        $reason = trim($reason);

        if ($reason === '') {
            return null;
        }

        return mb_substr($reason, 0, 500);
    }
}
