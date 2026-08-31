<?php

declare(strict_types=1);

namespace App\Services\Ops;

use Illuminate\Support\Facades\DB;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Every column in this schema, and whether any code outside `app/Models` names
 * it - decisions 8550-8579.
 *
 * ## ⛔ WHAT THIS REPLACES IS A GREP, AND A GREP IS WRONG IN ONE DIRECTION
 * SYSTEMATICALLY
 *
 * `docs/FAILURE-SHAPES.md`'s *"a table, column or control with no writer"*
 * bullet says its own count *"has never been the population"*, and every
 * attempt to find the
 * population so far has been a person running `grep` over `app/`. **A grep
 * counts a column's epitaph as a heartbeat.** In this codebase, a dead column's
 * only occurrence outside `app/Models` is very often a docblock paragraph
 * saying it is dead - so the ones somebody already investigated are exactly the
 * ones the next census scores as alive. Measured on 2026-08-23: a plain-substring
 * pass over `app/` (minus `app/Models`) plus `resources/` found **48**
 * application columns with no reference; stripping comments and applying a word
 * boundary found **73** on the same tree, and **14 of the 25 recovered were
 * columns whose sole trace in `app/` was prose about their own deadness**.
 * ⚠️ **71 once wave 17's `businesses` drop applied** - the figure moves on its
 * own, because it is derived. That is the property a prose list does not have,
 * and it is the only reason to prefer this to a paragraph.
 *
 * ⚠️ **Decision 8392 is the single-column form of this and it is what suggested
 * generalising it.** `businesses.messaging_mode` *"returns four hits in the code
 * directories, which reads as a live column. All four are reads, and the most
 * substantial is … a comment"*. That is one column, found by hand, in a slice
 * that then said in writing (8399) that it was **not** doing a writerless sweep
 * because the count has never been the population. This is the sweep, derived.
 *
 * ## ⚠️ WHAT IT CANNOT SEE, AND THE WORKED EXAMPLE IS IN THE CAVEAT FOR A REASON
 *
 * ⛔ **THE FIGURE THIS CLASS PRODUCES IS NOT THE POPULATION EITHER, AND SAYING
 * SO IS THE POINT RATHER THAN A DISCLAIMER.** It is a count under a stated
 * method, and the method has residual blind spots, **every one of which scores a
 * dead column as ALIVE** - so the figure is a floor and the error only ever runs
 * one way. Two are textual and are worked below; the fourth is arithmetic and
 * has a method rather than a paragraph; the computed-name one is structural and
 * follows them. ⚠️ **Both textual instances were found by somebody reading code,
 * not by this class**, which is the honest limit for those two: it replaces the
 * scout's *enumeration*, never the scout. ⛔ **The fourth is the exception and
 * is why it is worth having** - `self::collisions()` finds its instances
 * without a scout, and the count is `--collisions`' to print rather than this
 * paragraph's to carry.
 *
 *  - **A bare-token collision with an unrelated concept.** `locations.boost_score`
 *    has no reader; `MarketingCapability` declares `case BoostScore = 'boost_score'`
 *    for the registry key `features.boost_score`. `support_settings.retention_days`
 *    has no reader; `ExportBuilder` writes it twice as a key in an export
 *    payload manifest.
 *    ⛔ **THE TWO ARE NOT THE SAME SHAPE AND THIS PARAGRAPH SAID THEY WERE UNTIL
 *    2026-08-23** (8920). It read *"a predictable shape: a column named after
 *    something that is also a registry key"* and offered both as instances.
 *    **Only `boost_score` is one.** `StorageRetention`'s prefix
 *    `storage.retention_days.` is **dot-preceded, so `self::pattern()`'s
 *    lookbehind excludes it and it contributes zero** - the mechanism is
 *    documented at `self::namedInCode()` two hundred lines below and contradicted
 *    here. Re-derived per file on 2026-08-23: the whole live token set is
 *    `ExportBuilder`, twice. ⚠️ **The registry-key half is the *narrower* claim
 *    and naming it as the shape is what hid the wider one**: any occurrence of
 *    the bare name at all scores the column alive, registry key or not.
 *    **Both hand-verified dead on 2026-08-23 and both still reported alive by
 *    this class.** They are named rather than special-cased: two exceptions
 *    would become a list, and 511 is what a list becomes.
 *    ⚠️ **`--suspect` is the answer instead** - it orders every column scored
 *    *alive* by how few code matches it has, so the collisions surface at the
 *    top of a lead list rather than being silently absent from a dead one.
 *  - **A mention inside a Blade comment.** `.blade.php` is not tokenised, so
 *    `{{-- … --}}` counts. `support_settings.forwarding_verified_at` is dead and
 *    is reported alive by one line of
 *    `resources/views/livewire/account/calls.blade.php`.
 *  - ⛔ **A COLLISION WITH ANOTHER TABLE'S COLUMN OF THE SAME NAME - AND THIS
 *    ONE IS DERIVED RATHER THAN DESCRIBED.** The match carries no table
 *    qualifier, so every table sharing a column name shares one verdict:
 *    `automation_runs.quality_score` has no writer and no reader and is scored
 *    alive by the single occurrence that belongs to `growth_pages`.
 *    ⚠️ **A FOURTH PARAGRAPH IN A FILE WHOSE FIGURE IS ALREADY A FLOOR WOULD BE
 *    314-316's SHAPE** - the sentence explaining the limit is what stops the
 *    next reader checking - so this shape has a method instead of a warning.
 *    `self::collisions()` counts it by pigeonhole from `pg_class` and the same
 *    regex, `--collisions` prints it, and the number moves on its own. **It is
 *    the only one of the four with an arm that can go red.**
 *    ⛔ **AND THE PIGEONHOLE IS A FLOOR RATHER THAN THE ANSWER, WHICH THIS
 *    PARAGRAPH READ AS THOUGH IT WERE NOT - CORRECTED 2026-08-28 (wave 41 lane
 *    D, 11086, found by wave 41 lane B).** `self::collisions()` reports a
 *    shared name only while the occurrence count is **fewer** than the number
 *    of tables carrying it; the moment one table's real usage exceeds the
 *    sharer count, every sharer is "accounted for" arithmetically and the row
 *    disappears. **`hold_until` is the occupant**: three tables carry it
 *    (`growth_pages`, `platform_posts`, `replies`), seventeen occurrences name
 *    it, **all seventeen are about `growth_pages.hold_until`** - and
 *    `replies.hold_until` is only ever assigned `null` while
 *    `platform_posts.hold_until` is named nowhere outside `app/Models` at all.
 *    Both are dead as a hold, both score ALIVE, and **neither appears on the
 *    dead list or on `--collisions`.** ⚠️ **So the derived arm narrows the
 *    blind spot and does not close it**, and what still finds these is
 *    `--suspect` followed by **reading the call sites** - which is
 *    `CLAUDE.md`'s own rule that aliveness is visible only at call sites,
 *    arriving at the instrument built to answer without them.
 *
 * ⚠️ **And a column reached only by a computed name is invisible to any textual
 * method** - `$model->{$field}`, `->update($validated)` where the keys come from
 * a variable rule set. **So the honest reading of a `no reader` line is *nothing
 * spells this column out*, not *nothing touches it*.** `ShowColumnReaders`
 * prints every caveat on every run, because a number carried out of here
 * into a brief without them is `CLAUDE.md`'s 2505 shape and this class exists
 * because of that shape.
 *
 * ⛔ **`ShowColumnReaders` AND `TableFootprint` ARE NAMED BARE HERE AND MUST NOT
 * BE TIDIED INTO `{@see}` REFERENCES** (8573). `composer lint` hoists a
 * namespaced docblock name into a real `use` statement, and the import it
 * produced here was **a Service depending on a Console Command** — the
 * dependency pointing backwards, generated by a formatter, from a sentence.
 * Nothing was wrong with the prose and nothing would have been wrong with the
 * code; the import was invented between them.
 *
 * ## ⚠️ `app/Models` IS EXCLUDED ON PURPOSE AND IT IS THE WHOLE DESIGN
 *
 * A `$casts` entry, a `$fillable` entry and a `@property` annotation are what a
 * dead column looks like from the inside - decision 4961's
 * `businesses.pixel_tenant_id` had all of them and *"only `BusinessFactory` ever
 * wrote it"*. `database/factories` is excluded for the same reason and it is the
 * sharper half: a factory seeding a plausible value is what disguised
 * `locations.current_rating` as dead when it was alive, and what disguised
 * `support_settings.emergency_keywords` as alive when it was dead. **Neither a
 * cast nor a factory is a call site**, and `CLAUDE.md` says a control's aliveness
 * is visible only at its call sites.
 *
 * ## ⚠️ NO TENANT, AND THE CATALOG READ IS WHY
 *
 * `pg_class` and `pg_attribute` describe relations rather than rows, so no
 * policy applies and this needs no tenant established - the same property
 * `TableFootprint` carries at length. **A `count(*)` may never be added
 * here for the same reason it may never be added there**: it would return zero
 * for every FORCE row-level-secured table from a console process and read as an
 * empty table rather than as an unestablished tenant.
 */
