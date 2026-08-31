<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\AutopilotActionType;
use App\Models\Business;
use App\Services\ActivityService;
use App\Services\AuditService;
use App\Support\Tenancy;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Pause Everything — the one place this application stops a tenant, or starts
 * them again.
 *
 * `29` §11.2 row 5's *Pause*, and the mechanism `28` §9.5's *"pause on the
 * client's behalf"* defines itself against. Until this class, **the only kill
 * switch in the codebase was ours**: `config('autopilot.kill_switch')`, global,
 * and thrown by us. A tenant could not stop their own account, and support
 * could not stop it for them (820).
 *
 * ## What a pause stops, and the two things it must never stop
 *
 * `28` §9.5 words it as *"all sending + actuation off"* — everything this
 * application does **on the tenant's behalf**, to anybody. Concretely today:
 * every `AutopilotJob` skips, and no review invitation is offered.
 *
 * ⚠️ **IT DOES NOT STOP A CUSTOMER SPEAKING.** `/f/{slug}` keeps accepting
 * feedback while an account is paused, and that is the most important line in
 * this class. The flagship install is a QR code on a table: an owner pausing
 * because our replies read badly has not asked us to take down the card their
 * customer is looking at, and a paused submission that 404s loses that person's
 * words permanently, with nothing anywhere recording that they tried. Pause
 * stops *us acting*; it does not stop *them talking*.
 *
 * ⚠️ **AND IT DOES NOT REACH BACKWARDS.** A review already routed keeps its
 * hand-off buttons, so a customer who has been offered Google and taps it is
 * not answered with an error for a decision their business made afterwards.
 * The offer is ours to withhold; the tap is theirs to make.
 *
 * ## Resuming is not the same as never having paused
 *
 * Analysis skipped during a pause is picked up by `reviews:reanalyse`, which
 * sweeps unanalysed reviews and already excludes work it must not retry (364).
 * A paused tenant is skipped rather than failed for exactly that reason: a
 * `skipped` run does not consume the recovery budget, so a week-long pause does
 * not strand every review submitted during it — which is decisions 356 and
 * 364's bug, and it would be rebuilt by anything that failed instead.
 */
final class TenantPause
{
    public function __construct(
        private readonly AuditService $audit,
        private readonly ActivityService $activity,
    ) {}

    /**
     * Stop everything this application does for a tenant.
     *
     * Idempotent, and quietly so: pausing an already-paused account changes
     * nothing and records nothing. The alternative — refusing it — turns a
     * double click on the one control an owner reaches for in a bad moment into
     * an error message, and re-stamping the timestamp would rewrite when the
     * pause began, which is the one fact support will be asked about.
     *
     * @param  string  $actor  `audit_log`'s vocabulary — `user:14`, `support:9`.
     */
    public function pause(Business $business, string $actor, ?string $reason = null): void
    {
        $this->assertIsTenant($business);

        if ($this->isPaused($business)) {
            return;
        }

        $reason = $this->normaliseReason($reason);

        DB::transaction(function () use ($business, $actor, $reason): void {
            // forceFill, because these columns are guarded on the model: the
            // service is the only writer and a request body carrying
            // `paused_at` must not be one. See Business::$guarded.
            $business->forceFill([
                'paused_at' => now(),
                'paused_by' => $actor,
                'pause_reason' => $reason,
            ])->save();

            $this->audit->record('tenant.paused', $actor, $business, array_filter([
                'reason' => $reason,
            ]));

            // ⚠️ THE FEED ENTRY IS NOT OPTIONAL AND IT IS NOT THE AUDIT ROW.
            // `28` §9.4 requires the owner to see that support was there and
            // what they did, and a pause somebody else applied is the single
            // most confusing state to arrive at with no explanation — the
            // product simply stops. The audit row is what an auditor reads; this
            // is what the owner reads.
            // No location id: a pause is the whole account, and attaching it
            // to one location of several would file it under a place the owner
            // did not choose. The title comes from the enum, which is where the
            // closed vocabulary lives.
            $this->activity->record(AutopilotActionType::TenantPaused);
        });
    }

