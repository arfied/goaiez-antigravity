<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\StoredObjectKind;
use App\Services\Export\ExportBuilder;
use App\Services\Storage\StorageFootprint;
use App\Services\Storage\StorageRetention;
use App\Services\Storage\StoredObjectTotals;
use App\Support\Tenancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * What this application is holding in object storage for one account —
 * decision 4763.
 *
 * ⛔ **THIS IS THE READER, AND IT EXISTS BECAUSE A MEASUREMENT WITH NO READER IS
 * 272's SHAPE.** That failure has seventeen recorded instances in this codebase
 * and `CLAUDE.md` names the tell: an isolation test passes perfectly against a
 * table nothing reads, so the suite is green and the feature is inert. Two
 * columns and a service would have been exactly that. `ShowSearchConsoleStatus`
 * reached for the same answer for the same reason — an Ops command is what
 * `ZernioGbpClient`'s docblock also names as the permitted non-test caller — and
 * it is a tool support genuinely needs on the phone: *"why is this account's
 * storage bill what it is?"* has five answers this command can size and one it
 * cannot. ⛔ **THAT SENTENCE READ "FIVE POSSIBLE ANSWERS AND THIS PRINTS WHICH"
 * UNTIL 2026-08-23 — BOTH READINGS KEPT AND DATED.** The pixel event archive is
 * a sixth store, written per business, with no row this command could sum; it
 * was outside the total from the day the collector landed and nothing here said
 * so. {@see self::archiveNobodyCounts()} is what says so now, and
 * [[\App\Enums\StoredObjectKind]] carries why it is not simply added.
 *
 * ⛔ **AND IT REFUSES NOTHING.** There is no ceiling behind this figure and
 * object storage in aggregate is still uncapped — see {@see StorageFootprint}
 * for the four kinds a ceiling may not touch and why. Printing a number is the
 * whole of what this does.
 *
 * ⚠️ **ONE ACCOUNT, NAMED, NEVER A LIST — AND THAT IS THE TENANT BOUNDARY
 * RATHER THAN A UI PREFERENCE.** Decision 569's wall, in its sixth instance:
 * every one of the five tables is RLS `ENABLE`+`FORCE`d on `app.business_id`, a
 * platform operator has no tenant, and a leaderboard of the ten largest accounts
 * would need a query that crosses the boundary in five places at once. A
 * per-tenant total readable across tenants is a leak, not a metric. Naming one
 * account sets `app.business_id` to it and nothing else becomes visible; a wrong
 * number and a number belonging to nobody are indistinguishable, which is the
 * point.
 *
 * It is **read-only and files nothing** — no impersonation session and no audit
 * row, on `ShowSearchConsoleStatus`' reasoning: it reads counts and byte totals
 * and no customer's content, not a name, not a number, not a transcript. Compare
 * `AccountDirectory`, which does file `business.viewed_by_staff` (decision 804),
 * and correctly: it reads the account.
 */
#[Signature('storage:footprint {business : The business id}')]
#[Description('Print how many bytes object storage is holding for one account')]
final class ShowStorageFootprint extends Command
{
    public function handle(StorageFootprint $footprint, StorageRetention $retention): int
    {
        $businessId = (int) $this->argument('business');

        /** @var list<StoredObjectTotals> $lines */
        $lines = Tenancy::actingAs($businessId, fn (): array => $footprint->forTenant());

        $this->newLine();
        $this->line('Account   #'.$businessId);
        $this->line('Measured  from the rows that name an object, never from a bucket listing.');
        $this->newLine();

        foreach ($lines as $line) {
            $this->line(
                '  '.str_pad($line->kind->label(), 20)
                .str_pad($this->size($line->bytes), 12, ' ', STR_PAD_LEFT)
                .'  '.$line->objects.' object'.($line->objects === 1 ? '' : 's')
                .'  '.$this->keeping($line->kind, $retention)
            );
        }

        $this->newLine();
        $this->line('  '.str_pad('Total', 20).str_pad($this->size($this->sum($lines)), 12, ' ', STR_PAD_LEFT));

        $unmeasured = array_sum(array_map(static fn (StoredObjectTotals $l): int => $l->unmeasured, $lines));

        if ($unmeasured > 0) {
            // ⚠️ **A WARNING RATHER THAN A FOOTNOTE, BECAUSE THE TOTAL IS SHORT.**
            // These are objects stored before 4762 added a size column, so their
            // bytes are genuinely unknown rather than zero. An operator quoting
            // the total as complete is the misreport this line exists to stop.
            $this->newLine();
            $this->warn('  ⚠ '.$unmeasured.' stored object'.($unmeasured === 1 ? '' : 's')
                .' predate the size columns, so the total above is short by their size.');
        }

        // ⚠️ **THE ORPHAN CAVEAT IS PRINTED EVERY TIME AND IS NOT CONDITIONAL.**
        // Unlike the line above, this application cannot tell whether it applies
        // — that is the definition of an orphan — so a run that hid it would be
        // asserting something nothing here checked.
        $this->newLine();
        $this->line('  Bytes on the disk that no row names are not counted here. Only the');
        $this->line('  export prefix is ever swept for those (ExportBuilder::purgeOrphans).');

        $this->archiveNobodyCounts($businessId);

        return self::SUCCESS;
    }