final class ColumnReaders
{
    /**
     * Columns every table has, which say nothing about a feature being wired.
     *
     * @var list<string>
     */
    public const array HOUSEKEEPING = ['id', 'created_at', 'updated_at', 'deleted_at'];

    /**
     * Tables the framework owns, whose columns are read inside `vendor/`.
     *
     * ⚠️ **A LIST, WHICH IS THE THING THIS CLASS IS OTHERWISE AGAINST**, so it
     * is deliberately the smallest one that can exist: it names only tables
     * whose migrations ship inside `laravel/framework`, `laravel/sanctum` and
     * `laravel/passkeys`, and every entry is verifiable by looking for the
     * migration in `vendor/`. It filters a **display** column rather than an
     * assertion - nothing fails because of it - so it cannot decay into 511's
     * lint tuned until it catches nothing.
     *
     * @var list<string>
     */
    public const array FRAMEWORK_TABLES = [
        'cache',
        'cache_locks',
        'failed_jobs',
        'job_batches',
        'jobs',
        'migrations',
        'passkeys',
        'password_reset_tokens',
        'personal_access_tokens',
        'sessions',
        'users',
    ];

    /**
     * Absolute paths kept out of the corpus - see `self::excluding()`.
     *
     * @var list<string>
     */
    private array $excluded = [];

