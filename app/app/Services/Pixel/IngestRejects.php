<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Enums\OperatorAlertKind;
use App\Enums\PixelRefusal;
use App\Models\IngestReject;
use App\Services\Config\DefaultsRegistry;
use App\Services\Ops\OperatorAlerts;
use App\Support\PixelRateLimits;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The one writer of `ingest_rejects` — §11 row 2 and §5.7, decision 5000s.
 *
 * See the creating migration for why the table is an hourly rollup rather than
 * §5.7's row per rejected request, and why it is narrower than §5.7's own
 * reason vocabulary. `PixelCollector` calls {@see self::record()} from exactly
 * one place: the origin-allowlist gate.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ THE WRITE AND THE READ ARE BOTH HERE, ON PURPOSE
 * ---------------------------------------------------------------------------
 * `CLAUDE.md`'s most-repeated defect is a table with no writer, and its
 * clarification is the sharper half: *"a row is not a suppression until
 * something reads it."* §5.7 asks for rejects to be *"queryable, countable,
 * and **alertable**"*, so this class reads the tenant's rolling 24-hour total
 * back after incrementing a bucket and puts it in an operator alert. ⛔ **WITHOUT THAT READ
 * THIS TABLE WOULD BE A COUNTER NOBODY LOOKS AT** — `sending_health_windows`'
 * exact shape — because nothing in this application has a screen over it, and
 * decision 5000s does not pretend otherwise.
 *
 * ⛔ **THE LAST CLAUSE STOPPED BEING TRUE ON 2026-08-22 AND IS CORRECTED HERE
 * RATHER THAN LEFT STANDING — BOTH READINGS KEPT AND DATED** (7800-7803).
 * {@see self::refusedOrigins()} is a second reader and it answers **the
 * tenant**, on `Account\PixelInstall` — because the diagnosis this table holds
 * was never the operator's to act on: `OperatorAlertKind::PixelIngestRejects`
 * says so in its own comment, *"a tenant's unlisted website needs that
 * tenant"*. ⚠️ **7634(a) IS ONLY HALF CLOSED AND THE OTHER HALF IS THE HARD
 * ONE**: there is still no **Ops** screen over this table and there cannot
 * cheaply be one, because no admin screen in this codebase renders a
 * cross-tenant list from a row-level secured table (569). The account side is
 * buildable for the opposite reason — row-level security scopes it to the
 * reader without a `where` being written.
 *
 * ⚠️ **THE "ONCE" IS `OperatorAlerts`' OWN QUIET WINDOW**, on
 * `MonthlyEventCap`'s precedent, so this class needs no second dedup
 * mechanism.
 *
 * ⛔ **AND THE SENTENCE THAT FOLLOWED THAT ONE WAS TRUE OF THE ACCIDENTAL CASE
 * AND FALSE OF THE ADVERSARIAL ONE IT NAMES THREE PARAGRAPHS EARLIER —
 * CORRECTED 2026-08-22 (7708–7712).** It read: *"That costs one indexed
 * `SELECT` per rejected request on top of the upsert — accepted deliberately:
 * the path is already a refusal, and the volume at which that cost matters is
 * precisely the incident the bell is for."*
 *
 * ⛔ **{@see self::recentRejects()} IS AN AGGREGATE, NOT A LOOKUP, AND ITS INPUT
 * WAS THE CALLER'S TO GROW.** It sums every bucket in the tenant's rolling day,
 * and a bucket is `(business_id, reason, origin, hour)` with `origin` a header
 * the caller writes — so a caller varying it per request added a row per
 * request **and made its own next request more expensive**. On an
 * unauthenticated public endpoint at 300 requests a minute per source, that is
 * O(N) work on request N: 432,000 rows scanned per beacon by the end of a day,
 * and the whole of it computed **as an eager argument to
 * `OperatorAlerts::raise()`**, so it ran whether or not the quiet window let the
 * bell land.
 *
 * ✅ **WHAT FIXES IT IS BOUNDING THE INPUT RATHER THAN SKIPPING THE READ** —
 * see {@see self::ORIGINS_PER_HOUR}. Deferring the computation until the bell is
 * known to land would need a lazy `$context`, which is `OperatorAlerts`' to
 * offer and not this class's to fake; and a private copy of that class's dedupe
 * predicate here would be a second copy of a rule nothing compares, which is the
 * failure `OperatorAlerts::quietSince()` exists to prevent. **A bounded
 * aggregate is cheap however often it runs**, and it closes 7634(b) in the same
 * stroke: the table's within-window growth was the other half of the same
 * unbounded number.
 *
 * ⚠️ **CALLED AFTER `Tenancy::set()`, NEVER BEFORE.** `Tenancy::idOrFail()`
 * throws rather than writing a `null` business id, which is the same
 * discipline `MonthlyEventCap` and `SendingHealth::increment()` both use for a
 * raw write into a FORCE row-level security table.
 */
