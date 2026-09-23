<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Console\Commands\WarehouseReplay;
use App\Contracts\L0Archive;
use App\Enums\ConversionType;
use App\Enums\OperatorAlertKind;
use App\Enums\WebVital;
use App\Jobs\ArchivePixelBatchJob;
use App\Models\Business;
use App\Models\EtlRun;
use App\Services\Config\DefaultsRegistry;
use App\Services\Pixel\PixelCollector;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §5.8's replay job, and §11 row 7's gate.
 *
 * *"replay --tenant=… --from=… --to=… --layers=L1,L2 → read L0 objects for range
 * → idempotent upsert into L1 → rebuild affected L2 partitions"*, with the
 * acceptance criterion **"Truncate L1/L2, rebuild from L0, byte-identical
 * results"**.
 *
 * ⚠️ **"TRUNCATE" IS SCOPED TO A TENANT AND A RANGE, NOT TO THE TABLE.** A
 * literal `TRUNCATE l1_events` would erase every other tenant's rows to rebuild
 * one tenant's week — and row-level security would not stop it, because
 * `TRUNCATE` is a DDL statement that RLS policies do not apply to. That is worth
 * saying out loud: this is one of the few places in this codebase where the
 * second isolation layer genuinely does not cover the first one's mistake, so
 * the delete is a `DELETE … WHERE business_id = ?` and the whole job runs inside
 * `Tenancy::actingAs()`.
 *
 * ⛔ **THE ORDER IS DELETE-THEN-INSERT AND IT IS ONE TRANSACTION.** §5.8 says
 * "idempotent upsert", which `ReplacingMergeTree` gives ClickHouse for free and
 * Postgres does not. An upsert alone is not enough here: it would leave behind
 * rows derived from an L0 object that has since been superseded, so the rebuild
 * would be the union of two derivations rather than a rebuild. Deleting the
 * range first is what makes the result a function of L0 and nothing else. The
 * `ON CONFLICT DO NOTHING` beside it is the *other* idempotency — two L0 objects
 * carrying the same `event_id`, which is what a retried beacon produces.
 *
 * ⚠️ **AND THE `etl_runs` ROW IS INSIDE THAT TRANSACTION SINCE 8363, WHICH IT
 * WAS NOT BEFORE.** It used to be written after the commit, with the snapshot
 * digest — the largest allocation on the whole path — computed as one of its
 * arguments, so a run that died there left **the marts rebuilt and committed
 * with nothing recording that they were**, on exactly the runs large enough to
 * die. A rebuild with no record cannot be told from a replay that never ran, on
 * a warehouse that has already moved. Either both land or neither does.
 *
 * ⚠️ **NOTHING HERE READS A CLOCK EXCEPT `etl_runs`.** See [[L1Derivation]] and
 * decision 4862.
 *
 * ## ⛔ A REPLAY IS NOT A FUNCTION OF ITS RANGE'S L0 ALONE, AND THAT IS WHY THE
 * ROLL-UP §18 ASKS FOR CANNOT BE SCHEDULED (7840–7859, 2026-08-22)
 *
 * Two derivations below reach outside the range they are given, so the same L0
 * yields different marts depending on **how the range was cut** and **when the
 * replay was run**. Neither is caught by `ByteIdenticalReplayTest`, which
 * replays one range, whole, against an L1 nothing has pruned;
 * `tests/Feature/Warehouse/IncrementalRollUpTest.php` drives both directly on
 * the same fixture.
 *
 * ⛔ **"TWO" WAS THE 2026-08-22 MORNING READING AND THERE ARE THREE — CORRECTED
 * THE SAME DAY (7924–7929), BOTH READINGS KEPT** (4368). The count was never a
 * tally: 7845's own test header says so in terms — *"they do not prove the only
 * two reach-outside-the-range expressions are these two … a third would be
 * invisible to every gate in this directory in exactly the same way."* It was,
 * it is item 3 below, and it is in `rebuildL1()` rather than in a mart, which is
 * why reading these as a list of *L2* defects would have kept missing it.
 * ⚠️ **A fourth is not ruled out and this paragraph is not the instrument for
 * finding one** — `tests/Feature/Warehouse/RangeIndependenceTest.php` is the
 * gate that can fail.
 *
 *  1. ⛔ **`l2_fact_session.day` IS DECIDED BY THE RANGE.** It is
 *     `date_trunc('day', min(received_at))` over the events **inside** the
 *     range, so a session whose beacons straddle UTC midnight is one row on its
 *     first day when both days are replayed together and **two rows** when they
 *     are replayed a day at a time — with `pageviews`, `is_new` and every
 *     session-grain column of `l2_fact_daily_tenant` differing accordingly. On
 *     this repository's own replay fixture the whole-range answer records
 *     **`sessions = 0` on a day with a pageview**, which is also a defect in its
 *     own right: `SiteConversions::rate()` reads that column as its denominator
 *     and `28` §4.3 pairs its conversion trigger with *"≥100 sessions"*.
 *     ⚠️ **§8's boundary is the browser's** — `pixel.js`'s `currentSession()`
 *     splits on `utcDay(occurred_at)` — and `day` here is derived from
 *     **`received_at`**, so the two boundaries can never be made to agree by
 *     accident; a beacon fired at 23:59:58 and received at 00:00:01 straddles
 *     whatever the browser did. **Describe the defect, not the remedy**: fixing
 *     it changes what a session *is* in four marts and needs a ruling on *which
 *     clock* §8's midnight belongs to.
 *     ⚠️ **THE BLAST RADIUS IS MEASURED RATHER THAN GUESSED** (7840–7859). The
 *     obvious candidate — carry `date_trunc('day', received_at)` through
 *     `scoped`, `entry`, `exitp`, `active` and `agg`, key the session grain on
 *     `(session_id, that day)`, and add the same predicate to
 *     `rebuildFactConversion()`'s `own` join — was applied as a throwaway and the
 *     whole warehouse suite run against it. **`ByteIdenticalReplayTest` stays
 *     green**, and exactly one existing assertion moves:
 *     `HandComputedFixtureTest`'s *"a page that is only ever an exit still gets a
 *     row in the page rollup"*. ⛔ **That is why it was still refused here**: that
 *     file is §11 row 12's gate, its expected values exist precisely because they
 *     were worked out on paper from §8's text, and 5022 already records it once
 *     claiming to prove a boundary it had merely been handed — so editing its
 *     numbers to match a derivation changed in the same slice is reading the
 *     answer out of the code under test.
 *     ⛔ **THE OWNER RULED ON 2026-08-22 AND CHOSE NEITHER CLOCK: `day` IS GONE
 *     FROM THE SESSION GRAIN** (8040, 8041; 8100–8119). Everything above is kept
 *     and dated because it is the argument that produced the ruling and because
 *     it names the candidate that was measured and refused. **What is true today
 *     is narrower and must not be over-read**: a session is one row keyed
 *     `(business_id, session_id)`, so *the split* is unrepresentable — and
 *     `scoped` is still the range, so `pageviews`, `duration_s`, `active_s`,
 *     `is_engaged` and `is_new` are still a function of how the range was cut.
 *     **This item is narrowed rather than closed.** What replaced the split is a
 *     session row describing only the last piece replayed, which
 *     `IncrementalRollUpTest`'s first test still pins. ⚠️ **And the range delete
 *     had to change with it** — see `rebuildFactSession()`. ⚠️ **The measured
 *     blast radius above was of a *different* fix and does not describe this
 *     one**: under the ruling the exit-page divergence widens, so
 *     `HandComputedFixtureTest`'s exit-only row is untouched and the one
 *     assertion that moves there is the session's own day.
 *  2. ⛔ **`l2_fact_session.is_new` IS DECIDED BY WHETHER THE PRUNER HAS RUN.**
 *     Its `NOT EXISTS` walks every `l1_events` row the tenant has, so once
 *     [[WarehouseRetention]] has removed the days a visitor was first seen in,
 *     a replay of an untouched later range derives that returning visitor as
 *     **new**. 7700's horizon is what made this reachable — before it, nothing
 *     had ever deleted an `l1_events` row except a tenant erasure.
 *  3. ✅ **THE THIRD IS `rebuildL1()`'s DUPLICATE `event_id` ACROSS A DAY
 *     BOUNDARY, AND IT IS FIXED AS FAR AS A RANGE CAN FIX IT** (7924–7929,
 *     2026-08-22). `IncrementalRollUpTest`'s header predicted this exactly —
 *     *"a third would be invisible to every gate in this directory in exactly
 *     the same way"* — and it was. The delete is keyed on `received_at`, the
 *     insert on `(business_id, event_id)`, so a retried beacon whose two
 *     receipts straddle midnight has one copy outside the deleted range and the
 *     in-range insert is ignored. **Measured**: the same L0 replayed
 *     `D` then `D+1` puts the event on `D`, and replayed `D+1` then `D` puts it
 *     on `D+1`. What is fixed is that the winner is now the **earliest
 *     receipt** rather than the lowest object key, and that the drop is counted
 *     (`etl_runs.l1_suppressed`) instead of silent. ⛔ **What is NOT fixed is
 *     the order dependence itself** — see `rebuildL1()` and 7928 for why
 *     correcting it would mean deleting an `l1_events` row **outside** the range
 *     being rebuilt, leaving that range's marts counting an event L1 no longer
 *     holds. The remedy that exists is a replay whose range covers both
 *     receipts, which resolves it to the earliest receipt deterministically.
 *
 * ⛔ **SO THE HONEST STATEMENT OF `CLAUDE.md`'s *"L1/L2 MUST BE TRUNCATABLE AND
 * REBUILDABLE BYTE-IDENTICALLY"* IS NARROWER THAN THE RULE**: a replay of a
 * range reproduces itself given the same cut and the same surviving L1, and
 * nothing stronger. Read that before scheduling anything that calls this.
 * ⚠️ **AND THE WORD *cut* NOW CARRIES ONE MORE THING THAN 7846 GAVE IT**: two
 * cuts of the same range in a different **order** can leave L1 itself different,
 * which is 3 above and is a stronger statement than 1 and 2, because those two
 * move only derived rows.
 *
 * ⚠️ **DECISION 4861's "NO PRODUCTION WRITER FEEDS THIS" IS NO LONGER TRUE AND
 * THE CORRECTION IS DATED 2026-08-18.** It read: *"The collector is unbuilt, so
 * in production every range is empty and every replay is a no-op."*
 * [[\App\Services\Pixel\PixelCollector]] and [[\App\Jobs\ArchivePixelBatchJob]]
 * are that writer, and `PixelIngestToReplayTest` drives a real HTTP request
 * through to a replay of the range it landed in. ⛔ **What is still not true is
 * that traffic arrives**: nothing in this application delivers the bundle to a
 * page, so the archive is fed by whoever pastes the install snippet and by
 * nobody else today. Read decision 4966 before calling row 1's replay gate
 * closed.
 */