    /** Every non-comment character of every scanned file, concatenated. */
    private ?string $code = null;

    /** Every comment character of every scanned PHP file, concatenated. */
    private ?string $comments = null;

    /**
     * Every column, with what names it.
     *
     * Ordered table then ordinal position, which is the order a reader of the
     * migration meets them in.
     *
     * @return list<array{table: string, column: string, framework: bool, inCode: bool, inComment: bool}>
     */
    public function all(): array
    {
        /** @var list<object{tbl: string, col: string}> $rows */
        $rows = DB::select(<<<'SQL'
            SELECT c.relname AS tbl, a.attname AS col
            FROM pg_class c
            JOIN pg_namespace n ON n.oid = c.relnamespace
            JOIN pg_attribute a ON a.attrelid = c.oid
            WHERE n.nspname = current_schema()
              AND c.relkind = 'r'
              AND a.attnum > 0
              AND NOT a.attisdropped
            ORDER BY c.relname, a.attnum
        SQL);

        $lines = [];

        foreach ($rows as $row) {
            $column = (string) $row->col;

            if (in_array($column, self::HOUSEKEEPING, true)) {
                continue;
            }

            $table = (string) $row->tbl;

            $lines[] = [
                'table' => $table,
                'column' => $column,
                'framework' => in_array($table, self::FRAMEWORK_TABLES, true),
                'inCode' => $this->namedInCode($column),
                'inComment' => $this->namedInComment($column),
            ];
        }

        return $lines;
    }

    /**
     * Whether any code outside `app/Models` spells `$identifier` out.
     *
     * ⚠️ **THE LOOKBEHIND EXCLUDES A PRECEDING DOT AND THAT IS A DELIBERATE
     * TRADE.** It is what stops `storage.retention_days.` counting as a
     * reference to `support_settings.retention_days` - a registry key prefix and
     * a column that share a word. The cost is that a genuinely qualified string
     * (`'support_settings.retention_days'` handed to `Arr::get()`) is missed,
     * scoring a live column dead. **That direction is the loud one**: a false
     * *dead* gets investigated and corrected, a false *alive* is never looked at
     * again, which is the asymmetry this whole class is built around.
     */
    public function namedInCode(string $identifier): bool
    {
        return preg_match($this->pattern($identifier), $this->code()) === 1;
    }

