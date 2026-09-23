<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\TrialClaimKind;
use App\Services\Billing\TrialEligibility;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes signup-origin claims past their retention window.
 *
 * ⚠️ **THIS EXISTS BECAUSE THE ALTERNATIVE WAS A CLAIM RATHER THAN A POLICY.**
 * `PrunePublicAudits` sets the rule in its own docblock — *"A retention column
 * that nothing ever acts on is not a retention policy — it is a claim"* — and
 * the first version of this slice stored a keyed network hash per account
 * **forever**, against that precedent's 90 days, arguing that no window could be
 * chosen honestly until the grant lane existed. **That over-reached.** Forty
 * lines of {@see TrialEligibility} declare the velocity limit and its window "an
 * engineering rail and mine to set" on T137 §3's authority, and a retention
 * window on a hash-of-a-hash is the same class of number. 502's
 * withhold-rather-than-default posture is about **prices the owner owns**, and
 * `CLAUDE.md`'s own tie-break — *less stored PII* — points at a number rather
 * than at indefinite.
 *
 * ⛔ **ONLY THE SIGNUP-ORIGIN KIND. A LISTING CLAIM IS NEVER PRUNED, AND THE
 * ASYMMETRY IS THE WHOLE DESIGN.** A listing claim *is* the register: expiring
 * one silently un-burns an identity and hands a second account an allowance the
 * first one already holds. An origin claim is a velocity signal with a derivable
 * shelf life; a listing claim is a permanent statement about who holds what. A
 * sweep that could not tell them apart would be worse than no sweep, which is
 * why the predicate is `kind` and not age alone.
 *
 * ⚠️ **AND THE SWEEP DELETES THE ANCHOR, WHICH IS WHY THE WINDOW CANNOT BE
 * SHORT.** `TrialEligibility::signupOriginRefusal()` measures its window from the
 * account's *own* claim, so removing that row does not merely age a burst out —
 * it makes the account answer "no origin recorded", which is silently eligible.
 * Verified rather than reasoned about: a 30-day window returns every account in
 * a burst to eligible on day 31, the exact failure 3224's anchored window exists
 * to prevent.
 *
 * A hard delete, chunked, for `PrunePublicAudits`' reasons exactly: no erasure
 * story ends with the row still present, and a backlog should not be one long
 * transaction on a table the signup path also writes to.
 */
#[Signature('trials:prune-origins')]
#[Description('Delete trial signup-origin claims past their retention window')]
final class PruneTrialOriginClaims extends Command
{
    /**
     * How long a signup-origin claim is kept.
     *
     * ⚠️ **DERIVED RATHER THAN PICKED, AND THE DERIVATION IS THE POINT.** The
     * floor is what the velocity control needs in order to still be true when a
     * grant is minted: the trial is `billing.trial_days` (14), the burst window
     * is {@see app(\App\Services\Billing\TrialEligibility::class)->signupOriginWindowDays()} either side of the
     * anchor (30), and a grant can only be minted for an account that still
     * exists and is at or near trial. That is roughly 44 days of span, and
     * anything comfortably beyond it preserves everything 3224 needs **without
     * the funder having to exist first** — which is the step the first version
     * mistook for a blocker.
     *
     * 180 is that floor with a wide support and dispute tail, and it is
     * deliberately **twice** `public_audits`' 90: that window covers a visitor
     * who never signed up, and this one covers a live account's fraud signal,
     * where being wrong costs a real customer their allowance. It is short
     * enough that nobody carries a network hash into a second year.
     *
     * ⚠️ **IF THE GRANT LANE MINTS LATER THAN TRIAL, THIS NUMBER MOVES WITH IT.**
     * That is the one dependency the first version had right; what it got wrong
     * was concluding the number could not be set at all in the meantime.
     */
    public const int RETENTION_DAYS = 180;

    /**
     * Rows per DELETE — `PrunePublicAudits`' figure and its reasoning.
     */
    private const int CHUNK = 500;

    public function handle(): int
    {
        $cutoff = CarbonImmutable::now()->subDays(self::RETENTION_DAYS);

        $deleted = 0;

        do {
            // ⚠️ `DB::table()` RATHER THAN THE MODEL, DELIBERATELY. `TrialClaim`
            // refuses `deleting` outright (3237), which is right for every caller
            // except this one — that guard exists to stop a repair script
            // un-burning the register, and this sweep is the single sanctioned
            // deletion. Reaching past it here is `Business::provision()` reaching
            // past `$guarded`: one named writer, argued at the line.
            $batch = DB::table('trial_claims')
                ->whereIn('id', fn ($query): mixed => $query
                    ->select('id')
                    ->from('trial_claims')
                    ->where('kind', TrialClaimKind::SignupOrigin->value)
                    ->where('created_at', '<', $cutoff)
                    ->limit(self::CHUNK))
                ->delete();

            $deleted += $batch;
        } while ($batch === self::CHUNK);

        $this->info($deleted === 0
            ? 'No expired signup-origin claims to prune.'
            : "Pruned {$deleted} expired signup-origin ".str('claim')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
