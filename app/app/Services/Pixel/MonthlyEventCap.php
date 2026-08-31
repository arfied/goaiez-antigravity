<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Enums\OperatorAlertKind;
use App\Models\PixelMonthlyUsage;
use App\Services\Config\DefaultsRegistry;
use App\Services\Ops\OperatorAlerts;
use App\Services\Warehouse\WarehouseRetention;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 4 — the monthly event cap, and the
 * counter and bell it never had (decision 5000s).
 *
 * §11: *"Monthly cap (`free_event_cap_monthly`). At cap: pageviews continue,
 * others dropped, `events_dropped++`, ops alerted once. Never bill, never
 * hard-fail."* `CLAUDE.md`'s standing warning about this codebase's commonest
 * defect is why the counter and the alert are built in the same class as the
 * gate rather than after it: *"`events_dropped` and the ops alert are exactly
 * that risk [a control with no writer] … build the writer and the reader in
 * the same slice, and assert the consequence — a row written is not a
 * suppression until something reads it back."* This class's own tests read
 * `PixelMonthlyUsage` back after calling {@see self::admit()} rather than
 * only asserting the boolean it returns, which is that assertion.
 *
 * ---------------------------------------------------------------------------
 * WHY A BATCH IS ADMITTED OR REFUSED WHOLE, NOT EVENT BY EVENT
 * ---------------------------------------------------------------------------
 * §11's own words — *"pageviews continue, others dropped"* — read as a
 * per-event decision, and the literal build would decode the payload, drop
 * some events, and archive the rest. ⛔ **THAT IS THE SAME MISTAKE THE HIPAA
 * GATE REFUSES TO MAKE, FOR THE SAME REASON** (decision 4874, `PixelCollector`'s
 * own docblock): the payload is archived as an opaque string and may never be
 * decoded and re-encoded, because a round trip changes key order, float
 * rendering and unicode escaping while every value still compares equal —
 * there is no way to strip some events and stay faithful. So this is a
 * *batch*-level admission, on `PixelCollector::carriesFormValue()`'s own
 * precedent: **a batch containing only `pageview` events is admitted through
 * at the cap; a batch containing anything else is refused whole**, and what
 * gets dropped is the whole batch's event count, which is the honest number
 * of events that failed to land rather than an estimate of which ones would
 * have.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHAT THIS CLASS BOUNDS, AND THE THREE THINGS IT DOES NOT — 2026-08-22
 * ---------------------------------------------------------------------------
 * §11 row 4 is a cap on **which event types keep landing**, and it was read as
 * though it were a cap on volume. It is not, and the difference is the whole of
 * decisions 7700–7719.
 *
 *  1. ⛔ **A PAGEVIEW-ONLY BATCH IS ADMITTED PAST THE CAP UNCONDITIONALLY AND
 *     FOR EVER, AND THAT IS CORRECT.** *"At cap: pageviews continue"* and
 *     *"never hard-fail"* are the same row of the same table, and
 *     {@see PixelCollector}'s own precedent is that a
 *     batch is admitted or refused whole. **Nothing below changes it.** A lane
 *     that "fixes" this by refusing pageviews at the cap has broken §11 row 4
 *     rather than tightened it.
 *  2. ⚠️ **SO THE ONLY CEILING ON ACCEPTED VOLUME IS THE RATE LIMIT, AND THAT
 *     IS KEYED ON A HASHED SOURCE ADDRESS AND NOTHING ELSE** —
 *     `App\Support\PixelRateLimits` says so itself: 300 beacons a minute per
 *     source, at `StorePixelBatchRequest::MAX_EVENTS` of fifty, which is 21.6
 *     million conformed events a day from one host, multiplied by the number of
 *     hosts a distributed caller has. **There is no per-tenant ceiling on
 *     accepted traffic anywhere in this application** (7704).
 *  3. ✅ **WHAT IS BOUNDED NOW IS HOW LONG THAT TRAFFIC COSTS US**, which is a
 *     different sentence and is the one this platform can actually make true:
 *     {@see WarehouseRetention} expires the derived
 *     layer at §18's own figures. ⛔ **L0 is not bounded at all** — the spec's
 *     seven years is a sentence with no mechanism behind it — so a batch
 *     admitted today is in object storage for ever, and that is an owner's
 *     ruling rather than a lane's constant. See that class.
 *
 * ⚠️ **AND THE OPERATOR NOW HEARS ABOUT IT** — see {@see self::alertOnce()},
 * which used to ring on one arm of two.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS IS A VOLUME CAP AND NEVER TOUCHES THE CREDIT LEDGER
 * ---------------------------------------------------------------------------
 * `CLAUDE.md`'s commercial-model section (3293) deletes the per-tenant
 * *dollar* cost cap outright in favour of the credit balance being the only
 * ceiling. **This is not that ceiling.** §11 row 4 is the free-tier
 * infrastructure budget of §2.1 — *"Collector must be the only hot path …
 * total marginal cost per tenant at cap: under $0.05/month"* — never billed
 * and never spending a credit product. 3297's enumeration of every money path
 * against its debit does not need this row added to it, because it was never
 * a money path.
 */