    /**
     * How many times code outside `app/Models` spells `$identifier` out.
     *
     * ⛔ **THIS EXISTS BECAUSE TWO INDEPENDENT SPOT-CHECKS FOUND DEAD COLUMNS
     * THIS CLASS SCORES ALIVE, AND SILENCE ABOUT A BLIND SPOT IS THE FAILURE
     * THE CLASS DOCUMENTS.** A column with one or two matches is far likelier to
     * be a bare-token collision than one with thirty, so ordering the *alive*
     * set ascending turns the blind spot into a lead list somebody can work
     * through from the top. ⚠️ **It is deliberately not a threshold**: there is
     * no count above which a column is certainly alive, and picking one would be
     * 511's lint tuned until it catches nothing. The order is the product; the
     * judgement stays with the reader.
     */
    public function codeMatches(string $identifier): int
    {
        // ⚠️ `preg_match_all()` returns `false` on a compilation or backtrack
        // failure, never on "no matches" — which is `0`. Treating the failure as
        // zero would report a live column as a perfect suspect, so it is the one
        // case worth distinguishing: a pattern that could not run has said
        // nothing, and `-1` is not a match count anybody will read as one.
        $matches = preg_match_all($this->pattern($identifier), $this->code());

        return $matches === false ? -1 : $matches;
    }

    /**
     * Column names two or more application tables share, where the tree does
     * not hold enough occurrences of the name for all of them.
     *
     * ⛔ **THIS IS THE FOURTH BLIND SPOT, AND IT IS THE ONE THE THREE ABOVE
     * COULD NOT BE - IT IS ARITHMETIC RATHER THAN A CAVEAT.** The regex above
     * matches a bare column name with **no table qualifier**, so every column
     * that shares a name is scored by one verdict. `automation_runs.quality_score`
     * has no writer and no reader; `growth_pages.quality_score` is written at
     * `GrowthPages::…()`; there is **one** occurrence of the token in the whole
     * scanned tree, and it scores **both** alive.
     *
     * ⚠️ **THE PIGEONHOLE IS WHY THIS CAN BE STATED AND NOT MERELY WARNED
     * ABOUT.** One occurrence of a token is one site and refers to at most one
     * column, so a name carried by `T` tables with `M` occurrences leaves at
     * least `T - M` of those columns with **no reference of their own** - and
     * every one of them is scored alive whenever `M` is one or more, so every
     * one of them is missing from the dead list this class produces. **No
     * judgement, no threshold and no exemption list**: the table count comes
     * from `pg_class` and the occurrence count from the same regex the verdict
     * uses. It is a floor on a floor, and it runs the same one way.
     *
     * ⛔ **A TABLE-QUALIFIED MATCH WAS THE OBVIOUS FIX AND IT IS REFUSED, IN
     * WRITING, BECAUSE IT IS NOT HONEST HERE.** Requiring `automation_runs.` in
     * front of the name would attribute every match correctly and score
     * **almost every column in this schema dead**: Eloquent never qualifies -
     * `$page->quality_score = …`, `->where('status', …)`, `@property ?int $score`
     * - and the qualified spelling appears essentially only in raw SQL. That is
     * the loud direction, but wrongly and for about thirteen hundred columns at
     * once, which is exactly the trade `self::scan()` already refuses for Blade
     * tokenising. ⚠️ **Qualification is honest only where the writer set is
     * enumerable**, which is a question about one table rather than about the
     * schema, and is where a test can ask it.
     *
     * ⚠️ **FRAMEWORK TABLES ARE EXCLUDED FROM THE TABLE COUNT** on the same
     * ground the dead list excludes them: their columns are read inside
     * `vendor/`, which is not scanned, so counting `failed_jobs.failed_at`
     * against `tenant_exports.failed_at` would manufacture a shortfall out of a
     * corpus boundary rather than find one.
     *
     * ⚠️ **`unreferenced` MEANS *nothing spells this column out*, NEVER *this
     * column is dead*** - the class docblock's reading, unchanged. A column
     * reached only by a computed name is unreferenced and alive.
     *
     * @return list<array{column: string, tables: list<string>, matches: int, unreferenced: int, scoredAlive: bool}>
     */
    public function collisions(): array
    {
        $tablesByColumn = [];

        foreach ($this->all() as $line) {
            if ($line['framework']) {
                continue;
            }

            $tablesByColumn[$line['column']][] = $line['table'];
        }

        $collisions = [];

        foreach ($tablesByColumn as $column => $tables) {
            $tables = array_values(array_unique($tables));

            if (count($tables) < 2) {
                continue;
            }

            $matches = $this->codeMatches((string) $column);

            // ⚠️ `-1` is the pattern that could not run, which has said nothing
            // - see `self::codeMatches()`. Treating it as zero would invent a
            // shortfall the size of the whole collision.
            if ($matches < 0 || $matches >= count($tables)) {
                continue;
            }

            $collisions[] = [
                'column' => (string) $column,
                'tables' => $tables,
                'matches' => $matches,
                'unreferenced' => count($tables) - $matches,
                // ⛔ **THE HALF THAT IS NEW INFORMATION.** At zero occurrences
                // every column of that name is already on the dead list above;
                // at one or more they are all scored alive, so the shortfall is
                // columns the list **omits**.
                'scoredAlive' => $matches > 0,
            ];
        }

        usort(
            $collisions,
            static fn (array $a, array $b): int => [$b['unreferenced'], $a['column']]
                <=> [$a['unreferenced'], $b['column']],
        );

        return $collisions;
    }

