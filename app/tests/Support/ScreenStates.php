<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

/**
 * A Blade reader for the three screen states of `29` §9.1 — loading, empty,
 * error — used by tests/Feature/Architecture/ScreenStatesTest.php.
 *
 * A CLASS RATHER THAN FUNCTIONS IN architecture_helpers.php ON PURPOSE. That
 * file is shared by every domain lint and every branch appends to it, which is
 * the interleaving decision 960 split ArchitectureTest to stop. Nothing outside
 * this one lint reads any of this, so it takes a namespace of its own and adds
 * no global function names — 694/808's zero-output fatal is a duplicated global
 * helper, and this is a whole file of would-be helpers.
 *
 * ⚠️ **THIS IS A PARSER, NOT A RENDERER, AND THE DIFFERENCE IS THE POINT.**
 * Everything below is a fact about the *source* of a template. It can see that
 * a loop has an `@empty` branch; it cannot see that the branch is reachable, or
 * that the sentence in it is true, or that the collection is ever empty in
 * practice. Each public method's docblock says what it does not know, and the
 * test's names are written to claim only what the method actually checks
 * (352/397/565 — a test named for a claim it does not make is worse than none).
 */
final class ScreenStates
{
    /**
     * The four tokens the lints below scan for, named once.
     *
     * ⛔ **A LINT HOLDING ITS OWN COPY OF THE PATTERN ITS GUARD READS IS 8460'S
     * SHAPE EVEN WHEN BOTH COPIES ARE CORRECT TODAY** (`CLAUDE.md` §Convention
     * tests). The anti-vacuity floor in `ScreenStatesTest` re-typed
     * `'x-ui.empty-state'` and `'wire:submit'` as string literals of its own
     * and named neither of the other two at all — measured 2026-08-28 (11390)
     * by mistyping the admin-table and error-panel scans one at a time with a
     * real violation planted first: **the whole file stayed green at an
     * unchanged 10 assertions, and so did all 664 tests of
     * `tests/Feature/Architecture/`, at the baseline's own 3,765.** The floor
     * reads these constants and the population accessors under them now, so a
     * typo here takes the floor down with the lint instead of past it.
     */
    public const string TAG_ADMIN_TABLE = 'x-admin.table';

    public const string TAG_EMPTY_STATE = 'x-ui.empty-state';

    public const string TAG_ERROR_PANEL = 'x-ui.error-panel';

    public const string DIRECTIVE_SUBMIT = 'wire:submit';

    /**
     * The accepting arms of the five verdicts below, named where they are
     * computed rather than where they are checked.
     *
     * ⛔ **AN ARM IS A WAY OF SAYING YES, AND A CONTROL IS ONLY AS WIDE AS THE
     * ARMS ITS FRAGMENTS REACH** (2026-08-28, 11670). `ScreenStatesTest`'s
     * positive control asserted that each lint had *a* fragment and never that
     * it had one per arm — and the block's own comment, twelve lines above the
     * assertion, records the measurement that says why that is not enough:
     * with one fragment per lint, forcing `guardCoversEmptiness()` to return
     * true left the file **green at 6 tests, 25 assertions**, because that
     * fragment has no enclosing conditional and the guard arm is never reached
     * on it. **Deleting a fragment returned the file to that exact state and
     * the build stayed green.**
     *
     * ⛔ **THESE ARE NOT LABELS. EACH VERDICT IS COMPUTED FROM THEM**, so an
     * arm cannot be added to a classifier without a constant here, and
     * `ScreenStatesTest`'s coverage guard compares this set — read back by
     * reflection, never re-typed — against the arms its control fragments
     * exercise, in both directions. **A sixth arm on any of the five lints
     * arrives as a failure naming the arm that has no fragment.**
     *
     * ⚠️ **THE RESIDUAL IS STATED RATHER THAN GUARDED, BECAUSE GUARDING IT IS
     * 11392's REFUSED SHAPE.** A new accepting path bolted into an EXISTING
     * arm's condition — one more alternative inside a `preg_match`, one more
     * `||` inside an `if` that already pushes an arm — needs no constant, so
     * this mechanism does not see it. What it does see is a path added as a
     * path. Reading the boolean structure of the classifiers to find the other
     * kind means a matcher over PHP source that is neither necessary nor
     * sufficient, which is 511 before it has ever been right.
     *
     * ⛔ **AND THAT IS WHY AN ARM SET IS CREATABLE RATHER THAN DERIVABLE**
     * (11682, 11683). Driven over this file, where the arms are now ground
     * truth: a plausible shape detector — every `preg_match`, `str_contains`,
     * `in_array`, `str_starts_with` and `str_ends_with` in the classifiers —
     * counts **42** against a real **16**, and is wrong in both directions. It
     * takes the parser's own machinery for arms, and it cannot see
     * `ARM_ADMIN_TABLE_ACTION_DELIBERATELY_EMPTY`, which is spelled
     * `$action === ''`. **So no scan over `tests/Feature/Architecture/` can
     * hand anybody the list of lints that owe this**; each one's arms exist
     * only once its author declares them where they are computed, and the unit
     * of that work is one file.
     */
    public const string ARM_LOOP_ABSENCE_MARKER = 'a Blade comment directly above the loop opting it out';

    public const string ARM_LOOP_EMPTY_STATE_BRANCH = 'an <x-ui.empty-state> in the @empty branch';

    public const string ARM_LOOP_TABLE_CELL_BRANCH = 'a table cell in the @empty branch';

    public const string ARM_LOOP_GUARD_EMPTY_STATE = 'an enclosing guard on this collection holding an <x-ui.empty-state>';

    public const string ARM_LOOP_GUARD_ABSENCE_MARKER = 'an enclosing guard on this collection holding an opt-out marker';

    public const string ARM_ADMIN_TABLE_SENTENCE = 'an empty="…" sentence written for this table';

    public const string ARM_ADMIN_TABLE_ACTION = 'an empty-action attribute of its own';

    public const string ARM_ADMIN_TABLE_ACTION_DELIBERATELY_EMPTY = 'empty-action="", the deliberate nothing-to-do-here';

    public const string ARM_ADMIN_TABLE_ACTION_TARGET = 'an empty-action-target for the action';