final class IngestRejects
{
    /**
     * How long a reject bucket is kept — ninety days (7620–7639).
     *
     * ⛔ **THIS TABLE HAD NO PRUNER AT ALL AND ITS OWN CREATING MIGRATION SAID
     * SO**, twice, as an argument for the rollup shape: *"nothing in this
     * application prunes this table"*. The rollup bounds the **accidental** case
     * absolutely — a mis-installed tenant (4968) writes twenty-four rows a day
     * whatever their traffic — and the migration is equally plain that it bounds
     * the adversarial one not at all: *"`origin` is a header the client writes,
     * so a caller varying it per request still writes a row per request. That is
     * bounded by `PixelRateLimits` per source and by nothing else here."*
     * {@see PixelRateLimits::BEACONS_PER_MINUTE} is **300**, so one
     * source with a tenant's public key could write **432,000 rows a day**, each
     * carrying up to 512 bytes of text it chose. Permanence is the half a
     * horizon removes.
     *
     * ⛔ **THE PARAGRAPH ABOVE IS THE 2026-08-22 READING AND ITS LAST FIGURE IS
     * NO LONGER REACHABLE — BOTH KEPT AND DATED** (7710). The other half is
     * closed too: {@see self::ORIGINS_PER_HOUR} bounds a tenant's distinct
     * origins per hour, so the adversarial case now writes **about 1,224 rows a
     * day** rather than 432,000, and the migration's *"bounded by
     * `PixelRateLimits` per source and by nothing else here"* is history rather
     * than a description. ⚠️ **The horizon's argument is untouched by that** and
     * is why this constant stays: a bounded growth rate is still growth, the
     * support question below is still the reader, and ninety days is still the
     * answer to it.
     *
     * ## ⛔ THE FLOOR IS ONE DAY, IT IS DERIVED, AND IT IS ENFORCED BELOW RATHER
     * THAN BY THIS NUMBER
     *
     * {@see self::recentRejects()} sums {@see self::RECENT_WINDOW_HOURS} of
     * buckets and puts the total in every operator alert this class raises. That
     * figure is the whole reason this table is more than a counter nobody looks
     * at, so a cut inside that window would delete the rows the bell is standing
     * on. ⚠️ **The clamp in {@see self::prune()} is what guarantees it**, not
     * this constant — `OperatorAlerts::prune()`'s quiet-window rule exactly: a
     * property of the method rather than a range the setting is trusted to stay
     * inside.
     *
     * ## Ninety is a choice above that floor and is written as one
     *
     * ⚠️ **THE ONLY OTHER READER IS A PERSON.** Nothing in `app/` reads this
     * table beyond the rolling day above, and no screen renders it. What opens
     * it is somebody answering 4968's ticket — *"my pixel is not working"*, the
     * most likely real complaint this pipeline produces — and the question they
     * ask that no other artefact answers is **when it started**. §5.7's *"you
     * want to know that day"* is served by the alert; ninety days is for the
     * ticket that arrives a season after the install.
     *
     * ⛔ **"NO SCREEN RENDERS IT" IS THE MORNING'S READING AND WAS FALSE BY THE
     * AFTERNOON — BOTH KEPT AND DATED** (7802). The tenant's own install screen
     * renders {@see self::TENANT_WINDOW_HOURS} of it.
     * ⚠️ **AND THAT MAKES THIS HORIZON MATTER MORE RATHER THAN LESS**: the
     * ticket above is now often answered before it is raised, and what is left
     * for the ninety days is precisely what the seven cannot reach — the
     * conversation about an install that broke a season ago. ⚠️ **The floor
     * argument above is untouched and is now the smaller of two**: the clamp in
     * {@see self::prune()} protects the *bell's* twenty-four hours and knows
     * nothing about the tenant's seven days, so a horizon shortened to a week
     * would quietly empty a screen while the clamp reported itself satisfied.
     * `Feature/Pixel/PixelCollectionsTest` pins the relationship, because the
     * clamp cannot.
     *
     * ⛔ **DELIBERATELY A QUARTER OF `OperatorAlerts::RETENTION_DAYS`, AND THE
     * ASYMMETRY IS THE ARGUMENT RATHER THAN AN INCONSISTENCY.** That table holds
     * the *same* stranger-written string in `context.origin` and keeps it a
     * year. Its growth is one row per `(kind, subject)` per quiet window —
     * proportional to the customer base. This table's is proportional to
     * **requests**. Same field, two growth curves, two horizons; harmonising
     * them would mean either paying a year of row-per-request growth or
     * shortening the record an incident review reads.
     *
     * ⚠️ **AND IT IS WHY A YEAR HERE WOULD BUY NOTHING**: the alert already
     * keeps that string for a year, so no horizon on this table shortens what
     * this platform holds. **This number is a volume bound, not a privacy one**,
     * and writing it up as privacy would be a claim the next table over
     * disproves.
     *
     * ⚠️ **THIRTY WAS CONSIDERED AND REFUSED** on the support question above.
     * `PruneTrialOriginClaims::RETENTION_DAYS` refused to copy `public_audits`'
     * ninety in the other direction — *"this one covers a live account's fraud
     * signal"* — and neither of its reasons reaches here: nothing on this row is
     * a signal about a customer, and nobody's allowance depends on it.
     */
    public const int RETENTION_DAYS = 90;

