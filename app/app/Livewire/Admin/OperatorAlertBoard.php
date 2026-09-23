<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Console\Commands\ReconcileZernioAccounts;
use App\Console\Commands\WatchPixelCanary;
use App\Console\Commands\WatchPlatformComplaintRate;
use App\Enums\OperatorAlertKind;
use App\Enums\SignalState;
use App\Jobs\ArchivePixelBatchJob;
use App\Jobs\AutopilotJob;
use App\Jobs\RecordQueueHeartbeat;
use App\Models\OperatorAlert;
use App\Services\Config\DefaultsRegistry;
use App\Services\Ops\OperatorAlerts;
use App\Services\Ops\PlatformHealthChecks;
use App\Services\Ops\ScheduledRunMeter;
use App\Services\Pixel\MonthlyEventCap;
use App\Services\Voice\VoiceSpend;
use App\Support\Admin\AdminAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * What this platform has rung its own bell about — the reader `operator_alerts`
 * has never had.
 *
 * ## The defect this closes
 *
 * ⛔ **EVERY `OperatorAlertKind` FIRED INTO A TABLE NO SCREEN RENDERED.** Every
 * `OperatorAlerts::raise()` call site in `app/` has been writing rows since
 * 2026-08-15 and the only way to read one was a database client — ⚠️ **and the
 * number of those call sites is deliberately not written here**, because the
 * obvious grep counts one that is not one: `Livewire\Account\Support::raise()`
 * calls `SupportDesk::raise()` and opens a **customer support ticket**, which
 * has nothing to do with this table. `ObservabilityTest`'s
 * *"every operator alert kind is raised somewhere"* is the enumeration that
 * cannot be wrong. That is `CLAUDE.md`'s first recurring
 * failure shape from the reader's end — `sending_health_windows` had a threshold
 * and no reader, this had a writer and no reader — and it is the more expensive
 * end, because everything about it *works*: the row lands, the email queues, the
 * text sends, every test is green, and the one question the table's own creating
 * migration says it exists to answer — *"what fired last night, and what did it
 * say"* — could not be asked from this application at all.
 *
 * ⚠️ **THREE FINDINGS WERE WAITING ON A SCREEN THAT DID NOT EXIST** — 4888(a),
 * 6768 and 6917 each end with *"what this wants is an entry on the operator's
 * board"*. This is the board. It does not close any of the three: two of them
 * want an `OperatorAlertKind` case that 7023's lint will not let anybody mint
 * ahead of its raiser, and that raiser is not in this slice.
 *
 * ⛔ **THAT LAST SENTENCE IS TRUE OF THE SLICE THAT WROTE IT AND ONE OF THE TWO
 * HAS SINCE BEEN MINTED — CORRECTED 2026-08-21 (7200–7219).** **6768 is
 * closed**: `OperatorAlertKind::GbpBindingMismatched` exists, its raiser is
 * `GbpConnections::ringMismatch()`, and it appears here without this screen
 * being told about it — which is 7154's claim, tested rather than asserted.
 * ⚠️ **6917's case is still unminted** and 4888(a) was already superseded before
 * that slice ran, so *"none of the three"* is now *"one of the three"*.
 *
 * ## Read-only, and the "acknowledge" button is refused in writing
 *
 * ⛔ **NOTHING ON THIS SCREEN CHANGES ANYTHING** (R25 — a bell, never a brake).
 * Rendering alerts creates immediate pressure for an *acknowledge* or a
 * *silence* control, and three separate things say no:
 *
 * 1. ⛔ **The row IS the de-duplication.** {@see OperatorAlerts} refuses to fire
 *    again while a row of the same kind and subject is inside the quiet window,
 *    so a *dismiss* here would **re-arm** the alert rather than quieten it —
 *    {@see OperatorAlert}'s own docblock says exactly that — and an *acknowledge*
 *    that did quieten would be a second hand on the dedupe.
 * 2. ⚠️ **Silence already has a home and it is one field.**
 *    `ops.alert_quiet_minutes` is on the Ops settings screen. A second control
 *    here would be a second source of truth about how long a bell stays quiet.
 * 3. ⚠️ **It is a state machine and a support surface** — `CLAUDE.md`'s first
 *    tiebreaker is *less support surface*, and *"may an operator mark an alert
 *    as handled"* is the owner's question rather than this slice's. It is raised
 *    rather than guessed at.
 *
 * ## What is on the screen, and why the third section is not decoration
 *
 * **Ringing now** — the last {@see self::RINGING_HOURS} hours, grouped by the
 * dedupe key, so *"the same thing eleven times"* and *"eleven different things"*
 * are visibly different mornings. Reading that grouping is not re-implementing
 * the dedupe: the dedupe is a **decision** about whether to fire, and it stays
 * exactly where it is. ⚠️ **Ordered by what needs action and then by recency**
 * since 7200–7219 — see {@see self::ringing()} for why recency alone was the
 * wrong answer to *"which of these do I open first"*.
 * ⛔ **THE GROUPING IS POSTGRES' SINCE 2026-08-22 AND THIS SECTION IS BOUNDED**
 * (7960–7979, closing 7756). It used to load every row in the window into PHP
 * on the argument that the dedupe made the set small, which is false at the
 * quiet window's legal floor — see {@see self::ringing()} for the arithmetic.
 * ⚠️ **Every count is still taken over the whole window**, and what the bound
 * leaves out is reported as a quantity rather than dropped: {@see self::spread()}.
 *
 * **Everything, newest first** — the log, with the figures behind each row.
 *
 * **Every bell this platform can ring** — ⛔ **THE SECTION THAT MAKES AN EMPTY
 * SCREEN MEAN SOMETHING.** Production holds zero rows, so on the day this ships
 * an operator sees nothing at all, and *"nothing rang"* and *"nothing is being
 * checked"* look identical. Several kinds hang off a registry threshold, and
 * both alert channels ship blank — `ops.alert_email` and `ops.alert_sms` seed
 * `''`, which is the off switch. So a silent board on a fresh install is the
 * expected state and says nothing about the platform's health; this section is
 * what lets somebody tell the two apart.
 *
 * ⛔ **THAT SENTENCE READ "FOUR OF THE KINDS HANG OFF A REGISTRY THRESHOLD WHERE
 * `0` DISABLES THE CHECK OUTRIGHT" AND BOTH HALVES WERE WRONG — CORRECTED
 * 2026-08-22 (7740–7759).** There were five in the map when it said four, and
 * three more kinds had a registry threshold this screen said nothing about —
 * and **`0` disables the check outright on none of those three.** The count is
 * gone rather than corrected, on this file's own rule two paragraphs down; what
 * a zero does is now stated per row in {@see self::bells()}, because it is not
 * one fact.
 *
 * ⚠️ **THE LIST OF KINDS COUNTS ITSELF.** It is `OperatorAlertKind::cases()` and
 * never a number written down here — two people counted that enum in one day and
 * disagreed, and another lane is minting a case this wave. A kind with no
 * threshold of ours falls to a default sentence rather than a `match` arm, so a
 * new case renders honestly instead of throwing `UnhandledMatchError` on the one
 * screen somebody opens at 3am.
 *
 * ⚠️ **AND THE CASE THAT LANE WAS MINTING LANDED, WHICH IS WHAT THAT PARAGRAPH
 * PREDICTED RATHER THAN A CONTRADICTION OF IT** — `GbpBindingMismatched`
 * appeared in every section here with no edit to this file's `bells()`.
 * ⛔ **The `match` that arrived with it is in the enum and not in this file**, on
 * this paragraph's own reasoning: an opinion about a kind belongs beside the
 * kind, where adding a case without deciding it is a Larastan failure before it
 * is ever a 3am one. {@see self::ringing()} reads that opinion and combines it
 * with what the table says; it does not hold one.
 *
 * ## What is on this table, and what may never be
 *
 * ⚠️ **`operator_alerts` IS PLATFORM-SCOPED AND ITS `subject` IS FREE TEXT**, so
 * what may appear there was established before anything was rendered. Across
 * every raiser the subject is one of: empty, a process name of ours (`scheduler`,
 * `queue`), an endpoint name of ours, a provider name of ours, a pixel build
 * token of ours, a business id, or `unattributed`. **No customer, no phone
 * number, no address, no email.** That is the creating migration's rule and every
 * writer keeps it.
 *
 * ⛔ **ONE FIELD ON THIS TABLE IS WRITTEN BY A STRANGER AND IT IS RENDERED HERE**
 * — `context.origin`, from `IngestRejects::record()`, which is the `Origin`
 * request header the collector's own comment calls *"trivially set by anything
 * that is not"* a browser. It is not personal data and it is not a leak; it is
 * **untrusted text on a staff screen**, so context values are rendered as escaped
 * data in a mono face rather than as prose, and clipped for length. The
 * migration's *nothing personal may be written here* is a rule about our own
 * writers, and this is the one value that does not come from one.
 *
 * ## No audit entry, for `GbpGrantRevocations`' reason
 *
 * There is no tenant to file one under. This table has no `business_id`, the
 * screen cannot be pointed at an account, and what it renders are counts, rates
 * and thresholds of ours rather than a tenant's records. `StaffActivity` reads
 * three platform-scoped stores on the same terms.
 *
 * ⚠️ **`times` IS `int<1, max>` AND `state` IS TWO OF THE FOUR CASES, AND
 * NEITHER BOUND IS DECORATION.** `Collection`'s value template is not
 * covariant, so a plain `int` reads as wider and Larastan refuses the
 * assignment — and both narrower types say something true besides: a group
 * Postgres emits at all has at least one row in it, and **a bell that has
 * already rung is never `Ok` and never `Unknown`**.
 *
 * @phpstan-type RingingLine array{kind: OperatorAlertKind, subject: string, times: int<1, max>, state: SignalState::Alert|SignalState::Attention, firstAt: CarbonImmutable, lastAt: CarbonImmutable, latest: OperatorAlert}
 */
final class OperatorAlertBoard extends Component
{
    use WithPagination;

    /**
     * How far back *"is this still going on"* looks.
     *
     * ⚠️ **A DAY RATHER THAN THE QUIET WINDOW.** The quiet window is how long one
     * bell stays silent after ringing (an hour, by seed); this is how far back
     * somebody woken at 2am has to look to see the shape of the night. Tied
     * together, an operator would open this screen after an incident and find the
     * incident already scrolled off the section named for it.
     */
    private const int RINGING_HOURS = 24;

    /**
     * How many lines the incident view draws before it starts summarising.
     *
     * ⛔ **THIS IS NOT 7756's REFUSED `limit()` AND THE DIFFERENCE IS THE WHOLE
     * SLICE.** That row refuses a limit on the **row** query, because the
     * rendered lines are grouped and carry a count, so truncating rows
     * *"understates an incident's count on the screen somebody opens during
     * it"*. The grouping now happens in Postgres, so every count on this screen
     * is taken over the **whole** window whether or not its line is drawn, and
     * what this bounds is how many already-counted lines are printed.
     * ⚠️ **Nothing inside a line is ever cut short, and the screen says so in
     * those words** — see {@see self::spread()} for what is rendered in place
     * of the lines that are not.
     *
     * ⚠️ **THE SAME FIGURE AS {@see self::PER_PAGE}, DELIBERATELY.** Both
     * sections stop at a length somebody can scan at 3am, and a reader who has
     * learned where one stops has learned where the other does. It is a
     * constant rather than a public property for `PER_PAGE`'s reason: a page
     * size the browser picks is a page size a stranger picks.
     */
    private const int RINGING_LINES = 25;