final readonly class Replayer
{
    /**
     * The `event_type` an error on somebody's page arrives as.
     *
     * ⚠️ **READ OFF THE PIXEL BUNDLE'S SOURCE, WHICH EMITS IT FROM TWO PLACES** —
     * the `error` listener and the `unhandledrejection` listener — and there is
     * no collector-side constant to bind to, because the collector treats a
     * `js_error` like any other event. [[\App\Services\Pixel\PixelCollector]]'s
     * `VITAL_EVENT` is the pattern this would have used if one existed, and is
     * what the vitals mart binds instead of typing `'vital'` out again.
     */
    public const string JS_ERROR_EVENT = 'js_error';

    /**
     * The longest a session may be, in seconds — §8's own boundary, said once.
     *
     * ⛔ **THIS IS A BOUND ON A CLIENT CLOCK AND IT IS LOAD-BEARING** (8221).
     * `started_at`/`ended_at` are `min`/`max` of `occurred_at`, which the browser
     * writes; `l2_fact_session.duration_s` is their difference in seconds and is
     * an `integer`, so **two events one `POST` apart, dated 1970 and 9999, raised
     * `SQLSTATE[22003]` inside the mart INSERT and killed the whole range for
     * ever.** §8 ends a session at thirty minutes of inactivity or UTC midnight,
     * whichever comes first, so twenty-four hours is the ceiling the
     * specification itself implies and no legitimate session reaches it.
     *
     * ⚠️ **THE `active_ms` CLAMP ONE METHOD DOWN IS THE SAME NUMBER AND NOW READS
     * IT FROM HERE**, rather than carrying its own `86400000`. Two copies of one
     * ceiling is the shape {@see self::conversionTypePlaceholders()} was
     * extracted to prevent, on the dimension rather than on the vocabulary — and
     * the copies had already diverged in *effect*, because one of them bounded
     * the value and the other did not exist.
     */
    private const int SESSION_CEILING_SECONDS = 86_400;

    /**
     * §8's attribution lookback, in days — the window, and therefore the ceiling.
     *
     * ⛔ **`l2_fact_conversion.days_to_convert` IS A `smallint` AND ITS INPUT IS A
     * CLIENT CLOCK** (8222). It is measured from `own.started_at`, which is
     * `min(occurred_at)` over the session, so the same two-event `POST` that
     * killed `duration_s` produces 3.6 million days here — `SQLSTATE[22003]:
     * smallint out of range`, the same permanent denial one mart later.
     * ⚠️ **The clamp is not a plausibility judgement**: every value this column
     * is *defined* over comes from a lookback bounded by this same constant, so a
     * number above it is outside the column's own meaning rather than merely
     * surprising. **That is why a clamp is right here and a wider column is not** —
     * widening would preserve a value the column has no definition for.
     */
    public const int ATTRIBUTION_WINDOW_DAYS = 30;

    /**
     * The day a session belongs to, for every rollup that needs one.
     *
     * ⛔ **THIS IS 8041, AND IT EXISTS AS ONE STRING BECAUSE 8040 SAYS IT MUST.**
     * The owner's ruling took `day` off the session grain and said where the
     * question moves to: the daily rollups *"must attribute a session to a day at
     * **one** place instead of having it baked into a primary key"*. This is that
     * place. Two methods below read it — `rebuildFactDailyTenant()` for
     * `sessions`/`engaged_sessions`/`bot_sessions`/`users`/`new_users`, and
     * `rebuildFactPageDaily()` for `exits` — and a second copy of the expression
     * would be the four-marts-disagree failure `conversionTypePlaceholders()` was
     * extracted for (5146), on the dimension rather than on the vocabulary.
     *
     * ⚠️ **8041 IS "THE DAY THE SESSION STARTED" AND THIS IS THE RECEIPT CLOCK'S
     * ANSWER TO IT, WHICH IS A CHOICE AND NOT A TRANSCRIPTION.** The browser's
     * answer would be `date_trunc('day', started_at)` — `min(occurred_at)` — and
     * it is refused for the reason `rebuildL1()`'s own range predicate refuses
     * `occurred_at`: a client clock is unbounded, so a session could produce a
     * `l2_fact_daily_tenant` row for a day **outside the range that was just
     * deleted**, which is 7924's defect with the sign flipped and no dedupe
     * available (7933(B)). `first_received_at` is ours, is stamped at receipt,
     * and is inside the range by construction.
     *
     * ⛔ **AND THE DIVERGENCE THIS PRESERVES IS DELIBERATE.** A session that
     * started on the 13th and whose exit page's events were received on the 14th
     * puts its `exits` on the 13th and its `pageviews` on the 14th — so
     * `rebuildFactPageDaily()`'s `FULL OUTER JOIN` is still load-bearing and
     * `HandComputedFixtureTest`'s *"a page that is only ever an exit still gets a
     * row in the page rollup"* still kills the `LEFT JOIN` mutant. **Attributing
     * the exit to the exit event's own receipt would collapse the two sides onto
     * one day and revive that mutant**, which is the design boundary to protect
     * if anybody revisits this.
     */
    private const string SESSION_ROLLUP_DAY = "date_trunc('day', first_received_at)::date";

    /**
     * What "inside this range" means to the session grain, written once.
     *
     * ⛔ **THE DELETE AND THE INSERT MUST AGREE EXACTLY OR THE MART GAINS A
     * DUPLICATE KEY** (8040). Since a session is one row keyed
     * `(business_id, session_id)`, the range delete can no longer be a predicate
     * on the row itself: the row's own `first_received_at` moves when the cut
     * moves, so a delete keyed on it would miss the row the insert is about to
     * write and the INSERT would raise. **What the delete had to become is "the
     * sessions this range's L1 contains"**, and this constant is what makes the
     * two statements the same set rather than two statements a reader has to
     * compare by eye.
     *
     * ⚠️ **THE BINDINGS ARE `business_id`, `from`, `to` IN THAT ORDER**, and both
     * call sites bind them positionally.
     */
    private const string SESSION_RANGE = <<<'SQL'
        business_id = ?
          AND received_at >= ?::timestamp
          AND received_at <  (?::date + 1)::timestamp
          AND session_id IS NOT NULL
        SQL;

    public function __construct(
        private L0Archive $archive,
        private L1Loader $loader,
        private readonly DefaultsRegistry $registry,
    ) {}

    /**
     * Rebuild L1 and L2 for one business over a closed day range.
     *
     * ⛔ **A COVERED-ENTITY REFUSAL STOOD HERE AND IS GONE — THE OWNER
     * OVERRODE `29` §2 RULE 24 AND ITS §12.1 LINE ON 2026-08-30** (12532,
     * 12539). Three `PhiExclusion::refuse()` calls guarded this class: one here
     * before a single L0 object was read, one in {@see self::stage()} as the
     * staging table filled, and one in {@see L1Loader} that could not be
     * bypassed. **All three are removed together, deliberately** — leaving any
     * one of them would be a guard whose siblings no longer exist, which reads
     * on a later diff as a rule that is still in force.
     *
     * ⛔ **AND A RANGE THE ARCHIVE CANNOT SUPPLY IS REFUSED BEFORE ANYTHING IS
     * DELETED — ADDED 2026-08-26 (9845).** Until then, `paths()` returning
     * nothing was not an error: the range was emptied, an `EtlRun` row of zeros
     * was written, and the command printed *"0 objects, 0 lines → 0 L1, 0 L2"*
     * and exited `0`. **A tenant whose archive had been lost and a tenant with
     * no traffic produced the same line.** {@see self::refuseAShortArchive()}
     * carries the two questions it asks, the one it cannot, and why the check
     * has to sit in front of the delete rather than behind it.
     *
     * ⚠️ **THE SHORT-ARCHIVE REFUSAL IS UNTOUCHED BY ANY OF THAT.** It is not
     * a rule-24 guard and never was: its subject is a range the archive cannot
     * supply, and it sits in front of the delete for its own reason.
     */
    public function replay(Business $business, CarbonImmutable $from, CarbonImmutable $to): EtlRun
    {
        $businessId = $business->getKey();

        /** @var EtlRun $run */
        $run = Tenancy::actingAs($businessId, function () use ($businessId, $from, $to): EtlRun {
            $startedAt = CarbonImmutable::now();

            $fromDay = $from->utc()->startOfDay();
            $toDay = $to->utc()->startOfDay();

            $paths = $this->archive->paths($businessId, $fromDay, $toDay);

            // ⛔ **BEFORE `openStaging()`, BEFORE THE TRANSACTION AND BEFORE THE
            // DELETE.** A replay that cannot be supplied by the archive must
            // leave the warehouse alone rather than empty it and say so
            // afterwards — see the method's own docblock.
            $this->refuseAShortArchive($businessId, $fromDay, $toDay, count($paths));

            $this->openStaging();

            try {
                [$lines, $rejected] = $this->stage($businessId, $paths);

                return DB::transaction(function () use ($businessId, $fromDay, $toDay, $paths, $lines, $rejected, $startedAt): EtlRun {
                    $l1 = $this->rebuildL1($businessId, $fromDay, $toDay);

                    // ⚠️ ORDER IS THE DEPENDENCY CHAIN, NOT A LIST. `fact_session`
                    // must exist before `fact_conversion` (§8's attribution reads
                    // it for the 30-day lookback) and before `fact_daily_tenant`
                    // and `fact_page_daily` (both read session-grain facts back
                    // rather than re-deriving them from raw events — see those two
                    // migrations' docblocks). `l2_fact_source_daily` has no such
                    // dependency and could run anywhere in this list, and neither
                    // has `l2_fact_vital_daily` — it reads raw events only, which is
                    // a property of it being a histogram rather than a rollup of
                    // something already rolled up.
                    $l2 = $this->rebuildL2($businessId, $fromDay, $toDay)
                        + $this->rebuildFactSession($businessId, $fromDay, $toDay)
                        + $this->rebuildFactConversion($businessId, $fromDay, $toDay)
                        + $this->rebuildFactDailyTenant($businessId, $fromDay, $toDay)
                        + $this->rebuildFactPageDaily($businessId, $fromDay, $toDay)
                        + $this->rebuildFactVitalDaily($businessId, $fromDay, $toDay);

                    return EtlRun::create([
                        'business_id' => $businessId,
                        'from_day' => $fromDay->toDateString(),
                        'to_day' => $toDay->toDateString(),
                        'l0_objects' => count($paths),
                        'l0_lines' => $lines,
                        'l0_rejected' => $rejected,
                        'l1_rows' => $l1['written'],
                        'l1_suppressed' => $l1['suppressed'],
                        'l2_rows' => $l2,
                        'snapshot_digest' => WarehouseSnapshot::digest($businessId),
                        'started_at' => $startedAt,
                        'finished_at' => CarbonImmutable::now(),
                    ]);
                });
            } finally {
                try {
                    $this->closeStaging();
                } catch (\Throwable $e) {
                }
            }
        });

        return $run;
    }

    /**
     * Refuse a rebuild the archive cannot supply, **before anything is deleted**.
     *
     * ## ⛔ The defect this exists for: a destructive command that reports success
     *
     * {@see self::rebuildL1()} deletes the range and rebuilds it, and
     * {@see L0Archive::paths()} returning **nothing is not an
     * error** — an `EtlRun` row is written with zeros, the command prints
     * *"0 objects, 0 lines → 0 L1, 0 L2"* and exits `0`. **Nothing anywhere
     * compared objects found against objects expected**, so a range whose
     * archive is missing read exactly like a range with no traffic, on the one
     * command whose whole job is rebuilding from that archive.
     *
     * ⚠️ **[[PixelArrivals]] already named this hazard in the abstract** — *"a
     * derived layer is truncated and rebuilt from L0, so anything written by
     * hand is destroyed by the next replay, silently, because the replay reports
     * success"* — and nobody had connected it to an archive that had lost
     * objects rather than to a row somebody typed.
     *
     * ## ⛔ Before the delete, which is the whole of why this is safe
     *
     * ⛔ **A "FAIL LATE" VERSION OF THIS WOULD LEAVE THE TENANT WORSE OFF THAN
     * NOT REPLAYING AT ALL.** The delete runs first and the insert follows, so a
     * check placed after the rebuild would report the loss having just completed
     * it. This runs before `openStaging()`, before the transaction and before a
     * single row is removed, so **a refusal leaves the warehouse exactly as it
     * was** — which is what {@see WarehouseReplay} prints
     * when it catches this.
     *
     * ## ⚠️ Two questions, and neither subsumes the other
     *
     *  1. **Fewer objects than a previous replay of the identical range
     *     recorded.** L0 is append-only and is pruned nowhere
     *     ({@see WarehouseRetention}: *"L0 is therefore not pruned here and not
     *     pruned anywhere"*), and a batch's partition is decided by the
     *     collector's clock rather than by when the worker ran — so for a fixed
     *     range the object count can **grow** and can never fall. A fall is
     *     therefore not a judgement call: it is proof that the archive has lost
     *     objects or is no longer the archive that was written to.
     *     ⚠️ **It needs a prior run of the *same* bounds and is silent without
     *     one**, which is why it is not the only check.
     *  2. **No objects at all, over a range that still holds L1 rows.** L1 is
     *     derived from L0 and from nothing else — {@see L1Loader} has exactly two
     *     callers, this class and {@see ArchivePixelBatchJob}, and that
     *     job writes L0 *before* it derives — so rows without objects cannot
     *     arise from ordinary use. It is what a re-pointed bucket, an empty
     *     `AWS_ENDPOINT` or a lost credential looks like from in here, and it
     *     fires on the **first** replay after one, with no prior run needed.
     *
     * ## ⛔ What this does NOT detect, said plainly because the gap is the
     * dangerous half
     *
     * ⛔ **A PARTIAL LOSS WITH NO PRIOR RUN IS INVISIBLE HERE AND CANNOT BE MADE
     * VISIBLE FROM THIS CLASS.** If ninety of a day's hundred batches archived
     * and ten failed, L0 holds ninety objects, L1 holds their events, and the
     * two agree perfectly. **Nothing in this application counts accepted
     * beacons** — and `pixel_monthly_usage.events_total`, which does count
     * admitted *events* on the collector's own request, is monthly, is taken
     * upstream of two further refusal gates and counts events rather than
     * batches, so there is still no expected figure a range can be compared
     * against (9905, correcting this sentence's earlier absolute form). The only
     * record that the other ten ever existed is
     * {@see OperatorAlertKind::PixelArchiveFailed} and the
     * `failed_jobs` rows. That is why the bell exists and why
     * {@see WarehouseReplay} reads it back rather than
     * this method growing a guess.
     *
     * ⛔ **AND IT IS NOT AN INTEGRITY CHECK ON THE OBJECTS THEMSELVES.** It
     * counts keys. An object that is present and unreadable is
     * {@see ObjectStoreL0Archive::read()}'s refusal, one
     * layer down and already loud.
     *
     * @param  int  $found  `count($paths)` — passed rather than recomputed, so
     *                      the figure this refuses on is the one the rebuild
     *                      would have used.
     *
     * @throws RuntimeException
     */
    private function refuseAShortArchive(int $businessId, CarbonImmutable $fromDay, CarbonImmutable $toDay, int $found): void
    {
        // ⚠️ THE LATEST RUN OF THE **IDENTICAL** BOUNDS, NOT AN OVERLAPPING ONE.
        // A wider previous range legitimately found more objects than this one
        // will, and a narrower one legitimately found fewer, so anything but an
        // exact match compares two different questions. `orderByDesc('id')`
        // rather than a timestamp: `etl_runs` has no updated_at and `id` is the
        // only strictly increasing column on it.
        // ⚠️ THE MODEL RATHER THAN `value()`, SO THE CAST IS THE THING THAT
        // DECIDES THE TYPE. `value()` would hand back whatever the driver gave
        // and an `is_int()` guard over that fails **open** — the comparison is
        // silently skipped and the refusal never fires, which is the one
        // direction a guard here must not fail in.
        $previous = EtlRun::query()
            ->where('from_day', $fromDay->toDateString())
            ->where('to_day', $toDay->toDateString())
            ->orderByDesc('id')
            ->first();

        $expected = $previous?->l0_objects;

        if ($expected !== null && $found < $expected) {
            throw new RuntimeException(
                'the L0 archive is short: this range held '.$expected.' objects at the last replay and holds '
                .$found.' now. L0 is append-only, so this is lost archive rather than a quiet range. Nothing was '
                .'deleted. Check warehouse.l0_disk points at the bucket that was written to before replaying again.'
            );
        }

        if ($found > 0) {
            return;
        }

        $derived = DB::table('l1_events')
            ->where('business_id', $businessId)
            ->where('received_at', '>=', $fromDay)
            ->where('received_at', '<', $toDay->addDay())
            ->exists();

        if ($derived) {
            throw new RuntimeException(
                'the L0 archive holds no object for this range and L1 still holds rows derived from one. Rebuilding '
                .'would delete the only copy that is left. Nothing was deleted. Check warehouse.l0_disk points at the '
                .'bucket that was written to before replaying again.'
            );
        }
    }

    /**
     * The one-tenant, one-run scratch table the L0 read lands in.
     *
     * ⛔ **THIS EXISTS BECAUSE `replay()` USED TO HOLD EVERY DERIVED ROW OF THE
     * WHOLE RANGE IN PHP BEFORE WRITING ANY OF THEM** (8233, 8360). Measured on
     * the honest 33,000-event fixture: **66.9 MB, 2,125 bytes a row**, and the
     * growth is linear in *events in the range* while `WarehouseRetention` keeps
     * L1 for 400 days and L0 for ever. **The operator who does the right thing —
     * `warehouse:replay --from` over a tenant's history because a mart looks
     * wrong — is the person that ceiling is met by**, and PHP's answer to meeting
     * it is `Allowed memory size … exhausted` with the marts already rebuilt.
     *
     * ⚠️ **A TEMPORARY TABLE RATHER THAN A SMALLER PHP BUFFER, BECAUSE EVERY
     * PHP-SIDE ANSWER IS STILL LINEAR.** Two were tried on paper and rejected:
     * keeping only `event_id => received_at` and re-reading L0 a second time is
     * roughly eleven times cheaper and still runs out; chunking straight into
     * [[L1Loader]] with no dedupe at all reintroduces the exact defect
     * `firstReceiptPerEvent()` was written to close (7926) — `insertOrIgnore`
     * keeps whichever copy it meets first, and the order it meets them in is
     * `ObjectStoreL0Archive::paths()`'s **class-major** sort.
     *
     * ⛔ **IT IS NOT `ON COMMIT DROP`, AND THAT IS DELIBERATE.** The L0 read is
     * network I/O against an object store and can run for hours; making the
     * staging table transactional would mean holding the rebuild's transaction
     * open across the whole of it, which blocks vacuum on `l1_events` and every
     * mart for the duration. So the table is created before the transaction,
     * dropped in a `finally` after it, and **dropped again before it is created**
     * — a process that died mid-replay leaves one behind, and the roster walk in
     * [[\App\Console\Commands\WarehouseReplay]] replays every tenant on one
     * connection.
     *
     * ⛔ **"A PROCESS THAT DIED MID-REPLAY LEAVES ONE BEHIND" IS NOT WHY THE
     * PRE-CREATE DROP IS LOAD-BEARING, AND THE MITIGATION IS RIGHT FOR A REASON
     * THE SENTENCE BESIDE IT DOES NOT GIVE — CORRECTED 2026-08-23 (8508).**
     * **A dead backend takes its own `pg_temp` schema with it**: PostgreSQL
     * drops a session's temporary relations when the session ends, so the
     * ordinary crash — the process is killed, the connection closes, the backend
     * exits — is exactly the case that cleans up after itself, and a PHP fatal
     * such as `Allowed memory size … exhausted` does not run the `finally` and
     * does not need to. **What genuinely leaves one behind is a connection that
     * outlives its client**: a session-mode pooler handing a later process the
     * same backend, or a persistent PDO handle. ⚠️ **The clause that survives
     * intact is the second one, and it is the one that fires every day** — the
     * roster walk replays every tenant on **one** connection, so tenant 2's
     * `openStaging()` meets tenant 1's table if `closeStaging()` did not run.
     * **Keep the drop. It is the sentence that was wrong, not the line.**
     *
     * ⚠️ **`LIKE l1_events` RATHER THAN A COLUMN LIST**, so a column added to L1
     * arrives here without anybody remembering to add it. `LIKE` with no
     * `INCLUDING` copies names, types and `NOT NULL` and **not** the primary key
     * — which is required rather than incidental: this table has to be able to
     * hold the duplicate `(business_id, event_id)` pairs whose resolution is its
     * whole purpose.
     *
     * ## ⛔ WHAT REPLACED A PHP CEILING IS A DISK ONE, AND NOTHING BOUNDS IT,
     * WATCHES IT OR SAYS WHAT AN OPERATOR SEES WHEN IT IS HIT (8504)
     *
     * This table holds **one row per derived event of the whole range**, on
     * disk, for the length of the L0 read. **Measured: 374 bytes a row —
     * 12,140,544 bytes for 32,500 staged rows** — so a range that derives ten
     * million events writes something near four gigabytes into the database's
     * temporary space before a single row reaches `l1_events`. `L0` is kept for
     * ever and [[\App\Console\Commands\WarehouseReplay]]'s own docblock says
     * an operator *"may legitimately ask for five years"*.
     * ⚠️ **THE `[[…]]` FORM RATHER THAN `{@see …}` IS DELIBERATE HERE AND
     * EVERYWHERE ELSE IN THIS FILE** (lane E, wave 18): Pint's
     * `fully_qualified_strict_types` hoists a namespaced name out of a
     * `{@see}` into a real `use` statement, and several chokepoint lints read
     * `codeWithoutComments()` — so a class this file only *mentions* arrives at
     * those lints as an import it made. Written as `{@see}`, this paragraph
     * gave `Replayer` a `use App\Console\Commands\WarehouseReplay;` it has no
     * code for. **Do not tidy it back.**
     *
     * ⚠️ **`temp_file_limit` IS NOT THE INSTRUMENT AND SETTING IT WOULD NOT
     * HELP.** PostgreSQL's own documentation excludes explicit temporary tables
     * from that limit — it governs sort and hash spill files and held cursors —
     * so the only thing it would bound here is {@see self::STAGING_DISTINCT}'s
     * sort, and the table itself would go on growing. On this machine it is `-1`
     * and `log_temp_files` is `-1`, so a replay that wrote four gigabytes of
     * temporary files would leave no trace of having done so.
     *
     * ⛔ **AND THE FAILURE IS A FULL FILESYSTEM RATHER THAN A REFUSED REPLAY.**
     * The temporary relation lives in the ordinary data directory, so the
     * process that runs out of room is **PostgreSQL**, and what it takes down is
     * every other tenant's writes as well as this replay. **This is reported
     * rather than fixed** — a ceiling here is a number nobody has, and the honest
     * instruments are an operator-facing figure and a `pg_total_relation_size`
     * alert, neither of which is this slice's.
     */
    private const string STAGING_TABLE = 'l1_replay_staging';

    /**
     * How many derived rows go into the staging table in one statement.
     *
     * ⚠️ **THE SAME REASON AS [[L1Loader::CHUNK]] AND DELIBERATELY THE SAME
     * NUMBER.** The staging table has the same twenty-five columns plus one, so
     * the bind-parameter limit that decided 500 there decides it here.
     */
    private const int STAGING_CHUNK = 500;

    /**
     * Every distinct event in the staging table, earliest receipt first.
     *
     * ⛔ **THIS IS `firstReceiptPerEvent()`, MOVED INTO SQL, AND ITS ARGUMENT IS
     * UNCHANGED — KEPT AND DATED (4368).** That method's docblock read: *"this
     * exists so the surviving copy is a function of L0 rather than of the object
     * key's sort order (7926, 7927). `insertOrIgnore` keeps whichever copy it
     * meets first, and the order it meets them in is
     * `ObjectStoreL0Archive::paths()`'s `sort($paths, SORT_STRING)` over
     * `class=…/business=…/dt=…` — **class-major, not date-major**. Measured: a
     * business archived under `pii` on day one and `none` on day two has its
     * day-two object sort **first**, so the *later* receipt won, and with it the
     * row's `received_at`, its `l0_path`, its `data_class` and therefore the
     * `day` every mart buckets it into."* **All of that is still why this
     * ordering is written the way it is**; what changed is only that the
     * comparison happens in Postgres, where it costs no PHP heap.
     *
     * ⚠️ **`replay_seq` IS THE TIE-BREAK AND IT IS THE DERIVATION ORDER.** Two
     * receipts of one event can share a `received_at` — an L0 batch stamps one
     * receipt time on every line it holds — and PHP's `<` comparison kept the
     * **first encountered** on a tie. `replay_seq` is the ordinal this class
     * assigns as it reads, so the SQL keeps the same one.
     *
     * ⚠️ **EARLIEST RECEIPT RATHER THAN LOWEST PATH, BECAUSE THAT IS WHAT LIVE
     * INGEST ALREADY DOES.** [[L1Loader]]'s reason for existing is that *"two
     * insert paths would be two derivations that agree until they do not"*;
     * [[\App\Jobs\ArchivePixelBatchJob]] inserts receipts as they arrive, so the
     * first arrival is the row and the retry conflicts.
     *
     * ⛔ **THE INSERT ORDER THIS PRODUCES IS `event_id` ORDER RATHER THAN
     * DERIVATION ORDER, AND THAT IS INVISIBLE TO THE GATE.** `l1_events` has no
     * sequence column — [[L1Derivation]] says so in as many words — and
     * [[WarehouseSnapshot]] orders `l1_events` by `received_at, event_id`, so no
     * byte of the digest depends on which row was inserted first. It was checked
     * rather than assumed: the 33,000-row fixture digests identically before and
     * after this change.
     *
     * ## ⛔ THIS IS ONE STATEMENT OVER EVERY STAGED ROW OF THE RANGE, AND libpq
     * BUFFERS IT WHOLE — MEASURED 2026-08-23 (8501, 8502)
     *
     * {@see WarehouseSnapshot::section()} carries this warning and is the only
     * place in the tree that did: *"PostgreSQL has no unbuffered result set —
     * libpq retrieves the whole answer whatever PHP asks for."* It is true of
     * `DB::cursor()` here for exactly the same reason. **Measured: a drained
     * cursor over 131,250 `l1_events` rows costs +103.6 MB of the process's
     * resident set and +0.01 MB of PHP heap**, so this buffer is invisible to
     * `memory_limit`, to `memory_get_peak_usage()` and therefore to every
     * assertion in `ReplayMemoryTest`'s slope test.
     *
     * ⚠️ **AND IT IS NOT THE ONE TO FIX FIRST, WHICH IS THE USEFUL HALF.** This
     * buffer is linear in the **range**; `WarehouseSnapshot::digest()` runs later
     * in the same `replay()` and is linear in the **tenant's whole warehouse**,
     * with no range predicate at all. The two are sequential, so the process peak
     * is the larger of them, and a tenant is never smaller than one of its own
     * ranges — **bounding this cursor alone cannot lower the peak of any replay
     * that has ever run.** The remedy that would work on both is a server-side
     * `DECLARE … CURSOR` with `FETCH FORWARD n`, which PDO's pgsql driver does
     * not do for you; it is not taken here because it changes how the
     * byte-identical gate's own instrument reads its rows and has to be argued
     * against that gate on its own.
     *
     * ⚠️ **THE SORT UNDERNEATH IT IS SEPARATE AND IS UNMEASURED AT SCALE.**
     * `DISTINCT ON … ORDER BY` over a relation created without `INCLUDING
     * INDEXES` is a full sort of the range, once per replay, on a table with no
     * statistics that nothing `ANALYZE`s — which is 8230's own shape on a
     * statement 8360 introduced. Above `work_mem` it spills to disk.
     */
    private const string STAGING_DISTINCT = <<<'SQL'
        SELECT DISTINCT ON (business_id, event_id) *
          FROM l1_replay_staging
         ORDER BY business_id, event_id, received_at, replay_seq
        SQL;

    /**
     * Create the staging table, dropping any a dead process left behind.
     */
    private function openStaging(): void
    {
        DB::statement('DROP TABLE IF EXISTS '.self::STAGING_TABLE);
        DB::statement('CREATE TEMPORARY TABLE '.self::STAGING_TABLE.' (LIKE l1_events)');
        DB::statement('ALTER TABLE '.self::STAGING_TABLE.' ADD COLUMN replay_seq bigint NOT NULL');
    }

    private function closeStaging(): void
    {
        DB::statement('DROP TABLE IF EXISTS '.self::STAGING_TABLE);
    }

    /**
     * Read every L0 object of the range and land its derived rows in staging.
     *
     * ⚠️ **THE TWO COUNTERS ARE UNCHANGED IN MEANING.** `l0_lines` is receipts
     * read and `l0_rejected` is receipts that derived nothing — a truncated
     * payload, a batch with no events. Neither is a row count.
     *
     * ⛔ **THE PHI REFUSAL IS REPEATED HERE AND IT IS NOT BELT-AND-BRACES.**
     * {@see self::replay()} refuses a covered entity before a single object is
     * read, and [[L1Loader]] refuses again at the one INSERT — but between them
     * sits a table that now holds derived event rows, and the transition
     * the removed rule-24 guard existed for was *a column changing under a long-running
     * process*. A business raised to `phi` during a multi-hour L0 read would
     * otherwise go on filling this table until the drain refused. Asking once a
     * chunk makes the window one chunk wide, which is exactly the window
     * [[L1Loader]] already has.
     *
     * ⚠️ **"ONCE A CHUNK" IS THE BEST CASE AND NOT THE GUARANTEE — CORRECTED
     * 2026-08-23 (8509).** The refusal rides on {@see self::flushStaging()}, and
     * a flush happens when the buffer **reaches** {@see self::STAGING_CHUNK} or
     * when the read is over. **So on a range deriving fewer than five hundred
     * rows it fires exactly once, after every L0 object has already been read
     * and derived** — the window is the whole read, not one chunk. The sentence
     * above is kept because it describes what happens on a range large enough
     * for this guard to be worth having, and because the shape it hides is
     * 314–316's: a stated window that a reader takes as a bound. **The guard
     * that actually cannot be outrun is [[L1Loader]]'s, at the one INSERT.**
     *
     * @param  list<string>  $paths
     * @return array{0: int, 1: int} lines read, receipts that derived nothing
     */
    private function stage(int $businessId, array $paths): array
    {
        $lines = 0;
        $rejected = 0;
        $sequence = 0;
        $buffer = [];

        foreach ($paths as $path) {
            foreach ($this->archive->read($path) as $line) {
                $lines++;

                $derived = L1Derivation::rows($line, $path);

                if ($derived === []) {
                    $rejected++;

                    continue;
                }

                foreach ($derived as $row) {
                    $row['replay_seq'] = $sequence++;

                    $buffer[] = $row;

                    if (count($buffer) === self::STAGING_CHUNK) {
                        $this->flushStaging($buffer);

                        $buffer = [];
                    }
                }
            }
        }

        if ($buffer !== []) {
            $this->flushStaging($buffer);
        }

        return [$lines, $rejected];
    }

    /**
     * ⛔ **A `PhiExclusion::refuse()` CALL STOOD ON THIS LINE AND IS GONE WITH
     * `29` §2 RULE 24 — 2026-08-30 (12539). NOTHING REPLACES IT, DELIBERATELY.**
     *
     * ⚠️ **THE FIRST ATTEMPT AT THIS SLICE PUT A TENANCY CHECK HERE AND IT WAS
     * WRONG TWICE.** `$businessId` is the *replay subject*, and the replay runs
     * inside `Tenancy::actingAs()` on that same id — so the check could never
     * fire. **And a version that inspected `$buffer` instead would have been
     * worse than vacuous**: it would reverse 8505, which refused a tenant
     * predicate on the drain *in writing*, because filtering the foreign row out
     * here turns a loud refusal into a silent drop and the operator never learns
     * that an L0 object disagrees with its own path.
     *
     * ⚠️ **THE `$businessId` PARAMETER WENT WITH THE REFUSAL**, because it
     * existed only to be refused on. A parameter kept "in case" is how the next
     * reader concludes there is a check here.
     *
     * ✅ **A FOREIGN LINE IS STILL REFUSED, ONE LAYER DOWN AND ON PURPOSE.**
     * {@see L1Loader::insert()} carries the tenancy precondition that used to
     * ride on the PHI reader, and `ReplayStagingTest` asserts both halves: that
     * the foreign rows reach this table, and that the insert then refuses them
     * by name.
     *
     * @param  list<array<string, mixed>>  $buffer
     */
    private function flushStaging(array $buffer): void
    {
        DB::table(self::STAGING_TABLE)->insert($buffer);
    }

    /**
     * Rebuild `l1_events` for the range, and say what the range could not write.
     *
     * ⛔ **A DUPLICATE `event_id` WHOSE TWO RECEIPTS LAND ON DIFFERENT DAYS IS
     * WHY THIS RETURNS TWO NUMBERS — ADDED 2026-08-22 (7924–7929).** The delete
     * below is keyed on `received_at` and [[L1Loader]]'s insert is keyed on
     * `(business_id, event_id)`, so a retried beacon received at 23:59:59 and
     * again at 00:00:02 puts one copy **outside** the deleted range: the
     * in-range insert conflicts with a row the delete never covered and is
     * ignored. **Measured, not derived** — a single-day replay of the second day
     * reports `l0_lines = 1`, `l1_rows = 0` and `l0_rejected = 0`, a triple no
     * documented behaviour of this class explains, and the event is absent from
     * every mart of that day.
     *
     * ⚠️ **THE COMMENT ABOVE THE DELETE ALREADY NAMED THIS HAZARD FOR THE CLOCK
     * IT REJECTED AND NOT FOR THE ONE IT KEPT** (314–316). *"A wrong
     * `occurred_at` would put a row outside the range … so the delete would miss
     * it and the insert would collide with it"* is the argument for keying the
     * range on `received_at`; two genuine receipts on two days do the same thing
     * with no wrong clock anywhere, and the paragraph explaining the hazard is
     * what made the line beside it read as considered.
     *
     * ⛔ **THE ROWS COME FROM THE STAGING TABLE AND NOT FROM AN ARGUMENT, SINCE
     * 8360.** Everything about what is written and in what order is unchanged;
     * what changed is that the range no longer has to fit in `memory_limit`.
     *
     * @return array{written: int, suppressed: int} `suppressed` is how many
     *                                              distinct events this range derived and did not write, because the same
     *                                              `(business_id, event_id)` already lives outside it
     */
    private function rebuildL1(int $businessId, CarbonImmutable $fromDay, CarbonImmutable $toDay): array
    {
        // ⚠️ THE RANGE IS ON `received_at`, WHICH IS WHAT THE L0 PARTITION IS
        // KEYED ON — not on `occurred_at`. A client's own clock decides
        // `occurred_at` and a wrong one would put a row outside the range that
        // is about to be rebuilt from the object containing it, so the delete
        // would miss it and the insert would collide with it.
        DB::table('l1_events')
            ->where('business_id', $businessId)
            ->where('received_at', '>=', $fromDay)
            ->where('received_at', '<', $toDay->addDay())
            ->delete();

        $written = 0;
        $offered = 0;
        $buffer = [];

        foreach (DB::cursor(self::STAGING_DISTINCT) as $staged) {
            /** @var array<string, mixed> $row */
            $row = get_object_vars($staged);

            // ⚠️ THE TIE-BREAK COLUMN IS NOT AN `l1_events` COLUMN. `SELECT *`
            // is what keeps this in step with `LIKE l1_events` above, and it is
            // also what makes this line necessary; naming the twenty-five
            // columns instead would be the second copy of the schema that
            // `LIKE` exists to avoid.
            unset($row['replay_seq']);

            $offered++;
            $buffer[] = $row;

            if (count($buffer) === self::STAGING_CHUNK) {
                // ⚠️ **THE INSERT IS STILL [[L1Loader]]'s AND THE DELETE IS
                // STILL THIS CLASS'S.** Live ingest became the second writer of
                // this table when the collector landed, and two insert paths
                // would be two derivations that agree until they do not — a
                // divergence `ByteIdenticalReplayTest` structurally cannot see,
                // because both of its runs come through here. Draining the
                // staging table with an `INSERT … SELECT` would have been one
                // statement and would have been that second path.
                $written += $this->loader->insert($buffer);

                $buffer = [];
            }
        }

        if ($buffer !== []) {
            $written += $this->loader->insert($buffer);
        }

        // ⛔ **THE SUBTRACTION IS EXACT ONLY BECAUSE THE CURSOR ABOVE IS
        // `DISTINCT ON`, WHICH IS THE WHOLE REASON THAT CLAUSE IS NOT A TIDY-UP.**
        // Every key it yields is distinct, and the range this business owns was
        // deleted two statements ago — so a row `insertOrIgnore` did not write
        // conflicted with a row **outside** the range, and there is no other
        // cause. Counting the drops before the dedupe would have counted a
        // retried beacon received twice on the *same* day, which is ordinary and
        // is what §11 row 5's idempotency is for.
        return ['written' => $written, 'suppressed' => $offered - $written];
    }

    private function rebuildL2(int $businessId, CarbonImmutable $fromDay, CarbonImmutable $toDay): int
    {
        DB::table('l2_fact_source_daily')
            ->where('business_id', $businessId)
            ->whereBetween('day', [$fromDay->toDateString(), $toDay->toDateString()])
            ->delete();

        // ⛔ EVERY RULE OF THIS AGGREGATE IS A REPRODUCIBILITY RULE.
        //
        //  · `AT TIME ZONE 'UTC'` before `date_trunc`, written out. Without it
        //    the day boundary is the server's `TimeZone` setting, so the same
        //    event rolls into a different day on a machine with a different
        //    `PGTZ` — and either side of a DST change it does so for only some
        //    of the rows, which is the version nobody spots.
        //  · ⛔ **NO `AT TIME ZONE 'UTC'` ON THE DAY BOUNDARY, AND THAT IS THE
        //    OPPOSITE OF THE OBVIOUS HARDENING.** `received_at` is a `timestamp`
        //    — the house convention `TimeTest` enforces — so it carries no zone
        //    and `date_trunc` on it reads no session setting. Writing
        //    `date_trunc('day', received_at AT TIME ZONE 'UTC')` would CONVERT it
        //    to `timestamptz` and make the day boundary depend on the
        //    connection's `TimeZone`, so the same event would roll into
        //    different days on different hosts — and into different days for
        //    only *some* rows, which is the version nobody spots. The
        //    hostile-session test in `ByteIdenticalReplayTest` is what holds
        //    this, and it fails on exactly that edit.
        //  · `coalesce(…, '(none)')`, because the grouping columns are the
        //    primary key and Postgres treats NULLs as distinct in a unique
        //    index — two "no source" rollups would both insert.
        //  · `count(*) FILTER (…)` rather than a sum of a boolean cast, so the
        //    facts stay integers. §5.4: bots are excluded from tenant-facing
        //    metrics and counted separately, so `bot_events` is not a subset of
        //    `events`.
        //  · No `ORDER BY`. An aggregate's *result set* is unordered and that is
        //    fine — the stored bytes do not depend on insert order, because the
        //    table has no sequence and the snapshot sorts explicitly. Adding one
        //    here would look like it mattered and hide that the snapshot is
        //    where ordering is actually pinned.
        $inserted = DB::affectingStatement(<<<'SQL'
            INSERT INTO l2_fact_source_daily
                (business_id, day, utm_source, utm_medium, events, sessions, visitors, bot_events)
            SELECT
                business_id,
                date_trunc('day', received_at)::date AS day,
                coalesce(utm_source, '(none)') AS utm_source,
                coalesce(utm_medium, '(none)') AS utm_medium,
                count(*) FILTER (WHERE NOT is_bot)                       AS events,
                count(DISTINCT session_id) FILTER (WHERE NOT is_bot)     AS sessions,
                count(DISTINCT anonymous_id) FILTER (WHERE NOT is_bot)   AS visitors,
                count(*) FILTER (WHERE is_bot)                           AS bot_events
            FROM l1_events
            WHERE business_id = ?
              AND date_trunc('day', received_at)::date BETWEEN ?::date AND ?::date
            GROUP BY 1, 2, 3, 4
        SQL, [$businessId, $fromDay->toDateString(), $toDay->toDateString()]);

        return $inserted;
    }

    /**
     * §8's conversion list, as the bound placeholders four marts share.
     *
     * ⛔ **DERIVED RATHER THAN TYPED OUT, BECAUSE THE LIST IS GOING TO GROW**
     * (decision 5146). [[ConversionType]]'s own docblock records the fifth kind
     * — §8's tenant-fired `_q.track('conversion')` — as arriving *"with the
     * tracking call"*. Until 2026-08-18 `l2_fact_conversion` read the enum and
     * `l2_fact_session`, `l2_fact_daily_tenant` and `l2_fact_page_daily` each
     * carried their own copy of the same four strings, so on that day the
     * conversion mart would have counted the new kind and the `conversions`
     * column of the other three would not. ⚠️ **Nothing would have failed**:
     * four marts disagreeing about what a conversion is renders as two
     * different numbers on one screen, which reads as a rounding question
     * rather than as a defect.
     *
     * ⚠️ **PLACEHOLDERS RATHER THAN AN INTERPOLATED `IN` LIST**, which is why
     * the three call sites are heredocs carrying `{$conversions}` and not the
     * values themselves. The strings are code-controlled today; building SQL
     * out of them would make that a property somebody has to keep true rather
     * than one the query never depends on.
     */
    private static function conversionTypePlaceholders(): string
    {
        return implode(', ', array_fill(0, count(ConversionType::cases()), '?'));
    }

    /**
     * `l2_fact_session` — §8's sessionization, aggregated to the session
     * grain. §8's boundary itself is not re-derived here: `resources/js/
     * pixel.js`'s `currentSession()` already implements it exactly and mints
     * `session_id` before an event ever reaches L1 (see the migration's
     * docblock). This groups the events that already share one.
     *
     * ⛔ **AND THAT LAST SENTENCE IS WHERE THE RANGE DEPENDENCE COMES FROM —
     * ADDED 2026-08-22 (7840–7859), NOT A CORRECTION OF ANYTHING WRONG ABOVE.**
     * Grouping *"the events that already share one"* means grouping the ones
     * inside `scoped`, and `scoped` is the range. `day` then falls out of
     * `min(received_at)` **within that group**, so replaying two days together
     * and replaying them one at a time produce different rows from identical L0.
     * §8's midnight clause is the browser's and is keyed on the client's
     * `occurred_at`; `day` here is keyed on **our** `received_at`, so the two
     * boundaries differ by the network and the queue whatever the browser does.
     * See the class docblock for the whole finding and
     * `tests/Feature/Warehouse/IncrementalRollUpTest.php` for the proof.
     *
     * ## ⛔ THE SPLIT IS GONE AND THE RANGE DEPENDENCE IS NOT — 8040, 2026-08-22
     *
     * ✅ **`day` no longer exists and a session is one row**, keyed
     * `(business_id, session_id)`, so the paragraph above describes a defect that
     * can no longer be represented: no cut of any range can mint a second row for
     * one `session_id`. ⛔ **What it does NOT do is make this derivation
     * range-independent, and reading the ruling that way is the error to avoid.**
     * `scoped` is still the range, so `pageviews`, `duration_s`, `active_s`,
     * `is_engaged` and `is_new` are all still computed over whatever was replayed
     * — a session replayed a day at a time now ends up describing **only the last
     * piece replayed**, where before it was two rows describing a piece each.
     * **That is a different wrongness of the same size, and it is deliberately
     * still pinned** by `IncrementalRollUpTest`'s first test rather than quietly
     * absorbed. A durable per-session fact is the only thing that would close it
     * and it is not this slice's (7935(b) for the `is_new` half of the same
     * argument).
     *
     * ⛔ **THE RANGE DELETE IS NO LONGER A PREDICATE ON THIS TABLE, AND IT COULD
     * NOT BE.** `whereBetween('day', …)` worked because `day` was in the key; with
     * the key `(business_id, session_id)` the row's own receipt watermark **moves
     * when the cut moves**, so any predicate on the row misses the row the INSERT
     * below is about to write and the INSERT raises `SQLSTATE[23505]`. What it
     * became is *"delete the sessions this range's L1 contains"* — the same set
     * `scoped` is built from, through {@see self::SESSION_RANGE}, so the delete
     * and the insert cannot drift. ⚠️ **A session with no event inside the range
     * is therefore left alone rather than deleted**, which is the intended
     * asymmetry: its row was derived from some other range and is that range's to
     * rebuild.
     */
    private function rebuildFactSession(int $businessId, CarbonImmutable $fromDay, CarbonImmutable $toDay): int
    {
        $range = self::SESSION_RANGE;

        DB::delete(<<<SQL
            DELETE FROM l2_fact_session
             WHERE business_id = ?
               AND session_id IN (
                   SELECT session_id FROM l1_events WHERE {$range}
               )
        SQL, [$businessId, $businessId, $fromDay->toDateString(), $toDay->toDateString()]);

        // ⛔ EVERY RULE OF THIS AGGREGATE IS A REPRODUCIBILITY RULE, ON
        // `rebuildL2()`'S OWN TERMS ABOVE.
        //
        //  · No `AT TIME ZONE 'UTC'` on any `timestamp` column — see the
        //    class docblock's warning and `rebuildL2()`'s comment: these
        //    columns carry no zone, and applying it would introduce the
        //    session-setting dependency it looks like it removes.
        //  · `DISTINCT ON (session_id) … ORDER BY … event_id COLLATE "C"` is
        //    the deterministic tie-break for "the entry/exit event of this
        //    session" when two events share a millisecond. Byte order, not
        //    the database's collation — the same reasoning `WarehouseSnapshot`
        //    already applies to its own `ORDER BY`.
        //  · `duration_s`/`active_s` are `extract(epoch FROM …)::bigint`, a
        //    single deterministic subtraction on two already-fixed
        //    timestamps — not a summed aggregate, so float non-associativity
        //    (the reason `double precision` is forbidden) does not apply; the
        //    `::bigint` cast is what keeps the *stored* value an integer
        //    regardless of the intermediate type.
        //
        // ⛔ **AND THAT BULLET ARGUED DETERMINISM WHILE READING AS SAFETY,
        // WHICH IS WHY NOBODY LOOKED FROM THE DAY THE MART WAS CREATED — CORRECTED 2026-08-23
        // (8224).** Every word of it is true and none of it bounds anything:
        // **coercing to a known TYPE does not bound the VALUE.** `started_at`
        // and `ended_at` are `min`/`max` of `occurred_at`, which is the
        // browser's own clock, and until 2026-08-23 [[L1Derivation]] bounded it
        // no further than `strtotime()` did. **One `POST` carrying two events,
        // one dated `1970-01-01` and one dated `9999-12-31`, produced a
        // `duration_s` of 253,402,300,799 and `SQLSTATE[22003]: integer out of
        // range` — reproduced, not derived** — which aborts this INSERT, hence
        // `replay()`'s transaction, hence every mart of the range, for ever,
        // because L0 is immutable. Two events. No rate limit involved.
        //
        //  · `least(…, self::SESSION_CEILING_SECONDS)` is the fix and it is
        //    `active_ms`'s own ceiling one bullet down, in seconds rather than
        //    milliseconds: §8 ends a session at UTC midnight, so no legitimate
        //    session reaches twenty-four hours. **A clamp rather than a wider
        //    column, and rather than a CHECK.** A wider column would store an
        //    eight-thousand-year session on a tenant's dashboard — the quiet
        //    wrong number this codebase keeps saying is the one a green suite
        //    keeps — and a CHECK on a derived table is not a rejected write, it
        //    is a permanently unreplayable range (5031). ⚠️ **There is no
        //    `greatest(…, 0)` beside it and that is deliberate**: `ended_at` is
        //    `max()` of the same set `started_at` is `min()` of, so a negative
        //    is unfalsifiable and a guard that cannot fail is what makes the
        //    next reviewer believe something is being checked (398). The sign
        //    is said once, in the database, by
        //    `l2_fact_session_counts_are_not_negative`.
        //  · `properties::jsonb->>'active_ms'` is a read-time cast inside the
        //    query, never a stored column — `l1_events.properties` stays
        //    `text`, per decision 4873.
        //
        // ⛔ **AND THAT LAST ONE IS THE ONLY COLUMN IN ANY MART READ OUT OF
        // CLIENT-CONTROLLED JSON, SO IT IS THE ONLY TOTAL EXTRACTION — READ
        // THIS BEFORE SIMPLIFYING IT BACK TO A CAST.** The pixel key is
        // public by design, `PixelCollector` type-checks only the web-vital
        // shape, and `L1Derivation::canonicalise()` copies scalars verbatim
        // — so `active_ms` arrives as whatever a stranger typed. A bare
        // `(… ->> 'active_ms')::bigint` raised `SQLSTATE[22P02]` on a
        // string, a boolean, an object and a `%.17G`-rendered float, and
        // that error aborts the **whole `INSERT`**, hence `replay()`'s
        // transaction, hence the range — for every session in it, not the
        // offending one. **L0 is immutable**, so the poisoned receipt is
        // archived for ever and every retry fails identically: one `POST`
        // would permanently deny a tenant their own warehouse. Validating
        // at the collector would not have helped, because it cannot reach
        // objects already in L0.
        //
        //  · `jsonb_typeof(… -> 'active_ms') = 'number'` is the whole type
        //    check, and it is deliberately strict: a JSON string of digits
        //    is refused too. `canonicalise()` renders every float as its
        //    `%.17G` **string** (decision 4867), so a fractional `active_ms`
        //    is already unreadable at this layer by construction, and
        //    reconstructing it would mean parsing that rendering back —
        //    the fragile thing 4867 exists to avoid.
        //  · `greatest(…, 0)` is not symmetry with the clamp above it. A
        //    negative survives every cast and lands in the column, because
        //    Postgres has no unsigned integer and Laravel's
        //    `unsignedInteger()` is a plain `integer` here — so `-100000`
        //    stored `active_s = -100` with no error at all. That is the one
        //    hostile value that produced a **wrong number** rather than a
        //    dead range, which makes it the one a green suite would have
        //    kept.
        //  · An unreadable value reads as **zero, not null**: §8's engaged
        //    rule is `≥10s active`, and a value we cannot read is not ten
        //    seconds of anything. Failing open here would mark sessions
        //    engaged on a number nobody can see.
        //  · `trunc()`/`least()` run on `numeric`, never on `bigint`, so no
        //    intermediate can overflow before the clamp applies. The
        //    ceiling is 24h in ms — §8 ends a session at UTC midnight, so
        //    no legitimate session reaches it — and since 2026-08-23 it is
        //    {@see self::SESSION_CEILING_SECONDS} rather than a second
        //    `86400000` typed out here, because `duration_s` above now needs
        //    the same number and two copies of one ceiling is how they come
        //    to disagree.
        //
        // ⛔ **AND `is_new` BELOW IS THE ONE EXPRESSION IN THIS FILE THAT READS
        // OUTSIDE THE RANGE — ADDED 2026-08-22 (7840–7859).** Its `NOT EXISTS`
        // walks every `l1_events` row the tenant has, not the scoped ones, which
        // is correct as a definition — *"has this visitor been seen before"* —
        // and makes the derived value a function of **what L1 still holds**
        // rather than of the range's L0. Once `WarehouseRetention` has expired
        // the days a visitor was first seen in, a replay of an untouched later
        // range derives them as new. That is not fixable by scoping this
        // subquery: narrowing it to the range would make every visitor new on
        // every single-day replay, which is worse and quieter. **The fix is a
        // stored first-seen fact per `anonymous_id`, which is a table this
        // schema does not have; raised in the decision rows and not built here.**
        $conversions = self::conversionTypePlaceholders();
        $sessionCeiling = self::SESSION_CEILING_SECONDS;

        $inserted = DB::affectingStatement(<<<SQL
            WITH scoped AS (
                SELECT * FROM l1_events WHERE {$range}
            ),
            entry AS (
                SELECT DISTINCT ON (session_id)
                       session_id, business_id, anonymous_id,
                       page_path AS entry_page_path,
                       utm_source AS entry_utm_source,
                       referrer_host AS entry_referrer_host,
                       device_type AS entry_device_type,
                       consent_state AS entry_consent_state
                  FROM scoped
                 ORDER BY session_id, occurred_at ASC, event_id::text COLLATE "C" ASC
            ),
            exitp AS (
                SELECT DISTINCT ON (session_id)
                       session_id, page_path AS exit_page_path
                  FROM scoped
                 ORDER BY session_id, occurred_at DESC, event_id::text COLLATE "C" DESC
            ),
            active AS (
                SELECT session_id,
                       max(
                           CASE WHEN jsonb_typeof(properties::jsonb -> 'active_ms') = 'number'
                                THEN least(
                                         greatest(trunc((properties::jsonb ->> 'active_ms')::numeric), 0),
                                         {$sessionCeiling} * 1000
                                     )
                           END
                       ) AS max_active_ms
                  FROM scoped
                 WHERE event_type = 'scroll_depth'
                 GROUP BY session_id
            ),
            agg AS (
                SELECT session_id,
                       business_id,
                       min(occurred_at)   AS started_at,
                       max(occurred_at)   AS ended_at,
                       min(received_at)   AS first_received_at,
                       count(*) FILTER (WHERE event_type = 'pageview')         AS pageviews,
                       count(*) FILTER (WHERE event_type IN ({$conversions}))    AS conversions,
                       bool_or(is_bot)    AS is_bot
                  FROM scoped
                 GROUP BY session_id, business_id
            )
            INSERT INTO l2_fact_session
                (business_id, session_id, anonymous_id, started_at, ended_at, first_received_at,
                 duration_s, active_s, pageviews, conversions, is_engaged, is_bot, is_new,
                 entry_page_path, exit_page_path, source_key, device_type, consent_state)
            SELECT
                agg.business_id,
                agg.session_id,
                entry.anonymous_id,
                agg.started_at,
                agg.ended_at,
                -- ⛔ **STORED BECAUSE IT IS THE ONLY SERVER CLOCK LEFT ON THIS
                -- ROW, AND BECAUSE `is_new` BELOW ALREADY NEEDED IT** (8040).
                -- Two things read the column: `SESSION_ROLLUP_DAY`, which is
                -- 8041's one place, and `WarehouseRetention`, which cannot
                -- expire a mart on `started_at` — that is `min(occurred_at)`,
                -- a client clock, and a laptop set to 2041 would produce a row
                -- no horizon could ever reach.
                agg.first_received_at,
                least(
                    extract(epoch FROM (agg.ended_at - agg.started_at))::bigint,
                    {$sessionCeiling}
                ) AS duration_s,
                -- INTEGER division, deliberately. `max_active_ms` is `numeric`
                -- after the clamp above, and `numeric / 1000` keeps its
                -- fraction — which Postgres then **rounds** on the way into an
                -- integer column, so 15,500 ms would store 16 seconds. The
                -- clamp is what makes this `::bigint` safe.
                (coalesce(active.max_active_ms, 0)::bigint / 1000) AS active_s,
                agg.pageviews,
                agg.conversions,
                (NOT agg.is_bot) AND (
                    coalesce(active.max_active_ms, 0) >= 10000
                    OR agg.pageviews >= 2
                    OR agg.conversions >= 1
                ) AS is_engaged,
                agg.is_bot,
                CASE
                    WHEN entry.anonymous_id IS NULL THEN true
                    ELSE NOT EXISTS (
                        SELECT 1 FROM l1_events p
                         WHERE p.business_id = agg.business_id
                           AND p.anonymous_id = entry.anonymous_id
                           AND p.received_at < agg.first_received_at
                    )
                END AS is_new,
                entry.entry_page_path,
                exitp.exit_page_path,
                coalesce(
                    nullif(entry.entry_utm_source, ''),
                    CASE
                        WHEN entry.entry_referrer_host IS NOT NULL AND entry.entry_referrer_host <> ''
                            THEN 'referral:' || entry.entry_referrer_host
                        ELSE '(direct)'
                    END
                ) AS source_key,
                entry.entry_device_type,
                entry.entry_consent_state
            FROM agg
            JOIN entry ON entry.session_id = agg.session_id
            JOIN exitp ON exitp.session_id = agg.session_id
            LEFT JOIN active ON active.session_id = agg.session_id
        SQL, [$businessId, $fromDay->toDateString(), $toDay->toDateString(), ...ConversionType::values()]);

        return $inserted;
    }

    /**
     * `l2_fact_conversion` — §8's attribution: last non-direct touch, 30-day
     * lookback, resolved here and frozen on the row.
     *
     * ⚠️ **READS `l2_fact_session`, WHICH MUST ALREADY BE REBUILT FOR THIS
     * RANGE** (see `replay()`'s ordering comment) **AND FOR EVERY PRIOR DAY
     * THE LOOKBACK MIGHT REACH.** The 30-day lookback is answered against
     * whatever `l2_fact_session` rows already exist in Postgres — which
     * persist across separate `replay()` calls exactly as `l1_events` does —
     * not against a re-scan of L0. **A tenant whose history before this range
     * was never replayed answers with a narrower lookback than a tenant whose
     * was**, honestly, because there is nothing else to attribute against.
     *
     * ⛔ **AND THE `own` FALLBACK WAS A PLAIN `LEFT JOIN` UNTIL 2026-08-22, WHICH
     * COULD ABORT A WHOLE RANGE WITH `SQLSTATE[23505]`** (7920–7922). It read
     * `LEFT JOIN l2_fact_session own ON own.business_id = e.business_id AND
     * own.session_id = e.session_id` — **no day predicate** — while
     * `l2_fact_session`'s primary key is `(business_id, day, session_id)`, so one
     * `session_id` legitimately holds **two** rows the moment a straddling
     * session is replayed a day at a time (7841). Two `own` rows fan the SELECT
     * out to two rows per conversion event, `l2_fact_conversion` is keyed
     * `(business_id, conversion_id)` and this INSERT carries no `ON CONFLICT`,
     * so the second copy raises a duplicate key, aborts `replay()`'s
     * transaction and takes **every mart of the range** down with it — the same
     * whole-range failure `active_ms` and the vitals `value` cast are guarded
     * against one method up. ⚠️ **Reachable with no other change**: replay day
     * D, replay D+1, then re-replay D when it holds a conversion in that
     * session. `IncrementalRollUpTest` replays strictly forward and never meets
     * it.
     *
     * ⚠️ **THE LATERAL PICKS THE PIECE OF THE SESSION THE CONVERSION FELL IN,
     * AND IT IS TODAY'S ANSWER WHEREVER TODAY HAD ONE.** `started_at` is
     * `min(occurred_at)` over the session's events **inside the range**, and the
     * converting event is one of them — `rebuildFactSession()` ran over the same
     * range moments earlier — so `os.started_at <= e.occurred_at` is true of the
     * piece containing the conversion in every single-replay case and false of a
     * piece minted by some *later* range. `ORDER BY … LIMIT 1` is what makes the
     * result single-valued rather than a promise that it is; `os.day` is the
     * tie-break and is unique per `(business_id, session_id)` by that primary
     * key, so the choice is deterministic and the byte-identical gate cannot see
     * it move.
     *
     * ⛔ **THE LATERAL IS GONE AND THE PARAGRAPH ABOVE IS KEPT BECAUSE IT IS THE
     * ARGUMENT FOR REMOVING IT — 2026-08-22 (8040).** There are no pieces: a
     * session is one row keyed `(business_id, session_id)`, so `own` is a plain
     * equi-join that emits **at most one row by primary key** rather than by an
     * ordering rule somebody has to keep true. ⛔ **And `os.started_at <=
     * e.occurred_at` was carried forward and then deleted, deliberately, because
     * under the new key it is unfalsifiable** — 398 exactly. The converting event
     * is in the range, `rebuildFactSession()` ran over that range moments earlier
     * with `session_id IS NOT NULL`, so the session's row exists and its
     * `started_at` is `min(occurred_at)` over a set **containing the converting
     * event**: the predicate is true by construction and no mutation of it can
     * redden a test. Keeping a check that cannot fail is what makes the next
     * reviewer believe something is being checked. What survives it is the
     * primary key, which is a stronger guarantee than the `LIMIT 1` was.
     *
     * ⚠️ **`days_to_convert` SILENTLY CHANGES MEANING FOR A STRADDLING SESSION,
     * AND IT IS PINNED RATHER THAN LEFT TO BE NOTICED.** It rides on
     * `own.started_at`, which was *"the start of the piece the conversion fell
     * in"* and is now *"the start of the session, over the replayed range"* — so a
     * session that began before midnight and converted after it used to measure
     * from the post-midnight piece and now measures from the session's real
     * beginning. **That is the more truthful answer and it is still a change**;
     * `RangeIndependenceTest` asserts the number so the next edit to this join
     * has to mean it.
     *
     * ⛔ **AN `ON CONFLICT DO NOTHING` ON THE INSERT WAS THE OTHER CANDIDATE AND
     * IS REFUSED** (7923). It would resolve the crash by picking one of two
     * fanned-out rows arbitrarily and would go on doing so for any *future*
     * fan-out — 398's shape, an outer guard that makes the inner one
     * unfalsifiable. `e` is one row per `(business_id, event_id)`, `touch` is
     * `LIMIT 1` and `own` is now `LIMIT 1`, so this SELECT emits exactly one row
     * per conversion **by construction**; leaving the INSERT bare is what makes
     * a regression in that construction loud.
     */
    private function rebuildFactConversion(int $businessId, CarbonImmutable $fromDay, CarbonImmutable $toDay): int
    {
        DB::table('l2_fact_conversion')
            ->where('business_id', $businessId)
            ->whereBetween('day', [$fromDay->toDateString(), $toDay->toDateString()])
            ->delete();

        $conversions = self::conversionTypePlaceholders();
        $window = $this->registry->int('warehouse.attribution_window_days');

        // ⚠️ `touch_count` TREATS A NULL `anonymous_id` AS EXACTLY ONE TOUCH
        // — the conversion's own session — RATHER THAN ZERO. §10's GPC
        // reading leaves `anonymous_id` null (no storage, no continuity), and
        // a plain `fs2.anonymous_id = e.anonymous_id` comparison is false for
        // NULL on both sides in SQL, which would silently undercount to zero.
        // ⛔ It deliberately does NOT use `IS NOT DISTINCT FROM` for the same
        // comparison in the lookback below: that would treat every
        // null-anonymous visitor as *the same* visitor as every other,
        // attributing strangers' sessions to each other.
        $inserted = DB::affectingStatement(<<<SQL
            INSERT INTO l2_fact_conversion
                (business_id, day, conversion_id, session_id, anonymous_id, conversion_type,
                 occurred_at, attributed_source_key, attributed_at, touch_count, days_to_convert, page_path)
            SELECT
                e.business_id,
                date_trunc('day', e.received_at)::date AS day,
                e.event_id AS conversion_id,
                e.session_id,
                e.anonymous_id,
                e.event_type AS conversion_type,
                e.occurred_at,
                coalesce(touch.source_key, own.source_key, '(direct)') AS attributed_source_key,
                e.occurred_at AS attributed_at,
                CASE
                    WHEN e.anonymous_id IS NULL THEN 1
                    ELSE (
                        SELECT count(DISTINCT fs2.session_id)
                          FROM l2_fact_session fs2
                         WHERE fs2.business_id = e.business_id
                           AND fs2.anonymous_id = e.anonymous_id
                           AND fs2.started_at <= e.occurred_at
                           AND fs2.started_at >= e.occurred_at - make_interval(days => {$window})
                    )
                END AS touch_count,
                least(
                    extract(epoch FROM (e.occurred_at - coalesce(touch.started_at, own.started_at, e.occurred_at)))::bigint
                    / 86400,
                    {$window}
                ) AS days_to_convert,
                e.page_path
            FROM l1_events e
            LEFT JOIN LATERAL (
                SELECT fs.source_key, fs.started_at
                  FROM l2_fact_session fs
                 WHERE fs.business_id = e.business_id
                   AND fs.anonymous_id = e.anonymous_id
                   AND fs.started_at <= e.occurred_at
                   AND fs.started_at >= e.occurred_at - make_interval(days => {$window})
                   AND fs.source_key <> '(direct)'
                 ORDER BY fs.started_at DESC, fs.session_id::text COLLATE "C" DESC
                 LIMIT 1
            ) touch ON e.anonymous_id IS NOT NULL
            LEFT JOIN l2_fact_session own
                   ON own.business_id = e.business_id
                  AND own.session_id  = e.session_id
            WHERE e.business_id = ?
              AND NOT e.is_bot
              AND e.event_type IN ({$conversions})
              AND date_trunc('day', e.received_at)::date BETWEEN ?::date AND ?::date
        SQL, [$businessId, ...ConversionType::values(), $fromDay->toDateString(), $toDay->toDateString()]);

        return $inserted;
    }

    /**
     * `l2_fact_daily_tenant` — the tenant-facing daily rollup, read from
     * `l2_fact_session` for the session-grain facts and from `l1_events` for
     * the conversion-type breakdown. See that migration's docblock for why
     * `is_engaged`/`is_bot`/`is_new` are read back rather than re-derived.
     *
     * ⚠️ **THE SESSION SIDE BUCKETS THROUGH {@see self::SESSION_ROLLUP_DAY} SINCE
     * 2026-08-22, AND THE ANSWER IS UNCHANGED** (8040, 8041). It read
     * `WHERE day BETWEEN … GROUP BY day`, off a stored column that was
     * `date_trunc('day', min(received_at))`; it now applies the same truncation to
     * `first_received_at` here. **Every number this method produces is the same
     * one it produced before**, on any range that was replayed whole — what
     * changed is that the day is computed rather than keyed, which is the whole of
     * what 8040 asked for.
     */
    private function rebuildFactDailyTenant(int $businessId, CarbonImmutable $fromDay, CarbonImmutable $toDay): int
    {
        DB::table('l2_fact_daily_tenant')
            ->where('business_id', $businessId)
            ->whereBetween('day', [$fromDay->toDateString(), $toDay->toDateString()])
            ->delete();

        $from = $fromDay->toDateString();
        $to = $toDay->toDateString();

        // A `FULL OUTER JOIN` on (day, business_id), because a day can carry
        // sessions with no matching conversion-type events (the ordinary
        // case) or, in principle, the reverse — and either side being empty
        // must not drop the day from the report.
        $conversions = self::conversionTypePlaceholders();
        $sessionDay = self::SESSION_ROLLUP_DAY;

        $inserted = DB::affectingStatement(<<<SQL
            WITH session_stats AS (
                SELECT {$sessionDay} AS day, business_id,
                       count(*) FILTER (WHERE NOT is_bot)                  AS sessions,
                       count(*) FILTER (WHERE NOT is_bot AND is_engaged)   AS engaged_sessions,
                       count(*) FILTER (WHERE is_bot)                      AS bot_sessions,
                       count(DISTINCT anonymous_id) FILTER (WHERE NOT is_bot)               AS users,
                       count(DISTINCT anonymous_id) FILTER (WHERE NOT is_bot AND is_new)    AS new_users
                  FROM l2_fact_session
                 WHERE business_id = ?
                   AND {$sessionDay} BETWEEN ?::date AND ?::date
                 GROUP BY 1, 2
            ),
            event_stats AS (
                SELECT date_trunc('day', received_at)::date AS day, business_id,
                       count(*) FILTER (WHERE event_type = 'pageview')          AS pageviews,
                       count(*) FILTER (WHERE event_type IN ({$conversions}))     AS conversions,
                       count(*) FILTER (WHERE event_type = 'phone_click')       AS phone_clicks,
                       count(*) FILTER (WHERE event_type = 'form_submitted')    AS form_submissions,
                       count(*) FILTER (WHERE event_type = 'directions_click')  AS directions_clicks
                  FROM l1_events
                 WHERE business_id = ?
                   AND NOT is_bot
                   AND date_trunc('day', received_at)::date BETWEEN ?::date AND ?::date
                 GROUP BY 1, 2
            )
            INSERT INTO l2_fact_daily_tenant
                (business_id, day, sessions, engaged_sessions, bot_sessions, users, new_users,
                 pageviews, conversions, phone_clicks, form_submissions, directions_clicks)
            SELECT
                coalesce(s.business_id, e.business_id),
                coalesce(s.day, e.day),
                coalesce(s.sessions, 0), coalesce(s.engaged_sessions, 0), coalesce(s.bot_sessions, 0),
                coalesce(s.users, 0), coalesce(s.new_users, 0),
                coalesce(e.pageviews, 0), coalesce(e.conversions, 0),
                coalesce(e.phone_clicks, 0), coalesce(e.form_submissions, 0), coalesce(e.directions_clicks, 0)
            FROM session_stats s
            FULL OUTER JOIN event_stats e ON e.day = s.day AND e.business_id = s.business_id
        SQL, [$businessId, $from, $to, ...ConversionType::values(), $businessId, $from, $to]);

        return $inserted;
    }

    /**
     * `l2_fact_page_daily` — `exits` read back from `l2_fact_session.exit_page_path`
     * on the same reasoning `rebuildFactDailyTenant()` gives for its own
     * session-grain columns.
     *
     * ⚠️ **`js_errors` IS `28` §4.3's FOURTH TRIGGER'S NUMERATOR AND IT SITS
     * HERE RATHER THAN IN A MART OF ITS OWN** (decision 5603). The spec asks for
     * *"JS error rate on affected pages"*, and the denominator of that rate —
     * `pageviews`, for the same page, on the same day, filtered the same way —
     * is already in this table. Two tables would be two grains and one join to
     * get wrong.
     *
     * ⛔ **AND THE TWO SIDES BUCKET ON DIFFERENT CLOCKS ON PURPOSE, WHICH IS WHY
     * THE JOIN IS `FULL OUTER`.** `page_stats` is per **event receipt**;
     * `exit_stats` is per **session**, through {@see self::SESSION_ROLLUP_DAY}
     * (8041). A session that started at 23:50 and whose exit page's beacon
     * arrived after midnight therefore puts its `exits` on the first day and its
     * `pageviews` on the second, and the exit-only row has nothing to join to.
     * `HandComputedFixtureTest`'s *"a page that is only ever an exit still gets a
     * row in the page rollup"* is the gate; a `LEFT JOIN` returns two rows where
     * three are owed. ⚠️ **8040 widens that divergence rather than closing it**
     * and the mutant stays dead — see `SESSION_ROLLUP_DAY` for the edit that
     * would revive it.
     */
    private function rebuildFactPageDaily(int $businessId, CarbonImmutable $fromDay, CarbonImmutable $toDay): int
    {
        DB::table('l2_fact_page_daily')
            ->where('business_id', $businessId)
            ->whereBetween('day', [$fromDay->toDateString(), $toDay->toDateString()])
            ->delete();

        $from = $fromDay->toDateString();
        $to = $toDay->toDateString();

        $conversions = self::conversionTypePlaceholders();
        $sessionDay = self::SESSION_ROLLUP_DAY;

        $inserted = DB::affectingStatement(<<<SQL
            WITH page_stats AS (
                SELECT date_trunc('day', received_at)::date AS day, business_id, page_path,
                       count(*) FILTER (WHERE event_type = 'pageview')                            AS pageviews,
                       count(DISTINCT anonymous_id) FILTER (WHERE event_type = 'pageview')         AS unique_views,
                       count(*) FILTER (WHERE event_type IN ({$conversions}))                          AS conversions,
                       count(*) FILTER (WHERE event_type = ?)                                     AS js_errors
                  FROM l1_events
                 WHERE business_id = ?
                   AND NOT is_bot
                   AND date_trunc('day', received_at)::date BETWEEN ?::date AND ?::date
                 GROUP BY 1, 2, 3
            ),
            exit_stats AS (
                SELECT {$sessionDay} AS day, business_id, exit_page_path AS page_path, count(*) AS exits
                  FROM l2_fact_session
                 WHERE business_id = ?
                   AND {$sessionDay} BETWEEN ?::date AND ?::date
                   AND NOT is_bot
                 GROUP BY 1, 2, 3
            )
            INSERT INTO l2_fact_page_daily
                (business_id, day, page_path, pageviews, unique_views, exits, conversions, js_errors)
            SELECT
                coalesce(p.business_id, x.business_id),
                coalesce(p.day, x.day),
                coalesce(p.page_path, x.page_path),
                coalesce(p.pageviews, 0),
                coalesce(p.unique_views, 0),
                coalesce(x.exits, 0),
                coalesce(p.conversions, 0),
                coalesce(p.js_errors, 0)
            FROM page_stats p
            FULL OUTER JOIN exit_stats x
                   ON x.day = p.day AND x.business_id = p.business_id AND x.page_path = p.page_path
        SQL, [
            ...ConversionType::values(),
            self::JS_ERROR_EVENT,
            $businessId, $from, $to,
            $businessId, $from, $to,
        ]);

        return $inserted;
    }

    /**
     * `l2_fact_vital_daily` — `28` §4.3's real-user speed measurements, as a
     * daily histogram of the `vital` events the pixel bundle already
     * collects.
     *
     * ⛔ **NO PERCENTILE IS COMPUTED HERE AND NONE IS STORED.** The creating
     * migration carries the whole argument; the short version is that a stored
     * percentile is the one derived value this gate cannot hold over (it
     * interpolates, it returns `double precision`, and any tie it breaks needs
     * an ordering rule that reads a collation) **and** that a daily p75 cannot
     * be rolled up into the 7-day-versus-14-day comparison §4.3 asks for.
     * `App\Services\Warehouse\SiteVitals` walks these buckets at read time.
     *
     * ⛔ **EVERY RULE OF THIS AGGREGATE IS A REPRODUCIBILITY RULE**, on
     * `rebuildL2()`'s own terms, plus one family this mart is the first to
     * meet.
     *
     *  · No `AT TIME ZONE 'UTC'` on `received_at` — see the class docblock.
     *  · The metric list, the unit scale, the bucket width and the ceiling are
     *    **bound from [[\App\Enums\WebVital]]** rather than typed into the SQL,
     *    which is `conversionTypePlaceholders()`'s reasoning applied to a
     *    second vocabulary: four marts once disagreed about what a conversion
     *    is (decision 5146), and a metric this query knew and the enum did not
     *    would be a mart column that silently stopped filling.
     *  · The `JOIN metrics` **is** the allowlist. A `metric` property that is
     *    not one of the four joins to nothing and is dropped — never bucketed
     *    under some other name, and never counted.
     *  · Integer division throughout, so there is no float anywhere in the
     *    derivation. `numeric` is exact and `bigint` is exact; the only
     *    inexact thing in the whole path is the bucket width itself, which is
     *    declared.
     *
     * ⛔ **AND IT IS THE SECOND TOTAL EXTRACTION OUT OF CLIENT-CONTROLLED JSON
     * IN THIS FILE — READ `rebuildFactSession()`'s `active_ms` COMMENT BEFORE
     * SIMPLIFYING ANY OF IT.** W23's lesson, arriving exactly where it said it
     * would: the pixel key is public, `L1Derivation::canonicalise()` copies
     * scalars verbatim, and a bare `(properties::jsonb ->> 'value')::numeric`
     * raises `SQLSTATE[22P02]` on a string, a boolean or an object — which
     * aborts the whole `INSERT`, hence `replay()`'s transaction, hence the
     * range, for every tenant row in it. **L0 is immutable**, so one hostile
     * `POST` would deny a tenant their own warehouse for ever.
     *
     *  · `jsonb_typeof(… -> 'value') = 'number'` is the ordinary arm: LCP, INP
     *    and TTFB arrive as integers straight from `Math.round()`.
     *  · ⚠️ **THE `'string'` ARM IS NOT DEFENSIVE — IT IS WHERE CLS LIVES**, and
     *    getting this wrong is the defect that would have mattered most.
     *    `L1Derivation::canonicalise()` renders every float as its `%.17G`
     *    **string** (decision 4867), and CLS is the only fractional metric — so
     *    the strict `= 'number'` test `active_ms` uses, copied here, would have
     *    discarded **every non-zero CLS measurement ever collected**, leaving a
     *    permanently empty CLS histogram that no test asserting "the mart has
     *    rows" would notice. `sending_health_windows` with a stopwatch on it.
     *  · The pattern refuses an exponent and caps the digits, which is what
     *    makes `::numeric` **total**: `'1e999999'::numeric` overflows and takes
     *    the range down with it, and a negative or an `INF` renders as a string
     *    this refuses. The pixel cannot produce a legitimate exponent form —
     *    `Math.round(cls * 1000) / 1000` bottoms out at `0.001`, which `%.17G`
     *    writes in full.
     *  · An unreadable value is **dropped, not zeroed**. A zero here is the
     *    fastest measurement possible, so failing open would answer a hostile
     *    receipt with a faster site. Dropping it lowers the sample count
     *    instead, which is what pushes a reading below
     *    `SiteVitals`' floor and into `InsufficientData` — the fail-closed
     *    direction on a claim.
     *  · A negative is dropped for the same reason and `greatest(…, 0)` is
     *    deliberately **not** used, which is the opposite of `active_s`'s
     *    handling one method up. There, zero was the safe reading ("not
     *    engaged"); here it is the flattering one.
     *  · A value above the ceiling clamps **up** to the ceiling rather than
     *    being dropped, because an implausibly slow page is still a slow page,
     *    and dropping it is the one direction that improves the number.
     */
    private function rebuildFactVitalDaily(int $businessId, CarbonImmutable $fromDay, CarbonImmutable $toDay): int
    {
        DB::table('l2_fact_vital_daily')
            ->where('business_id', $businessId)
            ->whereBetween('day', [$fromDay->toDateString(), $toDay->toDateString()])
            ->delete();

        $metricRows = implode(', ', array_fill(0, count(WebVital::cases()), '(?::text, ?::bigint, ?::bigint, ?::bigint)'));

        $bindings = [];

        foreach (WebVital::cases() as $metric) {
            $bindings[] = $metric->value;
            $bindings[] = $metric->unitScale();
            $bindings[] = $metric->bucketWidth();
            $bindings[] = $metric->ceiling();
        }

        $inserted = DB::affectingStatement(<<<SQL
            WITH metrics (metric, scale, width, ceiling) AS (
                VALUES {$metricRows}
            ),
            scoped AS (
                SELECT date_trunc('day', received_at)::date AS day,
                       business_id,
                       device_type,
                       properties::jsonb AS props
                  FROM l1_events
                 WHERE business_id = ?
                   AND NOT is_bot
                   AND event_type = ?
                   AND date_trunc('day', received_at)::date BETWEEN ?::date AND ?::date
            ),
            typed AS (
                SELECT s.day, s.business_id, s.device_type, m.metric, m.width, m.scale, m.ceiling,
                       CASE
                           WHEN jsonb_typeof(s.props -> 'value') = 'number'
                               THEN (s.props ->> 'value')::numeric
                           WHEN jsonb_typeof(s.props -> 'value') = 'string'
                                AND (s.props ->> 'value') ~ '^[0-9]{1,12}(\.[0-9]{1,20})?$'
                               THEN (s.props ->> 'value')::numeric
                       END AS raw_value
                  FROM scoped s
                  JOIN metrics m ON m.metric = (s.props ->> 'metric')
            ),
            measured AS (
                SELECT day, business_id, device_type, metric, width,
                       least(round(raw_value * scale), ceiling)::bigint AS units
                  FROM typed
                 WHERE raw_value IS NOT NULL
                   AND raw_value >= 0
            )
            INSERT INTO l2_fact_vital_daily
                (business_id, day, metric, device_type, bucket, samples)
            SELECT business_id,
                   day,
                   metric,
                   device_type,
                   ((units + width - 1) / width) * width AS bucket,
                   count(*) AS samples
              FROM measured
             GROUP BY 1, 2, 3, 4, 5
        SQL, [
            ...$bindings,
            $businessId,
            PixelCollector::VITAL_EVENT,
            $fromDay->toDateString(),
            $toDay->toDateString(),
        ]);

        return $inserted;
    }
}