    public const string ARM_ADMIN_TABLE_ACTION_HREF = 'an empty-action-href for the action';

    public const string ARM_EMPTY_STATE_ACTION_HREF = 'an href the action links to';

    public const string ARM_EMPTY_STATE_ACTION_TARGET = 'a target the action calls';

    public const string ARM_SUBMIT_LOADING_DIRECTIVE = 'a wire:loading whose wire:target names this handler';

    public const string ARM_SUBMIT_COMPONENT_TARGET = 'an <x-ui.submit target="…"> naming this handler';

    public const string ARM_ERROR_PANEL_REFRESH = "Livewire's own \$refresh";

    public const string ARM_ERROR_PANEL_KNOWN_METHOD = 'a public method the component actually has';

    /**
     * Every Livewire template in the application.
     *
     * `resources/views/livewire` is the whole population: Livewire resolves a
     * component's view by convention and every `render()` in `app/Livewire`
     * returns a `livewire.*` name. A screen built outside this directory would
     * escape the lint, which is why the test also asserts the population is not
     * empty (256 — a lint that matches nothing passes vacuously).
     *
     * @return list<SplFileInfo>
     */
    public static function views(): array
    {
        $directory = base_path('resources/views/livewire');

        return array_values(File::allFiles($directory));
    }

    /**
     * Every arm satisfied across a population, in the order the classifiers
     * pushed them.
     *
     * ⛔ **THE VERDICT AND ITS REASONS COME BACK THROUGH ONE CODE PATH, WHICH
     * IS THE WHOLE POINT** (2026-08-28, 11670). Each of the five population
     * accessors classifies its members and carries the arms it satisfied;
     * each `…Without…` lint is a filter over that same classification. So the
     * arms a control fragment reports are the arms the lint itself reached —
     * not a second reading of the fragment, which would be 8460's twin and
     * would agree with the defect.
     *
     * @param  list<array{arms:list<string>}>  $members
     * @return list<string>
     */
    public static function armsIn(array $members): array
    {
        $arms = [];

        foreach ($members as $member) {
            // ⚠️ **SPREAD RATHER THAN `$arms[] =`, AND THAT SPELLING IS
            // RESERVED.** `ScreenStatesTest` reads this file for every
            // `$arms[] = …` and requires each one to name a `self::ARM_*`
            // constant, because that is where a verdict says yes. This method
            // is a re-collector rather than a verdict — it appends arms that
            // were already named — so it must not look like one.
            $arms = [...$arms, ...$member['arms']];
        }

        return array_values(array_unique($arms));
    }

    /**
     * The template's path relative to the project root, for an assertion
     * message a reader can act on without translating it first.
     */
    public static function name(SplFileInfo $file): string
    {
        return 'resources/views/livewire/'.str_replace('\\', '/', $file->getRelativePathname());
    }

    /**
     * Every record list in `$file` that has no empty state, as
     * `"<path>:<line> (<source>)"` strings.
     *
     * A RECORD LIST IS A LOOP OVER SOMETHING THE COMPONENT SUPPLIED THAT MIGHT
     * COME BACK WITH NOTHING IN IT. Four shapes are deliberately not that, and
     * demanding an invitation from any of them is how a lint gets tuned until
     * it catches nothing (511):
     *
     *  - a loop whose body opens an `<option>` or contains a radio or checkbox
     *    — a form control's choices, which are the same every time and whose
     *    "empty state" is a `<select>` with nothing in it;
     *  - a loop over a literal array or an enum's `cases()` — statically
     *    non-empty, so an empty branch would be dead code;
     *  - a loop over a relation reached through another record
     *    (`$customer->tags`) — a fragment of a row that is already on screen,
     *    not a section of the screen;
     *  - a loop nested inside another loop — the outer list is what is empty or
     *    not, and it carries the state for both;
     *  - a loop over a collection the same template also renders through
     *    `<x-admin.table :rows="…">` — that component owns an `@forelse` and an
     *    invitation of its own, and a second empty state for the same records
     *    would either contradict it or repeat it.
     *
     * A sixth shape is not excluded but opted out of, in writing:
     * {@see self::ABSENCE_MARKER}.
     *
     * ⚠️ **The third exclusion is the one that hides real gaps.** A support
     * agent looking at an account with no locations sees a heading and nothing
     * under it. That is a finding this lint deliberately does not make, because
     * the alternative — a dashed invitation card inside every row of every
     * table — is the noise that gets a lint deleted.
     *
     * @return list<string>
     */
    public static function recordListsWithoutEmptyState(SplFileInfo $file): array
    {
        $missing = [];

        foreach (self::recordLists($file) as $loop) {
            if ($loop['hasEmptyState']) {
                continue;
            }

            $missing[] = self::name($file).':'.$loop['line'].' ('.$loop['source'].')';
        }

        return $missing;
    }

    /**
     * The record lists in `$file` that the method above inspects — every loop
     * left after the five exclusions in its docblock, whether or not it has an
     * empty state.
     *
     * ⛔ **THIS IS THE POPULATION, AND IT IS NOT `x-ui.empty-state`.** The floor
     * counted the *remedy* and called it the subject: measured 2026-08-28 by
     * making `isRecordSource()` return false for everything with a real
     * violation planted, the lint above went green and the floor never moved,
     * because empty states also live in `@if` branches and in guards and their
     * count does not fall when the loops stop being classified. **A floor over
     * the fix cannot see the population go to zero.**
     *
     * @return list<array{line:int,kind:string,source:string,hasEmptyState:bool,arms:list<string>,isRecordList:bool}>
     */
    public static function recordLists(SplFileInfo $file): array
    {
        $tabled = self::tableBoundSources($file);

        return array_values(array_filter(
            self::loops($file),
            static fn (array $loop): bool => $loop['isRecordList'] && ! in_array($loop['source'], $tabled, true),
        ));
    }