    /**
     * ⚠️ **A CONSTANT, NOT A PUBLIC PROPERTY** — `StaffActivity`'s rule. Every
     * public property on a Livewire component is settable from the browser, so a
     * public `$perPage` is a page size the client picks, and `perPage = 1000000`
     * asks Postgres for a million rows and PHP to hydrate them.
     */
    private const int PER_PAGE = 25;

    /**
     * The sentence {@see self::bells()} prints over a kind it knows no threshold
     * for.
     *
     * ⛔ **PUBLIC, AND THE ONLY REASON IS THAT A LINT HAS TO BE ABLE TO NAME
     * IT** (8120–8139). `OperatorAlertBoardTest`'s *"every alert kind has a
     * threshold row on this screen or is a named exception"* has to tell a real row from
     * this fallback, and the two things it could have keyed on instead are both
     * worse: a copy of the sentence in the test is a second source of truth for
     * a string, and `SignalState::Unknown` is an implementation detail this
     * method is free to use again. **A constant is not part of the browser
     * surface** — 7141's rule is about methods and properties, and nothing can
     * call this.
     *
     * ⚠️ **THE SENTENCE ITSELF MAKES NO NEGATIVE CLAIM, ON PURPOSE.** Whether
     * one of these can ring today is a fact about the code path it hangs off,
     * and this screen cannot see it — 7740–7759's correction, which found three
     * kinds falling here that had a threshold and a zero that meant three
     * different things.
     */
    public const string NO_THRESHOLD_DETAIL = 'It has no threshold of its own here, so this screen cannot tell you whether the thing that raises it ran.';

    /**
     * The kind filter — the one control on the screen.
     *
     * ⚠️ **IT NAMES NO RECORD, WHICH IS WHY IT IS THE ONLY PUBLIC PROPERTY AND
     * WHY NOTHING HERE CARRIES `#[Locked]`.** A public property naming a row is
     * how a component gets pointed at something its `mount()` never authorised;
     * this screen deliberately has none, so there is nothing to lock, and a test
     * asserts the property list stays that way rather than leaving the next
     * person to notice.
     *
     * ⚠️ **AN UNRECOGNISED VALUE WIDENS TO EVERYTHING RATHER THAN NARROWING TO
     * NOTHING** — `StaffActivity::agentId()`'s direction. A filter that silently
     * showed no rows on a typo is how somebody concludes the platform was quiet.
     */
    #[Url(as: 'kind', except: '')]
    public string $kind = '';

    public function mount(): void
    {
        // Repeated on the component as well as on the route's `can:` middleware
        // — decisions 630 and 809: `can:` refuses during route matching, so a
        // route-gate test passes while `mount()` is wide open, and
        // `Livewire::test()` runs no middleware at all.
        $this->authorize(AdminAccess::GATE);
    }

    public function updatedKind(): void
    {
        $this->resetPage();
    }

    public function clearKind(): void
    {
        $this->kind = '';
        $this->resetPage();
    }

    /**
     * Distinct bells rung in the last day — the ones needing action first.
     *
     * ⛔ **"NEWEST FIRST" WAS THIS METHOD'S WHOLE ORDER UNTIL 2026-08-21 AND IS
     * NOW THE TIE-BREAK** (7152, closed at 7200–7219). When two bells rang in
     * one night, recency was the only thing telling an operator which to answer,
     * and recency is *"which broke last"* rather than *"which is still getting
     * worse"*. {@see OperatorAlertKind::severity()} now answers that, so the
     * section leads with what needs action and falls back to recency inside each
     * band. ⚠️ **The log below is untouched and is still purely newest-first** —
     * it is a record, and a record that reorders itself by an opinion is one
     * nobody can scan.
     *
     * ⚠️ **REPETITION IS STILL READ AND IT IS THE FLOOR, NOT THE REPLACEMENT.**
     * A `(kind, subject)` that has outlasted more than one quiet window shows as
     * needing action whatever its kind answers, because *"this has been true all
     * night"* is a fact about the incident that no property of the kind can
     * know — 7152's original reasoning, kept, with the kind's own answer added
     * beneath it rather than instead of it.
     *
     * ⚠️ **THE STATE IS DECIDED HERE AND NOT IN THE VIEW.** 7152 refused to put
     * a `match` over kinds in a Livewire template and named the enum as the right
     * home; the enum now holds the opinion, and this is the one place it is
     * combined with what the table says, so the template renders a state it is
     * handed.
     *
     * ⚠️ **GROUPED ON `(kind, subject)` BECAUSE THAT IS THE DEDUPE KEY**, so the
     * count on each row is *"how many quiet windows this outlasted"* rather than
     * an arbitrary tally. One row that rang eleven times is a thing that has been
     * broken all night; eleven rows that rang once each is a different morning.
     *
     * ## ⛔ "A SMALL SET BY CONSTRUCTION" WAS THE PREMISE OF THIS METHOD AND IT
     * WAS FALSE — CORRECTED 2026-08-22 (7960–7979)
     *
     * This paragraph read: *"**BOUNDED BY THE WINDOW RATHER THAN PAGINATED.**
     * The rows inside a day are bounded by the dedupe itself — one per kind and
     * subject per quiet window — so this is a small set by construction, and a
     * paginated summary would be a summary somebody has to page through."*
     * **The first clause is true and the conclusion does not follow.** The
     * dedupe is one row per `(kind, subject)` per quiet window;
     * `ops.alert_quiet_minutes` has a legal floor of **one minute**
     * (7580–7599); and four kinds take a **business id** as their subject — the
     * same four that run inside an unauthenticated HTTP request. At the floor
     * that is 1,440 rows per kind per tenant per day, and this method loaded
     * every one of them into PHP as a model with its `jsonb` cast, to count
     * them and throw them away.
     *
     * ⚠️ **WAVE 13's PUSH BUDGET DOES NOT HELP AND MUST NOT BE READ AS HELPING**
     * (7820–7839). It bounds **pushes**; a withheld alert still inserts a row.
     *
     * ## What replaced it, in the order the reasons matter
     *
     * ⛔ **THE COUNT IS TAKEN IN POSTGRES, OVER THE WHOLE WINDOW, BEFORE
     * ANYTHING IS DROPPED.** That is what makes {@see self::RINGING_LINES}
     * legitimate where 7756's `limit()` was not: a line that is drawn says how
     * many times its bell rang in the whole day, and a line that is not drawn
     * is **counted into {@see self::spread()}** rather than vanishing.
     *
     * ⚠️ **TWO READS, AND THE SECOND ONE IS 7756's OWN PRESCRIPTION** — *"the
     * honest fix is grouping in SQL plus a second query for each group's latest
     * row"*. It is one query for all of them rather than one each: the group
     * read carries the id of its own newest row and the models are fetched
     * together. So the number of models this method hydrates is bounded by
     * `2 × RINGING_LINES` no matter how large the window gets, which is the
     * property `OperatorAlertBoardTest` pins by counting `retrieved` events.
     *
     * ⛔ **`latest` IS STILL A HYDRATED MODEL AND THAT IS DELIBERATE.** The
     * summary and the id could have been selected as raw columns and one query
     * saved; the model is where `summary`'s length, `kind`'s enum cast and
     * `fired_at`'s date cast live, and re-deriving those beside the query would
     * be a second source of truth about a row for the sake of a read that is
     * already bounded.
     *
     * ⚠️ **`lastAt` IS THE LATEST MODEL'S OWN `fired_at` RATHER THAN A
     * `max(fired_at)` COLUMN.** They are the same value by construction — the
     * latest row is the one with the greatest `(fired_at, id)` — and taking it
     * from the model means it arrives cast rather than parsed back out of a
     * string. `firstAt` has no such twin and is `min(fired_at)`, aliased to
     * `fired_at` **so that the model's own cast applies to it**.
     *
     * ⚠️ **THE SHAPE IS THE CLASS'S `RingingLine` ALIAS AND IS WRITTEN ONCE.**
     * It used to be spelled out here; the accumulator below needs the same
     * seven keys declared a second time, because **PHPStan generalises the type
     * of a variable built inside a loop** and `times` came back out as a plain
     * `int`. Two copies of a shape that has to match a template's exact bounds
     * is the divergence this file already refuses for a clamp (7661), so
     * `@phpstan-type` carries it — `Services\Actuation\ChangeSet`'s precedent.
     *
     * @return Collection<int, RingingLine>
     */
    public function ringing(): Collection
    {
        $since = CarbonImmutable::now()->subHours(self::RINGING_HOURS);

        // ⛔ **THE BAND IS COMPUTED IN SQL BECAUSE THE LIMIT HAS TO SELECT THE
        // RIGHT LINES, AND IT IS DERIVED FROM `severity()` RATHER THAN REPEATING
        // IT.** Ordering the page by recency and then bounding it would drop
        // exactly the lines 7200–7219 put at the top — the ones needing action
        // — on the night the bound first matters. `urgentKinds()` is
        // *defined* as the cases whose own answer is `Alert`, so this predicate
        // and the `state` below cannot disagree without somebody editing that
        // definition; the alternative was a second `match` over kinds living in
        // a query string, which is 7661's stale clamp with worse consequences.
        $urgent = self::urgentKinds();
        $needsActionFirst = $urgent === []
            ? 'case when count(*) > 1 then 0 else 1 end'
            : 'case when count(*) > 1 or kind in ('
                .implode(', ', array_fill(0, count($urgent), '?'))
                .') then 0 else 1 end';

        $groups = OperatorAlert::query()
            ->where('fired_at', '>=', $since)
            ->groupBy('kind', 'subject')
            // ⚠️ `min(fired_at) as fired_at` IS AN ALIAS THAT EARNS ITS
            // CONFUSION: the model casts a column called `fired_at`, so the
            // earliest moment in the group arrives as a `CarbonImmutable`
            // instead of a string to be parsed. ⚠️ **The latest row is picked by
            // `(fired_at, id)` and not by `max(id)`** — `fired_at` is not
            // `created_at` and a backfilled row would make the two disagree,
            // which `OperatorAlerts::alreadyRang()` says out loud about the
            // dedupe that produced these rows.
            ->selectRaw(
                'kind, subject, count(*) as times, min(fired_at) as fired_at, '
                    .'(array_agg(id order by fired_at desc, id desc))[1] as latest_id',
            )
            ->orderByRaw($needsActionFirst, $urgent)
            ->orderByRaw('max(fired_at) DESC NULLS LAST')
            // The tie-break `alerts()` documents at length, here for the same
            // reason: one sweep raises several alerts inside the same second,
            // and an undefined order between two lines is a line that moves
            // under the bound.
            ->orderByRaw('max(id) DESC NULLS LAST')
            ->limit(self::RINGING_LINES)
            ->get();

        /** @var Collection<int, OperatorAlert> $latest */
        $latest = OperatorAlert::query()
            ->whereIn('id', $groups->map(
                static fn (OperatorAlert $group): int => (int) $group->getAttribute('latest_id'),
            )->all())
            ->get()
            ->keyBy('id');

        // ⚠️ **A `map()` RATHER THAN A `foreach`, FOR A TYPE-SYSTEM REASON WORTH
        // KNOWING.** PHPStan **generalises the type of a variable built inside a
        // loop**, so an accumulator filled by `$entries[] = […]` comes back out
        // with `times` widened to `int` and `state` widened to `SignalState` —
        // the two bounds this collection's template needs, lost silently, with a
        // `@var` on the empty array making no difference because it applies at
        // the assignment and not after the loop. A closure's return type is not
        // generalised, so the shape survives.
        return $groups
            ->map(static function (OperatorAlert $group) use ($latest): ?array {
                $newest = $latest->get((int) $group->getAttribute('latest_id'));

                // Unreachable in practice and not left to chance on this screen
                // of all screens: `OperatorAlerts::prune()` cannot remove a row
                // inside the quiet window and nothing else deletes one at all,
                // so a group whose newest row vanished between the two reads is
                // a state this application has no writer for. A missing key here
                // would be a 500 at 3am; a missing line is a line.
                if (! $newest instanceof OperatorAlert) {
                    return null;
                }

                // `max(1, …)` is the cast rather than a correction: `count(*)`
                // over a group Postgres emitted is at least one, and this is how
                // that is said to Larastan.
                $times = max(1, (int) $group->getAttribute('times'));

                return [
                    'kind' => $group->kind,
                    'subject' => $group->subject,
                    'times' => $times,
                    // The kind's own answer is the floor and repetition can only
                    // raise it — see this method's docblock.
                    'state' => $times > 1 || $group->kind->severity() === SignalState::Alert
                        ? SignalState::Alert
                        : SignalState::Attention,
                    'firstAt' => $group->fired_at,
                    'lastAt' => $newest->fired_at,
                    'latest' => $newest,
                ];
            })
            // The guard above, and nothing else: `filter()` with no callback
            // drops exactly the nulls it returned.
            ->filter()
            // ⚠️ **RECENCY INSIDE A BAND IS HELD TWICE OVER AND NEITHER COPY IS
            // REDUNDANT ENOUGH TO DROP** (7212). `sortBy()` is stable and the
            // query above is already ordered the same way, so each of the two
            // would survive the other being broken — which was checked by
            // breaking both, one at a time and then together, before this method
            // was rewritten and again after. The second element is written out
            // because *"it happens to arrive sorted"* is exactly the property a
            // later refactor of the query removes silently.
            // ⚠️ **WHAT CHANGED IS THAT THE QUERY'S COPY NOW ALSO SELECTS THE
            // PAGE**, and that is not a reason to drop the other: the sort still
            // decides what the operator reads first, and it is the only copy
            // that survives somebody rewriting the query.
            ->sortBy(static fn (array $entry): array => [
                $entry['state'] === SignalState::Alert ? 0 : 1,
                -$entry['lastAt']->getTimestamp(),
            ])
            ->values();
    }

