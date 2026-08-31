<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\RetentionScope;
use App\Services\Ops\PlatformHealthChecks;
use App\Services\Ops\TableFootprint;
use App\Services\Ops\TableFootprintLine;
use App\Support\TableHorizons;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * What this database is holding, table by table, and what removes any of it -
 * decisions 8000-8019.
 *
 * ## ⛔ WHAT THIS REPLACES IS A LIST OF THIRTEEN TABLE NAMES THAT WAS NEVER THE
 * POPULATION
 *
 * *"Thirteen tables have no horizon"* was repeated across three waves of briefs
 * and **the largest table in this schema was not on it**. It was not on it
 * because a list of table names is written by a person reading migrations, and
 * a person reading migrations finds the tables they thought to look at. That is
 * `docs/FAILURE-SHAPES.md`'s *"the count in this bullet is not a tally of the
 * shape and should not be read as one"*, one shape over from where the list
 * lived.
 *
 * ⚠️ **THE ANSWER HERE IS DERIVED FROM `pg_class` AND CANNOT BE INCOMPLETE.**
 * Every table in `current_schema()` is a line, {@see TableHorizons} supplies the
 * horizon column, and *no horizon* is what a table gets by having nothing say
 * otherwise. **A table added to this schema next month appears the day its
 * migration runs, with nobody to remember it.**
 *
 * ## ⛔ IT REFUSES NOTHING AND IT RINGS NOTHING, AND BOTH ARE ARGUED
 *
 * `storage:footprint` and `PlatformHealth::durationsBySource()` are this
 * codebase's two standing answers to *"measure a quantity nobody has a figure
 * for"*, and both are on-demand commands whose reader is a person rather than a
 * threshold. This is the third, on the same terms, and 8010 records why a size
 * bell was refused rather than not thought of: **a healthy platform's tables
 * grow**, so an absolute byte ceiling is the shape
 * {@see PlatformHealthChecks} already warns about in writing -
 * *"the figure an operator picked for a quiet Tuesday fires every busy afternoon
 * and the pager is muted by the person carrying it"*.
 *
 * ## ⚠️ IT NEEDS NO TENANT AND THAT IS THE PART THAT IS EASY TO GET WRONG
 *
 * Commands here walk every user and every business they own, because a
 * statement against a FORCE row-level-secured table from a console process
 * matches zero rows and exits 0 (7626). {@see TableFootprint} carries why none
 * of that applies to a catalog read, why a `count(*)` may never be added to
 * it, and why the count this sentence used to carry is deleted rather than
 * corrected (11262).
 *
 * It is **read-only and files nothing** - no impersonation session and no audit
 * row, on `ShowStorageFootprint`'s reasoning. It reads sizes and write counters
 * and no customer's content: not a name, not a number, not a transcript. It
 * cannot even name an account.
 */
#[Signature('db:footprint {--unbounded : Only the tables nothing on a clock deletes a row from}')]
#[Description("Print every table's size, write shape and retention horizon")]
final class ShowTableFootprint extends Command
{
    public function handle(TableFootprint $footprint): int
    {
        $all = $footprint->all();
        $lines = $this->option('unbounded')
            ? array_values(array_filter($all, static fn (TableFootprintLine $l): bool => ! $l->rowsAreBounded()))
            : $all;

        $this->newLine();
        $this->line('Schema    '.$this->schema().'  -  '.count($all).' tables, largest first');
        $this->line('Measured  from pg_class and pg_stat_user_tables. No tenant, no owner walk.');
        $this->newLine();

        $this->row('TABLE', 'SIZE', '~ROWS', 'REWRITTEN', 'REMOVED', 'HORIZON');

        foreach ($lines as $line) {
            $this->row(
                $line->table,
                $this->size($line->bytes),
                $this->rows($line),
                $this->rewrites($line),
                $this->removed($line),
                $this->horizon($line),
            );
        }

        $this->summarise($all);
        $this->caveats($all);

        return self::SUCCESS;
    }

    /**
     * One line of the report.
     *
     * ⛔ **THE COLUMNS ARE PADDED *AND* SEPARATED BY A MARK, WHICH LOOKS
     * REDUNDANT AND IS NOT — 8003.** `laravel/pao` is a `require-dev` package
     * whose `PaoOutputStyle` replaces the console output object and runs
     * `OutputCleaner` over every line, and one of its rules is
     * `preg_replace('/[ \t]+/', ' ', $output)`. **So every run of spaces in
     * anything written through `$this->line()` collapses to one, in every
     * environment that has dev dependencies** — which is every local run and
     * every test — and does not collapse in production, which installs
     * `--no-dev`. Padding alone therefore aligns on the one machine nobody is
     * looking at. The mark is what makes the collapsed form readable; the
     * padding is what makes the production form a table.
     *
     * ⚠️ **THE SAME CLEANER COLLAPSES `\.{3,}` TO `..` AND STRIPS BOX-DRAWING
     * CHARACTERS**, so a dot-filled leader and `$this->table()`'s borders are
     * both out for the same reason. ⛔ **AND NO TEST MAY ASSERT THIS
     * ALIGNMENT**: the suite runs with dev dependencies, so an assertion about
     * column positions would pin the collapsed form and go green while
     * production drifted. 352/397/565's rule — pin the honest thing and say
     * what it does not cover.
     */
    private function row(string $table, string $size, string $rows, string $rewrites, string $removed, string $horizon): void
    {
        $this->line(
            '  '.str_pad($table, 34)
            .' · '.str_pad($size, 9, ' ', STR_PAD_LEFT)
            .' · '.str_pad($rows, 11, ' ', STR_PAD_LEFT)
            .' · '.str_pad($rewrites, 5, ' ', STR_PAD_LEFT)
            .' · '.str_pad($removed, 5, ' ', STR_PAD_LEFT)
            .' · '.$horizon
        );
    }