    /**
     * Every `<x-admin.table>` in `$file` that renders a bare report rather than
     * an invitation, as `"<path>:<line>"` strings.
     *
     * The table component owns its own `@forelse`, so its call sites are record
     * lists that the loop scan above cannot see. `29` §5.7's "empty states are
     * invitations" makes two attributes load-bearing rather than optional: a
     * sentence written for this table, and an action.
     *
     * ⚠️ **It cannot check the sentence is not "No results found" in other
     * words.** It checks the attribute is present and not the component's
     * default; the tone is a reviewer's job.
     *
     * ⚠️ **A NON-EMPTY ACTION MUST ALSO SAY WHAT IT DOES.** A label with no
     * `empty-action-target` and no `empty-action-href` renders a button wired to
     * nothing — the dead-retry defect this file's last method exists to catch,
     * arriving through the attribute added to fix empty states. `empty-action=""`
     * is exempt because it is the deliberate "there is nothing to do here", and
     * a target is meaningless for it.
     *
     * @return list<string>
     */
    public static function adminTablesWithoutInvitation(SplFileInfo $file): array
    {
        $missing = [];

        foreach (self::adminTables($file) as $tag) {
            if (self::adminTableIsAnInvitation($tag['arms'])) {
                continue;
            }

            $missing[] = self::name($file).':'.$tag['line'];
        }

        return $missing;
    }

    /**
     * Whether the arms an `<x-admin.table>` satisfied add up to an invitation.
     *
     * ⚠️ **THE ONE VERDICT HERE THAT IS A CONJUNCTION, WHICH IS WHY IT NEEDS A
     * METHOD.** The other four say yes on any arm; this one needs a sentence
     * AND an action AND a destination for it, and the destination is itself
     * three ways of being reached. So an arm here is a requirement met rather
     * than a verdict reached, and the control's per-arm pair is written the
     * same way for both shapes: a fragment missing exactly this arm, and the
     * same fragment with exactly this arm added.
     *
     * @param  list<string>  $arms
     */
    private static function adminTableIsAnInvitation(array $arms): bool
    {
        return in_array(self::ARM_ADMIN_TABLE_SENTENCE, $arms, true)
            && in_array(self::ARM_ADMIN_TABLE_ACTION, $arms, true)
            && (
                in_array(self::ARM_ADMIN_TABLE_ACTION_DELIBERATELY_EMPTY, $arms, true)
                || in_array(self::ARM_ADMIN_TABLE_ACTION_TARGET, $arms, true)
                || in_array(self::ARM_ADMIN_TABLE_ACTION_HREF, $arms, true)
            );
    }

    /**
     * Every `<x-admin.table>` in `$file` — the population the method above
     * inspects, unnarrowed, because that method skips nothing.
     *
     * @return list<array{line:int,markup:string,arms:list<string>}>
     */
    public static function adminTables(SplFileInfo $file): array
    {
        return array_map(
            static function (array $tag): array {
                $arms = [];
                $action = preg_match('/(?<![\w:.-]):?empty-action\s*=\s*"([^"]*)"/', $tag['markup'], $found) === 1
                    ? trim($found[1])
                    : null;

                if (preg_match('/(?<![\w:.-])empty\s*=\s*"/', $tag['markup']) === 1) {
                    $arms[] = self::ARM_ADMIN_TABLE_SENTENCE;
                }

                if ($action !== null) {
                    $arms[] = self::ARM_ADMIN_TABLE_ACTION;
                }

                if ($action === '') {
                    $arms[] = self::ARM_ADMIN_TABLE_ACTION_DELIBERATELY_EMPTY;
                }

                if (preg_match('/(?<![\w:.-]):?empty-action-target\s*=\s*"[^"]+"/', $tag['markup']) === 1) {
                    $arms[] = self::ARM_ADMIN_TABLE_ACTION_TARGET;
                }

                if (preg_match('/(?<![\w:.-]):?empty-action-href\s*=\s*"[^"]+"/', $tag['markup']) === 1) {
                    $arms[] = self::ARM_ADMIN_TABLE_ACTION_HREF;
                }

                return [...$tag, 'arms' => $arms];
            },
            self::componentTags($file, self::TAG_ADMIN_TABLE),
        );
    }

    /**
     * Every `<x-ui.empty-state>` in `$file` offering an action that goes
     * nowhere, as `"<path>:<line>"` strings.
     *
     * ⛔ **THE INVITATION IS THE POINT OF THE COMPONENT, SO A LABEL WITH NO
     * DESTINATION IS THE WORST DEFECT IT CAN CARRY** — a button drawn on the
     * screen somebody reached by finding nothing, which does nothing when they
     * press it, and which nothing reveals until they do. It is the dead-retry
     * shape one method below, arriving through the attribute that exists to fix
     * empty states rather than through a rename.
     *
     * An empty state with no `action` at all is not a violation. Some screens
     * genuinely have nothing to offer — a phone number that provisioning is
     * still fetching — and saying so plainly beats a button that means "wait".
     *
     * @return list<string>
     */
    public static function emptyStatesWithDeadAction(SplFileInfo $file): array
    {
        $dead = [];

        foreach (self::emptyStatesOfferingAction($file) as $tag) {
            if ($tag['arms'] !== []) {
                continue;
            }

            $dead[] = self::name($file).':'.$tag['line'];
        }

        return $dead;
    }

    /**
     * Every `<x-ui.empty-state>` in `$file` that offers an action — the
     * population the method above inspects.
     *
     * ⚠️ **NARROWER THAN THE TAG COUNT, AND THAT IS WHY IT IS A METHOD.** An
     * empty state with no action at all is not a violation and is skipped, so a
     * floor over `x-ui.empty-state` spellings would stay comfortably positive
     * on a corpus in which not one of them offered anything.
     *
     * @return list<array{line:int,markup:string,arms:list<string>}>
     */
    public static function emptyStatesOfferingAction(SplFileInfo $file): array
    {
        return array_map(
            static function (array $tag): array {
                $arms = [];

                if (preg_match('/(?<![\w:.-]):?href\s*=\s*"[^"]+"/', $tag['markup']) === 1) {
                    $arms[] = self::ARM_EMPTY_STATE_ACTION_HREF;
                }

                if (preg_match('/(?<![\w:.-]):?target\s*=\s*"[^"]+"/', $tag['markup']) === 1) {
                    $arms[] = self::ARM_EMPTY_STATE_ACTION_TARGET;
                }

                return [...$tag, 'arms' => $arms];
            },
            array_values(array_filter(
                self::componentTags($file, self::TAG_EMPTY_STATE),
                static fn (array $tag): bool => preg_match('/(?<![\w:.-]):?action\s*=\s*"([^"]+)"/', $tag['markup']) === 1,
            )),
        );
    }