    /**
     * The kinds whose own answer is *needs action* before any repetition.
     *
     * ⛔ **DERIVED FROM {@see OperatorAlertKind::severity()} AND NEVER A LIST.**
     * A hand-written list here would be a second opinion about a kind living in
     * a screen, which 7152 refused and the enum now answers; and the one thing
     * that must stay true is that this and {@see self::ringing()}'s `state`
     * agree, because one of them chooses which lines are drawn and the other
     * says what they are.
     *
     * @return list<string>
     */
    private static function urgentKinds(): array
    {
        return array_values(array_map(
            static fn (OperatorAlertKind $case): string => $case->value,
            array_filter(
                OperatorAlertKind::cases(),
                static fn (OperatorAlertKind $case): bool => $case->severity() === SignalState::Alert,
            ),
        ));
    }

    /**
     * What rang in the window that the incident view had no room to draw.
     *
     * ⛔ **THE HALF OF 7756 THAT MAKES THE BOUND HONEST.** A truncated list is
     * only acceptable if the thing it truncates is still visible as a quantity,
     * and on the night this matters the quantity **is** the diagnosis: *"one
     * kind, across nine hundred accounts"* and *"nine hundred different things
     * wrong"* are the same number of lines and completely different nights, and
     * a list cut off at twenty-five lines cannot tell them apart. So the totals
     * are per **kind**, which is bounded by `OperatorAlertKind::cases()` and
     * therefore cannot itself run away.
     *
     * ⛔ **IT CARRIED `push_withheld_at` UNTIL 2026-08-22 AND NO LONGER DOES —
     * THE OLD PARAGRAPH IS KEPT AND DATED** (4368's rule, 8120–8139). It read:
     * *"⚠️ **IT CARRIES `push_withheld_at`, WHICH NOTHING HAS EVER RENDERED**
     * (7820–7839). A flood is precisely when the pager stops pushing, so the
     * operator reading this screen has to be told that what woke them is not the
     * whole of it — otherwise the bounded pager reads as a quiet night."*
     * **Every clause of that is true and it was in the wrong method.** This one
     * is gated on the page having **filled**, which is a statement about how
     * many `(kind, subject)` pairs rang; a spent push budget is a statement
     * about how many times **one** of them did, and one account on one kind
     * spends a whole day's allowance by itself at the seeded quiet window. So
     * the totals appeared only in a flood that was wide and the pager dies in
     * one that is deep. {@see self::withheld()} carries it now, at every volume
     * and behind no gate.
     *
     * ⚠️ **NULL WHEN THE LINES ARE THE WHOLE TRUTH, AND NO QUERY IS RUN THEN.**
     * The ordinary night draws fewer lines than {@see self::RINGING_LINES} and
     * pays nothing for this; a full page is what makes it worth asking. ⚠️ **A
     * full page with nothing behind it also returns null** — twenty-five lines
     * and exactly twenty-five things that rang is not a summary worth printing.
     *
     * ⚠️ **`$shown` IS PASSED IN RATHER THAN RE-DERIVED**, because the only
     * honest source for *"how many lines are on the page"* is the page.
     *
     * ⛔ **PRIVATE, AND THE TEST THAT CAUGHT IT IS THE ARGUMENT.** It was
     * written public like every other reader on this class and *"the board
     * declares no method that changes anything"* went red — **every public
     * method on a Livewire component is callable from the browser**, so a
     * public `spread(int $shown)` is a client-chosen integer reaching a query
     * on the one screen whose surface 7141 argued down to a single filter. The
     * sibling readers are public because tests drive them by hand; this one is
     * read off `viewData('spread')`, which is what the screen actually gets.
     *
     * @param  int  $shown  {@see self::ringing()}'s own count
     * @return array{groups: int, alerts: int, hidden: int, kinds: list<array{kind: OperatorAlertKind, subjects: int, alerts: int}>}|null
     */
    private function spread(int $shown): ?array
    {
        if ($shown < self::RINGING_LINES) {
            return null;
        }

        $since = CarbonImmutable::now()->subHours(self::RINGING_HOURS);

        $rows = OperatorAlert::query()
            ->where('fired_at', '>=', $since)
            ->groupBy('kind')
            ->selectRaw('kind, count(*) as alerts, count(distinct subject) as subjects')
            // Loudest first, which is the order somebody reads a flood in.
            ->orderByRaw('count(*) DESC NULLS LAST')
            ->get();

        $kinds = [];
        $groups = 0;
        $alerts = 0;

        foreach ($rows as $row) {
            $subjects = max(1, (int) $row->getAttribute('subjects'));
            $rang = max(1, (int) $row->getAttribute('alerts'));

            $groups += $subjects;
            $alerts += $rang;

            $kinds[] = [
                'kind' => $row->kind,
                'subjects' => $subjects,
                'alerts' => $rang,
            ];
        }

        // `max(0, …)` rather than a bare subtraction: this read and the one
        // above take `now()` a moment apart, so a row can land between them and
        // a bell that has not rung yet must never be reported as one that was
        // hidden.
        $hidden = max(0, $groups - $shown);

        if ($hidden === 0) {
            return null;
        }

        return [
            'groups' => $groups,
            'alerts' => $alerts,
            'hidden' => $hidden,
            'kinds' => $kinds,
        ];
    }