    /**
     * Start again.
     *
     * Idempotent for pause()'s reason, and it clears all three columns together
     * because the CHECK constraint requires it — a resume that left `paused_by`
     * behind would be refused by the database, which is the constraint doing its
     * job on the path most likely to forget.
     */
    public function resume(Business $business, string $actor): void
    {
        $this->assertIsTenant($business);

        if (! $this->isPaused($business)) {
            return;
        }

        $pausedBy = $business->paused_by;

        DB::transaction(function () use ($business, $actor, $pausedBy): void {
            $business->forceFill([
                'paused_at' => null,
                'paused_by' => null,
                'pause_reason' => null,
            ])->save();

            // Both sides on the row, because "who ended it" and "who began it"
            // are different questions and support is asked the second one:
            // an owner resuming a support-applied pause is the case that needs
            // reading back, and an entry naming only the resumer loses it.
            $this->audit->record('tenant.resumed', $actor, $business, array_filter([
                'paused_by' => $pausedBy,
            ]));

            $this->activity->record(AutopilotActionType::TenantResumed);
        });
    }

    /**
     * Is this business stopped?
     *
     * Reads the column rather than a cached flag. A pause is checked before
     * every side effect, so a stale answer is a side effect that happened after
     * the owner pressed stop — which is the whole of what this feature is for.
     */
    public function isPaused(Business $business): bool
    {
        return $business->paused_at !== null;
    }

    /**
     * Why this account is stopped, or null.
     *
     * ⚠️ IT EXISTS SO THE SUPPORT CONSOLE DOES NOT HAVE TO READ THE COLUMN.
     * `28` §9.3's quick-actions rail shows the pause state on somebody else's
     * account, and the honest ways to serve that were an entry on the pause
     * chokepoint's allowlist for a *screen that only reads*, or two accessors
     * here. The allowlist is what stops a second idea of what a pause is from
     * appearing (see the lint), and every name on it is a file that can write
     * one — so the reader goes through the writer instead.
     *
     * Null for an owner's own pause: decision 825 asks them for no reason.
     */
    public function reasonFor(Business $business): ?string
    {
        return $business->pause_reason;
    }

    /**
     * Who stopped this account, in `audit_log`'s vocabulary, or null.
     */
    public function pausedBy(Business $business): ?string
    {
        return $business->paused_by;
    }

    /**
     * Is the tenant in context stopped?
     *
     * For callers that hold a tenant but not the model — `AutopilotJob` runs
     * from a queue payload and `ReviewRouter` from a location. Returns **true**
     * when no business row can be read, which is fail-closed on purpose: the
     * two ways that happens are a deleted business and a tenant that is not
     * established, and doing the tenant's work for them in either case is worse
     * than not doing it.
     */
    public function isCurrentTenantPaused(): bool
    {
        $business = Business::query()->find(Tenancy::idOrFail());

        return $business === null || $this->isPaused($business);
    }

    /**
     * ⚠️ THE CROSS-TENANT GUARD, HERE RATHER THAN IN EACH CALLER. Support pauses
     * a tenant that is not its own (`28` §9.5), so this class is reachable with
     * one business in context and another passed in — and writing a pause onto
     * the wrong business is the failure mode that looks like an outage to
     * somebody who did nothing. `BaaRecords` makes the same assertion for the
     * same reason.
     */
    private function assertIsTenant(Business $business): void
    {
        if ((int) $business->id !== Tenancy::idOrFail()) {
            throw new InvalidArgumentException(
                'Pause acts on the tenant in context. Wrap the call in Tenancy::actingAs().'
            );
        }
    }

    /**
     * Bounded, and empty means absent.
     *
     * It lands in `audit_log`, which is append-only forever, so an unbounded box
     * lets whoever fills it paste a document into a table nothing can edit
     * afterwards — `PhiTenants`' reasoning, and the column is 500 for the same
     * reason. A whitespace-only reason is stored as null rather than as a blank
     * string, so "no reason given" has one representation instead of two.
     */
    private function normaliseReason(?string $reason): ?string
    {
        $reason = trim((string) $reason);

        if ($reason === '') {
            return null;
        }

        return mb_substr($reason, 0, 500);
    }
}