    /**
     * Every `wire:submit` handler in `$file` with no loading affordance aimed
     * at it, as `"<path>:<line> (<handler>)"` strings.
     *
     * A FORM SUBMIT IS THE ONE ACTION A STATIC READ CAN BE SURE SOMEBODY WAITS
     * ON. It is a round trip the person triggered deliberately, it is the place
     * a second click sends the thing twice, and `29` §9.1's loading state is
     * what tells them the first one landed. So the requirement is exact: a
     * `wire:loading` somewhere in the same template whose `wire:target` names
     * this handler.
     *
     * ⛔ **`wire:click` IS NOT COVERED AND THAT IS A REAL HOLE, NOT AN
     * OVERSIGHT.** Whether a click needs a spinner is a fact about how long its
     * handler takes, which no parser can read. Demanding one on every sort
     * arrow and tab would be the lint nobody keeps. The gap is recorded at
     * decision 3006 rather than papered over with a weaker assertion.
     *
     * ⚠️ **"Same template" is not "same form", and an untargeted
     * `wire:loading` does not count.** An untargeted affordance fires on every
     * round trip on the screen, so accepting one would let a spinner on an
     * unrelated filter satisfy a form three sections away.
     *
     * @return list<string>
     */
    public static function submitsWithoutLoadingState(SplFileInfo $file): array
    {
        $missing = [];

        foreach (self::submitHandlers($file) as $submit) {
            if ($submit['arms'] !== []) {
                continue;
            }

            $missing[] = self::name($file).':'.$submit['line'].' ('.$submit['handler'].')';
        }

        return $missing;
    }

    /**
     * Every `wire:submit` handler in `$file` — the population the method above
     * inspects, whether or not anything is aimed at it.
     *
     * The pattern is built from {@see self::DIRECTIVE_SUBMIT} rather than
     * spelling the directive again, for the reason on that constant.
     *
     * @return list<array{line:int,handler:string,arms:list<string>}>
     */
    public static function submitHandlers(SplFileInfo $file): array
    {
        $blade = self::withoutComments($file);
        $componentTargets = self::submitComponentTargets($blade);
        $directiveTargets = self::loadingDirectiveTargets($blade);
        $handlers = [];

        preg_match_all(
            '/'.preg_quote(self::DIRECTIVE_SUBMIT, '/').'(?:\.[\w.]+)?\s*=\s*"([^"(]+)/',
            $blade,
            $matches,
            PREG_OFFSET_CAPTURE,
        );

        foreach ($matches[1] as $match) {
            $handler = trim($match[0]);
            $arms = [];

            if (in_array($handler, $directiveTargets, true)) {
                $arms[] = self::ARM_SUBMIT_LOADING_DIRECTIVE;
            }

            if (in_array($handler, $componentTargets, true)) {
                $arms[] = self::ARM_SUBMIT_COMPONENT_TARGET;
            }

            $handlers[] = [
                'line' => self::lineAt($blade, $match[1]),
                'handler' => $handler,
                'arms' => $arms,
            ];
        }

        return $handlers;
    }

    /**
     * Every `<x-ui.error-panel>` in `$file` whose retry cannot work, as
     * `"<path>:<line> (<retry>)"` strings.
     *
     * ⛔ **THIS IS THE HONEST HALF OF THE ERROR STATE AND THE NAME OF THE TEST
     * SAYS SO.** "Every screen that can fail shows what happened" is not
     * checkable by reading a template: whether a read can throw is a fact about
     * the component, the service under it and the vendor under that, and a lint
     * asserting it would be a claim it cannot make. Demanding a panel on every
     * screen instead would put an unreachable branch on thirty templates and
     * teach everyone that the state is decoration.
     *
     * What *is* checkable is that the panels which exist are wired to something
     * real. A retry button naming a method that was renamed is a dead button on
     * the one screen where the person is already having a bad time, and it is
     * invisible until it is pressed. `$refresh` is Livewire's own and always
     * resolves.
     *
     * @param  array<string, list<string>>  $methods  public method names, keyed by view name
     * @return list<string>
     */
    public static function errorPanelsWithDeadRetry(SplFileInfo $file, array $methods): array
    {
        // The view-not-in-$methods narrowing lives on the population accessor
        // and is deliberately not repeated here: two copies of one skip is
        // 8460's shape, and this one would be the copy that goes stale.
        $dead = [];

        foreach (self::errorPanelsOfferingRetry($file, $methods) as $tag) {
            if ($tag['arms'] !== []) {
                continue;
            }

            preg_match('/(?<![\w:.-])retry\s*=\s*"([^"]*)"/', $tag['markup'], $attribute);

            $dead[] = self::name($file).':'.$tag['line'].' ('.trim($attribute[1]).')';
        }

        return $dead;
    }

    /**
     * Every `<x-ui.error-panel>` in `$file` naming an explicit retry — the
     * population the method above inspects.
     *
     * ⛔ **TWICE NARROWER THAN THE TAG COUNT, AND BOTH NARROWINGS SUBTRACT IN
     * SILENCE.** A panel with no `retry` attribute takes Livewire's `$refresh`
     * and is skipped; a template whose view name is absent from `$methods` is
     * skipped whole, which is the failure direction the `livewireViewMethods()`
     * docblock in `ScreenStatesTest` describes — *"a blind spot here does not
     * report; it subtracts"*. So the honest floor for that lint is this
     * number and not the number of panels on the screens.
     *
     * ⚠️ **At the time of writing it is 1** — the deliberate fixture at
     * `resources/views/livewire/admin/probe-table.blade.php`, which exists
     * because every real panel in the application takes the default and a lint
     * that only ever sees `$refresh` cannot tell a working retry from a renamed
     * one. **If that fixture is ever deleted the lint stops checking anything**,
     * and the floor is what says so.
     *
     * @param  array<string, list<string>>  $methods  public method names, keyed by view name
     * @return list<array{line:int,markup:string,arms:list<string>}>
     */
    public static function errorPanelsOfferingRetry(SplFileInfo $file, array $methods): array
    {
        $known = $methods[self::viewName($file)] ?? null;

        if ($known === null) {
            return [];
        }

        return array_map(
            static function (array $tag) use ($known): array {
                preg_match('/(?<![\w:.-])retry\s*=\s*"([^"]*)"/', $tag['markup'], $attribute);

                $retry = trim($attribute[1]);
                $method = trim(preg_replace('/\(.*$/s', '', $retry) ?? $retry);
                $arms = [];

                if ($retry === '$refresh') {
                    $arms[] = self::ARM_ERROR_PANEL_REFRESH;
                }

                if (in_array($method, $known, true)) {
                    $arms[] = self::ARM_ERROR_PANEL_KNOWN_METHOD;
                }

                return [...$tag, 'arms' => $arms];
            },
            array_values(array_filter(
                self::componentTags($file, self::TAG_ERROR_PANEL),
                static fn (array $tag): bool => preg_match('/(?<![\w:.-])retry\s*=\s*"([^"]*)"/', $tag['markup']) === 1,
            )),
        );
    }