final class MonthlyEventCap
{
    public const string CAP_KEY = 'pixel.free_event_cap_monthly';

    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly OperatorAlerts $alerts,
    ) {}

    /**
     * Apply §11 row 4 to one received batch, recording the outcome.
     *
     * ⚠️ **CALLED AFTER `Tenancy::set()`, NEVER BEFORE.** The counter is
     * tenant-scoped row-level security and `Tenancy::idOrFail()` throws rather
     * than writing a null, which is `SendingHealth::increment()`'s own
     * precedent for exactly this shape of raw upsert.
     *
     * @return bool true when the batch may proceed to the collector's
     *              remaining gates, false when it was refused for the cap.
     */
    public function admit(int $eventCount, bool $pageviewOnly, ?CarbonImmutable $at = null): bool
    {
        $at ??= CarbonImmutable::now();
        $businessId = Tenancy::idOrFail();
        $month = $at->utc()->startOfMonth();

        $cap = max(0, $this->registry->int(self::CAP_KEY));

        // ⚠️ **ZERO OR UNSET MEANS UNCAPPED, THE OPPOSITE DIRECTION FROM A
        // DOLLAR CEILING** — see the registry entry's own docblock. §11 row 4's
        // "never hard-fail" forbids a fresh tenant's first pageview being
        // refused because nobody has set a figure yet.
        if ($cap === 0) {
            $this->increment($businessId, $month, totalDelta: $eventCount, droppedDelta: 0);

            return true;
        }

        // ⚠️ **ONE READ OF BOTH COUNTERS RATHER THAN TWO READS OF ONE.** The
        // alert below needs `events_dropped` and this gate needs `events_total`;
        // reading them together is what lets {@see self::alertOnce()} take its
        // figures as arguments instead of issuing a second SELECT after the
        // upsert. See that method's docblock.
        $usage = PixelMonthlyUsage::query()
            ->where('month', $month->toDateString())
            ->first(['events_total', 'events_dropped']);

        $currentTotal = $usage instanceof PixelMonthlyUsage ? $usage->events_total : 0;
        $currentDropped = $usage instanceof PixelMonthlyUsage ? $usage->events_dropped : 0;

        $atCap = $currentTotal >= $cap;

        if ($atCap && ! $pageviewOnly) {
            $this->increment($businessId, $month, totalDelta: 0, droppedDelta: $eventCount);
            $this->alertOnce($businessId, $cap, $currentTotal, $currentDropped + $eventCount);

            return false;
        }

        $this->increment($businessId, $month, totalDelta: $eventCount, droppedDelta: 0);

        if ($atCap) {
            $this->alertOnce($businessId, $cap, $currentTotal + $eventCount, $currentDropped);
        }

        return true;
    }

    /**
     * The atomic increment. `SendingHealth::increment()`'s own pattern:
     * Eloquent has no upsert-with-increment, so this is a raw upsert with the
     * addition done in SQL, which is correct under any amount of concurrency
     * because the database does the arithmetic rather than reading a value in
     * PHP and writing it back.
     *
     * ⚠️ **THE DELTAS ARE ADDED THROUGH POSTGRES' `excluded` PSEUDO-ROW, NEVER
     * INTERPOLATED INTO THE SQL** — decision 5000s. `SendingHealth::increment()`
     * writes `"… + 1"` because its delta genuinely is the literal one; here the
     * delta is a batch's event count, and `DB::raw("… + {$delta}")` would be a
     * query built by string concatenation from a value that arrived in an HTTP
     * request. It is an integer and it is safe today, which is exactly the kind
     * of reasoning that stops being true after one refactor widens the type.
     * `excluded.events_total` is the row the `INSERT` half already bound as a
     * parameter, so the number reaches Postgres once, bound, and the update
     * expression is a constant string.
     */
    private function increment(int $businessId, CarbonImmutable $month, int $totalDelta, int $droppedDelta): void
    {
        $now = CarbonImmutable::now();

        DB::table('pixel_monthly_usage')->upsert(
            [[
                'business_id' => $businessId,
                'month' => $month->toDateString(),
                'events_total' => $totalDelta,
                'events_dropped' => $droppedDelta,
                'created_at' => $now,
                'updated_at' => $now,
            ]],
            ['business_id', 'month'],
            [
                'events_total' => DB::raw('pixel_monthly_usage.events_total + excluded.events_total'),
                'events_dropped' => DB::raw('pixel_monthly_usage.events_dropped + excluded.events_dropped'),
                'updated_at' => $now,
            ],
        );
    }

    /**
     * §11 row 4's "ops alerted once" — `OperatorAlerts::raise()`'s own
     * dedup-per-quiet-window is the "once", so this class does not need a
     * second mechanism for the same property.
     *
     * ---------------------------------------------------------------------
     * ⛔ IT RINGS ON REACHING THE CAP, NOT ON THE REFUSAL — CORRECTED
     * 2026-08-22 (7700–7719)
     * ---------------------------------------------------------------------
     * This method was called from **one** arm of {@see self::admit()}: the one
     * that refuses a batch. §11 row 4's own sentence conditions the alert on
     * neither arm — *"At cap: pageviews continue, others dropped,
     * `events_dropped++`, **ops alerted once**"* — the subject of every clause
     * is *at cap*, and only the middle clause is about dropping.
     *
     * ⛔ **SO A TENANT EMITTING NOTHING BUT `pageview` EVENTS PASSED THE CAP
     * SILENTLY, FOR EVER, AND THAT IS THE LOUDEST TRAFFIC THERE IS.** Every
     * such batch is admitted — correctly, because *"pageviews continue"* and
     * *"never hard-fail"* are the same row — and the old placement meant the
     * one artefact that would have told an operator fired only if some
     * *other* event type happened to arrive. A tenant at ten times the cap and
     * a tenant at the cap looked identical from outside: no reject row,
     * because the traffic was accepted; no bell, because nothing was dropped;
     * `events_total` climbing in a table nothing measures.
     *
     * ⚠️ **ONE KIND, ONE SUBJECT, ONE SUMMARY — AND NO NEW
     * [[\App\Enums\OperatorAlertKind]] CASE** (7023's rule, and this wave's
     * file boundaries). `PixelMonthlyCapReached` already means *"this tenant is
     * at its monthly pixel event cap"*, and both arms are that. The summary
     * therefore describes the **regime** rather than this batch, so it is true
     * whichever arm rang it; which arm it was, and how far past the cap the
     * tenant is, are in the context.
     *
     * ⚠️ **THE COST, STATED** — `IngestRejects`' docblock made a version of
     * this claim that stopped being true, so this one is written against the
     * adversarial case rather than the accidental one. On the admitted arm
     * this runs **per request** for as long as a tenant stays over its cap,
     * and it costs one `OperatorAlerts::alreadyRang()` — a single indexed
     * `exists()` on `(kind, subject, fired_at)`, **O(1) in the size of
     * anything the caller controls**. That is the property that matters and it
     * is the one `IngestRejects::recentRejects()` does not have.
     *
     * ⚠️ **THE FIGURES ARE PASSED IN RATHER THAN RE-READ, WHICH REMOVES A
     * QUERY RATHER THAN ADDING ONE.** {@see self::admit()} has already read
     * the row it is deciding on; adding this batch's delta to what it read is
     * the same number the row now holds, and a second `SELECT` after the
     * upsert would be a second read of a value we computed. ⛔ **They can be
     * a batch behind under concurrency and that is correct for a bell**: an
     * alert saying a tenant is 1,900,000 events past a 500,000 cap does not
     * become wrong because a racing request made it 1,900,050.
     *
     * ⚠️ **`events_total` IS CARRIED AS WELL AS `events_dropped`, AND ON THIS
     * ARM IT IS THE ONLY ONE THAT MOVES.** `CLAUDE.md`'s sharper half of 272
     * is that a row is not a suppression until something reads it, and
     * `events_dropped` is permanently zero for a pageview-only flood — so a
     * bell carrying only that figure would be `sending_health_windows`'
     * permanently-zero rate wearing a third constant. The number an operator
     * actually needs here is *how far past the cap*, which is `events_total`.
     */
    private function alertOnce(int $businessId, int $cap, int $eventsTotal, int $eventsDropped): void
    {
        $this->alerts->raise(
            OperatorAlertKind::PixelMonthlyCapReached,
            subject: (string) $businessId,
            summary: 'A tenant has reached its monthly pixel event cap: pageviews keep landing and every '
                .'other event type is being dropped.',
            context: [
                'business_id' => $businessId,
                'cap' => $cap,
                'events_this_month' => $eventsTotal,
                'events_dropped_this_month' => $eventsDropped,
            ],
        );
    }
}
