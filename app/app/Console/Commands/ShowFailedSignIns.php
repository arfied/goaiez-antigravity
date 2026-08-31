<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\FailedSignIn;
use App\Services\Auth\FailedSignIns;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * *Is somebody working through a list of addresses right now, and did any of it
 * land on a real account?* — decisions 9860–9879.
 *
 * ⛔ **THIS COMMAND IS WHY THE TABLE IS NOT `FAILURE-SHAPES.md`'s *a table with
 * a writer and no reader*.** A register nothing asks a question of is a row
 * count, and the shape's own worked example — `businesses.pixel_tenant_id` —
 * is a column that shipped, was written by one thing, read by nothing, and
 * looked like the answer for months. **The listener and this command are one
 * slice deliberately**, and a screen is not what was owed: `CLAUDE.md`'s first
 * tiebreaker is *less support surface*, and the person who asks this question
 * has an SSH session open at the time they ask it.
 *
 * ⛔ **IT REFUSES NOBODY AND ALWAYS EXITS ZERO.** 9723 refused a lockout, a
 * captcha and stuffing detection by arithmetic, and they are the owner's. A
 * non-zero exit here would be the first step toward this becoming a brake by
 * accident, through some later `composer deploy` step or a monitor reading its
 * status. It reports.
 *
 * ## ⚠️ WHAT THE REPORT LEADS WITH, AND WHY IT IS NOT VOLUME
 *
 * Sources are ordered by **distinct addresses tried**, never by attempts.
 * Spraying is *defined* by being low volume per address (9723), so a table
 * ordered by attempts puts one person mistyping their own password at the top
 * and a thousand-address sweep below the fold. Breadth is the discriminator and
 * it is the sort key.
 *
 * ⛔ **AND THE SOURCE COLUMN IS THE HALF THAT LIES ON THIS DEPLOYMENT.**
 * `trustProxies` is unconfigured (336), so the day anything is put in front of
 * this application every request carries the edge's address and the whole
 * report collapses to one source — **while every figure keyed on the address
 * tried stays exactly right.** The header says so on every run rather than
 * leaving it in a decision row, because a report that reads *"1 source"* during
 * a distributed spray is worse than no report.
 *
 * ## ⚠️ THE ADDRESSES ARE NOT HERE AND CANNOT BE
 *
 * `failed_sign_ins` stores a keyed fingerprint, because most of the addresses
 * tried belong to people who are not our users (the creating migration carries
 * the argument in full). `--email=` is how a named address is checked: it
 * hashes what you type and looks that up, so the cleartext exists for the
 * length of one command and is never written anywhere.
 */
#[Signature('auth:failed-sign-ins
    {--hours=24 : How far back to look}
    {--limit=10 : Rows per table}
    {--email= : Ask about one address instead — hashed locally, never stored}')]