    /**
     * The store this command cannot size, said out loud on every run.
     *
     * ⛔ **THE CAVEAT ABOVE IS TECHNICALLY TRUE OF THE PIXEL ARCHIVE AND READS
     * AS BEING ABOUT STRAGGLERS.** *"Bytes on the disk that no row names"*
     * does literally cover L0 — every object of it — but an operator reads that
     * sentence as a handful of orphans left behind by a failed delete, not as a
     * whole store written once per beacon. `StoredObjectKind`'s own bolded rule
     * is that a metric quietly omitting a kind *"reads as complete and is
     * not"*, and quiet is the half this line ends. The omission itself stays,
     * permanently, and that enum's docblock carries the three reasons.
     *
     * ⚠️ **IT NAMES NO NUMBER, AND THAT IS THE POINT RATHER THAN A GAP.** How
     * much the total is short by is **not derivable from this application**:
     * there is no per-object row, and the one row that names an L0 object
     * (`l1_events.l0_path`) is many-to-one, empty for an object that derived
     * nothing, and pruned on a horizon the object itself does not have — so a
     * figure taken from it would fall as the archive grew. A wrong number here
     * would be acted on where a missing one is asked about.
     *
     * ⚠️ **UNCONDITIONAL, AND `warn` RATHER THAN `line`.** The orphan caveat
     * above is `line` because this application cannot tell whether it applies;
     * this one always applies, so it is printed at the same weight as the
     * *"predate the size columns"* warning, which is the other line that says
     * the total is short.
     */
    private function archiveNobodyCounts(int $businessId): void
    {
        $this->newLine();
        $this->warn('  ⚠ Pixel event archive (L0) is not in the total above, and this command');
        $this->warn('    cannot size it: one gzipped object per beacon under');
        $this->warn('    class=…/business='.$businessId.'/ on the warehouse.l0_disk disk, with no');
        $this->warn('    row to sum. Nothing deletes it on a period — only a tenant erasure.');
    }

    /**
     * How long this kind is kept, in the operator's words.
     *
     * ⛔ **A RUNTIME READ, WHERE THIS USED TO BE `StoredObjectKind::isPruned()`**
     * (4940). That method answered a compile-time `$this === self::Export` and
     * the line printed *"(nothing deletes these)"* against four kinds — true when
     * 4763 wrote it, and it would have stayed on the screen unchanged on the day
     * a period was set and objects started disappearing. A footprint that
     * describes its own turnover has to read the same figure the sweep reads, or
     * it is 2505's shape in the one place an operator goes to check.
     *
     * ⚠️ **THE UNSET LINE NAMES THE KEY**, because "kept indefinitely" without it
     * is an observation and with it is something a person can act on.
     */
    private function keeping(StoredObjectKind $kind, StorageRetention $retention): string
    {
        $days = $retention->periodFor($kind);

        if ($days !== null) {
            return '(deleted after '.$days.' days)';
        }

        $key = $kind->retentionKey();

        if ($key === null) {
            // Export, whose seven days are published and fixed in code (4941).
            return '(deleted after '.ExportBuilder::LINK_EXPIRY_DAYS.' days)';
        }

        return '(kept indefinitely — no period set: '.$key.')';
    }

    /**
     * @param  list<StoredObjectTotals>  $lines
     */
    private function sum(array $lines): int
    {
        return array_sum(array_map(static fn (StoredObjectTotals $l): int => $l->bytes, $lines));
    }

    /**
     * Bytes an operator can read at a glance.
     *
     * ⚠️ **1024 AND NOT 1000, BECAUSE THE COMPARISON IS AGAINST A CEILING IN
     * CODE.** `KnowledgeUploads::MAX_KILOBYTES` and `CampaignMedia::MAX_BYTES`
     * are both binary, and an operator dividing this figure by one of them wants
     * the same base on both sides. R2's own bill is quoted in decimal GB, which
     * is one more reason this command prints no money.
     */
    private function size(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }

        foreach (['KB', 'MB', 'GB', 'TB'] as $index => $unit) {
            $scaled = $bytes / (1024 ** ($index + 1));

            if ($scaled < 1024 || $unit === 'TB') {
                return number_format($scaled, 1).' '.$unit;
            }
        }

        return $bytes.' B';
    }
}