    /**
     * Whether any comment outside `app/Models` names `$identifier`.
     *
     * ⛔ **A `true` HERE BESIDE A `false` ABOVE IS THE FINDING, NOT A FOOTNOTE.**
     * It means somebody already established this column was dead, wrote it down
     * in the file where it would be read, and **that paragraph is now what makes
     * the column look alive to the next grep.** Fourteen columns were in that
     * state on 2026-08-23. The recording of an instance is what removes it from
     * the next scout's list, which is why `CLAUDE.md`'s count has never been the
     * population and could not have been.
     */
    public function namedInComment(string $identifier): bool
    {
        return preg_match($this->pattern($identifier), $this->comments()) === 1;
    }

    /**
     * The same census over the same corpus with `$paths` taken out of it.
     *
     * ⛔ **THIS EXISTS BECAUSE THE CENSUS MEASURES ITS OWN SOURCE, AND THAT IS
     * NOT AN ODDITY - IT IS THE ONE CORPUS MEMBER WHOSE CONTENTS ARE CHOSEN BY
     * WHOEVER IS WRITING ABOUT THE CENSUS** (8785, 8925). `ShowColumnReaders`
     * prints four worked examples to an operator on every run, and every one of
     * them is a `$this->line('…')` string literal - a `T_CONSTANT_ENCAPSED_STRING`
     * that `self::scan()` keeps, because it strips only `T_COMMENT` and
     * `T_DOC_COMMENT`. `self::scannedPaths()` globs `app/*` minus `Models`, which
     * includes `app/Console` and this very directory. **So the sentences
     * explaining the blind spots are inside the corpus the verdicts come from.**
     *
     * ⚠️ **WHAT SAVES IT TODAY IS ONE CHARACTER AND A HABIT.** Every column name
     * in that block is written table-qualified, and `self::pattern()`'s
     * lookbehind excludes a preceding dot. **Measured on 2026-08-23**: drop the
     * qualifier from one name in one printed line and the dead list loses a
     * column (73 → 72, `support_settings.sla_minutes` silently resurrected) or a
     * `--collisions` row disappears (7 → 6, `quality_score` - the block's own
     * worked example deleting the finding it exists to explain). **Nothing was
     * red in either case**, which is why this is a seam and not a paragraph.
     *
     * ⚠️ **IT IS DELIBERATELY GENERAL AND NAMES NOTHING.** A service must not
     * import a console command - `self::scan()`'s docblock records `composer lint`
     * hoisting exactly that dependency out of a sentence once already - so the
     * *caller* names the two files and this method knows about neither.
     *
     * ⚠️ **`$paths` ARE ABSOLUTE FILE PATHS, COMPARED EXACTLY.** A path that
     * matches no scanned file removes nothing and is silent, so a caller that
     * needs to know the exclusion happened has to prove it - which is why
     * `ColumnCensusTest` asserts a token only these files carry has gone to zero
     * rather than trusting the call.
     *
     * @param  list<string>  $paths
     */
    public function excluding(array $paths): self
    {
        $census = new self;
        $census->excluded = $paths;

        return $census;
    }