    /**
     * The handlers named by `<x-ui.submit target="…">` in `$blade`.
     *
     * @return list<string>
     */
    private static function submitComponentTargets(string $blade): array
    {
        preg_match_all('/<x-ui\.submit\b[^>]*?(?<![\w:.-])target\s*=\s*"([^"]*)"/s', $blade, $matches);

        return array_values(array_filter(array_map(
            static fn (string $target): string => trim($target),
            $matches[1],
        )));
    }

    /**
     * The collections this template hands to `<x-admin.table>`.
     *
     * A screen that renders records as a table and *also* loops them for a
     * secondary affordance — a row of detail buttons under the table — has one
     * empty state, inside the component, and it is the right one. Naming the
     * second loop would push a reader toward writing a contradicting invitation
     * beside a table that already said what to do.
     *
     * @return list<string>
     */
    private static function tableBoundSources(SplFileInfo $file): array
    {
        $sources = [];

        foreach (self::componentTags($file, self::TAG_ADMIN_TABLE) as $tag) {
            if (preg_match('/(?<![\w:.-]):rows\s*=\s*"([^"]*)"/', $tag['markup'], $found)) {
                $sources[] = trim($found[1]);
            }
        }

        return array_values(array_unique($sources));
    }

    /**
     * The Livewire view name a template answers to, e.g. `account.customers`.
     */
    public static function viewName(SplFileInfo $file): string
    {
        $path = str_replace(['\\', '/'], '.', $file->getRelativePathname());

        return (string) preg_replace('/\.blade\.php$/', '', $path);
    }

    /**
     * Blade with its comments replaced by blank space of the same length, so
     * every offset and line number below still points at the real file.
     *
     * Comments are stripped because this lint's own explanatory comments name
     * the very directives it scans for, and a docblock that says "@forelse"
     * must not read as one.
     *
     * ⛔ **THE PATTERN IS NOT HERE ANY MORE AND MUST NOT COME BACK** (9338).
     * There were eight independent copies of it across the suite and this one
     * was the only offset-preserving spelling — so it was the only one the
     * others could not have replaced, which is exactly why it wanted a shared
     * home rather than a private one. `bladeCommentsBlanked()` in
     * `tests/Support/architecture_helpers.php` is that home;
     * `tests/Feature/Architecture/BladeScanningTest.php` fails the build on a
     * ninth copy.
     *
     * ⚠️ **A PLAIN FUNCTION FROM A `require_once` IN `tests/Pest.php`**, which
     * is loaded before any test runs and is the only context this class has.
     * Nothing outside the suite may call this class without that require.
     */
    private static function withoutComments(SplFileInfo $file): string
    {
        return bladeCommentsBlanked($file->getContents());
    }

    /**
     * Every `@foreach`/`@forelse` in the template, classified.
     *
     * ⚠️ **The guard is resolved in a second pass, and it has to be.** A loop
     * closes before the conditional wrapping it does, so at the moment the loop
     * is finished the sibling branch holding its invitation has not been read
     * yet. Deciding there would have called every guarded section a violation.
     *
     * @return list<array{line:int,kind:string,source:string,hasEmptyState:bool,arms:list<string>,isRecordList:bool}>
     */
    private static function loops(SplFileInfo $file): array
    {
        $blade = self::withoutComments($file);
        $raw = $file->getContents();
        $directives = self::directives($blade);

        /** @var list<array<string, mixed>> $open */
        $open = [];
        /** @var list<array<string, mixed>> $conditionals */
        $conditionals = [];
        /** @var list<array<string, mixed>> $loops */
        $loops = [];
        $loopDepth = 0;

        foreach ($directives as $directive) {
            $name = $directive['name'];

            if ($name === 'foreach' || $name === 'forelse') {
                $open[] = [
                    'type' => 'loop',
                    'kind' => $name,
                    'arg' => $directive['arg'],
                    'line' => $directive['line'],
                    'bodyStart' => $directive['end'],
                    'emptyStart' => null,
                    'nested' => $loopDepth > 0,
                    'guards' => self::enclosingConditionals($open),
                    'optedOut' => self::precededByAbsenceMarker($raw, $directive['start']),
                ];
                $loopDepth++;

                continue;
            }

            if ($name === 'endforeach' || $name === 'endforelse') {
                $frame = self::popFrame($open, 'loop');

                if ($frame === null) {
                    continue;
                }

                $loopDepth--;
                $loops[] = self::finishLoop($blade, $frame, $directive['start']);

                continue;
            }

            // `@empty` with no argument is a forelse branch; `@empty($thing)` is
            // the standalone conditional and opens a block of its own.
            if ($name === 'empty' && $directive['arg'] === null) {
                $index = count($open) - 1;

                if ($index >= 0 && $open[$index]['type'] === 'loop' && $open[$index]['kind'] === 'forelse') {
                    $open[$index]['emptyStart'] = $directive['end'];
                }

                continue;
            }

            if (in_array($name, ['if', 'unless', 'isset', 'empty'], true)) {
                $open[] = [
                    'type' => 'conditional',
                    'index' => count($conditionals),
                    'conditions' => [$directive['arg'] ?? ''],
                    'start' => $directive['start'],
                ];
                $conditionals[] = ['conditions' => [], 'text' => '', 'source' => ''];

                continue;
            }

            if ($name === 'elseif') {
                $index = count($open) - 1;

                if ($index >= 0 && $open[$index]['type'] === 'conditional') {
                    $open[$index]['conditions'][] = $directive['arg'] ?? '';
                }

                continue;
            }

            if (in_array($name, ['endif', 'endunless', 'endisset', 'endempty'], true)) {
                $frame = self::popFrame($open, 'conditional');

                if ($frame === null) {
                    continue;
                }

                /** @var int $index */
                $index = $frame['index'];
                /** @var list<string> $conditions */
                $conditions = $frame['conditions'];
                /** @var int $start */
                $start = $frame['start'];

                $conditionals[$index] = [
                    'conditions' => $conditions,
                    'text' => substr($blade, $start, $directive['end'] - $start),
                    // The same span before the comments were blanked, and only
                    // the marker is read from it — see ABSENCE_MARKER.
                    'source' => substr($raw, $start, $directive['end'] - $start),
                ];
            }
        }

        return array_map(
            static fn (array $loop): array => self::resolveEmptyState($loop, $conditionals),
            $loops,
        );
    }