    /**
     * The three counts, and the one line this whole command exists to print.
     *
     * ⛔ **THE LARGEST TABLE WITH NO ROW HORIZON IS NAMED OUT LOUD**, because
     * the failure this replaces was not that nobody could have worked it out -
     * it was that working it out took reading a hundred and sixty migrations,
     * and so it was done from memory instead. One sentence, derived, is what
     * stops the next brief carrying a stale list.
     *
     * @param  list<TableFootprintLine>  $all
     */
    private function summarise(array $all): void
    {
        $rowBounded = array_filter($all, static fn (TableFootprintLine $l): bool => $l->rowsAreBounded());
        $bytesOnly = array_filter(
            $all,
            static fn (TableFootprintLine $l): bool => $l->horizon !== null
                && $l->horizon['scope'] === RetentionScope::Bytes,
        );
        // ⛔ ITS OWN COUNT, NOT FOLDED INTO EITHER NEIGHBOUR - wave 41 lane D
        // (11073). Before this slice these were inside `$rowBounded`, which is
        // the one arrangement that cannot be read correctly: an operator who
        // wants to know what grows without limit reads the third figure, and
        // five tables that grow without limit were in the first.
        $unset = array_filter($all, static fn (TableFootprintLine $l): bool => $l->periodIsUnset());
        $noHorizon = array_filter($all, static fn (TableFootprintLine $l): bool => $l->horizon === null);
        $unbounded = array_values(array_filter(
            $all,
            static fn (TableFootprintLine $l): bool => ! $l->rowsAreBounded(),
        ));

        $this->newLine();
        $this->line('  '.count($rowBounded).' tables have a row horizon with a period; '
            .count($unset).' are swept by a command with no period set; '
            .count($bytesOnly).' lose their bytes and keep their rows; '
            .count($noHorizon).' have nothing on a clock at all.');
        $this->line('  '.count($unbounded).' of them therefore have an unbounded row count.');

        $largest = $unbounded[0] ?? null;

        if ($largest instanceof TableFootprintLine) {
            $this->line('  Largest with no row horizon: '.$largest->table
                .' at '.$this->size($largest->bytes).'.');
        }
    }

    /**
     * What the figures above do and do not mean.
     *
     * ⚠️ **PRINTED EVERY RUN AND NEVER CONDITIONAL**, `ShowStorageFootprint`'s
     * orphan-caveat rule: a run that hid one of these would be asserting
     * something this command has not checked.
     *
     * ⛔ **THE FIRST LINE IS THE ONE THAT MATTERS AND IT IS FIRST FOR THAT
     * REASON.** A reader who takes the unbounded count as a defect count will go
     * and build a hundred and forty pruners, and about a hundred and thirty of
     * them would delete the customer's own account a row at a time.
     *
     * @param  list<TableFootprintLine>  $all
     */
    private function caveats(array $all): void
    {
        $this->newLine();
        $this->line('  No horizon is the right answer for most of this schema. A row per business,');
        $this->line('  per user or per plan is bounded by the thing it describes. The tables worth');
        $this->line('  arguing about are the ones with a low REWRITTEN figure - one row per event,');
        $this->line('  never touched again, counting up for as long as the platform runs.');

        $this->newLine();
        $this->line('  A dash in HORIZON means nothing on a CLOCK deletes here. It does not mean the');
        $this->line('  rows stay: sessions, cache and password_reset_tokens are cleared by their own');
        $this->line('  drivers, and jobs is emptied by a worker process - one that may not be running,');
        $this->line('  and that only pops the queue names it was started with. REMOVED is what tells');
        $this->line('  those apart from a table that only ever grows, and what tells a horizon that');
        $this->line('  works from one that is merely scheduled.');

        $this->newLine();
        $this->line('  NO PERIOD SET in HORIZON means a sweep is scheduled and has no number to');
        $this->line('  sweep by, so it opens no query and deletes nothing. That is a decision nobody');
        $this->line('  has made rather than one that was got wrong - these periods are deliberately');
        $this->line('  unseeded, and several of them are counsel\'s to settle. Until a number is in');
        $this->line('  Ops the rows are as unbounded as a table with no sweep at all, and this report');
        $this->line('  counts them that way. Run the named command to see the key.');

        $this->newLine();
        $this->line('  SIZE is exact: heap, indexes and TOAST, from pg_total_relation_size.');
        $this->line('  ~ROWS, REWRITTEN and REMOVED come from the statistics collector. They reset');
        $this->line('  when a table is rebuilt and they count transactions that later rolled back, so');
        $this->line('  read them as a shape and never as a census. REMOVED can exceed 100% after a');
        $this->line('  reset, for the same reason.');

        $unanalysed = array_filter(
            $all,
            static fn (TableFootprintLine $l): bool => $l->analyzedAt === null && $l->bytes > 0,
        );

        if ($unanalysed !== []) {
            // ⚠️ **A WARNING RATHER THAN A FOOTNOTE.** A never-analysed table
            // reports `n_live_tup = 0`, which is indistinguishable on this
            // report from an empty table - so on a fresh install every row
            // estimate is a zero that means "unknown".
            $this->newLine();
            $this->warn('  '.count($unanalysed).' of these have never been analysed, so their ~ROWS');
            $this->warn('  reads 0 whatever they hold. Run ANALYZE to make that column mean anything.');
        }
    }

