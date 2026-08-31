<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Ops\ColumnReaders;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Which columns in this schema nothing outside `app/Models` names -
 * decisions 8550-8579.
 *
 * ## ⛔ WHAT THIS REPLACES IS A SCOUT, AND EVERY SCOUT SO FAR HAS BEEN WRONG
 *
 * `CLAUDE.md`'s writerless bullet lists instances and says in bold that the
 * count *"has never been the population"*. It has been re-derived by hand at
 * least four times - 272, 4961, 6880, 8295 - and 8391 records the last one
 * missing *"the sibling on the adjacent line"* of the very `Schema::create` it
 * was reading. A brief written this way is wrong in a direction nobody checks,
 * because a column that is not on the list is a column nobody looks at.
 *
 * ⚠️ **THIS IS `db:footprint`'s ARGUMENT ONE LEVEL DOWN** (8000-8019). That
 * command replaced *"a list of thirteen table names that was never the
 * population"* with a derivation from `pg_class`, on the grounds that *"a list
 * of table names is written by a person reading migrations, and a person reading
 * migrations finds the tables they thought to look at."* Same sentence, same
 * catalog, columns instead of tables.
 *
 * ## ⚠️ IT REFUSES NOTHING, IT RINGS NOTHING, AND IT IS NOT A LINT
 *
 * ⛔ **A LINT WAS CONSIDERED AND REFUSED, AND 8551 CARRIES THE ARGUMENT.** A
 * build-failing *"every column has a reader"* over a schema that legitimately
 * carries columns for unbuilt features is red on the day it is written, so it
 * ships with an exemption list of seventy-odd entries - and this wave's own lane
 * B exists because exemption lists decay. `CLAUDE.md` 511: a lint tuned until it
 * stops crying wolf is one tuned until it catches nothing.
 *
 * **What is machine-checked instead is one rule with one argument** -
 * `tests/Feature/ConfirmIsUnbuiltTest.php`, for `29` §2 rules 37 and 38 - and
 * this command is the derivation a person runs when they want the population.
 * Two different jobs; neither substitutes for the other.
 *
 * ## ⚠️ THE CAVEATS PRINT ON EVERY RUN AND THAT IS DELIBERATE
 *
 * The number below is a count under a stated method and **not** the population
 * either; {@see ColumnReaders} names the shapes it cannot see. A figure
 * carried out of here into a brief without them becomes the next stale sentence,
 * which is the failure this command was built to end rather than to join.
 */
#[Signature('db:column-readers {--table= : Only this table} {--silent : Only columns nothing in app/ mentions at all} {--suspect : Instead, list columns scored ALIVE by fewest code matches - where the collisions hide} {--collisions : Instead, list the column names MORE THAN ONE table carries and the tree cannot account for - the one blind spot that is arithmetic}')]
#[Description('Print every column that nothing outside app/Models names')]
final class ShowColumnReaders extends Command
{
    public function handle(ColumnReaders $readers): int
    {
        $all = $readers->all();
        $table = $this->option('table');

        $dead = array_values(array_filter(
            $all,
            static fn (array $line): bool => ! $line['inCode'] && ! $line['framework'],
        ));

        $shown = $this->option('suspect')
            ? $this->suspects($readers, $all)
            : $dead;

        if (is_string($table) && $table !== '') {
            $shown = array_values(array_filter(
                $shown,
                static fn (array $line): bool => $line['table'] === $table,
            ));
        }

        if ($this->option('silent')) {
            $shown = array_values(array_filter(
                $shown,
                static fn (array $line): bool => ! $line['inComment'],
            ));
        }

        $this->newLine();
        $this->line('Schema    '.$this->schema().'  -  '.count($all).' columns, housekeeping excluded');
        $this->line('Searched  '.count($readers->scannedPaths()).' roots, comments stripped. app/Models, database/ and tests/ excluded.');
        $this->newLine();

        if ($this->option('collisions')) {
            $this->collisions($readers, is_string($table) ? $table : null);
            $this->caveats();

            return self::SUCCESS;
        }

        $this->row('TABLE', 'COLUMN', 'STATE');

        foreach ($shown as $line) {
            $this->row(
                $line['table'],
                $line['column'],
                $this->option('suspect')
                    ? $readers->codeMatches($line['column']).' code match(es)'
                    : ($line['inComment'] ? 'documented' : 'silent'),
            );
        }

        $this->summarise($all, $dead);
        $this->caveats();

        return self::SUCCESS;
    }