    /**
     * @param  array<string, mixed>  $loop
     * @param  list<array<string, mixed>>  $conditionals
     * @return array{line:int,kind:string,source:string,hasEmptyState:bool,arms:list<string>,isRecordList:bool}
     */
    private static function resolveEmptyState(array $loop, array $conditionals): array
    {
        /** @var list<int> $guardIndexes */
        $guardIndexes = $loop['guardIndexes'];
        /** @var string $source */
        $source = $loop['source'];
        /** @var string|null $emptyBranch */
        $emptyBranch = $loop['emptyBranch'];

        // The marker written directly above the loop, for a list that has no
        // guard to put one in because it cannot come back empty — three fixed
        // rate readings, a manifest's own rows, an enum's documents. An
        // `@empty` branch on one of those is dead code, and dead code that
        // satisfies a lint is worse than the gap it closes.
        // ⚠️ A TABLE'S EMPTY ROW COUNTS WITHOUT THE CARD, AND THE COMPONENT IS
        // THE WRONG SHAPE THERE RATHER THAN MERELY UNUSUAL. `x-ui.empty-state`
        // draws a dashed card; a dashed card inside a bordered `<td>` is a box
        // in a box, which is why `x-admin.table`'s own empty branch is a
        // sentence and a button rather than the component. So a hand-rolled
        // table whose `@empty` renders a cell has said what it shows when there
        // are none, in the only way that markup can.
        $arms = [];

        if ((bool) $loop['optedOut']) {
            $arms[] = self::ARM_LOOP_ABSENCE_MARKER;
        }

        if ($emptyBranch !== null && str_contains($emptyBranch, self::TAG_EMPTY_STATE)) {
            $arms[] = self::ARM_LOOP_EMPTY_STATE_BRANCH;
        }

        if ($emptyBranch !== null && preg_match('/<td[\s>]/', $emptyBranch) === 1) {
            $arms[] = self::ARM_LOOP_TABLE_CELL_BRANCH;
        }

        // ⚠️ EVERY ENCLOSING CONDITIONAL, NOT ONLY THE INNERMOST. A screen with
        // three sections that each vanish when their own list is empty, and one
        // invitation covering all three, is the honest shape for that screen —
        // three empty states stacked on a page where nothing has happened yet
        // would be three ways of saying the same thing. So the invitation may
        // live in any conditional this loop sits inside, as long as that
        // conditional names this collection's emptiness.
        // ⛔ **NO EARLY BREAK, AND THAT IS THE ARM MECHANISM RATHER THAN A
        // TIDY-UP.** This loop used to stop the moment `$satisfied` went true,
        // which is correct for a boolean and wrong for a census of arms: a
        // fragment already covered by its `@empty` branch would then report
        // nothing about the guard it also sits in, and the control could not
        // tell an unreached arm from an absent one. The verdict is unchanged —
        // it is `$arms !== []` — and every arm is now recorded.
        foreach ($guardIndexes as $guardIndex) {
            if (! isset($conditionals[$guardIndex])) {
                continue;
            }

            /** @var array{conditions:list<string>,text:string,source:string} $guard */
            $guard = $conditionals[$guardIndex];

            if (! self::guardNamesEmptiness($guard, $source)) {
                continue;
            }

            if (str_contains($guard['text'], self::TAG_EMPTY_STATE)) {
                $arms[] = self::ARM_LOOP_GUARD_EMPTY_STATE;
            }

            if (preg_match(self::ABSENCE_MARKER, $guard['source']) === 1) {
                $arms[] = self::ARM_LOOP_GUARD_ABSENCE_MARKER;
            }
        }

        return [
            'line' => (int) $loop['line'],
            'kind' => (string) $loop['kind'],
            'source' => $source,
            'hasEmptyState' => $arms !== [],
            'arms' => array_values(array_unique($arms)),
            'isRecordList' => (bool) $loop['isRecordList'],
        ];
    }

    /**
     * @param  array<string, mixed>  $frame
     * @return array<string, mixed>
     */
    private static function finishLoop(string $blade, array $frame, int $end): array
    {
        /** @var int $bodyStart */
        $bodyStart = $frame['bodyStart'];
        /** @var int|null $emptyStart */
        $emptyStart = $frame['emptyStart'];
        /** @var string $kind */
        $kind = $frame['kind'];
        /** @var int $line */
        $line = $frame['line'];
        /** @var bool $nested */
        $nested = $frame['nested'];

        $bodyEnd = $emptyStart ?? $end;
        $body = substr($blade, $bodyStart, max(0, $bodyEnd - $bodyStart));
        $emptyBranch = $emptyStart === null ? null : substr($blade, $emptyStart, max(0, $end - $emptyStart));
        $source = self::loopSource((string) ($frame['arg'] ?? ''));

        return [
            'line' => $line,
            'kind' => $kind,
            'source' => $source,
            'emptyBranch' => $emptyBranch,
            'isRecordList' => ! $nested && self::isRecordSource($source) && ! self::isChoiceLoop($body),
            'guardIndexes' => $frame['guards'],
            'optedOut' => $frame['optedOut'],
        ];
    }