    /**
     * The rolling window {@see self::recentRejects()} sums, and therefore the
     * floor {@see self::prune()} may never cut inside.
     *
     * ⚠️ **A CONSTANT RATHER THAN THE `subDay()` IT WAS.** The reader and the
     * pruner have to agree on this number or the horizon silently eats the
     * figure the bell carries, and one literal in each method is the shape that
     * drifts — the reader's window would move and the pruner's floor would not.
     */
    public const int RECENT_WINDOW_HOURS = 24;

    /**
     * The window {@see self::refusedOrigins()} reports to the tenant whose
     * traffic it is — seven days (7802).
     *
     * ⛔ **DELIBERATELY LONGER THAN {@see self::RECENT_WINDOW_HOURS} AND THE
     * TWO ANSWER DIFFERENT QUESTIONS.** The bell's rolling day answers *"is this
     * a spike, today?"* for somebody who is paged. This answers *"why is my
     * dashboard empty?"* for somebody who pasted a line last week, went back to
     * running a restaurant, and is looking for the first time — the exact person
     * 4968 predicted would report *"the pixel is not working"*. A day-long
     * window would show them nothing on a Monday about the Friday they
     * installed it.
     *
     * ⚠️ **AND IT MAY NOT EXCEED {@see self::RETENTION_DAYS}**, which is ninety
     * — a reader whose window reaches past the horizon reports a total that
     * quietly stops growing. Seven is well inside it, and
     * `IngestRejectsTest` pins the relationship rather than leaving it to
     * arithmetic somebody has to redo.
     *
     * ⚠️ **NOT A REGISTRY KEY.** `WidgetInstalls::STALE_AFTER_HOURS`' reasoning
     * verbatim (3092): it is not a tenant's to set, and a key implies an Ops
     * screen and a change history for a number nobody has asked to move.
     */
    public const int TENANT_WINDOW_HOURS = 168;