    /**
     * The directories a reference may live in.
     *
     * `app/Models`, `database/` and `tests/` are absent on purpose - see the
     * class docblock. `routes/` and `config/` are present because a column can
     * legitimately be named in a scheduled closure or a driver map.
     *
     * @return list<string>
     */
    public function scannedPaths(): array
    {
        $paths = array_values(array_filter(
            glob(base_path('app/*'), GLOB_ONLYDIR) ?: [],
            static fn (string $dir): bool => basename($dir) !== 'Models',
        ));

        return array_merge($paths, [
            base_path('resources'),
            base_path('routes'),
            base_path('config'),
        ]);
    }

    private function pattern(string $identifier): string
    {
        return '/(?<![A-Za-z0-9_.])'.preg_quote($identifier, '/').'(?![A-Za-z0-9_])/';
    }

    private function code(): string
    {
        $this->scan();

        return $this->code ?? '';
    }

    private function comments(): string
    {
        $this->scan();

        return $this->comments ?? '';
    }

    /**
     * Read the tree once and split it into code and comments.
     *
     * ⚠️ **ONCE, BECAUSE THE ALTERNATIVE IS QUADRATIC.** There are roughly 1,500
     * columns and 1,200 scanned files; re-reading the tree per column is a
     * minute of work to answer a question that takes a second.
     *
     * ⚠️ **`.blade.php` IS NOT TOKENISED**, so its comments stay in the code
     * half. `token_get_all()` on a Blade template parses the directives as HTML
     * inline text rather than PHP and loses the `{{ }}` expressions that are the
     * real references, which would score live columns dead - the loud direction,
     * but wrongly and for hundreds of columns at once. Keeping Blade whole
     * accepts a handful of false alives instead, named in the class docblock.
     */
    private function scan(): void
    {
        if ($this->code !== null) {
            return;
        }

        $code = '';
        $comments = '';

        foreach ($this->files() as $file) {
            $source = (string) file_get_contents($file);

            if (! str_ends_with($file, '.php') || str_ends_with($file, '.blade.php')) {
                $code .= "\n".$source;

                continue;
            }

            foreach (token_get_all($source) as $token) {
                if (! is_array($token)) {
                    $code .= $token;

                    continue;
                }

                if ($token[0] === T_COMMENT || $token[0] === T_DOC_COMMENT) {
                    $comments .= "\n".$token[1];

                    continue;
                }

                $code .= $token[1];
            }
        }

        $this->code = $code;
        $this->comments = $comments;
    }

    /**
     * @return list<string>
     */
    private function files(): array
    {
        $files = [];

        foreach ($this->scannedPaths() as $path) {
            if (! is_dir($path)) {
                continue;
            }

            /** @var iterable<SplFileInfo> $found */
            $found = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            );

            foreach ($found as $entry) {
                if ($entry->isFile() && preg_match('/\.(php|js|css)$/', $entry->getFilename()) === 1) {
                    $files[] = $entry->getPathname();
                }
            }
        }

        foreach (glob(base_path('app/*.php')) ?: [] as $file) {
            $files[] = $file;
        }

        // ⚠️ FILTERED HERE RATHER THAN IN `scan()`, SO THE EXCLUSION REACHES THE
        // COMMENT HALF TOO. A census asked to leave a file out must leave it out
        // of both corpora, or `namedInComment()` would still answer from it and
        // the `documented` column would disagree with the verdict beside it.
        if ($this->excluded !== []) {
            $files = array_values(array_filter(
                $files,
                fn (string $file): bool => ! in_array($file, $this->excluded, true),
            ));
        }

        sort($files);

        return $files;
    }
}