    /**
     * The names more than one application table carries that the tree cannot
     * account for.
     *
     * ⛔ **THIS SAID "TWO APPLICATION TABLES" AND THE `--collisions` HELP TEXT
     * SAID "TWO TABLES", AND THE THRESHOLD IS `count($tables) >= 2` — WAVE 42,
     * DECISION 11250.** The next paragraph of this very docblock reasons about
     * *"a name carried by three tables with two occurrences"*, and the caveat
     * block this command prints on every run names `hold_until` across
     * **three** — so the command contradicted itself between its own `--help`
     * and its own output. ⚠️ **Derived rather than taken from a brief**: read
     * straight out of `pg_attribute`, `hold_until` is carried by
     * `growth_pages`, `platform_posts` and `replies`, and two further names in
     * this schema are carried by three tables each. **A fixed arity in a
     * sentence describing a `>= 2` predicate is `CLAUDE.md`'s own *state it as
     * a property, never as an inventory* (8861), on the smallest possible
     * scale.**
     *
     * ⛔ **THIS IS THE ONE BLIND SPOT WITH AN ANSWER RATHER THAN A CAVEAT, AND
     * THE DIFFERENCE IS THAT IT IS ARITHMETIC.** One occurrence of a bare token
     * is one site and refers to at most one column, so a name carried by three
     * tables with two occurrences leaves at least one of the three with no
     * reference of its own - and all three are scored alive, so that one is
     * **missing from the list above**. No judgement, no threshold, no exemption
     * list; the table count is `pg_class` and the occurrence count is the same
     * regex the verdict uses.
     *
     * ⚠️ **A ZERO-OCCURRENCE ROW IS NOT NEW INFORMATION AND IS MARKED AS SUCH.**
     * Those columns are already on the dead list; they are printed because the
     * arithmetic is the same and hiding them would make the shortfall look like
     * it needed a rule.
     *
     * ⚠️ **IT DOES NOT SAY *WHICH* OF THE SHARING COLUMNS IS UNREFERENCED**, and
     * it cannot: the method that would - qualifying the match with a table name
     * - scores almost every column in this schema dead, which the service
     * refuses in writing. **The pair is the finding and the reader picks.**
     */
    private function collisions(ColumnReaders $readers, ?string $table): void
    {
        $collisions = $readers->collisions();

        if ($table !== null && $table !== '') {
            $collisions = array_values(array_filter(
                $collisions,
                static fn (array $c): bool => in_array($table, $c['tables'], true),
            ));
        }

        $this->row('COLUMN', 'CARRIED BY', 'UNACCOUNTED FOR');

        $omitted = 0;

        foreach ($collisions as $collision) {
            if ($collision['scoredAlive']) {
                $omitted += $collision['unreferenced'];
            }

            $this->row(
                $collision['column'],
                implode(', ', $collision['tables']),
                $collision['unreferenced'].' of '.count($collision['tables'])
                    .' from '.$collision['matches'].' occurrence(s)'
                    .($collision['scoredAlive'] ? ' - scored ALIVE, so missing above' : ' - already listed above'),
            );
        }

        $this->newLine();
        $this->line('  '.count($collisions).' shared column name(s) the tree cannot account for.');
        $this->line('  At least '.$omitted.' column(s) have no reference of their own AND are scored alive,');
        $this->line('  so the dead list omits at least that many. This is a floor under a floor.');
    }

    /**
     * The columns scored *alive*, fewest code matches first.
     *
     * ⛔ **THIS IS THE BLIND SPOT TURNED INTO A LEAD LIST, AND IT EXISTS BECAUSE
     * THE BLIND SPOT BIT TWICE IN ONE DAY.** `support_settings.retention_days`
     * and `locations.boost_score` are both dead, both hand-verified, and both
     * reported alive above - each because something unrelated spells the bare
     * word. ⛔ **THIS SAID *"each because a column shares its name with a registry
     * key (`storage.retention_days.*`, `features.boost_score`)"* UNTIL 2026-08-23
     * AND ONLY ONE OF THE TWO IS THAT** (8920). `features.boost_score` really is
     * the collider, spelled bare as `MarketingCapability`'s case value;
     * `storage.retention_days.` is **dot-preceded and contributes zero**, and the
     * live token belongs entirely to `ExportBuilder`'s export manifest. **A dead
     * list that silently omits a class of dead column is the failure this whole
     * command documents**, so the alive set is orderable rather than opaque.
     *
     * ⚠️ **NO THRESHOLD AND NO EXEMPTIONS.** There is no match count above which
     * a column is certainly alive; a cut-off would be 511's lint tuned until it
     * catches nothing, and a list of known collisions would be the exemption list
     * this slice refused to write. **The order is the product** - start at the
     * top, and stop when the matches stop looking like coincidences.
     *
     * @param  list<array{table: string, column: string, framework: bool, inCode: bool, inComment: bool}>  $all
     * @return list<array{table: string, column: string, framework: bool, inCode: bool, inComment: bool}>
     */
    private function suspects(ColumnReaders $readers, array $all): array
    {
        $alive = array_values(array_filter(
            $all,
            static fn (array $line): bool => $line['inCode'] && ! $line['framework'],
        ));

        usort(
            $alive,
            static fn (array $a, array $b): int => $readers->codeMatches($a['column'])
                <=> $readers->codeMatches($b['column']),
        );

        return $alive;
    }

