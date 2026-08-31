<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MagicLinkService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Delete sign-in links that can no longer be used (7880–7899).
 *
 * ## ⛔ WHAT THIS CLOSES: A PRUNER WITH NO CALLER, OVER EMAIL ADDRESSES
 *
 * `MagicLinkService::prune()` was written on 2026-07-30 with the table, tested
 * never, and **called by nothing** — not by `app/`, not by `routes/console.php`,
 * not by a test. Two artefacts described the sweep as though it ran: the
 * service's own docblock says consumed rows are *"kept for a while rather than
 * deleted on use … a day is long enough for that conversation"*, and the
 * creating migration says the row is kept *"so that pruning is one scheduled
 * sweep rather than a delete on the login path"* and indexes
 * `['expires_at', 'consumed_at']` to *"serve the pruning sweep"*. **"A while"
 * was for ever, and the index served nothing.**
 *
 * ⚠️ **THE COST IS THE `less stored PII` TIEBREAKER AND NOT A DISK ONE**, and
 * saying which matters, because on volume this table is uninteresting: one row
 * per sign-in request. What it accumulated is a **permanent history of when
 * each account holder asked to sign in, and from which network**, back to the
 * day the table shipped and forward without limit.
 *
 * ⚠️ **AND THE NETWORK HALF IS JOINABLE, WHICH IS THE PART A ROW COUNT DOES NOT
 * SHOW.** `requested_ip_hash` is `HashedIp::hash()`, and `public_audits.ip_hash`
 * is *the same function of the same address* — `TrialEligibility` says so in
 * writing, and domain-separates `trial_claims` away from both precisely so that
 * pair cannot be joined. `public_audits` is pruned at ninety days by
 * `audits:prune`. This table was pruned at never, so the un-pruned side kept
 * the join alive indefinitely against a table that expires.
 *
 * ## ⚠️ WHY THERE IS NO REGISTRY ROW HERE, AND `storage.retention_days.*` HAS
 * FOUR
 *
 * A retention period over somebody's personal data is normally the owner's
 * ruling (4941, 4942) — this codebase may not invent one any more than it may
 * quote the Limited tier price. **That rule is about a period nothing derives**:
 * an inbound photograph or a voicemail recording has no natural expiry, so any
 * number is invented, and an invented one has to come from the person who
 * carries the commitment.
 *
 * This period is derived. A magic link is unusable
 * `MagicLinkService::LIFETIME_MINUTES` after issue — fifteen minutes, argued
 * where it is declared — and the extra day exists for one stated purpose, so
 * that support can tell a replay from an expiry when somebody asks why a link
 * failed. **The number was argued in the file before this command existed and
 * is not changed here; what was missing is the caller and only the caller.**
 *
 * ⛔ **AND AN OPS ROW WOULD BE WRONG IN BOTH DIRECTIONS OF `CLAUDE.md`'s OWN
 * TIEBREAKER.** Shorter than a day breaks the support conversation the row is
 * kept for; longer is an operator lengthening how long this platform holds a
 * record of who signed in, from a settings screen, with nothing asking why. A
 * tenant-facing or Ops-facing toggle here is a support surface whose only use
 * is to store more PII for longer — (1) and (2) pointing the same way, which is
 * rare enough to be worth writing down.
 *
 * ## ⚠️ NO OWNER WALK, AND THAT WAS CHECKED RATHER THAN ASSUMED
 *
 * `PruneIngestRejects` is the seventh owner walk in this codebase and it exists
 * because `ingest_rejects` is `ENABLE`+`FORCE` row-level secured: a range DELETE
 * from a command with no tenant set matches **zero rows and exits 0**, which is
 * also what a healthy night looks like (7626, 7785(d)).
 *
 * **`magic_link_tokens` is the other case.** It carries no `business_id`, its
 * creating migration says it *cannot* be tenant-owned because the row is written
 * and read before anyone is authenticated, and `TenancyTest`'s `$exempt` census
 * names it — *"issued to an email, read before auth"*. `pg_class` was read on
 * 2026-08-22 rather than inferred from that: `relrowsecurity` **false**,
 * `relforcerowsecurity` **false**, no policy. So a plain DELETE is the correct
 * shape here and an owner walk would be ceremony — worse than ceremony, since
 * `magic_link_tokens` rows belong to users who may own no business at all, and
 * a walk keyed on `businesses.owner_user_id` would silently skip every one of
 * them.
 *
 * ⚠️ **THE TEST ASSERTS THAT PREMISE DIRECTLY**, 7626's rule: rows exist, the
 * command runs with no tenant established, and the rows are gone.
 *
 * ## ⚠️ IT RECLAIMS RATHER THAN ENFORCES, AND THAT IS WHY IT IS NIGHTLY
 *
 * `audits:prune`'s distinction. A token is refused by `consume()`'s
 * `expires_at > now()` predicate the instant it expires, so a row this sweep has
 * not reached yet is already dead as a credential. What the sweep removes is the
 * **record**, which is the thing with the retention question attached.
 */
#[Signature('auth:prune-magic-links')]
#[Description('Delete sign-in link records past their retention window')]
final class PruneMagicLinkTokens extends Command
{
    public function handle(MagicLinkService $links): int
    {
        $deleted = $links->prune();

        $this->info($deleted === 0
            ? 'No expired sign-in links to prune.'
            : "Pruned {$deleted} expired sign-in ".str('link')->plural($deleted).'.');

        return self::SUCCESS;
    }
}
