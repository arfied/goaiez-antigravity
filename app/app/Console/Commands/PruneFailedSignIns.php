<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PublicAudit;
use App\Services\Auth\FailedSignIns;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Deletes failed-sign-in buckets past their retention window (9860–9879).
 *
 * ⛔ **IT SHIPPED IN THE SAME SLICE AS THE TABLE, AND THAT IS THE POINT.**
 * `PruneMagicLinkTokens` exists because `magic_link_tokens` was written on
 * 2026-07-30 with a `prune()` that had **no caller anywhere in this
 * repository** until 2026-08-22 — every artefact around it read as though the
 * table were bounded and none of them was wrong about the code (7880–7899).
 * `PrunePublicAudits` states the rule it produced: *a retention column that
 * nothing ever acts on is not a retention policy, it is a claim.* A new table
 * holding keyed fingerprints of other people's email addresses, growing at a
 * rate an attacker chooses, is the worst possible one to ship on a claim.
 */
#[Signature('auth:prune-failed-sign-ins')]
#[Description('Delete failed-sign-in buckets past their retention window')]
final class PruneFailedSignIns extends Command
{
    /**
     * How long a bucket is kept.
     *
     * ⛔ **A CEILING THAT WAS DERIVED, NOT A PERIOD THAT WAS PICKED — AND THE
     * DIFFERENCE IS STATED RATHER THAN GLOSSED.** 4941/4942 make a retention
     * period over somebody's personal data the owner's ruling, and
     * `PruneMagicLinkTokens` narrows that: **the rule is about a period nothing
     * derives.** An inbound photograph has no natural expiry, so any number for
     * it is invented and an invented one has to come from the person carrying
     * the commitment.
     *
     * ✅ **THIS ONE HAS A BOUND AND THE BOUND IS A JOIN.** `ip_hash` here is
     * `HashedIp::of()` — *the same function of the same address* as
     * `public_audits.ip_hash`, which is exactly the sentence 7886 turned on. That
     * decision's finding is that **an un-pruned side keeps a join alive
     * indefinitely against a table that expires**, which is how
     * `magic_link_tokens` came to hold a network fingerprint for ever beside a
     * neighbour that dropped it at ninety days. Keeping these longer than
     * {@see PublicAudit::RETENTION_DAYS} would rebuild that asymmetry in the
     * commit that closed it, so **the join sets a ceiling**, and the ceiling is
     * the neighbour's own figure.
     *
     * ⚠️ **A CEILING IS NOT A DERIVATION AND ANY SHORTER FIGURE ALSO SATISFIES
     * IT.** Ninety is the longest defensible number rather than the only
     * defensible one, and `CLAUDE.md`'s *less stored PII* points down from here
     * and not up. **Shortening it is the owner's and needs no engineering**;
     * lengthening it past `PublicAudit::RETENTION_DAYS` reopens 7886 and must
     * not be done without answering it. Declared against that constant so the
     * relationship is visible on a diff rather than a coincidence of two nineties.
     *
     * ⛔ **AND IT IS NOT A REGISTRY ROW, WHICH IS THE OTHER HALF OF
     * `PruneMagicLinkTokens`' ARGUMENT AND POINTS THE SAME WAY HERE.** An
     * Ops-editable period on this table would be a support surface whose only
     * use is to hold a record of who was targeted for longer — tiebreakers (1)
     * and (2) agreeing, which is rare enough to be worth writing down — and an
     * unset `storage.retention_days.*` key prunes **nothing at all** (4940–4955),
     * so the fail-open state of a row would be the unbounded table this command
     * exists to prevent.
     */
    public const int RETENTION_DAYS = PublicAudit::RETENTION_DAYS;

    /**
     * Rows per DELETE — `PrunePublicAudits`' figure and its reasoning.
     */
    private const int CHUNK = 500;

    public function handle(FailedSignIns $register): int
    {
        // ⚠️ AGAINST `window_start` AND NOT `last_seen_at`. The bucket is named
        // by the hour it covers, so an hour-long bucket whose last attempt
        // landed at :59 would otherwise outlive its neighbours by an hour for no
        // reason anybody could state. At ninety days the difference is
        // cosmetic; stating which column bounds the table is not.
        $cutoff = CarbonImmutable::now()->subDays(self::RETENTION_DAYS);

        $deleted = $register->prune($cutoff, self::CHUNK);

        $this->info($deleted === 0
            ? 'No expired failed-sign-in buckets to prune.'
            : "Pruned {$deleted} expired failed-sign-in ".str('bucket')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