    /**
     * What removes from this table, and whether it has anything to remove by.
     *
     * ⛔ **A HORIZON WHOSE PERIOD IS AN OPERATOR SETTING PRINTS THE SETTING'S
     * STATE, NOT A POINTER TO ANOTHER COMMAND — wave 41 lane D (11073).** These
     * five cells read *"per operator setting - run automation:prune-runs to see
     * if it is set"* until this slice: the report declining to answer the one
     * question it is being read for, in the column an operator takes at a
     * glance, beside a `rowsAreBounded()` that had already answered **yes** on
     * their behalf.
     *
     * ⚠️ **THE UNSET WORDING IS DESCRIPTIVE AND DELIBERATELY NOT A REPROACH.**
     * Every one of these periods is unseeded on purpose and several are
     * counsel's to settle, so the cell names the row and states the
     * consequence, and {@see self::caveats()} carries the sentence saying a
     * pending decision is a legitimate state. A report that scolded an operator
     * over a question nobody has answered is muted exactly the way 511
     * describes.
     */
    private function horizon(TableFootprintLine $line): string
    {
        if ($line->horizon === null) {
            return '-';
        }

        $keeps = $line->horizon['keeps'];

        if ($line->horizon['key'] !== null) {
            $keeps .= $line->statedDays === null
                ? ' - NO PERIOD SET ('.$line->horizon['key'].'), so nothing is deleted'
                : ' - '.$line->statedDays.' days';
        }

        return $line->horizon['command'].', '.$keeps
            .' ('.$line->horizon['scope']->label().')';
    }

    private function rows(TableFootprintLine $line): string
    {
        return $line->analyzedAt === null ? '?' : number_format($line->liveRows);
    }

    /**
     * The share of writes that rewrote an existing row, as a percentage.
     *
     * A dash where nothing has ever been written: `0%` there would read as
     * "pure event table", which is a claim about a table nothing has exercised.
     */
    private function rewrites(TableFootprintLine $line): string
    {
        $bp = $line->rewriteRateBp();

        return $bp === null ? '-' : round($bp / 100).'%';
    }

    /**
     * The share of everything ever inserted that has since been removed.
     *
     * ⛔ **THE COLUMN THAT ANSWERS *"IS THE DASH BESIDE `jobs` HONEST?"* —
     * 8750-8779.** Every other column on this report reads identically on a
     * queue that drains and a queue nothing consumes: the HORIZON dash means
     * *no scheduled deleter*, which is true in both states, and `~ROWS` is
     * `n_live_tup`, which is `0` on a never-analysed table whatever it holds.
     * This one is lifetime insert and delete counters, which need no ANALYZE,
     * and a queue nobody pops shows it here first.
     *
     * ⚠️ **AND IT IS THE ONE COLUMN THAT CAN FALSIFY A HORIZON RATHER THAN
     * REPEAT IT.** A `0%` beside a table whose HORIZON cell names a command is
     * a scheduled sweep that has never deleted anything - `SendingHealth::
     * prune()`'s eleven silent months (7785(d)) rendered in one glance.
     *
     * A dash where nothing has ever been inserted, on `rewrites()`' reasoning:
     * `0%` there would claim nothing is ever removed from a table nothing has
     * exercised.
     */
    private function removed(TableFootprintLine $line): string
    {
        $bp = $line->removalRateBp();

        return $bp === null ? '-' : round($bp / 100).'%';
    }

    private function schema(): string
    {
        /** @var object{current_schema: string|null}|null $row */
        $row = DB::selectOne('SELECT current_schema() AS current_schema');

        return (string) ($row->current_schema ?? 'unknown');
    }

    /**
     * Bytes an operator can read at a glance.
     *
     * ⚠️ **1024 AND NOT 1000, AND THE SAME BASE AS `storage:footprint`'s
     * FORMATTER DELIBERATELY.** The two reports sit beside each other on the
     * same phone call - one for the bucket, one for the database - and a person
     * comparing them should not have to ask which base each used. It is a second
     * private formatter rather than a shared helper because extracting it means
     * editing a command this slice has no other business in.
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