    /**
     * Whether the Blade comment immediately above `$start` opts this loop out.
     *
     * "Immediately" is literal: the comment must close with nothing but
     * whitespace between it and the directive. A marker three paragraphs up in
     * a file header would opt out a loop nobody was thinking about when they
     * wrote it, which is how an escape hatch stops being one.
     */
    private static function precededByAbsenceMarker(string $raw, int $start): bool
    {
        $before = rtrim(substr($raw, 0, $start));

        if (! str_ends_with($before, '--}}')) {
            return false;
        }

        $opens = strrpos($before, '{{--');

        if ($opens === false) {
            return false;
        }

        return preg_match(self::ABSENCE_MARKER, substr($before, $opens)) === 1;
    }

    /**
     * The collection half of a loop argument: `$duplicates as $duplicate`
     * yields `$duplicates`.
     */
    private static function loopSource(string $argument): string
    {
        $source = preg_split('/\s+as\s+/', trim($argument), 2)[0] ?? '';

        return trim($source);
    }

    /**
     * Whether a loop's source could come back empty at runtime.
     */
    private static function isRecordSource(string $source): bool
    {
        if ($source === '' || str_starts_with($source, '[') || str_starts_with($source, 'array(')) {
            return false;
        }

        if (str_contains($source, '::cases()')) {
            return false;
        }

        // A relation or property reached through another record. `$this->` is
        // the component itself and is a record source like any other.
        return ! preg_match('/^\$(?!this\b)\w+\s*(->|\[)/', $source);
    }

    /**
     * Whether the loop renders a form control's choices rather than records.
     */
    private static function isChoiceLoop(string $body): bool
    {
        if (preg_match('/\A(?:\s*@php\b[^\n]*\n)*\s*<option\b/', $body)) {
            return true;
        }

        return (bool) preg_match('/type\s*=\s*"(radio|checkbox)"/', $body);
    }

    /**
     * The one way to say a section is deliberately absent when its list is
     * empty, and why.
     *
     * ⚠️ **AN OPT-OUT THAT COSTS A SENTENCE, WHICH IS THE POINT.** Some
     * sections are right to vanish: a "This might be the same person" panel that
     * announced "no duplicates" would be noise on every well-kept customer, and
     * a "Recently merged" panel with nothing in it invites somebody to undo
     * something that did not happen. The dodge is available to any screen — but
     * only in writing, in the template, where a reviewer reads it and a `grep`
     * finds every one of them. A bare marker with no reason does not match.
     *
     * ⛔ **IT IS READ OUT OF THE RAW SOURCE, AND FOR EIGHTEEN HOURS IT WAS NOT**
     * (3008). Every other read in this class goes through `withoutComments()`,
     * because a docblock naming `@forelse` must not read as one — and the marker
     * is written *in* a Blade comment, so the one thing it is spelled in was the
     * one thing blanked before the regex ran. It matched nothing, anywhere,
     * ever: 256's vacuous lint and 272's writerless control at once, in the
     * escape hatch rather than in the rule. The two markers already written in
     * `account/customer-profile.blade.php` were still being reported as
     * violations, which is the only reason it was found.
     */
    private const string ABSENCE_MARKER = '/empty-state:\s*absent because\s+\S/i';

    /**
     * Whether a section already wrapped in a conditional says what it shows
     * when its collection is empty.
     *
     * TWO SPELLINGS ARE ACCEPTED BECAUSE BOTH ARE HONEST. `@forelse` with an
     * `@empty` branch is the plain one. The other is a section wrapped in a
     * conditional on the same collection — the shape a screen takes when an
     * error state has to be tested first — and there the invitation lives in
     * the sibling branch, so the whole conditional is what is read.
     *
     * ⚠️ **Adding an `@empty` branch without removing such a guard is decision
     * 398's trap in a template**: an outer `@if ($rows->isNotEmpty())` makes
     * the inner `@empty` unreachable, and both the lint and the eye read it as
     * covered. Which is why an accepted guard must itself name the collection's
     * emptiness — a conditional on something else does not count.
     *
     * ⚠️ **THE TWO READS TAKE TWO DIFFERENT SPANS OF THE SAME SECTION, AND THAT
     * IS NOT AN INCONSISTENCY.** The invitation is markup, so it is looked for
     * in the comment-blanked text, where a docblock mentioning the component
     * cannot stand in for rendering it. The marker IS a comment, so it is looked
     * for in the raw source — see `ABSENCE_MARKER`, and the eighteen hours it
     * spent matching nothing because both reads used the blanked span.
     *
     * ⛔ **IT ANSWERS ABOUT THE CONDITION ONLY, AND THE TWO CONTENTS ARE TWO
     * ARMS READ AT THE CALL SITE** (2026-08-28, 11670). This method used to
     * fuse *"the guard names this collection's emptiness"* with *"the guard
     * holds an invitation or an opt-out"* into one boolean, so a control
     * fragment reaching it proved nothing about which of the two contents it
     * had reached. `resolveEmptyState()` pushes `ARM_LOOP_GUARD_EMPTY_STATE`
     * and `ARM_LOOP_GUARD_ABSENCE_MARKER` separately now. **The verdict is
     * unchanged**: the same conjunction, spelled where the arms are named.
     *
     * @param  array{conditions:list<string>,text:string,source:string}  $guard
     */
    private static function guardNamesEmptiness(array $guard, string $source): bool
    {
        $variable = preg_replace('/^(\$\w+).*$/s', '$1', $source) ?? $source;

        foreach ($guard['conditions'] as $condition) {
            if (! str_contains($condition, $variable)) {
                continue;
            }

            // `$codes === []` is an emptiness test as much as `->isEmpty()` is,
            // and it is the only one available for an array the component built
            // rather than a collection it queried.
            if (preg_match('/isEmpty|isNotEmpty|->count\(|count\s*\(|filled\s*\(|blank\s*\(|[!=]==\s*\[\s*\]|\[\s*\]\s*[!=]==/', $condition)) {
                return true;
            }
        }

        return false;
    }