    /**
     * The bells this platform stopped sending — the depth half of a flood,
     * which {@see self::spread()} could not see (8120–8139).
     *
     * ## ⛔ Why this is not a field on the summary above
     *
     * ⛔ **{@see self::spread()}'s GATE IS A *BREADTH* CONDITION AND A SPENT
     * PUSH BUDGET IS A *DEPTH* ONE, AND THE TWO ARE INDEPENDENT.** That method
     * returns null unless {@see self::RINGING_LINES} lines were drawn **and**
     * something was left off, so the withheld totals it carried appeared only in
     * a flood that was **wide**. ⛔ **The pager dies in a flood that is deep**:
     * the budget is per *kind*, the quiet window's seeded floor is an hour, so
     * **one account on one kind rings twenty-four times a day and spends that
     * kind's whole ten-push allowance by itself** — after which every other
     * account's alert of that kind is recorded and sent to nobody, on a screen
     * drawing **one line**, with no summary rendered at all.
     *
     * ⚠️ **SO `withheld` IS GONE FROM {@see self::spread()} RATHER THAN
     * DUPLICATED HERE** (7971's reader, moved). One fact in one place: that
     * method answers *"more rang than fits"* and this answers *"the pager
     * stopped"*, and on a night that is both wide and deep an operator reads two
     * blocks saying two different things instead of one paragraph saying half of
     * each.
     *
     * ## ⚠️ It runs on every render, and {@see self::spread()}'s saving is not
     * available to it
     *
     * ⚠️ **THERE IS NO CHEAP PROXY FOR THE DEPTH CONDITION.** `spread()` asks
     * the page's own line count first and pays nothing on an ordinary night
     * (7970); *"did anything get held back"* has no equivalent — the only
     * source is the column. ⛔ **And gating it on the page filling is the defect
     * being fixed**, so the read is unconditional and is stated rather than
     * hidden: one grouped aggregate, on `fired_at`'s own index, over a
     * population {@see app(OperatorAlerts::class)->retentionDays()} bounds, on a screen
     * behind the admin gate that already runs a `COUNT(*)` for its paginator.
     *
     * ⚠️ **THE WINDOW IS THIS SCREEN'S {@see self::RINGING_HOURS} AND NOT
     * {@see app(OperatorAlerts::class)->pushBudgetHours()}, THOUGH BOTH ARE TWENTY-FOUR.**
     * 7581's rule: a value read by two things with opposite interests is a
     * coupling nobody stated. This section is part of *still ringing* and
     * follows it; the budget is a bound on sending and moves for its own
     * reasons.
     *
     * ⚠️ **`min(push_withheld_at) as push_withheld_at` IS AN ALIAS THAT EARNS
     * ITS CONFUSION**, exactly as `ringing()`'s `min(fired_at)` does: the model
     * casts a column of that name, so the first moment the pager went quiet on a
     * kind arrives as a `CarbonImmutable` rather than a string to be parsed.
     *
     * ⛔ **PRIVATE, ON {@see self::spread()}'s GROUND** — every public method on
     * a Livewire component is callable from the browser, and this one is a
     * grouped read with nothing an operator needs to point at. It is asserted
     * through `viewData('withheld')`, which is what the screen actually gets.
     *
     * @return array{alerts: int, kinds: list<array{kind: OperatorAlertKind, alerts: int, subjects: int, since: CarbonImmutable}>}|null
     */
    private function withheld(): ?array
    {
        $since = CarbonImmutable::now()->subHours(self::RINGING_HOURS);

        $rows = OperatorAlert::query()
            ->where('fired_at', '>=', $since)
            ->whereNotNull('push_withheld_at')
            ->groupBy('kind')
            ->selectRaw(
                'kind, count(*) as alerts, count(distinct subject) as subjects, '
                    .'min(push_withheld_at) as push_withheld_at',
            )
            // Loudest first, the same order as the summary beside it.
            ->orderByRaw('count(*) DESC NULLS LAST')
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        $kinds = [];
        $alerts = 0;

        foreach ($rows as $row) {
            $held = max(1, (int) $row->getAttribute('alerts'));
            $subjects = max(1, (int) $row->getAttribute('subjects'));
            $since = $row->push_withheld_at;

            // Unreachable and not left to chance: every row in this set has a
            // `push_withheld_at`, because the `whereNotNull` above is what
            // selected it, so `min()` over the group cannot be null. A missing
            // moment here would be a 500 on the screen somebody opens during the
            // flood; a missing line is a line.
            if (! $since instanceof CarbonImmutable) {
                continue;
            }

            $alerts += $held;

            $kinds[] = [
                'kind' => $row->kind,
                'alerts' => $held,
                'subjects' => $subjects,
                'since' => $since,
            ];
        }

        if ($kinds === []) {
            return null;
        }

        return [
            'alerts' => $alerts,
            'kinds' => $kinds,
        ];
    }