    /**
     * The two counts, and the one sentence this command exists to print.
     *
     * ⛔ **`documented` IS CALLED OUT SEPARATELY BECAUSE IT IS THE FINDING.** A
     * column whose only trace outside `app/Models` is a paragraph recording that
     * it is dead **reads as alive to every grep anybody will ever run over this
     * tree** - so the ones already investigated are precisely the ones a hand
     * census drops. That is why the population has always been undercounted, and
     * it is not a property of any particular scout being careless.
     *
     * @param  list<array{table: string, column: string, framework: bool, inCode: bool, inComment: bool}>  $all
     * @param  list<array{table: string, column: string, framework: bool, inCode: bool, inComment: bool}>  $dead
     */
    private function summarise(array $all, array $dead): void
    {
        $application = array_filter($all, static fn (array $l): bool => ! $l['framework']);
        $documented = array_filter($dead, static fn (array $l): bool => $l['inComment']);

        $tables = array_unique(array_map(static fn (array $l): string => $l['table'], $dead));

        $this->newLine();
        $this->line('  '.count($dead).' of '.count($application).' application columns have no reference in code'
            .' outside app/Models, across '.count($tables).' tables.');
        $this->line('  '.count($documented).' of those are named in an app/ comment - a grep scores every one of them alive.');
    }

    /**
     * What a `no reader` line does not mean.
     *
     * ⚠️ **PRINTED EVERY RUN RATHER THAN LEFT IN A DOCBLOCK**, because the
     * failure being closed is a number travelling into a brief without its
     * method. `CLAUDE.md` removed the pixel's measured byte figure from prose
     * for exactly this reason and left the gate's own message as the authority.
     *
     * ⛔ **EVERY COLUMN NAME BELOW MUST BE WRITTEN TABLE-QUALIFIED, AND THAT IS
     * NOT A STYLE RULE - IT IS WHAT KEEPS THESE SENTENCES FROM CHANGING THE
     * NUMBER PRINTED ABOVE THEM** (8785, 8925). These are `$this->line()` string
     * literals, so they are **code** to `ColumnReaders::scan()`, which strips
     * only `T_COMMENT` and `T_DOC_COMMENT`; and `scannedPaths()` globs `app/*`
     * minus `Models`, so this file is in the corpus its own verdicts come from.
     * **The only thing that stops a name here counting as a reference is the dot
     * in front of it**, which `ColumnReaders::pattern()`'s lookbehind excludes.
     * ⚠️ **Measured 2026-08-23 by planting two unqualified names in this block**:
     * the dead list went 73 → 72 and a `--collisions` row vanished, 7 → 6 -
     * silently, and one of the two was this block's own worked example.
     * ✅ **`ColumnCensusTest` now fails the build on it**, by asking the census
     * the same questions over a corpus without this file and `ColumnReaders`
     * and requiring the same answers. **Do not remove a qualifier to make a line
     * fit.**
     */
    private function caveats(): void
    {
        $this->newLine();
        $this->line('  This is a count under a stated method and not the population. It cannot see:');
        $this->line('   · a bare-token collision - the same word spelled, unqualified, about something');
        $this->line('     else. locations.boost_score is dead and reads alive from the registry key');
        $this->line('     features.boost_score; support_settings.retention_days is dead and reads alive');
        $this->line('     from an unrelated export-manifest key in ExportBuilder - NOT from');
        $this->line('     storage.retention_days.*, whose leading dot is excluded.');
        $this->line('     --suspect orders the alive set by fewest matches, which is where they surface.');
        $this->line('   · a mention in a Blade comment - support_settings.forwarding_verified_at is dead');
        $this->line('     and reads alive, from one {{-- --}} line in account/calls.blade.php.');
        $this->line('   · a collision with another table\'s column of the same name - the match carries');
        $this->line('     no table qualifier, so automation_runs.quality_score is scored alive by the one');
        $this->line('     occurrence that belongs to growth_pages. --collisions counts a collision only');
        $this->line('     while the occurrences are FEWER than the tables sharing the name, so it goes');
        $this->line('     quiet the moment one table is used enough to account for all of them:');
        $this->line('     growth_pages.hold_until is named all over app/Services/Content, which accounts');
        $this->line('     for replies.hold_until and platform_posts.hold_until too - both dead as a hold,');
        $this->line('     both scored alive, and on neither list.');
        $this->line('   · a column reached by a computed name - $model->{$field}, ->update($validated).');
        $this->line('  Every one of these scores a dead column ALIVE, so the real figure is higher, never lower.');
        $this->newLine();
    }

    private function row(string $table, string $column, string $state): void
    {
        // ⚠️ PADDED *AND* MARK-SEPARATED - `laravel/pao`'s OutputCleaner
        // collapses every run of spaces in a dev install and not in production,
        // so padding alone aligns only on the machine nobody is looking at
        // (8003). No test may assert this alignment.
        $this->line('  '.str_pad($table, 34).' · '.str_pad($column, 34).' · '.$state);
    }

    private function schema(): string
    {
        /** @var object{current_schema: ?string}|null $row */
        $row = DB::selectOne('SELECT current_schema()');

        return $row->current_schema ?? 'unknown';
    }
}