    /**
     * The `wire:target` method names covered by a `wire:loading` in `$blade`.
     *
     * ⛔ **THIS WAS ONE METHOD RETURNING BOTH SOURCES AND IT IS TWO NOW**
     * (2026-08-28, 11670). `<x-ui.submit target="…">` and a targeted
     * `wire:loading` are two arms of one verdict, and a single list of names
     * cannot say which of them covered a handler — so a control fragment
     * carrying only one of the two proved the whole method reachable and said
     * nothing about the other. The verdict names the arm now, and
     * `ScreenStatesTest`'s control carries a pair for each.
     *
     * ⚠️ **`<x-ui.submit target="…">` COUNTS, AND IT IS THE ONLY COMPONENT THAT
     * DOES.** That component exists to carry this exact affordance — the
     * disabled attribute and the label swap, aimed at one handler — so reading
     * its call sites is reading the requirement, not relaxing it. Accepting any
     * component with a `target` attribute would be the tuning that stops a lint
     * catching anything (511); this is one name, checked literally.
     *
     * @return list<string>
     */
    private static function loadingDirectiveTargets(string $blade): array
    {
        if (! str_contains($blade, 'wire:loading')) {
            return [];
        }

        $targets = [];

        preg_match_all('/wire:target\s*=\s*"([^"]*)"/', $blade, $matches);

        foreach ($matches[1] as $list) {
            foreach (explode(',', $list) as $target) {
                $target = trim(preg_replace('/\(.*$/s', '', trim($target)) ?? '');

                if ($target !== '') {
                    $targets[] = $target;
                }
            }
        }

        return array_values(array_unique($targets));
    }

    /**
     * Every `<x-…>` tag of one name in the template, with its opening markup.
     *
     * Reads to the end of the opening tag rather than to the closing tag: every
     * attribute this lint reads lives there, and a self-closing `/>` and a
     * paired `</x-…>` then need no separate handling.
     *
     * @return list<array{line:int,markup:string}>
     */
    private static function componentTags(SplFileInfo $file, string $tag): array
    {
        $blade = self::withoutComments($file);
        $found = [];
        $offset = 0;

        while (($start = strpos($blade, '<'.$tag, $offset)) !== false) {
            $offset = $start + 1;

            // Not this tag if the name continues — `<x-ui.empty-state-thing`.
            $next = $blade[$start + strlen($tag) + 1] ?? '';

            if ($next !== '' && ! preg_match('/[\s\/>]/', $next)) {
                continue;
            }

            $end = self::endOfOpeningTag($blade, $start);

            $found[] = [
                'line' => self::lineAt($blade, $start),
                'markup' => substr($blade, $start, $end - $start),
            ];
        }

        return $found;
    }

    /**
     * The offset just past the `>` that closes the opening tag at `$start`,
     * skipping any `>` inside a quoted attribute value.
     */
    private static function endOfOpeningTag(string $blade, int $start): int
    {
        $length = strlen($blade);
        $quote = null;

        for ($index = $start; $index < $length; $index++) {
            $character = $blade[$index];

            if ($quote !== null) {
                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;

                continue;
            }

            if ($character === '>') {
                return $index + 1;
            }
        }

        return $length;
    }

    /**
     * Every Blade directive in the template, with the offsets this class needs.
     *
     * Hand-rolled rather than regex-per-directive because the argument is a PHP
     * expression that legitimately contains parentheses — `@if (count($a) > 0)`
     * — so the closing paren has to be matched, not guessed at.
     *
     * @return list<array{name:string,arg:?string,start:int,end:int,line:int}>
     */
    private static function directives(string $blade): array
    {
        $directives = [];
        $length = strlen($blade);

        for ($index = 0; $index < $length; $index++) {
            if ($blade[$index] !== '@') {
                continue;
            }

            // `@@if` is an escaped literal, not a directive.
            if (($blade[$index + 1] ?? '') === '@') {
                $index++;

                continue;
            }

            if (! preg_match('/\G@([a-z]+)/', $blade, $match, 0, $index)) {
                continue;
            }

            $name = $match[1];
            $end = $index + strlen($match[0]);
            $argument = null;

            $after = $end;

            while (($blade[$after] ?? '') === ' ' || ($blade[$after] ?? '') === "\t") {
                $after++;
            }

            if (($blade[$after] ?? '') === '(') {
                $close = self::matchingParen($blade, $after);

                if ($close !== null) {
                    $argument = substr($blade, $after + 1, $close - $after - 1);
                    $end = $close + 1;
                }
            }

            $directives[] = [
                'name' => $name,
                'arg' => $argument,
                'start' => $index,
                'end' => $end,
                'line' => self::lineAt($blade, $index),
            ];

            $index = $end - 1;
        }

        return $directives;
    }

    /**
     * The offset of the `)` matching the `(` at `$open`, or null if unbalanced.
     */
    private static function matchingParen(string $blade, int $open): ?int
    {
        $depth = 0;
        $length = strlen($blade);
        $quote = null;

        for ($index = $open; $index < $length; $index++) {
            $character = $blade[$index];

            if ($quote !== null) {
                if ($character === '\\') {
                    $index++;
                } elseif ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === '"' || $character === "'") {
                $quote = $character;
            } elseif ($character === '(') {
                $depth++;
            } elseif ($character === ')') {
                $depth--;

                if ($depth === 0) {
                    return $index;
                }
            }
        }

        return null;
    }

    /**
     * Every conditional this loop sits inside, innermost first, stopping at an
     * enclosing loop.
     *
     * @param  list<array<string, mixed>>  $open
     * @return list<int>
     */
    private static function enclosingConditionals(array $open): array
    {
        $found = [];

        for ($index = count($open) - 1; $index >= 0; $index--) {
            if ($open[$index]['type'] === 'loop') {
                break;
            }

            if ($open[$index]['type'] === 'conditional') {
                /** @var int $conditionalIndex */
                $conditionalIndex = $open[$index]['index'];
                $found[] = $conditionalIndex;
            }
        }

        return $found;
    }

    /**
     * @param  list<array<string, mixed>>  $open
     * @return array<string, mixed>|null
     */
    private static function popFrame(array &$open, string $type): ?array
    {
        for ($index = count($open) - 1; $index >= 0; $index--) {
            if ($open[$index]['type'] !== $type) {
                continue;
            }

            $frame = $open[$index];
            array_splice($open, $index);

            return $frame;
        }

        return null;
    }

    private static function lineAt(string $blade, int $offset): int
    {
        return substr_count($blade, "\n", 0, $offset) + 1;
    }
}