    /**
     * The most distinct origins one tenant may occupy in one hour before the
     * rest are folded into {@see self::OVERFLOW_ORIGIN} — 7634(b), built
     * (7708–7712).
     *
     * ⛔ **THE CREATING MIGRATION NAMED THIS HOLE AND WAVE 11 LEFT IT OPEN ON
     * PURPOSE**: *"`origin` is a header the client writes, so a caller varying
     * it per request still writes a row per request. That is bounded by
     * `PixelRateLimits` per source and by nothing else here."* 7634(b) put the
     * consequence in figures — 432,000 rows a day from one host, **39 million
     * before the first one expires** under the ninety-day horizon — and named a
     * per-hour distinct-origin ceiling as the mechanism, deferring it because
     * *"it changes what the table records, which is a different decision from
     * how long it is kept"*. That is the right distinction and this is that
     * decision, made because the same unbounded number turned out to be the
     * cost of {@see self::recentRejects()} as well as the size of the table.
     *
     * ⚠️ **FIFTY IS AN ENGINEERING RAIL AND IS MINE TO SET**, on
     * `PruneTrialOriginClaims::RETENTION_DAYS`' precedent: 502's
     * withhold-rather-than-default posture is about **prices the owner owns**,
     * and this is a shape constraint on a diagnostic table. The floor is what
     * the accidental case needs — 4968's mis-installed tenant emits **one**
     * origin, and a tenant running a handful of sites they never listed emits a
     * handful — so any figure in the tens preserves every real diagnosis. Fifty
     * leaves an order of magnitude of headroom above the worst honest case and
     * still bounds the rolling-day aggregate at roughly 1,200 rows.
     *
     * ⛔ **WHAT IS LOST, SAID PLAINLY** (352, 397). Past the ceiling, the *name*
     * of the fifty-first distinct origin in that hour is not recorded — only
     * that it was one of many. **That is exactly the case in which the name was
     * worthless**: a caller minting a fresh origin per request is not telling
     * anybody where their pixel is installed. The reject **count** is unaffected
     * and still exact, because the overflow bucket is incremented like any
     * other.
     */
    public const int ORIGINS_PER_HOUR = 50;

    /**
     * The bucket every origin past {@see self::ORIGINS_PER_HOUR} is counted in.
     *
     * ⚠️ **IT CANNOT COLLIDE WITH A BROWSER'S `Origin`, WHICH IS A SERIALISED
     * ORIGIN OR THE STRING `null` AND NEVER THIS.** A non-browser caller can of
     * course send it deliberately, and the consequence is that its rejects are
     * counted in the overflow bucket — it cannot evict a real origin's row, take
     * another tenant's slot, or make the count wrong. ⛔ **`null` IS NOT THIS
     * AND MUST NEVER BECOME IT**: 5002 makes `null` mean *"the caller sent no
     * `Origin` header at all"*, which is one bucket an hour that cannot grow and
     * is a genuinely different diagnosis.
     */
    public const string OVERFLOW_ORIGIN = '(other)';

    /**
     * Rows per DELETE — `PrunePublicAudits`' figure and its reasoning, which
     * applies here harder than it does there: the adversarial case above makes
     * this the one table in the pixel schema that can genuinely be large, and a
     * single unbounded DELETE would hold locks on a table the public ingest path
     * writes to on every refused beacon.
     */
    private const int CHUNK = 500;