#[Description('Report failed credential checks: how wide, how deep, and whether any landed on a real account')]
final class ShowFailedSignIns extends Command
{
    public function handle(FailedSignIns $register): int
    {
        $hours = max(1, (int) $this->option('hours'));
        $limit = max(1, (int) $this->option('limit'));
        $since = CarbonImmutable::now()->subHours($hours);

        $address = $this->option('email');

        if (is_string($address) && $address !== '') {
            return $this->reportOneAddress($register, $address, $since, $hours);
        }

        $summary = $register->summarySince($since);

        $this->info("Failed credential checks in the last {$hours}h.");
        $this->newLine();

        $this->line('  Attempts                          '.number_format($summary['attempts']));
        $this->line('  Distinct addresses tried          '.number_format($summary['addresses']));
        $this->line('  Distinct sources                  '.number_format($summary['sources']));

        // ⛔ ONLY WHEN THERE ARE ANY, AND THE LINE EXISTS SO THE TABLE BELOW
        // RECONCILES WITH THE FIGURE ABOVE IT (9876). A bucket with no client
        // address is a row in "widest sources" and is NOT a source, so without
        // this an operator counts three rows under a headline saying two.
        //
        // ⚠️ IT DOES NOT PROMISE THE ROW IS VISIBLE. `--limit` can push it off
        // the table, so the sentence states the arithmetic and nothing about
        // where to look — a caveat that is false at limit 2 and true at limit 3
        // is worse than one that says less.
        if ($summary['sourceless_attempts'] > 0) {
            $this->line('    with no client address          '
                .number_format($summary['sourceless_attempts'])
                .' '.str('attempt')->plural($summary['sourceless_attempts'])
                .', counted above and not counted as a source');
        }

        $this->line('  Attempts against a real account   '.number_format($summary['attempts_on_accounts']));
        $this->line('  Real accounts touched             '.number_format($summary['addresses_with_accounts']));
        $this->newLine();

        if ($summary['attempts'] === 0) {
            $this->line('  Nothing recorded in this window.');
            $this->newLine();
            $this->disclose();

            return self::SUCCESS;
        }

        $this->line('Widest sources — ordered by how many DISTINCT addresses each tried,');
        $this->line('because a spray is broad and shallow and a typo is narrow and deep.');
        $this->newLine();

        $this->table(
            ['source', 'addresses', 'attempts', 'on real accounts', 'last seen'],
            $register->widestSourcesSince($since, $limit)->map(
                fn (FailedSignIn $row): array => [
                    // The bare column, since these rows are grouped projections
                    // rather than buckets — `ip_hash` is null for a source-less
                    // attempt and `-` says so rather than printing nothing.
                    self::short($row->getAttribute('ip_hash')),
                    number_format((int) $row->getAttribute('addresses')),
                    number_format((int) $row->getAttribute('attempts')),
                    number_format((int) $row->getAttribute('attempts_on_accounts')),
                    (string) $row->getAttribute('last_seen_at'),
                ]
            )->all(),
        );

        $this->newLine();
        $this->line('Most-targeted addresses.');
        $this->newLine();

        $this->table(
            ['address', 'attempts', 'sources', 'has an account', 'last seen'],
            $register->mostTargetedSince($since, $limit)->map(
                fn (FailedSignIn $row): array => [
                    self::short($row->getAttribute('email_hash')),
                    number_format((int) $row->getAttribute('attempts')),
                    number_format((int) $row->getAttribute('sources')),
                    $row->getAttribute('account_existed') ? 'yes' : 'no',
                    (string) $row->getAttribute('last_seen_at'),
                ]
            )->all(),
        );

        $this->disclose();

        return self::SUCCESS;
    }

    /**
     * One address, in cleartext, hashed here and not kept.
     */
    private function reportOneAddress(FailedSignIns $register, string $address, CarbonImmutable $since, int $hours): int
    {
        $buckets = $register->forAddressSince($address, $since);

        $this->info("Failed credential checks against that address in the last {$hours}h.");
        $this->newLine();

        if ($buckets->isEmpty()) {
            // ⚠️ AND THIS SENTENCE IS BOUNDED ON PURPOSE. "None" is true of the
            // window and of the retention horizon, never of all time, and an
            // operator reading it as "never" would be reading a pruned table as
            // a quiet one.
            $this->line('  None in this window. The register keeps '
                .PruneFailedSignIns::RETENTION_DAYS.' days; older buckets are gone.');
            $this->newLine();
            $this->disclose();

            return self::SUCCESS;
        }

        $this->table(
            ['hour', 'source', 'attempts', 'account existed', 'last seen'],
            $buckets->map(fn (FailedSignIn $row): array => [
                $row->window_start->toDateTimeString(),
                self::short($row->ip_hash),
                number_format($row->attempts),
                $row->account_existed ? 'yes' : 'no',
                $row->last_seen_at->toDateTimeString(),
            ])->all(),
        );

        $this->disclose();

        return self::SUCCESS;
    }

    /**
     * ⛔ PRINTED ON EVERY RUN, INCLUDING THE QUIET ONES, AND ESPECIALLY THOSE.
     * Both sentences change what the numbers above mean, and a caveat an
     * operator has to already know is not a caveat.
     */
    private function disclose(): void
    {
        $this->newLine();
        $this->line('  This register refuses nobody — `POST /login` is bounded by 5/minute per');
        $this->line('  (address, source) and by nothing across addresses (9723). A lockout, a');
        $this->line('  captcha and stuffing detection are the owner\'s to rule on.');
        $this->line('  TrustProxies is unconfigured (336): behind a CDN the source column');
        $this->line('  collapses to one value and every address figure above stays correct.');
    }

    /**
     * A fingerprint an operator can compare by eye without pasting 64 hex
     * characters into a chat window.
     */
    private static function short(mixed $hash): string
    {
        return is_string($hash) && $hash !== '' ? substr($hash, 0, 12).'…' : '-';
    }
}