    /**
     * Everything this platform still keeps, newest first.
     *
     * ⛔ **"EVERYTHING" ACQUIRED A HORIZON ON 2026-08-22 AND THE SCREEN SAYS SO
     * RATHER THAN THIS DOCBLOCK SAYING IT ALONE** (7520–7539).
     * `OperatorAlerts::prune()` removes a row after
     * {@see app(OperatorAlerts::class)->retentionDays()}, so this listing ends at a year and
     * an empty one is no longer *"this has never happened"*. ⚠️ **A screen whose
     * heading says everything and whose data stops silently is the failure the
     * horizon would otherwise introduce**, which is why the retention is
     * rendered beside the list and why the empty state was rewritten to make no
     * claim about *ever* — `ops:alert-channels`' own *"an empty table is not a
     * healthy one and it says so"*.
     *
     * ⚠️ **AND THE HORIZON IS WHAT MAKES THIS PAGINATION BOUNDED.**
     * `LengthAwarePaginator` runs a `COUNT(*)` over the whole filtered set on
     * every render, so an unbounded table is an unbounded count on the one
     * screen somebody opens at 3am. Nothing here changed to fix that; the table
     * stopped being unbounded.
     *
     * ⚠️ **`id` IS THE TIE-BREAK AND IT IS NOT TIDINESS.** `fired_at` is a
     * timestamp and one sweep can raise several alerts inside the same second, so
     * an order on it alone is undefined between them — under pagination that
     * means a row appearing on two pages or on none, which on the screen somebody
     * opens after an incident is the row they are looking for going missing.
     *
     * ⚠️ **`NULLS LAST` IS SAID OUT LOUD EVEN THOUGH `fired_at` IS `NOT NULL`.**
     * Postgres sorts nulls **first** on a descending order, so `ConventionsTest`
     * refuses a bare `orderByDesc` on anything but `id` — and it refuses it
     * without reading the schema, deliberately, because three sites in one slice
     * reached for the unsafe spelling and two of them decided which record a
     * message was sent from. The column being non-nullable today is not a
     * property this file can promise tomorrow.
     *
     * @return LengthAwarePaginator<int, OperatorAlert>
     */
    public function alerts(): LengthAwarePaginator
    {
        $kind = OperatorAlertKind::tryFrom($this->kind);

        return OperatorAlert::query()
            ->when(
                $kind instanceof OperatorAlertKind,
                static fn (Builder $query): Builder => $query->where('kind', $kind?->value),
            )
            ->orderByRaw('fired_at DESC NULLS LAST')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE);
    }

    /**
     * Every bell, and whether anything is watching for it.
     *
     * ⛔ **THE THRESHOLD MAP IS AN ARRAY WITH A FALLBACK, DELIBERATELY NOT A
     * `match`.** Another lane is adding a case to that enum this wave. A `match`
     * would either need an arm for a case that does not exist on this branch —
     * impossible — or a `default` that is a guess; an unmatched case would throw
     * `UnhandledMatchError` **inside `render()`**, on the screen whose entire job
     * is to still work when other things do not. A new case falls to a sentence
     * that makes no claim about it instead.
     *
     * ⚠️ **EVERY KEY IS READ OFF A CONSTANT ON THE CLASS THAT OWNS IT**, never
     * a literal. `PlatformHealthChecks`' six exist so *"the watch command can say
     * which are unset"* and this is their second reader;
     * {@see WatchPlatformComplaintRate}'s two were promoted from literals when
     * this became their second reader (7607), and
     * {@see WatchPixelCanary}'s three were minted for the same reason at
     * 7740–7759 — **the rule was stated in this docblock while two of the keys it
     * governs had no constant to be read off anywhere in `app/`.**
     * ⚠️ **The count is deliberately not written here** — it was "the four keys"
     * while there were six, and the map below is one `count()` away.
     *
     * ## ⛔ ZERO DOES NOT MEAN THE SAME THING ON EVERY ROW BELOW (7740–7759)
     *
     * This screen used to close with *"only the platform-health checks have a
     * threshold that can be turned down to zero"*, and three kinds that fell to
     * the *"it has no threshold of its own here"* sentence had one. That is
     * 314–316 on the one screen whose job is telling an operator which bells are
     * armed — worse than silence, because the sentence reads as considered.
     *
     * The three are here now, each saying what **its** zero does rather than
     * being folded into one claim:
     *
     *   {@see OperatorAlertKind::PixelCanaryHalted} has no off value at all. A
     *   zero threshold is a hair trigger and a **rollback**, not a mute.
     *
     *   {@see OperatorAlertKind::InboundVoiceMinutes} has a zero that switches
     *   the bell off **and** stops this platform downloading any voicemail
     *   recording for anybody. That one is deliberate — an operator's emergency
     *   brake on a cost path a stranger can start — and it is why it is stated
     *   here rather than refused there.
     *
     *   {@see OperatorAlertKind::PixelMonthlyCapReached} has a zero that means
     *   **uncapped**: nothing is ever refused, so nothing ever rings.
     *
     * ⚠️ **AND EACH IS READ THROUGH THE CLASS THAT APPLIES IT**, not recomputed —
     * 7661, where a clamp copied into this very file went stale in one merge. The
     * canary's two figures come from {@see WatchPixelCanary::haltThresholdBp()}
     * and {@see WatchPixelCanary::sampleFloor()}, which are what the sweep
     * itself reads, so a row outside their range prints what will actually
     * happen rather than what was typed. {@see VoiceSpend} answers its own
     * ceilings for the same reason.
     *
     * ⚠️ **ONE ROW READS NO REGISTRY KEY AT ALL AND IS NOT AN EXCEPTION TO THE
     * RULE ABOVE** (8120–8139). {@see OperatorAlertKind::PagerBudgetSpent}'s
     * gate is {@see app(OperatorAlerts::class)->pushBudgetPerKind()}, a **constant** —
     * 7830 refuses to make a bound on how loudly a stranger may ring the pager
     * editable from the same screen as the pager — so there is nothing an
     * operator could have zeroed and nothing for {@see self::switched()} to
     * report. The figures are still read off the class that applies them rather
     * than repeated, which is the half of the rule that was ever about drift.
     *
     * ⛔ **AND EVERY CASE WITHOUT A ROW HERE IS NOW A NAMED EXCEPTION**
     * (7780, 7901). Two integrators in two consecutive waves found a newly
     * minted kind falling through to {@see self::NO_THRESHOLD_DETAIL} over a
     * bell whose gate was two registry keys, because the only test on this map
     * asserted one entry per case **in order** — which the fallback satisfies
     * perfectly. `OperatorAlertBoardTest` now holds an explicit exemption list
     * on `TenancyTest`'s row-level-security shape, red on a new case with
     * neither a row nor an argument **and** red on an exemption that has since
     * gained one.
     *
     * ⛔ **THIS SIGNATURE IS DELIBERATELY UNCHANGED, WHICH IS 7661's NOTE ABOUT
     * `render()` ARRIVING ONE METHOD ALONG.** `VoiceSpend` began as a second
     * parameter here and it broke a sibling test that drives `bells()` directly
     * with one argument — in a file this lane may not edit. A public method a
     * test calls by hand is part of the surface, so the service is resolved
     * inside rather than injected in.
     *
     * @return list<array{kind: OperatorAlertKind, watched: bool, state: SignalState, status: string, detail: string}>
     */
    public function bells(DefaultsRegistry $registry): array
    {
        $window = $registry->int(PlatformHealthChecks::WINDOW_KEY);
        $failed = $registry->int(PlatformHealthChecks::FAILED_JOB_KEY);
        $webhook = $registry->int(PlatformHealthChecks::WEBHOOK_KEY);
        $rateBp = $registry->int(PlatformHealthChecks::VENDOR_RATE_KEY);
        $floor = $registry->int(PlatformHealthChecks::VENDOR_FLOOR_KEY);
        $stale = $registry->int(PlatformHealthChecks::STALE_KEY);
        $tripBp = $registry->int(WatchPlatformComplaintRate::TRIP_KEY);
        $tripFloor = $registry->int(WatchPlatformComplaintRate::FLOOR_KEY);
        $tenantTripBp = $registry->int(WatchPlatformComplaintRate::TENANT_TRIP_KEY);
        $tenantFloor = $registry->int(WatchPlatformComplaintRate::TENANT_FLOOR_KEY);

        /** @var array<string, array{watched: bool, state: SignalState, status: string, detail: string}> $thresholds */
        $thresholds = [
            'FailedJobSpike' => self::switched(
                $failed > 0,
                "Rings above {$failed} failed jobs in {$window} minutes.",
                'Its threshold is 0, so nothing counts failed jobs and this can never ring.',
            ),
            // ⛔ **THE ARMED SENTENCE STOPPED AT THE FIGURE, OVER A COUNTER
            // WHOSE QUANTITY IS SOMEBODY ELSE'S — NARROWED 2026-08-29
            // (12350–12352).** It read *"Rings above 10 rejected webhooks from
            // one endpoint in 60 minutes."* and nothing else. **That is the same
            // over-claim the two rows below were narrowed for**, on the same
            // screen, in the same method — `CLAUDE.md`'s *a fix applied to the
            // lint that taught a lesson does not reach the lints that copied
            // it*, one row up this time.
            //
            // ⛔ **AND THE MANIFEST ENTRY FOR THE KEY HAD ALREADY SAID IT.**
            // `ops.webhook_signature_failure_spike`'s own description: *"an
            // endpoint carrying a few notifications per subscription event may
            // never reach ten in an hour on any install this platform has"*, and
            // *"AN ENDPOINT FOR A FEATURE THAT IS SWITCHED OFF RECEIVES
            // NOTHING, so its bell cannot ring however wrong its secret is."*
            // **A vacuity declared in a seed an operator never opens, under a
            // screen that says the bell is armed, is a declaration nobody
            // receives** — and this screen is where an operator goes to find out
            // which bells are armed (7780, 7901).
            //
            // ⚠️ **THE OFF SENTENCE IS UNCHANGED ON PURPOSE**, on the two rows
            // below's own reasoning: it is about the threshold rather than the
            // coverage, and it was never wrong.
            //
            // ⚠️ **AND THE ROW STAYS ON `switched()` RATHER THAN BECOMING THE
            // CHECK'S OWN ANSWER LIKE `HeartbeatSilent`'s.** That row moved
            // because it reads an **absence** and needed a presence to measure
            // against. This one reads a counter somebody else's code is already
            // writing, so a non-zero threshold genuinely is the whole of its
            // arming — what was missing was never the state, only the sentence.
            'WebhookSignatureFailures' => self::switched(
                $webhook > 0,
                "Rings above {$webhook} rejected webhooks from one endpoint in {$window} minutes — and "
                    .'the quantity counted is the vendor posting to us, never anything this platform '
                    .'does. An endpoint that receives fewer webhooks than that in the window cannot '
                    .'reach the figure however wrong its signing secret is, and an endpoint for a '
                    .'switched-off feature receives nothing at all, so this can ring about neither. '
                    .'Lowering the figure does not arm it for them, because the quantity is not ours '
                    .'to produce. What reports a secret that is missing rather than wrong, at any '
                    .'volume, is ops:webhook-material on every deploy.',
                'Its threshold is 0, so unverifiable webhooks are refused silently and this can never ring.',
            ),
            // ⛔ **THE ARMED SENTENCE SPOKE ABOUT "A PROVIDER" OVER A COUNTER
            // ONE CLASS WRITES — NARROWED 2026-08-24 (9280–9285).** It read
            // *"Rings when a provider fails more than N% of at least F calls in
            // W minutes."* `OperatorAlertKind::VendorErrorRate` reads
            // `PlatformHealthSignal::VendorCall` and nothing else, and the AI
            // router is the only thing in `app/` that writes that signal — so
            // the bell can only ever fire about `anthropic` or `openai`. **This
            // screen's whole job is telling an operator which bells are armed**
            // (7780, 7901), so a coverage claim here is the expensive kind.
            //
            // ⛔ **THE CLASS IS NAMED IN PROSE RATHER THAN IN BACKTICKS AND THAT
            // IS NOT A STYLE CHOICE** (9285(d)). `Architecture\AiTest`'s *"no AI
            // call sits on a synchronous request path"* — `29` §2, one of the 48
            // — greps the router's own class name, word-anchored, over **raw**
            // source across all of `app/Livewire`, so writing that name **in a
            // comment** on any Livewire component fails the build. That is the negative-arm case
            // `CLAUDE.md` names, where raw is deliberately the stricter read; the
            // cost is that a screen may not cite the class it is describing, and
            // the lint is right and stays as it is. `ObservabilityTest` §10 is
            // where the class is named, and it is not a synchronous path.
            //
            // ⚠️ **AND THE ROW DOES NOT SAY WHAT THE OTHERS DO INSTEAD, WHICH IT
            // ALMOST DID.** A draft read *"every other outside service reaches
            // `VendorLog`, which writes no table"* — true of nearly all of them and
            // **false of object storage**, which reaches no `VendorLog` and no
            // `Log::` call at all (`Services/Warehouse/ObjectStoreL0Archive.php`,
            // checked rather than assumed). A coverage sentence corrected by a
            // sentence that over-claims in a smaller way is `CLAUDE.md`'s *prose
            // written to correct prose over-claims coverage*, inside the row it
            // is correcting. **What is asserted is only that they report nothing
            // HERE**, which is the claim this screen can actually make.
            //
            // ⚠️ **IT IS `HeartbeatSilent`, THE NEXT ROW DOWN, ONE WAVE LATER.**
            // That row was narrowed at 8880–8899 for saying "the scheduler or a
            // queue worker" over a beat that rides one queue name; the correction
            // was applied to it and not to its neighbour, which is `CLAUDE.md`'s
            // *a fix applied to the lint that taught a lesson does not reach the
            // lints that copied it*, inside one method.
            //
            // ⛔ **AND THE SENTENCE IS NOT WHAT KEEPS ITSELF TRUE.**
            // `ObservabilityTest` §10 pins the writer set of that signal, so a
            // second writer arriving — which `WatchPlatformHealth`'s docblock
            // actively invites, `recordFailure(VendorCall, 'deepgram')` — reddens
            // the build and names this row. Narrowing a sentence with nothing
            // under it is 314–316 one revision later.
            //
            // ⚠️ **THE OFF SENTENCE IS UNCHANGED ON PURPOSE**, on the same
            // reasoning as `HeartbeatSilent`'s: it is about the threshold rather
            // than the coverage, and it was never wrong.
            'VendorErrorRate' => self::switched(
                $rateBp > 0 && $floor > 0,
                'Rings when a provider fails more than '.round($rateBp / 100, 1)
                    ."% of at least {$floor} calls in {$window} minutes — and only for a provider "
                    .'that reports its own calls to this counter. Today that is the model providers '
                    .'behind the AI router and nothing else. Google Business through Zernio, the '
                    .'carrier, mail, the payment gateways and object storage report nothing here, '
                    .'so this cannot ring about one of them however badly it is failing.',
                'Its rate or its floor is 0, so provider failures are not measured and this can never ring.',
            ),
            // ⛔ **THE ARMED SENTENCE SPOKE IN THE PLURAL ABOUT QUEUES THE BEAT
            // BEHIND IT CANNOT SEE — NARROWED 2026-08-23 (8880-8899).** It read
            // *"Rings when the scheduler or a queue worker has not reported for
            // N minutes."* `RecordQueueHeartbeat` carries no `$queue`, so it
            // rides one of the six names `config/horizon.php` designs for; a
            // worker that stopped popping any other name produces **no row at
            // all** — not amber, not a per-queue tick — while this panel went on
            // saying the bell was armed over it. **This screen's whole job is
            // telling an operator which bells are armed**, which is what makes
            // the plural expensive here rather than merely loose (7780, 7901).
            //
            // ⚠️ **THE OFF SENTENCE IS UNCHANGED ON PURPOSE.** It is pinned by
            // `OperatorAlertBoardTest`, it is about the threshold rather than
            // the coverage, and it was never wrong.
            //
            // ⛔ **AND IT IS THE ONE ROW HERE THAT CANNOT BE ARMED BY ITS
            // THRESHOLD ALONE — MOVED OFF `switched()` 2026-08-26 (9930–9944).**
            // Every other check on this screen reads a counter somebody else's
            // code is already writing, so a non-zero threshold is genuinely the
            // whole of its arming. **This one reads an absence, and an absence
            // needs a presence to be measured against**: a process that has
            // never beaten once cannot go silent, `sweepHeartbeats()` skips it
            // for a reason recorded there, and this panel said `Watching` over
            // it. On an install whose `queue:work` cron line was never added —
            // and nothing in this repository creates a crontab (416) — that is
            // the bell for *"nothing is finishing your work"* rendered as armed
            // while it is structurally unable to ring.
            //
            // ⚠️ **THE ROW IS THE CHECK'S OWN ANSWER, ON 7661's RULE** — a
            // screen reads a figure off the class that applies it rather than
            // repeating it, with a state in place of a figure. The queue phrase
            // still goes the other way, because that derivation is this
            // screen's and is pinned against the dispatcher by
            // `QueueRoutingTest`.
            'HeartbeatSilent' => app(PlatformHealthChecks::class)->heartbeatCoverage(
                $stale,
                self::heartbeatQueuePhrase(),
            ),
            'DeliveryReceiptsSilent' => self::switched(
                $tripBp > 0 && $tripFloor > 0,
                "Rings when at least {$tripFloor} messages have gone to a carrier in 24 hours "
                    .'and not one delivery outcome has come back.',
                'The platform complaint trip is switched off, so there is no automatic halt '
                    .'to go blind and this can never ring.',
            ),
            // ⚠️ **ADDED BY THE WAVE-13 INTEGRATOR, AND IT IS 7780 A SECOND
            // TIME** (7901). The kind is lane D's and this file was nobody's,
            // so the bell for the loudest thing this platform does would have
            // rendered "It has no threshold of its own here" — false, because
            // its gate is the pair below and zeroing either one makes the halt
            // unable to trip at all. ⛔ **The same two keys as
            // `DeliveryReceiptsSilent`, deliberately**: one switch arms the
            // automatic halt and the bell that announces it, so a row claiming
            // one is watched while the other is not would be the lie this
            // screen exists to prevent. D shipped no test pinning the fallback,
            // which is what let this cost one entry (7739's precedent).
            'PlatformSendingHalted' => self::switched(
                $tripBp > 0 && $tripFloor > 0,
                'Rings when the platform stops its own sending — the complaint rate over at least '
                    ."{$tripFloor} delivered messages reached the trip.",
                'The platform complaint trip is switched off, so sending is never halted '
                    .'automatically and this can never ring.',
            ),

            // ⚠️ **ADDED BY THE WAVE-12 INTEGRATOR, NOT BY EITHER LANE** (7780).
            // The kind is lane C's and this file was lane D's, so the row its
            // author predicted would be missing was missing: without it this
            // screen printed "It has no threshold of its own here" over a bell
            // whose gate is two registry keys. **Both keys, because a zero in
            // either makes `SendingRates::trafficWithoutOutcomes()` return false
            // for every tenant** — the trip it reports on is switched off, so
            // there is nothing left to go blind.
            'TenantDeliveryReceiptsSilent' => self::switched(
                $tenantTripBp > 0 && $tenantFloor > 0,
                "Rings when some accounts have handed at least {$tenantFloor} messages to a carrier "
                    .'in 24 hours with no delivery outcome back, while the rest of the platform is '
                    .'being reported on normally.',
                'The per-tenant complaint trip is switched off, so no account has a trip that could '
                    .'go blind and this can never ring.',
            ),
            // ⛔ **THE ONE ROW HERE THAT IS ABOUT THE PAGER RATHER THAN ABOUT
            // THE WORLD, AND ITS LAST SENTENCE IS THE POINT OF IT** (8120–8139).
            // The budget counts pushes that left the building (7826), so on an
            // install with no address and no number nothing is spent, nothing is
            // withheld and this bell is structurally unreachable — which is the
            // state every fresh install is in and is exactly the *"quiet board,
            // switched-off check"* ambiguity this whole section exists for.
            // ⚠️ **`watched: true` unconditionally, because the mechanism has no
            // registry key at all**: 7830 refuses to make the allowance a
            // setting, so there is nothing an operator could have zeroed and
            // nothing for `switched()` to report.
            'PagerBudgetSpent' => [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => 'Rings when any one bell here has already been emailed or texted '
                    .app(OperatorAlerts::class)->pushBudgetPerKind().' times in '
                    .app(OperatorAlerts::class)->pushBudgetHours().' hours and another of that kind is recorded, '
                    .'so that a pager going quiet is never mistaken for a problem going away. '
                    .'It has no setting: the limit is fixed in the code, so there is nothing here to '
                    .'turn up or switch off. Where no address and no number are set nothing is ever '
                    .'sent, so nothing is ever held back and this cannot ring.',
            ],
            // ⛔ **THE ROW FOR THE ONE BELL THAT RINGS ABOUT A PLATFORM THAT
            // HAS ALREADY STOPPED SENDING** (8270–8289). It is written out
            // rather than exempted, and the choice is the opposite of the four
            // names in `OperatorAlertBoardTest`'s exemption list: those are all
            // *"there is no figure to set"* **and** *"this screen cannot see
            // whether the thing that raises it ran"* — a webhook, an erasure
            // sweep, a collector. ⚠️ **Only the first half is true here.** There
            // is no figure, and there is also nothing conditional about the
            // raiser: `PlatformHealthChecks::sweepCounters()` asks on every run
            // of `ops:watch-platform-health`, unconditionally, with no
            // threshold in front of it. Printing `NO_THRESHOLD_DETAIL` over
            // that would make no claim where a true one is available, on the
            // screen whose whole job is telling an operator which bells are
            // armed. `PagerBudgetSpent` above is the shape it follows.
            //
            // ⛔ **THE LAST SENTENCE IS THE ONE THAT COULD NOT BE LEARNED
            // ANYWHERE ELSE ON THIS SCREEN.** Every other row here describes
            // something an operator might go and adjust; this one is fixed from
            // a console and never from Ops, because adopting an epoch is a
            // person asserting a fact this application cannot check (8185) and
            // 8201 made both verbs interactive-only on purpose.
            'SuppressionsUnreadable' => [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => 'Rings when this install can no longer read the suppression hashes it has '
                    .'stored — an APP_KEY that changed, or an upgrade that arrived with suppressions '
                    .'already in it. It has no threshold and no off switch: the platform-health sweep '
                    .'asks on every run. While it is ringing every send on the platform is already '
                    .'being refused, and it is cleared from a console with php artisan '
                    .'consent:hash-epoch, never from this screen.',
            ],
            // ⛔ **A ROW RATHER THAN AN EXEMPTION, AND THE CHOICE IS AGAINST
            // ITS OWN TWO GBP NEIGHBOURS** (9234). `GbpGrantOutstanding` and
            // `GbpBindingMismatched` are both on `OperatorAlertBoardTest`'s
            // exemption list because neither has a figure to set — one
            // outstanding grant is the trip, and a disagreement either
            // happened or it did not. **This one has a registry key**, so
            // falling through to `NO_THRESHOLD_DETAIL` would print a sentence
            // making no claim where a true one is available, on the screen
            // whose whole job is telling an operator which bells are armed —
            // which is exactly what 7780 and 7901 each cost an integrator.
            //
            // ⚠️ **THE FIGURE IS READ OFF THE CLASS THAT APPLIES IT** (7661).
            // The command clamps at zero, so a row hand-edited negative prints
            // what will actually happen rather than what was typed.
            //
            // ⚠️ **AND THE OFF SENTENCE SAYS WHAT ELSE STOPS**, because on
            // this bell zeroing the threshold does not stop the sweep or the
            // screen — the accounts are still counted nightly and still listed
            // on `Admin\GbpGrantRevocations`. An operator who zeroes this is
            // choosing to look rather than be told, which is a different
            // decision from switching a measurement off.
            'GbpOrphanedAccounts' => self::switched(
                ReconcileZernioAccounts::alertThresholdCents($registry) > 0,
                'Rings when Google accounts connected under our Zernio key that nothing here is '
                    .'using would cost more than $'
                    .number_format(ReconcileZernioAccounts::alertThresholdCents($registry) / 100, 2)
                    .' a month. Nothing is refused and nothing here can end one: they are '
                    .'disconnected in Zernio\'s own console.',
                'Its threshold is 0, so nothing rings about Google accounts we are billed for and '
                    .'are not using. The nightly reconciliation still runs and they are still '
                    .'listed on the Google grant revocations screen.',
            ),
            // ⛔ **A ROW RATHER THAN AN EXEMPTION, AND THE ARGUMENT IS
            // `SuppressionsUnreadable`'s ABOVE RATHER THAN THE FIVE EXEMPT
            // NAMES'** (9320–9327). Those five are *"there is no figure to
            // set"* **and** *"this screen cannot see whether the thing that
            // raises it ran"*. Only the first is true here: there is no figure,
            // and the raiser is `PlatformHealthChecks::sweepCounters()`, asked
            // unconditionally on every run of the platform-health sweep with no
            // threshold in front of it. Printing `NO_THRESHOLD_DETAIL` over that
            // would make no claim where a true one is available.
            //
            // ⛔ **THE SECOND SENTENCE IS THE COVERAGE CLAIM AND IT IS THE
            // EXPENSIVE KIND** (9280, 8880–8899). This bell reads two counters
            // written from one place — the class that resolves every platform
            // credential — so it covers **every** credential this application
            // declares, and it covers **none** of a credential that is present
            // and wrong. A revoked key, a key pasted into the wrong box and a
            // key the vendor has disabled all read here exactly like a working
            // one, and `Credentials` refuses a "Test connection" button on the
            // stated argument that a green light proving nothing is worse than
            // no light. Saying so is this screen's whole job.
            //
            // ⛔ **AND THE THIRD SENTENCE IS THE ONE THAT COULD NOT BE LEARNED
            // ANYWHERE ELSE**: the repeat is bounded by whether this platform
            // has seen the fault before, not by a threshold, so a second bell
            // about the same key does not arrive and its silence is not the
            // problem going away.
            'PlatformCredentialUnusable' => [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => 'Rings the first time this platform is refused a vendor credential it '
                    .'needed — one that has never been set, or one whose stored value this install '
                    .'can no longer decrypt after an APP_KEY change. It has no threshold and no off '
                    .'switch: a credential is missing at one call an hour exactly as much as at ten '
                    .'thousand, so there is no figure that would be honest here. It cannot tell you '
                    .'a credential is present and wrong — a revoked or mistyped key reads here '
                    .'exactly like a working one. And it rings once per key: while the fault lasts '
                    .'the silence after it is not the problem going away, and the standing answer is '
                    .'on the Credentials screen.',
            ],
            // ⛔ **A ROW RATHER THAN AN EXEMPTION, ON THE SAME TEST AS THE ONE
            // ABOVE** (9380–9394): there is no figure to set, and the raiser is
            // `PlatformHealthChecks::sweepCounters()`, asked unconditionally on
            // every run of the platform-health sweep. Only the first half of the
            // exemption list's argument is true of it.
            //
            // ⛔ **THE COVERAGE CLAIM IS THE EXPENSIVE SENTENCE AND IT IS
            // NARROW** (9280, 8880–8899). Only the endpoints that fetch signing
            // material over the network can raise this; every other one compares
            // an HMAC against a secret this platform already holds, has nothing
            // to fetch, and can never raise it. Saying "webhook endpoints"
            // without saying which would be the vendor bell's own defect
            // installed one row down.
            //
            // ⚠️ **THE COUNTS ARE GONE AND THAT IS THE REPAIR, NOT A TIDY-UP.**
            // This sentence said "two of the eight" and "the other six", and
            // `WebhookKeySourceBellTest` pinned the first of those verbatim — so
            // a ninth endpoint left both wrong **and the test still passing**, a
            // hand-kept count with its own assertion string standing guard over
            // it. The two that fetch are named instead, which is what the
            // sentence was for; the rest is stated as a property.
            // ⚠️ **`WebhookMaterialCensus` now derives this from the router**,
            // so a reader who wants the number can ask the tree for it. Coupling
            // this row to that census was weighed and left: it is a bell's
            // description, it is read by an operator rather than by a machine,
            // and the property above is true at every count.
            //
            // ⚠️ **AND THE LAST SENTENCE IS THE ONE THAT COULD NOT BE LEARNED
            // ANYWHERE ELSE**: this bell repeats while the fault lasts, which is
            // the opposite of the credential row above it, and an operator who
            // has read that row would otherwise carry its rule across.
            'WebhookKeysUnavailable' => [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => 'Rings the first time a webhook endpoint cannot fetch the keys it checks '
                    .'callers with — deliveries arriving meanwhile are turned away without being '
                    .'judged, and they are lost. Only the endpoints that fetch signing material '
                    .'over the network can raise it: Amazon SES feedback and Gmail push. Every '
                    .'other webhook endpoint compares a signature against a secret already held '
                    .'here and has nothing to fetch. It has no threshold and no off switch: a key host '
                    .'is unreachable at one delivery an hour exactly as much as at ten thousand. '
                    .'Unlike the credential bell it keeps ringing while the fault lasts, because '
                    .'every hour it is true is more deliveries lost.',
            ],
            'PixelCanaryHalted' => self::canaryBell($registry),
            'InboundVoiceMinutes' => self::voiceBell(app(VoiceSpend::class)),
            'PixelMonthlyCapReached' => self::eventCapBell($registry),
            // ⛔ **A ROW RATHER THAN AN EXEMPTION, ON `SuppressionsUnreadable`'s
            // AND `PlatformCredentialUnusable`'s ARGUMENT** (9370–9379). The
            // five exempt names are *"there is no figure to set"* **and** *"this
            // screen cannot tell you whether the thing that raises it ran"*.
            // Only the first is true here: there is no figure, and the raiser is
            // `DeliverPlatformMail::failed()`, which the queue calls on every
            // permanent failure of every platform email with nothing in front of
            // it. Printing `NO_THRESHOLD_DETAIL` would make no claim where a
            // true one is available.
            //
            // ⛔ **THE THIRD SENTENCE IS THE ONE THAT COULD NOT BE LEARNED
            // ANYWHERE ELSE, AND IT IS THE REASON THIS ROW EXISTS AT ALL.**
            // `OperatorAlerts::email()` sends through the same mailer this bell
            // reports on, so on a broken mail path the email for it is refused
            // by the transport that raised it and only the text arrives.
            // **A bell whose only clapper is the mail system cannot ring about
            // the mail system** — that sentence has been in `OperatorAlerts`
            // since the day it shipped and was nowhere an operator could read
            // it. It is here, on this kind's own row, because this is the one
            // kind for which it decides whether anybody is told at all.
            //
            // ⚠️ **AND THE FOURTH SAYS WHAT IT CANNOT SEE.** A message accepted
            // by the transport and then bounced, filed as spam or delivered to a
            // mailbox nobody reads is not a failure this bell can know about:
            // there is no feedback signal on this transport (open question H),
            // so silence here is not evidence that mail is arriving.
            // ⛔ **AND ITS COVERAGE IS NARROWER THAN THE ROW READ UNTIL WAVE 41
            // (11132).** `OperatorAlertKind::PlatformMailUndeliverable` is raised
            // in exactly one place — `App\Jobs\DeliverPlatformMail::failed()` —
            // so it watches `PlatformMailer::send()`'s population and no other.
            // Every `deliverNow()` caller is outside it, and wave 41 moved six
            // senders across that line in one night (11010, 10980–10995), each
            // reporting instead through its own `AutopilotJob::failed()` or its
            // command's exit code. ⚠️ **The sentence was already narrower than it
            // read before any of them moved**, which is why this is a correction
            // rather than a consequence. ⛔ **An operator reads this row to decide
            // whether silence is good news; a coverage claim here is the one
            // place in this file where being vague is the same as being wrong.**
            'PlatformMailUndeliverable' => [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => 'Rings the first time a QUEUED platform email is refused or is not '
                    .'accepted by the mailer, and then not again about that mailer for '
                    .app(OperatorAlerts::class)->mailPathRepeatHours().' hours. It has no threshold and no '
                    .'off switch: a mail transport that has stopped is stopped at three messages an '
                    .'hour exactly as much as at ten thousand, so there is no figure that would be '
                    .'honest here. Its own email is sent through the mailer it reports on, so on a '
                    .'broken mail path the text is the only channel that arrives. It cannot see a '
                    .'message the mailer accepted and then failed to deliver — no bounce or '
                    .'complaint signal reaches this platform — so quiet here is not proof that mail '
                    .'is landing. And it does not watch every email we send: it is raised inside the '
                    .'queued job, so a message sent straight out rather than queued is outside it '
                    .'entirely. Those senders report their own failures instead, each one to its own '
                    .'automation, so quiet on this row is not quiet on the platform.',
            ],
            // ⛔ **A ROW RATHER THAN AN EXEMPTION, ON `PlatformMailUndeliverable`'s
            // ARGUMENT ABOVE** (9700–9719). The exempt names are *"there is no
            // figure to set"* **and** *"this screen cannot tell you whether the
            // thing that raises it ran"*. Only the first is true here: there is
            // no figure, and the raiser is the queue's own `failed()` hook on the
            // base class every automation extends, called on every permanent
            // failure with nothing in front of it.
            //
            // ⛔ **THE SECOND SENTENCE IS THE ONE THAT COULD NOT BE LEARNED
            // ANYWHERE ELSE, AND IT IS THE REASON THIS BELL EXISTS.** The
            // automation runs screen has said *"Needs a look"* against a failed
            // run since it shipped, and that word covers **an attempt that
            // failed** — which the queue usually retries successfully. This rings
            // only where the retries are spent, and the two are indistinguishable
            // on that screen.
            //
            // ⛔ **AND THE THIRD IS THE COVERAGE CLAIM, WHICH IS THE EXPENSIVE
            // KIND** (9280, 8880–8899). It covers the automations that extend
            // that base class and **nothing else that this platform queues** —
            // the support mailbox poll, the dunning ladder, the site measurement
            // and the webhook ingests are all outside it, and a permanent
            // failure of one of those still reaches nothing but `failed_jobs`
            // and the spike counter above.
            //
            // ⛔ **THIS LIST NAMED THE PIXEL ARCHIVE AND STOPPED BEING TRUE
            // ABOUT IT ON 2026-08-26 (9843) — THE READING IS KEPT AND DATED**
            // (4368). `ArchivePixelBatchJob` now has a `failed()` hook of its
            // own and a row two entries down. ⚠️ **The claim is deliberately
            // still written as a list rather than as "everything else"**,
            // because the *shape* of the hazard is unchanged: a queued class
            // that extends nothing opts out of this bell silently, with no lint
            // red and nothing on a diff to see, and the next one to gain a hook
            // has to come and delete itself from here.
            'AutomationAbandoned' => [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => 'Rings the first time an automation runs out of retries and abandons a '
                    .'piece of work, and then not again about that automation for '
                    .AutopilotJob::ABANDONED_REPEAT_HOURS.' hours. It has no threshold and no off '
                    .'switch: work abandoned for one account costs that account the same at one '
                    .'dispatch an hour as at ten thousand, so there is no figure that would be '
                    .'honest here. "Needs a look" on the automation runs screen is a wider word '
                    .'than this — it also covers an attempt that was retried and then worked. This '
                    .'covers the automations only: a queued job that is not one of them reports to '
                    .'nothing but the failed-job count above, unless it has a row of its own here.',
            ],
            // ⛔ **A ROW RATHER THAN AN EXEMPTION, ON `PlatformMailUndeliverable`'s
            // AND `AutomationAbandoned`'s ARGUMENT ABOVE** (9840–9849). The
            // exempt names are *"there is no figure to set"* **and** *"this
            // screen cannot tell you whether the thing that raises it ran"*.
            // Only the first is true here: there is no figure, and the raiser is
            // `ArchivePixelBatchJob::failed()`, which the queue calls on every
            // permanent failure with nothing in front of it.
            //
            // ⛔ **THE SECOND SENTENCE IS THE ONE THAT COULD NOT BE LEARNED
            // ANYWHERE ELSE, AND IT IS WHY THIS BELL EXISTS AT ALL.** The
            // collector answers `204` the moment it has read the bytes, so by
            // the time the queue gives up there is nobody left to tell: the
            // browser has forgotten the request, nothing re-sends, and L0 is the
            // only durable copy this architecture keeps. **A ring here is a
            // report of data already destroyed, not of work waiting to be
            // retried** — which is the opposite of every other bell on this
            // screen.
            //
            // ⛔ **AND THE THIRD IS WHAT IT CANNOT SEE, WHICH IS THE EXPENSIVE
            // KIND** (9280). It rings about batches that reached the queue and
            // failed there. It says nothing about how many events were lost —
            // this platform counts admitted *events* per tenant per month and
            // counts beacons nowhere, so no figure here can be differenced
            // against a batch — and nothing about a batch the collector refused
            // before dispatch, which is `PixelIngestRejects` two rows up.
            //
            // ⚠️ **"COUNTS ACCEPTED BEACONS NOWHERE" IS WHAT THIS SAID UNTIL
            // 2026-08-26 AND IT WAS TRUE ABOUT BEACONS AND FALSE AS WRITTEN.**
            // `MonthlyEventCap::increment()` — which this very file already
            // reads, three rows up, for the event-cap bell — has upserted
            // `pixel_monthly_usage.events_total` per tenant on the collector's
            // own request since 2026-08-18. **The claim on the rendered row is
            // unchanged**, because a monthly per-tenant total taken upstream of
            // two further refusal gates still cannot be differenced against one
            // batch. See `OperatorAlertKind::PixelArchiveFailed` for what that
            // costs 9848.
            'PixelArchiveFailed' => [
                'watched' => true,
                'state' => SignalState::Ok,
                'status' => 'Watching',
                'detail' => 'Rings the first time a batch of website visitor events fails '
                    .'permanently on its way to the archive, and then not again about that '
                    .'archive for '.ArchivePixelBatchJob::ARCHIVE_REPEAT_HOURS.' hours. It has no '
                    .'threshold and no off switch: a store that is refusing writes is refusing '
                    .'them at one beacon an hour exactly as much as at ten thousand. The events '
                    .'are already gone when it rings — the website was answered "received" before '
                    .'the archive was attempted, and nothing re-sends them — so this reports a '
                    .'loss rather than work waiting. It cannot say how many events were lost, and '
                    .'it does not cover a batch refused before it was queued: that is the '
                    .'unlisted-website bell above.',
            ],
            // ⛔ **A ROW RATHER THAN AN EXEMPTION, ON `PlatformMailUndeliverable`'s
            // AND `AutomationAbandoned`'s ARGUMENT** (9945–9959). The exempt
            // names are *"there is no figure to set"* **and** *"this screen
            // cannot tell you whether the thing that raises it ran"*. Only the
            // first is true here: there is no figure, and the raiser is
            // `ScheduledRunMeter`, wired into the framework's own scheduler
            // events with nothing in front of it.
            //
            // ⛔ **ITS SIBLING ONE ROW UP IS ON THE EXEMPTION LIST AND THIS IS
            // NOT, WHICH IS THE ARGUMENT RATHER THAN AN INCONSISTENCY.**
            // `ScheduledRunOverranLock` is exempt because its threshold is real
            // and there are forty-four of them — every entry's own
            // `withoutOverlapping()` window, argued per command in
            // `routes/console.php` and none of them in the registry. **This one
            // has no threshold anywhere at all**, which is a different sentence
            // and a true one.
            //
            // ⛔ **THE FOURTH SENTENCE IS THE COVERAGE CLAIM AND IT IS THE
            // EXPENSIVE KIND** (9280). Every one of these is raised from a
            // scheduler event, so a scheduler that is not running dispatches
            // nothing and rings nothing — which is the one failure an operator
            // reading this screen is most likely to read the silence as ruling
            // out.
            // ⛔ **THE ROW IS THE METER'S OWN ANSWER RATHER THAN A LITERAL
            // HERE — MOVED 2026-08-26 (10020–10025), ON `HeartbeatCoverage`'s
            // PRECEDENT AND 7661's RULE.** It shipped hours earlier as a fixed
            // `Watching` row, which was the same over-claim lane C had just
            // found one arm up: **this bell has no threshold, so nothing on this
            // screen could ever have said it was unarmed**, and on an install
            // whose `schedule:run` cron line was never added it has never been
            // able to ring at all. A screen asks the class that applies the
            // figure; here there is no figure, so it asks for the state.
            'ScheduledRunFailed' => app(ScheduledRunMeter::class)->failedRunCoverage(),
        ];

        return array_map(
            static function (OperatorAlertKind $case) use ($thresholds): array {
                $threshold = $thresholds[$case->name] ?? null;

                if ($threshold === null) {
                    return [
                        'kind' => $case,
                        'watched' => true,
                        'state' => SignalState::Unknown,
                        'status' => 'Rings from the work it watches',
                        // ⚠️ MAKES NO NEGATIVE CLAIM, ON PURPOSE. Whether one of
                        // these can ring today is a fact about the code path it
                        // hangs off — a nightly sweep, a webhook, an erasure —
                        // and this screen cannot see it.
                        'detail' => self::NO_THRESHOLD_DETAIL,
                    ];
                }

                return ['kind' => $case] + $threshold;
            },
            OperatorAlertKind::cases(),
        );
    }

    /**
     * The queue the heartbeat rides, named when there is a name to give.
     *
     * ⚠️ **`null` IS NOT `'default'` AND MUST NOT BE PRINTED AS ONE.** A `sync`
     * connection declares no queue at all, and filling the gap with the string
     * `default` would put a specific, checkable, wrong queue name on the one
     * screen that says which bells are armed. The name-free phrase claims
     * exactly what is known: one queue, whichever this install dispatches to.
     */
    private static function heartbeatQueuePhrase(): string
    {
        $name = RecordQueueHeartbeat::queueWatched();

        return $name === null
            ? 'the queue this platform dispatches to'
            : 'the '.$name.' queue';
    }

    /**
     * A bell whose threshold has an off value, and a zero that is it.
     *
     * The shape 2409's convention describes and the four `PlatformHealthChecks`
     * counters keep — **and the three rows below this one deliberately do not**,
     * which is why they are written out rather than squeezed through here.
     *
     * @return array{watched: bool, state: SignalState, status: string, detail: string}
     */
    private static function switched(bool $on, string $onDetail, string $offDetail): array
    {
        return [
            'watched' => $on,
            'state' => $on ? SignalState::Ok : SignalState::Alert,
            'status' => $on ? 'Watching' : 'Not being checked',
            'detail' => $on ? $onDetail : $offDetail,
        ];
    }

    /**
     * The pixel canary's halt threshold — a bell with no off value.
     *
     * ⚠️ **NEVER `Not being checked`, BECAUSE IT CANNOT BE.** Whatever is in the
     * registry, {@see WatchPixelCanary::haltThresholdBp()} returns something the
     * sweep will act on; there is no figure that stops the automatic halt. The
     * state an operator needs to see instead is whether the figure is a
     * threshold or a trip.
     *
     * @return array{watched: bool, state: SignalState, status: string, detail: string}
     */
    private static function canaryBell(DefaultsRegistry $registry): array
    {
        $thresholdBp = WatchPixelCanary::haltThresholdBp($registry);
        $floor = WatchPixelCanary::sampleFloor($registry);

        if ($thresholdBp < WatchPixelCanary::MIN_REGRESSION_BP) {
            return [
                'watched' => true,
                'state' => SignalState::Attention,
                'status' => 'Watching, on a hair trigger',
                'detail' => 'Its threshold is 0, which is not an off switch here: a release is halted '
                    .'and rolled back when it is a single basis point noisier than the one it '
                    .'replaces. Nothing switches the automatic halt off.',
            ];
        }

        return [
            'watched' => true,
            'state' => SignalState::Ok,
            'status' => 'Watching',
            'detail' => "Halts a pixel release and rings when its JS-error rate is more than {$thresholdBp} "
                ."basis points worse than the version it replaces, once both have {$floor} pageviews. "
                .'There is no value that switches the automatic halt off.',
        ];
    }

    /**
     * Inbound call minutes — a bell whose zero is also a platform-wide brake.
     *
     * ⛔ **THE SENTENCE THIS ROW EXISTS FOR.** `ops.alert_quiet_minutes` tells an
     * operator to quieten one noisy check by setting its own threshold to zero.
     * Doing that here stops this platform downloading **any** voicemail
     * recording, for **every** account, and the missed call, the caller's number
     * and the text-back are all that survive it. That is a real control and
     * {@see VoiceSpend} argues for keeping it; what it is not is a mute, and this
     * is where an operator finds that out before typing rather than after.
     *
     * @return array{watched: bool, state: SignalState, status: string, detail: string}
     */
    private static function voiceBell(VoiceSpend $voice): array
    {
        $tenant = $voice->dailyTenantMinutesCeiling();
        $unattributed = $voice->dailyUnattributedMinutesCeiling();

        if ($tenant === 0) {
            return [
                'watched' => false,
                'state' => SignalState::Alert,
                'status' => 'Not being checked',
                'detail' => 'Its per-account ceiling is 0, which does more than quieten this bell: '
                    .'no voicemail recording is downloaded for any account while it stands. '
                    .'The bell goes with it, because at a ceiling of zero every call would cross it.',
            ];
        }

        if ($unattributed === 0) {
            return [
                'watched' => true,
                'state' => SignalState::Attention,
                'status' => 'Watching accounts only',
                'detail' => "Rings when one account's numbers take more than {$tenant} minutes of inbound "
                    .'calls in a day. The ceiling for numbers no account owns is 0, so calls to those — '
                    .'the ones nobody is billed for and nobody would notice — raise nothing.',
            ];
        }

        return [
            'watched' => true,
            'state' => SignalState::Ok,
            'status' => 'Watching',
            'detail' => "Rings when one account's numbers take more than {$tenant} minutes of inbound calls "
                ."in a day, or numbers no account owns take more than {$unattributed}. ⚠️ The per-account "
                .'figure is also a brake: past it, voicemail recordings stop being downloaded.',
        ];
    }

    /**
     * The free-tier monthly event cap — a bell whose zero means *uncapped*.
     *
     * ⚠️ **THE KEY IS READ AND NOTHING ELSE IS TOUCHED.** The cap itself and what
     * it admits belong to `Services\Pixel`; what this row states is the meaning
     * the registry entry publishes — *"zero or unset means uncapped, the opposite
     * direction from a dollar ceiling"* — which is the fact an operator following
     * the quiet-key's advice needs and could not get from this screen.
     *
     * @return array{watched: bool, state: SignalState, status: string, detail: string}
     */
    private static function eventCapBell(DefaultsRegistry $registry): array
    {
        $cap = max(0, $registry->int(MonthlyEventCap::CAP_KEY));

        return self::switched(
            $cap > 0,
            'Rings once an account has sent more than '.number_format($cap).' pixel events in a month '
                .'and a batch is refused for it.',
            'Its cap is 0, which means uncapped rather than off: no batch is ever refused for it, '
                .'so nothing can ring. Every event is accepted and kept.',
        );
    }

    /**
     * Which of the three channels a raised alert actually leaves by.
     *
     * ⛔ **WHETHER AN ADDRESS IS SET, NEVER THE ADDRESS.** The question this
     * answers is *"would anybody have been woken"*, and that is answered by `set`
     * or `not set`. `ops.alert_email` and `ops.alert_sms` hold the operator's own
     * address and mobile number; putting them on a page is stored contact detail
     * rendered for no reason, and `CLAUDE.md`'s second tiebreaker is *less stored
     * PII*. They are editable one screen away, where changing them is the point.
     *
     * ⚠️ **THE LOG IS LISTED AND IT IS NOT PADDING.** `OperatorAlerts::fire()`
     * writes `Log::critical` **before** either channel, deliberately, because it
     * needs no credential, no vendor and no network — so on a fresh install,
     * where both other rows read *not set*, it is the only place the alert went.
     *
     * @return list<array{label: string, state: SignalState, status: string, detail: string}>
     */
    public function channels(DefaultsRegistry $registry): array
    {
        $email = trim((string) $registry->stringOrNull(OperatorAlerts::EMAIL_KEY)) !== '';
        $sms = trim((string) $registry->stringOrNull(OperatorAlerts::SMS_KEY)) !== '';

        return [
            [
                'label' => 'Email',
                'state' => $email ? SignalState::Ok : SignalState::Attention,
                'status' => $email ? 'An address is set' : 'No address set',
                'detail' => $email
                    ? 'Alerts are emailed as well as recorded here.'
                    : 'Nothing is emailed. A blank address is the off switch, and it is set in Ops settings.',
            ],
            [
                'label' => 'Text message',
                'state' => $sms ? SignalState::Ok : SignalState::Attention,
                'status' => $sms ? 'A number is set' : 'No number set',
                'detail' => $sms
                    ? 'Alerts are texted as well as recorded here.'
                    : 'Nothing is texted. A blank number is the off switch, and it is set in Ops settings.',
            ],
            [
                'label' => 'Application log',
                'state' => SignalState::Ok,
                'status' => 'Always',
                'detail' => 'Every alert is written to the log at critical level before either message is attempted, so it survives both of those being unconfigured.',
            ],
        ];
    }

    /**
     * How long one kind and subject stays quiet after ringing.
     *
     * ⚠️ **CLAMPED THE SAME WAY THE SERVICE CLAMPS IT** — and since 2026-08-22
     * that means **asking the service** rather than repeating its arithmetic
     * (7661, on 7580-7599's work). `OperatorAlerts::quietMinutes()` now clamps
     * **both** ends, so a screen repeating `max(1, …)` would print a week-long
     * window the platform refuses to honour. ⛔ **The divergence lasted one
     * merge and errs in the direction that matters least** — the screen printed
     * the alarming number while the platform behaved as though the ceiling were
     * typed — but a second copy of a clamp is the thing that goes stale, which
     * is why this reads the service instead.
     *
     * ⛔ **AND IT IS STATED ONCE, AS THE RULE, RATHER THAN COMPUTED PER ROW.** A
     * *"quiet until 03:47"* stamp on every alert would be this screen deciding
     * for itself when the next bell may ring, which is the one thing that has to
     * keep living in `OperatorAlerts::alreadyRang()`. The rule is rendered; the
     * decision is not re-derived.
     */
    public function quietMinutes(OperatorAlerts $alerts): int
    {
        return $alerts->quietMinutes();
    }

    public function render(DefaultsRegistry $registry): View
    {
        $this->authorize(AdminAccess::GATE);

        $ringing = $this->ringing();

        return view('livewire.admin.operator-alert-board', [
            'ringing' => $ringing,
            // Null on every ordinary night, and one grouped read on the night it
            // is not — {@see self::spread()} for why the page's own count is
            // what decides.
            'spread' => $this->spread($ringing->count()),
            // ⛔ **NOT GATED ON THE PAGE FILLING, WHICH IS THE WHOLE OF
            // 8120–8139.** The summary above answers a question about breadth
            // and returns null without querying on an ordinary night; this
            // answers one about depth, has no cheap proxy, and gating it the
            // same way is the defect it was built to close.
            'withheld' => $this->withheld(),
            'ringingLines' => self::RINGING_LINES,
            'alerts' => $this->alerts(),
            'kinds' => OperatorAlertKind::cases(),
            'bells' => $this->bells($registry),
            'channels' => $this->channels($registry),
            'quietMinutes' => $this->quietMinutes(app(OperatorAlerts::class)),
            'ringingHours' => self::RINGING_HOURS,
            // The constant itself, never a copy of the number. A screen carrying
            // its own idea of how long rows last is a screen that goes on saying
            // "a year" after somebody shortens the horizon.
            'retentionDays' => app(OperatorAlerts::class)->retentionDays(),
        ]);
    }
}