    public function __construct(
        private readonly OperatorAlerts $alerts,
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    public function retentionDays(): int
    {
        return $this->registry->int('pixel.rejects.retention_days');
    }

    public function recentWindowHours(): int
    {
        return $this->registry->int('pixel.rejects.recent_window_hours');
    }

    public function tenantWindowHours(): int
    {
        return $this->registry->int('pixel.rejects.tenant_window_hours');
    }

    public function originsPerHour(): int
    {
        return $this->registry->int('pixel.rejects.origins_per_hour');
    }

    /**
     * Record one origin-mismatch reject into its hourly bucket, and ring the
     * operator bell with the tenant's rolling 24-hour reject total.
     *
     * ⚠️ **`$origin` IS A HOSTNAME A BROWSER SENT, NOT PERSONAL DATA ABOUT THE
     * VISITOR BEHIND IT.** See the creating migration for why it is the one
     * extra field this table carries beyond the reason, the count and the
     * tenant — and why `null`, meaning "the caller sent no `Origin` header at
     * all", is kept distinct from a named-but-unlisted site.
     */
    public function record(PixelRefusal $reason, ?string $origin, ?CarbonImmutable $at = null): void
    {
        $businessId = Tenancy::idOrFail();
        $at ??= CarbonImmutable::now();
        $hour = $at->utc()->startOfHour();
        $origin = $origin === null ? null : $this->bucketed(mb_substr($origin, 0, 512), $reason, $hour);

        $this->increment($businessId, $reason, $origin, $hour);

        $this->alerts->raise(
            OperatorAlertKind::PixelIngestRejects,
            subject: (string) $businessId,
            summary: "A tenant's pixel traffic is being refused because it comes from a website their account does not list.",
            context: [
                'business_id' => $businessId,
                'reason' => $reason->value,
                'origin' => $origin,
                'rejects_last_24h' => $this->recentRejects($hour),
            ],
        );
    }

    /**
     * Every website this tenant's pixel traffic was refused from, inside
     * {@see self::TENANT_WINDOW_HOURS} — the read that puts §5.7's *"queryable"*
     * in front of the person who can act on it (7802).
     *
     * ⛔ **THE ONLY OTHER READER WAS AN OPERATOR AND THE DIAGNOSIS WAS NEVER
     * THEIRS TO ACT ON.** `OperatorAlertKind::PixelIngestRejects` reads *"a
     * tenant's website is not listed, so their pixel is collecting nothing"*
     * and rings `Attention` with the comment *"a tenant's unlisted website needs
     * that tenant"*. This is that same fact, answered for the tenant, and it is
     * only buildable here because `ingest_rejects` is `ENABLE`+`FORCE` row-level
     * secured on `app.business_id`: an account-side reader is scoped by the
     * acting tenant's own session, which is the exact opposite of the problem an
     * Ops screen over this table would have (7634(a), still open for Ops).
     *
     * ⛔ **FILTERED TO {@see PixelRefusal::OriginNotAllowed} EXPLICITLY, EVEN
     * THOUGH {@see self::record()} WRITES NOTHING ELSE TODAY.** The screen above
     * this says, in words, *"turned away because the website was not on your
     * list"*, and that sentence has to stay true on the day a second refusal
     * gains a row here. A read of every reason under copy naming one is 256's
     * vacuity with the sign flipped: the lint would still pass and the sentence
     * would be a lie about somebody's data.
     *
     * ⚠️ **AGGREGATED IN SQL AND ORDERED NOWHERE.** `ConventionsTest` fails the
     * build on a descending sort over anything but `id`, and the ordering a
     * screen wants here is by count rather than by any column — so the grouping
     * is the database's and the ordering is {@see PixelCollections}'.
     *
     * ⚠️ **THE ROW COUNT THIS SCANS IS BOUNDED AND THE BOUND IS NOT OBVIOUS.**
     * {@see self::ORIGINS_PER_HOUR} caps a tenant's distinct origins at fifty an
     * hour (7710), so the worst case inside this window is roughly 8,600 buckets
     * and the ordinary case — 4968's mis-installed tenant, one website they
     * never listed — is one row an hour. Without that ceiling this read would
     * have been the second O(N) aggregate on the same table that 7708 removed.
     *
     * @return list<RefusedOrigin>
     */
    public function refusedOrigins(?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $since = $now->utc()->startOfHour()->subHours($this->tenantWindowHours());

        $rows = IngestReject::query()
            ->where('reason', PixelRefusal::OriginNotAllowed->value)
            ->where('hour', '>=', $since)
            ->groupBy('origin')
            ->selectRaw('origin, sum(rejects) as rejects, max(updated_at) as last_at')
            ->get();

        $origins = [];

        foreach ($rows as $row) {
            $origin = $row->getAttribute('origin');
            $lastAt = $row->getAttribute('last_at');

            $origins[] = new RefusedOrigin(
                is_string($origin) ? $origin : null,
                (int) $row->getAttribute('rejects'),
                CarbonImmutable::parse(is_string($lastAt) ? $lastAt : $now->toDateTimeString()),
            );
        }

        return $origins;
    }

    /**
     * Delete this tenant's reject buckets past the horizon.
     *
     * ⛔ **PER TENANT, AND THAT IS NOT A STYLE CHOICE.** `ingest_rejects` is
     * `ENABLE`+`FORCE` row-level secured on `app.business_id`, so a single range
     * DELETE from a console command with no tenant set matches **zero rows and
     * succeeds** — the silent failure this codebase has recorded twice, and the
     * one a pruner is least able to notice, because "deleted nothing" is also
     * what a healthy day looks like. The enumeration lives in
     * `App\Console\Commands\PruneIngestRejects` — the seventh owner walk — and
     * this method is only ever reached inside `Tenancy::actingAs()`.
     *
     * ## ⛔ THE CUT IS THE EARLIER OF TWO MOMENTS, NEVER THE HORIZON ALONE
     *
     * `OperatorAlerts::prune()`'s clamp, for the same class of reason and a
     * different reader. Every alert this class raises carries
     * {@see self::recentRejects()}'s rolling total, and that figure — not the
     * row — is what makes §5.7's *"alertable"* true. A horizon applied blind
     * would delete the buckets that total is summing, and the failure would be
     * invisible: the bell still rings, carrying a number that has quietly
     * stopped counting the same thing. **So no row inside
     * {@see self::RECENT_WINDOW_HOURS} is reachable from here, whatever
     * `$keepDays` says.**
     *
     * ⚠️ **THE CLAMP IS ARITHMETIC ON TWO CONSTANTS AND CANNOT FAIL, WHICH IS
     * WHERE THIS DEPARTS FROM ITS PRECEDENT AND WHY.** `OperatorAlerts::prune()`
     * puts its floor calculation *inside* the `try` because that floor is a
     * registry read that can throw, and falling back to the horizon alone would
     * delete the row the pager stands on. There is no registry key here — the
     * window is `RECENT_WINDOW_HOURS` and the horizon is a constant — so the
     * `try` below wraps the delete rather than a read. **Stating that rather
     * than copying the shape silently**: a future registry key for either value
     * belongs inside the `try` on the precedent's argument exactly.
     *
     * ## ⛔ CONTAINED AND LOGGED, AND THE CONTAINMENT IS NOT ORDERING
     *
     * A failed prune returns `0` and warns. The precedent reached this by way of
     * a defect — it prunes the very table the alert is written to, so an
     * uncaught throw turned *"the alert is lost"* into *"the sweep exits
     * non-zero"*. ⚠️ **The same reasoning arrives here in a different shape.**
     * This is a walk: an unreachable row for tenant seventeen must not stop
     * tenants eighteen onward being swept, and a command that reddens on one
     * tenant's lock contention is one whose red is ignored inside a week
     * (`ExecuteTenantDeletions`' rule). ⛔ **`0` is therefore the one number this
     * method cannot tell apart from a quiet tenant**, which is what the log line
     * is for and is stated rather than hidden — a prune failing silently for a
     * year is `sending_health_windows` with a DELETE in it.
     *
     * ⚠️ **CHUNKED, AND THIS IS THE TABLE WHERE THAT MATTERS.** See
     * {@see self::CHUNK}.
     *
     * ⚠️ **ONE DELETE PATH ALREADY EXISTED AND IS NOT CODE**: `business_id` is
     * `constrained()->cascadeOnDelete()`, so erasing a tenant carries its
     * buckets out with it. That is the only reason this table has never been
     * unbounded in the *number of tenants*; it has always been unbounded in
     * time, which is what this closes.
     *
     * @param  int  $keepDays  {@see self::RETENTION_DAYS}, passed by the caller
     *                         rather than read here, on `OperatorAlerts::prune()`'s
     *                         shape.
     * @return int rows removed — `0` where the delete could not run at all
     */
    public function prune(int $keepDays, ?CarbonImmutable $now = null): int
    {
        $now ??= CarbonImmutable::now();

        try {
            $horizon = $now->utc()->subDays(max(1, $keepDays));

            // The oldest bucket `recentRejects()` still sums. Derived from the
            // reader's own window and its own truncation, so the two cannot
            // disagree about where the boundary is.
            $reader = $now->utc()->startOfHour()->subHours($this->recentWindowHours());

            $cut = $horizon->lessThan($reader) ? $horizon : $reader;

            $deleted = 0;

            do {
                // Through the model, so the global scope adds `business_id` and
                // row-level security sits under it — the two layers `CLAUDE.md`
                // insists are not a substitute for each other. Postgres compiles
                // a limited DELETE to `where ctid in (select …)`, and the inner
                // select carries the scope's predicate.
                $batch = IngestReject::query()
                    ->where('hour', '<', $cut)
                    ->limit(self::CHUNK)
                    ->delete();

                $deleted += $batch;
            } while ($batch === self::CHUNK);

            return $deleted;
        } catch (Throwable $e) {
            Log::warning('ingest rejects could not be pruned', [
                'business_id' => Tenancy::id(),
                'keep_days' => $keepDays,
                'exception' => $e::class,
            ]);

            return 0;
        }
    }

    /**
     * The atomic increment. `SendingHealth::increment()`'s own pattern:
     * Eloquent has no upsert-with-increment, so this is a raw upsert with the
     * addition done in SQL, which is correct under any concurrency because the
     * database does the arithmetic rather than reading a value in PHP and
     * writing it back.
     *
     * ⚠️ **`excluded.rejects` RATHER THAN A LITERAL `+ 1`** so the expression
     * stays a constant string and the number reaches Postgres bound, on
     * `MonthlyEventCap::increment()`'s reasoning. The conflict target is the
     * `NULLS NOT DISTINCT` unique index the migration creates by hand — a
     * `null` origin has to collide with a `null` origin, or the rollup rolls
     * nothing up for the non-browser caller.
     */
    private function increment(int $businessId, PixelRefusal $reason, ?string $origin, CarbonImmutable $hour): void
    {
        $now = CarbonImmutable::now();

        DB::table('ingest_rejects')->upsert(
            [[
                'business_id' => $businessId,
                'reason' => $reason->value,
                'origin' => $origin,
                'hour' => $hour,
                'rejects' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['business_id', 'reason', 'origin', 'hour'],
            [
                'rejects' => DB::raw('ingest_rejects.rejects + excluded.rejects'),
                'updated_at' => $now,
            ],
        );
    }

    /**
     * The bucket this origin is counted in — itself, or the overflow one.
     *
     * ⛔ **THE CEILING IS PER `(tenant, reason, hour)` AND IS CHECKED AGAINST
     * THE ROWS THAT ALREADY EXIST**, so it is self-limiting: once the ceiling is
     * reached the count below scans at most {@see self::ORIGINS_PER_HOUR} rows
     * plus the overflow one, whatever the caller does. That is the property the
     * whole change is for — see the class docblock — and it is why the count is
     * on `hour` rather than on a rolling window, which would grow again.
     *
     * ⚠️ **AN ORIGIN THAT ALREADY HAS A BUCKET KEEPS IT, EVEN PAST THE
     * CEILING.** Without that second check a tenant's one genuine origin would
     * start landing in the overflow bucket the moment a flood filled the hour,
     * and the row an operator actually needs would stop moving. It costs one
     * more indexed read and only ever runs on the arm that is already at the
     * ceiling, which is the arm where something is wrong.
     *
     * ⚠️ **READ-THEN-WRITE, SO CONCURRENT REQUESTS CAN OVERSHOOT BY AS MANY
     * ROWS AS THERE ARE REQUESTS IN FLIGHT, AND THAT IS FINE.** This is a
     * volume rail rather than a correctness invariant — a locked count on the
     * public ingest path would trade an unbounded read for a contended write,
     * which is the wrong direction on the endpoint this whole change is about.
     * Fifty-three buckets in an hour instead of fifty costs nothing; the
     * property that matters is that the number cannot grow with the caller's
     * request count.
     *
     * ⚠️ **READ THROUGH THE MODEL, SO THE GLOBAL SCOPE AND ROW-LEVEL SECURITY
     * BOTH APPLY** — {@see self::recentRejects()}'s rule, for its reason: a raw
     * `DB::table()` count here would be a cross-tenant read of how many distinct
     * origins the whole platform saw this hour.
     */
    private function bucketed(string $origin, PixelRefusal $reason, CarbonImmutable $hour): string
    {
        // ⚠️ **NO `limit()` ON THIS COUNT, AND ITS ABSENCE IS DELIBERATE.**
        // Laravel moves a limit onto the aggregate's own result set rather than
        // onto the rows it scans, so `->limit(50)->count()` reads as a bound and
        // is not one. **The bound is the ceiling itself**: once it is reached
        // this predicate matches at most `ORIGINS_PER_HOUR` rows plus the
        // overflow bucket, for ever, whatever the caller sends.
        $occupied = IngestReject::query()
            ->where('reason', $reason->value)
            ->where('hour', $hour)
            ->count();

        if ($occupied < $this->originsPerHour()) {
            return $origin;
        }

        $known = IngestReject::query()
            ->where('reason', $reason->value)
            ->where('hour', $hour)
            ->where('origin', $origin)
            ->exists();

        return $known ? $origin : self::OVERFLOW_ORIGIN;
    }

    /**
     * How many batches this tenant has had refused in the last 24 hours — the
     * number §5.7's *"a spike in rejects"* is actually about, read back through
     * the tenant-scoped model rather than the raw table so the global scope and
     * row-level security both apply to the read.
     *
     * ⛔ **A ROLLING DAY RATHER THAN THIS BUCKET, AND THE QUIET WINDOW IS WHY**
     * — a defect found by the test that asserts this figure, not by reading the
     * code. `OperatorAlerts` de-duplicates per `(kind, subject)` for
     * `ops.alert_quiet_minutes`, so **the only bell that ever lands is the
     * first one**, and the first reject in a bucket is by definition reject
     * number one. Reporting the bucket meant every alert this class will ever
     * raise would carry `1` — a read-back that is technically a read and tells
     * an operator nothing, which is `sending_health_windows`' permanently-zero
     * rate with a different constant. A rolling day is stable across the hour
     * boundary and grows between bells, so each recurrence carries a figure
     * that has actually moved.
     */
    private function recentRejects(CarbonImmutable $hour): int
    {
        return (int) IngestReject::query()
            ->where('hour', '>=', $hour->subHours($this->recentWindowHours()))
            ->sum('rejects');
    }
}
