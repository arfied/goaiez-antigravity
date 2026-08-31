<?php

declare(strict_types=1);

namespace App\Services\Sms;

use App\Console\Commands\RollUpNumberHealth;
use App\Enums\ErrorBucket;
use App\Enums\InboundKeyword;
use App\Enums\NumberState;
use App\Enums\OutreachChannel;
use App\Enums\OutreachStatus;
use App\Models\DlrErrorBucket;
use App\Models\InboundMessage;
use App\Models\NumberHealthDaily;
use App\Models\OutreachMessage;
use App\Models\PhoneNumber;
use App\Services\Config\DefaultsRegistry;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * THE ONE SCORER — row 4 slice 6 phase 2, doc `51` §4, I45.
 *
 * ⚠️ **THIS CLASS IS WHAT PHASE 1'S MIGRATION CLAIMED AHEAD OF ITSELF, AND THAT
 * CLAIM WAS DELETED RATHER THAN LEFT TO ROT** (1623). `create_phone_numbers_
 * table.php` named `NumberHealthService` as a writer on the day it did not
 * exist; the fix removed the sentence instead of stubbing the class. This is
 * the class, built once the ground under the claim is real, and the claim is
 * restored on `phone_numbers.health_score` and `number_health_daily`'s own
 * migrations rather than copied back onto the deleted one.
 *
 * I45: *"One `NumberHealthService`. A second scorer or a parallel quarantine
 * path anywhere … is a CI grep failure."* Held by a `MessagingTest` chokepoint
 * lint on the class *name* — nothing outside this file may reference
 * {@see NumberHealthDaily}, {@see DlrErrorBucket}, or the nine
 * `numbers.health_weight_*` / `numbers.degraded_below_score` /
 * `numbers.min_sends_quarantine` / `numbers.min_sends_stop` /
 * `numbers.quarantine_stop_pct` / `numbers.quarantine_failure_pct` registry
 * keys — 1633's shape, one layer down. **A second lint holds the other half of
 * I45**: nothing outside this file may name `NumberState::Quarantined` as a
 * transition target, so the "parallel quarantine path" clause is mechanical
 * rather than remembered.
 *
 * ## What this class builds, and what it deliberately does not
 *
 * §4's signals, score and daily rollup — **and, since 2026-08-14 (AG6), §5.2's
 * auto-quarantine triggers** ({@see self::evaluateQuarantine()}).
 *
 * **Still not built, and named so nobody reads the absence as a gap:**
 *
 *  - **§5.1's Degraded transition.** `numbers.degraded_below_score` is seeded
 *    and still has no reader. Its only consumer is §6.2's reduced rotation
 *    weight, which is phase 4 — a Degraded writer with nothing that weighs
 *    Degraded differently is `CLAUDE.md`'s writerless-control shape one level
 *    up, and a bare score comparison with no hysteresis would flap a number
 *    across the line every hour. `NumberState::Degraded` has no writer at all
 *    to match — not a factory, not a seeder, nothing walks a row there —
 *    checked 2026-08-23 (8690).
 *    ⛔ **AND THIS PARAGRAPH IS WHY THE CHOKEPOINT'S GUARD-ON-THE-GUARD WENT
 *    SOFT.** That arm — *"every key must actually appear in the one scorer, or
 *    the lint is confining a string nothing reads"* — read **raw** source
 *    until 2026-08-23, and the only three occurrences of this key in this file
 *    are the three docblock mentions above and below. **It was green on the
 *    strength of the paragraph explaining its own absence**, which is 2015 in
 *    the file 2015 is about. It reads stripped source now, and the key is
 *    carried in a named `$awaitingReader` list that reddens the build the day
 *    phase 3 gives it a reader in code — so the sentence "still has no reader"
 *    stops being something a person has to keep true.
 *  - ⛔ **§5.3's COOLDOWN RECOVERY WAS UNBUILT AND IS NOT — CORRECTED
 *    2026-08-20 (6420).** This entry read *"nothing counts
 *    `quarantine_cooldown_days` and walks a rested number back, so the only way
 *    out of a quarantine is `numbers:release-quarantine`"*. `NumberRecovery`
 *    and `numbers:recover-rested` count it and walk the number back —
 *    `quarantined → recovering` on the cooldown, then `recovering → active` on
 *    the re-entry climb. ⚠️ **THE SECOND HOP IS WHY IT MATTERS TO THIS CLASS**:
 *    `recovering` is outside `EVALUABLE` and `active` is inside it, so that hop
 *    is what puts a released number back within reach of the triggers below —
 *    3988's *"an eager containment converts itself into a disabled one, one
 *    release at a time"*, closed. ⚠️ **Doc 51 §5.3's pool-alarm hold is
 *    deliberately not implemented** and `NumberRecovery`'s docblock argues why.
 *    ⚠️ **The warmup CAPS are still unbuilt** — only the step's duration is
 *    read — so a recovering number sends at full capacity throughout.
 *  - **§5.4's second strike, §5.5's retire/park, §7's pool alarm, §6's
 *    rotation and sticky sender, §8's Text Health card.**
 *
 * ⚠️ **`phone_numbers.health_score` STILL HAS NO READER.** The quarantine
 * triggers below read *rates*, never the score — §5.2's formula is stated in
 * rates, and reading the score instead would let a resting number's decaying
 * score re-confirm its own quarantine. Rotation weighting is what finally
 * reads that column, and it is phase 4.
 *
 * ## `sends` and `accepted` gathering is tenant-aware; scoring is not
 *
 * {@see self::outreachCounts()} runs inside whatever tenant the caller has
 * already established (`Tenancy::set()`/`actingAs()`) — it is an ordinary
 * scoped `OutreachMessage` query, RLS beneath it like any other. **This class
 * never resolves a tenant and never loops across one**; the number being
 * scored may be a tenant's own (one tenant, one call) or the shared Lane A
 * pool number (many tenants' contributions, summed by the caller through
 * repeated calls under `Tenancy::actingAs()` — see {@see RollUpNumberHealth}
 * for why that enumeration cannot live here). {@see self::inboundCounts()} is
 * the opposite shape on purpose: `inbound_messages` carries no tenant and no
 * RLS at all, so STOP and reply counts for a number are read **once**,
 * regardless of how many tenants share it — reading them once per tenant
 * inside a loop would multiply the shared pool's STOP count by however many
 * tenants happen to use it.
 *
 * ## The 7-day window reads `number_health_daily`, never raw events
 *
 * Doc 51 §4.4: *"Score caches on `phone_numbers.health_score` … `number_health_
 * daily` is the truth."* {@see self::recompute()} sums the six **stored**
 * `number_health_daily` rows before today plus the in-memory `$today` totals
 * the caller just gathered — it never re-queries `outreach_messages` or
 * `inbound_messages` for a historical day. A number whose raw messages from
 * six days ago have since been pruned still scores correctly from what was
 * published at the time.
 *
 * ## Hourly recompute is the daily close-out
 *
 * Doc 51 §4.4 asks for "hourly rolling recompute … daily close-out row at
 * local midnight." `number_health_daily` carries no "closed" flag in doc 51
 * §10's own column list, so there is no state a separate midnight trigger
 * would flip. This class always recomputes **today's** row from scratch, never
 * increments it; once the calendar date turns over, that row is never touched
 * again by any later call, which is what "closing" means here. A second,
 * midnight-only code path would be decorative given there is nothing for it to
 * do that the next hourly run does not already do.
 *
 * ⚠️ **"TODAY" IS THE APPLICATION'S CONFIGURED TIMEZONE, NOT A PER-TENANT
 * LOCAL MIDNIGHT.** Doc 51 §4.4 says "local midnight"; `phone_numbers` carries
 * no timezone for either the shared pool or a tenant's own number in this
 * schema, so there is nothing to key a per-tenant midnight on. A documented
 * simplification rather than an oversight — the boundary is at most a few
 * hours off from a true per-tenant midnight, which does not change which
 * *day* the vast majority of a day's traffic falls on.
 */
final class NumberHealthService
{
    /**
     * Doc 51 §4.3's blend: "70% 7-day / 30% 24-hour." Not a registry key — see
     * the class docblock on why only the eight weights/thresholds are.
     */
    private const float SEVEN_DAY_WEIGHT = 0.7;

    private const float ROLLING_DAY_WEIGHT = 0.3;

    /**
     * Doc 51 §4.3's `min(reply_rate/0.05, 1)` — the reply rate that earns the
     * full engagement weight. Not a registry key, for the same reason as the
     * blend above.
     */
    private const float REPLY_RATE_CAP = 0.05;

    /**
     * How many opt-outs the STOP trigger must be arithmetically unable to fire
     * on, whatever `numbers.quarantine_stop_pct` is set to.
     *
     * ⛔ **NOT A REGISTRY KEY, AND THE REASON IS 3986'S.** It is a property of
     * the arithmetic rather than a policy: an Ops-editable *"how few opt-outs
     * may stop every text on the platform"* has no setting anybody would want
     * other than the one {@see self::minDeliveredForStopTrigger()} already
     * computes from the threshold. That method's docblock carries the argument
     * for why this is 2 and not 1, 3 or 4.
     */
    private const int STOP_NOISE_FLOOR_K = 2;

    /**
     * The states a quarantine trigger may be evaluated against — doc 51 §5.2's
     * *"evaluated on the rolling window"*, made explicit about which numbers
     * that means.
     *
     * ⛔ **`recovering` IS ABSENT AND ITS ABSENCE IS THE LOAD-BEARING PART.**
     * The window a trigger reads is the last 24 hours, and for a number that
     * was released moments ago **that window still contains the very traffic
     * that quarantined it** — so evaluating a recovering number would
     * re-quarantine it inside the hour, every time, and make the release
     * command useless. §5.3's real re-entry is a `quarantine_cooldown_days`
     * wait plus a warmup climb (phase 4); by the time that exists, the window
     * is entirely post-quarantine and `recovering → active` puts the number
     * back under this list on its own.
     *
     * ⚠️ **THE CONSEQUENCE IS STATED RATHER THAN HIDDEN**: until phase 4, a
     * released number that is *genuinely* still bad will keep sending until a
     * person looks. That is the trade the release command buys, and §5.4's
     * second-strike rule is what closes it.
     *
     * The four sendable states are `warming`, `active`, `degraded` and
     * `recovering` ({@see NumberState::maySend()}); this list is deliberately
     * not derived from that method, because the two answer different questions
     * and a number that may send is not automatically a number whose recent
     * history means anything.
     *
     * @var list<NumberState>
     */
    private const array EVALUABLE = [
        NumberState::Warming,
        NumberState::Active,
        NumberState::Degraded,
    ];

    public function __construct(
        private readonly DefaultsRegistry $registry,
        private readonly NumberLifecycle $lifecycle,
    ) {}

    /**
     * `outreach_messages` counts for one number over one window, inside
     * whatever tenant the caller has already established.
     *
     * ⚠️ **TENANT-SCOPED BY THE CALLER, NOT BY THIS METHOD.** It is an
     * ordinary `OutreachMessage::query()` — the model's `BelongsToTenant` scope
     * and RLS both apply beneath it exactly as they would for any other query
     * against this table. Calling it under no tenant returns every row's
     * global scope refusing to run, per `BelongsToTenant`'s own contract — the
     * same as any other query against this table with no tenant resolved.
     */
    public function outreachCounts(int $numberId, Carbon|CarbonImmutable $from, Carbon|CarbonImmutable $to): NumberHealthSignals
    {
        $row = OutreachMessage::query()
            ->leftJoin('dlr_error_buckets', 'dlr_error_buckets.error_name', '=', 'outreach_messages.error_message')
            ->where('outreach_messages.number_id', $numberId)
            ->where('outreach_messages.channel', OutreachChannel::Sms->value)
            ->whereBetween('outreach_messages.created_at', [$from, $to])
            ->selectRaw(
                'count(*) as sends, '
                .'count(*) as accepted, '
                .'count(*) filter (where outreach_messages.status = ?) as delivered, '
                .'count(*) filter (where outreach_messages.status = ? and coalesce(dlr_error_buckets.bucket, ?) = ?) as failed_filtered, '
                .'count(*) filter (where outreach_messages.status = ? and coalesce(dlr_error_buckets.bucket, ?) <> ?) as failed_other, '
                // The names the coalesce above is silently answering `other`
                // for — see the log below.
                .'string_agg(distinct outreach_messages.error_message, \',\') filter ('
                .'where outreach_messages.status = ? and outreach_messages.error_message is not null '
                .'and dlr_error_buckets.bucket is null) as unmapped',
                [
                    OutreachStatus::Delivered->value,
                    OutreachStatus::Failed->value, ErrorBucket::Other->value, ErrorBucket::Filtered->value,
                    OutreachStatus::Failed->value, ErrorBucket::Other->value, ErrorBucket::Filtered->value,
                    OutreachStatus::Failed->value,
                ],
            )
            ->first();

        // `getAttribute()` rather than a property read — the aggregate is not a
        // column on the model, which is the pattern the by-class loop below
        // already uses.
        $this->observeUnmapped($row?->getAttribute('unmapped'));

        $byClass = [];

        foreach (
            OutreachMessage::query()
                ->where('number_id', $numberId)
                ->where('channel', OutreachChannel::Sms->value)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('coalesce(purpose, ?) as class_key, count(*) as total', ['unspecified'])
                ->groupBy('class_key')
                ->get() as $group
        ) {
            $byClass[(string) $group->getAttribute('class_key')] = (int) $group->getAttribute('total');
        }

        return new NumberHealthSignals(
            sends: (int) ($row->sends ?? 0),
            accepted: (int) ($row->accepted ?? 0),
            delivered: (int) ($row->delivered ?? 0),
            failedFiltered: (int) ($row->failed_filtered ?? 0),
            failedOther: (int) ($row->failed_other ?? 0),
            byClass: $byClass,
        );
    }

    /**
     * Say when a carrier error name matched no row in `dlr_error_buckets`.
     *
     * ⛔ **THE SILENT DEFAULT IS EXACTLY WHAT LET 1650 LIVE FOR A WHOLE SLICE**
     * (3794).
     * `coalesce(dlr_error_buckets.bucket, 'other')` answers `other` for a name
     * nobody has mapped, which is indistinguishable — in the arithmetic and in
     * every test — from a name deliberately mapped to `other`. That is how
     * `dlr_error_buckets` came to ship with no `filtered` row at all while the
     * filtered rate read a flat zero for every number in production, for ever,
     * with a green suite. **The bucket table is a vocabulary of somebody else's
     * strings**, and a vendor that adds one, renames one, or sends a
     * `REJECTED`-group report whose `error` object is populated differently
     * (3763 leaves that open) is not a hypothetical.
     *
     * ⚠️ **A LOG LINE RATHER THAN A REFUSAL.** An unknown error name is not a
     * reason to stop scoring — `other` is the conservative bucket and the number
     * is still counted as failed. What is wrong is doing it quietly.
     *
     * ⚠️ **THE NAME IS SAFE TO LOG AND THE MESSAGE IS NOT.** `DeliveryReceipts`
     * stores the vendor's status *name* precisely because the description is
     * prose that can quote a destination number back at us; this column is a
     * fixed enumeration of `EC_…` strings, which is what makes it printable at
     * all. **Never widen this to the row, the reference, or the recipient.**
     *
     * ⚠️ **THE VOLUME IS ONE LINE PER WINDOW PER *CALL*, AND THE SHARED POOL
     * NUMBER IS WHERE THAT IS NOT TWO AN HOUR.** A tenant-owned number is
     * scored once an hour over two windows, so at most two lines.
     * {@see RollUpNumberHealth} sums the shared Lane A number across every
     * tenant, calling this once per tenant per window — so an unmapped name
     * that reached many tenants is loud in proportion to how widely it reached.
     * That is the right direction for a vocabulary gap, and stating it is
     * better than promising a ceiling this method does not control.
     */
    private function observeUnmapped(mixed $names): void
    {
        if (! is_string($names) || trim($names) === '') {
            return;
        }

        Log::warning('A carrier delivery receipt named an error this application has not bucketed.', [
            'error_names' => array_values(array_unique(array_filter(array_map(
                static fn (string $name): string => trim($name),
                explode(',', $names),
            )))),
        ]);
    }

    /**
     * `inbound_messages` counts for one number over one window.
     *
     * ⚠️ **NEVER CALL THIS INSIDE A PER-TENANT LOOP.** `inbound_messages`
     * carries no tenant and no RLS (`InboundMessage`'s own docblock), so this
     * answers the same total no matter which tenant is currently established —
     * calling it once per tenant while accumulating the shared pool's rollup
     * would multiply the true STOP count by the number of tenants iterated.
     * Call it exactly once per number per window; see
     * {@see RollUpNumberHealth}.
     *
     * ⚠️ **MATCHED WITH AND WITHOUT A LEADING `+`.** `InfobipInboundController`
     * records `to_number` exactly as Infobip's webhook sent it, unnormalised —
     * `InboundStopTest`'s own fixtures show Infobip's wire format carries no
     * `+` (`'to' => '15550001111'`), where `phone_numbers.e164` is written in
     * E.164 form, with one (`'+15550001111'`, `NumberInventoryTest`'s own
     * fixtures). Nothing consumed `to_number` before this method, so that gap
     * was never exercised; matching both forms is the reading that cannot be
     * wrong regardless of which one production traffic turns out to send.
     */
    public function inboundCounts(string $e164, Carbon|CarbonImmutable $from, Carbon|CarbonImmutable $to): NumberHealthSignals
    {
        $row = InboundMessage::query()
            ->whereIn('to_number', [$e164, ltrim($e164, '+')])
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw(
                'count(*) filter (where keyword = ?) as stops, '
                .'count(*) filter (where keyword <> ?) as replies',
                [InboundKeyword::Stop->value, InboundKeyword::Stop->value],
            )
            ->first();

        return new NumberHealthSignals(
            stops: (int) ($row->stops ?? 0),
            replies: (int) ($row->replies ?? 0),
        );
    }

    /**
     * Write today's row and, unless the sample is too small to trust, refresh
     * `phone_numbers.health_score`.
     *
     * $today and $rollingDay are gathered by the caller — see
     * {@see self::outreachCounts()} and {@see self::inboundCounts()} — because
     * *how* they are gathered differs by who is scoring the number (a single
     * tenant, or every tenant that shares the pool), and that decision does
     * not belong in the one class doc 51's I45 wants free of a second reason
     * to change.
     *
     * ⚠️ **IT RETURNS THE QUARANTINE REASON, OR NULL WHEN NOTHING FIRED**
     * (3793). `29` §2 requires every automated action to reach the activity
     * feed, and a recompute that stops a tenant's texting is one — but only when
     * it actually quarantined something, which is the caller's to know. Returning
     * it rather than recording it here keeps this class free of the tenant
     * resolution its own docblock says it never performs.
     */
    public function recompute(PhoneNumber $number, NumberHealthSignals $today, NumberHealthSignals $rollingDay): ?string
    {
        $date = CarbonImmutable::now()->startOfDay();

        $priorDays = NumberHealthDaily::query()
            ->where('number_id', $number->id)
            ->where('date', '>=', $date->subDays(6)->toDateString())
            ->where('date', '<', $date->toDateString())
            ->get();

        $sevenDayTotal = $priorDays
            ->reduce(
                static fn (NumberHealthSignals $carry, NumberHealthDaily $day): NumberHealthSignals => $carry->add(new NumberHealthSignals(
                    sends: $day->sends,
                    accepted: $day->accepted,
                    delivered: $day->delivered,
                    failedFiltered: $day->failed_filtered,
                    failedOther: $day->failed_other,
                    stops: $day->stops,
                    replies: $day->replies,
                )),
                // Today's own totals are already in memory — starting the
                // reduction here means this never re-reads the row it is
                // about to write.
                $today,
            );

        $threshold = $this->registry->int('numbers.min_sends_quarantine');

        // Doc 51 §4.3: "numbers with < min_sends_quarantine sends in-window
        // hold their last score (or 100 if new)." `phone_numbers.health_score`
        // is NOT NULL with a database default of 100, so "or 100 if new"
        // follows from simply not touching the column rather than needing its
        // own branch.
        //
        // ⚠️ TWO CONDITIONS, NOT ONE, AND THE SECOND IS NOT IN §4.3. A window
        // can clear the send threshold and still have decided nothing — 60
        // sends whose receipts have not landed is the ordinary state of an
        // hourly recompute, not an edge case. Scoring that as a 0% delivered
        // rate marks a number down for having sent something recently, so the
        // absence of outcomes holds exactly as a small sample does. Each half
        // reddens its own test.

        $score = $sevenDayTotal->sends < $threshold || ! $sevenDayTotal->hasEvidence()
            ? $number->health_score
            : $this->blend($sevenDayTotal, $rollingDay);

        NumberHealthDaily::query()->updateOrCreate(
            ['number_id' => $number->id, 'date' => $date->toDateString()],
            [
                'sends' => $today->sends,
                'accepted' => $today->accepted,
                'delivered' => $today->delivered,
                'failed_filtered' => $today->failedFiltered,
                'failed_other' => $today->failedOther,
                'stops' => $today->stops,
                'replies' => $today->replies,
                // The row's own score mirrors whatever was actually in force
                // after this recompute — held or freshly blended — so a
                // historical read of this table never shows a score
                // `phone_numbers` did not really carry at that moment.
                'score' => $score,
                'by_class' => $today->byClass === [] ? null : $today->byClass,
            ],
        );

        if ($score !== $number->health_score) {
            $number->update(['health_score' => $score]);
        }

        // ⚠️ **AFTER THE ROW, AND ON THE ROLLING WINDOW RATHER THAN THE 7-DAY
        // TOTAL.** Doc 51 §5.2's triggers are stated over 24 hours; the
        // seven-day sum above is the *score's* window and using it here would
        // keep a number quarantined on the strength of a week-old bad
        // afternoon. Writing the daily row first means the evidence for a
        // quarantine is already durable when the transition it caused is
        // filed beside it.
        return $this->evaluateQuarantine(
            $number,
            $rollingDay,
            $this->stopWindow($number, $today, $priorDays, $date),
        );
    }

    /**
     * The window the STOP trigger is allowed to divide in — **whole days, today
     * plus yesterday**, and it is a different window from the rolling one on
     * purpose.
     *
     * ⛔ **THE NUMERATOR AND THE DENOMINATOR WERE DIFFERENT POPULATIONS UNTIL
     * 3985, AND THAT IS WHAT MADE THE TRIGGER FIRE ON NOTHING.** `stops` comes
     * from `inbound_messages.created_at` — **when the STOP arrived** — and
     * `delivered` from `outreach_messages.created_at` — **when the message was
     * sent**. STOPs lag their sends by hours, so a rolling 24-hour window
     * routinely counts opt-outs generated by traffic that has already fallen
     * out of the denominator. The scenario is ordinary rather than contrived:
     * Monday 09:00 a tenant sends 400 invites over the shared number, six
     * people opt out through the day (1.5%, entirely normal), Tuesday 09:00
     * they send 40 more. At Tuesday's 10:00 rollup the rolling window holds 40
     * delivered and 6 stops — **15%**, five times the threshold — and the
     * shared Lane A number is quarantined, which stops every text on the
     * platform (3775). Nothing abnormal happened anywhere in it.
     *
     * ⚠️ **THE FIX IS TO WIDEN THE DENOMINATOR, NOT TO ATTRIBUTE THE
     * NUMERATOR** (3985), and the choice is worth stating. Attributing each
     * STOP to the send that caused it would mean joining a member of the
     * public's mobile number across `inbound_messages` (untenanted) and
     * `outreach_messages` (tenant-owned, RLS-forced) — a cross-tenant join on
     * PII, per number, hourly, to recover a few hours of ordering. Widening
     * costs nothing and is exactly as correct in aggregate: **a send can only
     * contribute a STOP while the send itself is still in the denominator.**
     *
     * ⚠️ **WHOLE DAYS, BECAUSE THE DAILY ROWS ALREADY EXIST AND ALREADY PAIR
     * THE TWO COUNTERS.** `number_health_daily` stores `delivered` and `stops`
     * for the same day from the same gathering pass, so today's in-memory
     * totals plus yesterday's stored row is a well-formed union — no third
     * query, no new window for a caller to gather and get wrong. The window is
     * between 24 and 48 hours wide depending on the hour of the run, and it is
     * never narrower than the rolling one.
     * ⚠️ **THAT LAST CLAUSE IS TRUE OF THE DAY PAIR AND WAS FALSE OF THE
     * FALLBACK WRITTEN BESIDE IT — NARROWED 10080.** `$today` alone spans
     * midnight to now, which before noon is **narrower** than the rolling 24
     * hours, so the sentence never covered the arm it sat next to. It holds of
     * every window this method returns with a yesterday in it, and the one
     * remaining arm without a yesterday is a number that did not exist to have
     * one.
     *
     * ⛔ **THE FILTER TRIGGER IS DELIBERATELY LEFT ON THE ROLLING 24 HOURS.**
     * Its numerator and denominator are both `outreach_messages` rows counted
     * by `created_at`, so a receipt landing late is counted in the window its
     * own message was sent in — the populations already coincide, and widening
     * that trigger would dilute a bad afternoon into a good yesterday and delay
     * the finding that actually damages the pool by up to a day.
     *
     * ⛔ **AN ABSENT YESTERDAY IS NOT A QUIET YESTERDAY, AND UNTIL 10080 THE
     * TWO PRODUCED BYTE-IDENTICAL OUTPUT.** This method returned `$today`
     * unchanged when yesterday's row was missing, and
     * {@see NumberHealthSignals::add()} is a field-wise sum — so a stored row
     * of `delivered: 0, stops: 0` and **no row at all** reached the trigger as
     * the same object. **The window silently collapsed from two days to one**,
     * the returned object carried no width, and the reason string built from
     * it went on saying *"over today and yesterday"* either way. `CLAUDE.md`
     * and `docs/FAILURE-SHAPES.md` carry the general shape: *a lookback window
     * is safe when the thing asked about is a rate and dangerous when it is a
     * state*, and whether this number may keep sending is a state.
     *
     * ⛔ **AND IT PUSHED BOTH WAYS AT ONCE.** At the seeded 3.0% and
     * {@see self::minDeliveredForStopTrigger()}'s 67, a number that delivered
     * 400 yesterday with no opt-outs yet arrived and 80 today, three of
     * yesterday's opt-outs landing this morning, reads **0.6%** on the day pair
     * and **3.75%** on today alone — so an absent row *manufactures* a
     * quarantine, which is 3985's own Monday-then-Tuesday defect reached by a
     * missing row instead of by a rolling window. The other way, a number
     * delivering 40 with 3 opt-outs on each of two days reads **7.5%** across
     * 80 delivered and trips, and splits into two halves of 40 that each fail
     * the sample gate — so an absent row also *loses* a real quarantine.
     *
     * ⛔ **THE ROW IS ABSENT FOR FIVE REASONS AND ONLY ONE OF THEM IS
     * WATCHED.** {@see RollUpNumberHealth} enumerates **every** `phone_numbers`
     * row, so a genuinely quiet yesterday *does* write a zero row. What leaves
     * no row is: the scheduler or the queue being dead, or a stranded
     * `withoutOverlapping` lock — **the heartbeat sees this one** (9930–9944);
     * the `numbers.health_rollup` kill switch being thrown, which returns
     * before anything is enumerated; the tenant being paused or suspended,
     * which `AutopilotJob` refuses before `execute()`; the rollup job having
     * failed permanently; and **the number not having existed yesterday, which
     * is legitimate and stays legitimate.** So *"the rollup did not run"* is
     * one reason of five, and a fix that only covered it would be a fifth of
     * the defect.
     *
     * ⚠️ **SO AN UNOBSERVED YESTERDAY ANSWERS `null` — "this trigger has no
     * window to divide in" — RATHER THAN A NARROWER WINDOW.** That is
     * {@see self::threshold()}'s posture one method over: a trigger that
     * cannot be evaluated is off and says so, rather than being evaluated
     * against something else. It is also the honest arithmetic — **with
     * yesterday unobserved the numerator and the denominator are back to being
     * different populations**, which is the exact condition 3985 widened this
     * window to fix. Today's `stops` include opt-outs generated by yesterday's
     * unknown send, and yesterday's own stops are unknown too, so the two-day
     * rate is not bounded from either side and today's ratio is neither a
     * ceiling nor a floor on it.
     *
     * ⛔ **WHICH DIRECTION THIS CHOOSES TO BE WRONG IN, AND WHY IT IS NOT
     * SIMPLY "FAIL CLOSED".** The refusal is symmetric — it withholds the
     * spurious quarantine **and** the one that would have been right on the
     * narrow base — so it is a real trade rather than a free fix. The costs
     * are not symmetric, which is what settles it. **The number most likely to
     * clear the sample floor on today alone is the shared Lane A pool number**,
     * whose quarantine stops every text on the platform, is filed under no
     * tenant, reaches no activity feed, and comes back only through a
     * seven-day cooldown or an Ops command nobody has been told to run —
     * {@see RollUpNumberHealth} can only `warn()` on a background-scheduled
     * command. A **withheld** quarantine leaves STOP and HELP handling
     * untouched and unconditional: everybody who opted out stays opted out,
     * because suppression is not this trigger's job, and what is lost is a
     * containment for the pool's reputation rather than anybody's consent.
     * 3988 already settled the posture for this trigger — *"an eager
     * containment converts itself into a disabled one, one release at a
     * time"* — and so did 3986's `k = 2`.
     *
     * ⚠️ **AND IT IS NOT SILENT ABOUT IT, WHICH IS THE HALF THAT MAKES THE
     * TRADE AFFORDABLE.** {@see self::observeWithheldStopTrigger()} says so
     * exactly when the refusal changed an outcome, with the rate, the
     * delivered count and the threshold — everything a person needs to file
     * the same quarantine by hand through `numbers:quarantine`, which is doc
     * 51 §5.2's own third trigger row and exists for this.
     *
     * ✅ **A NUMBER THAT DID NOT EXIST YESTERDAY HAS AN EMPTY YESTERDAY RATHER
     * THAN AN UNOBSERVED ONE**, and it is evaluated with the window's own
     * arithmetic unchanged — `delivered + 0`, `stops + 0` — so the reason
     * string's *"over today and yesterday"* stays literally true on every arm
     * that can still produce it. Its opt-outs cannot be lagged from sends that
     * never happened, so nothing about the narrow base is unsound there.
     * ⚠️ **`created_at` IS THE PREDICATE AND *"HAS NO PRIOR ROW"* IS NOT**: a
     * number created a month ago on a platform whose rollup has never run also
     * has no prior row, and that is precisely the case this refusal exists
     * for. A number with no creation stamp at all is treated as unobserved,
     * because it cannot be placed either side of yesterday.
     *
     * @param  Collection<int, NumberHealthDaily>  $priorDays
     * @return ?NumberHealthSignals The window the STOP trigger may divide in,
     *                              or **null when yesterday was not observed**
     *                              and there is therefore no two-day rate to
     *                              compare against anything.
     */
    private function stopWindow(
        PhoneNumber $number,
        NumberHealthSignals $today,
        Collection $priorDays,
        CarbonImmutable $date,
    ): ?NumberHealthSignals {
        $yesterday = $priorDays->first(
            static fn (NumberHealthDaily $day): bool => $day->date->toDateString() === $date->subDay()->toDateString(),
        );

        if ($yesterday instanceof NumberHealthDaily) {
            return $today->add(new NumberHealthSignals(
                delivered: $yesterday->delivered,
                stops: $yesterday->stops,
            ));
        }

        $createdAt = $number->created_at;

        if ($createdAt instanceof CarbonInterface && $createdAt->greaterThanOrEqualTo($date)) {
            return $today;
        }

        $this->observeWithheldStopTrigger($number, $today);

        return null;
    }

    /**
     * Say when an unobserved yesterday withheld a quarantine this trigger
     * would otherwise have filed.
     *
     * ⚠️ **ONLY WHEN THE REFUSAL CHANGED AN OUTCOME**, which is
     * {@see self::observeUnmapped()}'s rule at the identical point and
     * `NumberHealthRollup`'s rule for the activity feed. A missing rollup row
     * is the **ordinary** state of a deliberately paused tenant and of a
     * deliberately thrown kill switch, so a line for every incomplete window
     * would be one per number per hour about somebody's own decision — and a
     * line nobody can act on gets filtered out along with the one they can
     * (511). What is worth a line is the moment this platform declined to stop
     * a number it would have stopped, which is rare and which nothing else in
     * this application can report.
     *
     * ⛔ **THE OTHER DIRECTION IS NOT REPORTABLE FROM HERE AND IS STATED
     * RATHER THAN PAPERED OVER.** A quarantine *lost* because a two-day pair
     * split into two halves that each fail the sample gate is invisible to
     * this method by construction: with yesterday unobserved there is no rate
     * to notice being high. **The finding there is that the rollup did not
     * run**, and it belongs to whatever watches the rollup rather than to the
     * scorer reading its output.
     *
     * ⚠️ **THE NUMBER'S ID, NEVER ITS `e164`.** `RollUpNumberHealth` prints
     * the shared pool number's E.164 and argues it may because the number is
     * ours; this method also runs for a tenant's own number, so it logs the
     * id, which is what an operator looks a number up by anyway.
     *
     * ⚠️ **THE THRESHOLD IS READ DIRECTLY RATHER THAN THROUGH
     * {@see self::threshold()}**, which would log its own warning a second
     * time for the same run when the configured percentage is unusable.
     */
    private function observeWithheldStopTrigger(PhoneNumber $number, NumberHealthSignals $today): void
    {
        if (! $this->isEvaluable($number)) {
            return;
        }

        $stopPct = $this->registry->float('numbers.quarantine_stop_pct');

        if ($stopPct <= 0.0) {
            return;
        }

        $stopPercent = $today->stopRatePercent();

        if ($today->delivered < $this->minDeliveredForStopTrigger($stopPct) || $stopPercent < $stopPct) {
            return;
        }

        Log::warning('A STOP-rate quarantine was withheld because yesterday\'s number health row was never written.', [
            'number_id' => $number->id,
            'stop_rate_percent_today' => round($stopPercent, 1),
            'threshold_percent' => $stopPct,
            'delivered_today' => $today->delivered,
            'stops_today' => $today->stops,
        ]);
    }

    /**
     * Whether this number's recent history is allowed to decide anything —
     * {@see self::EVALUABLE}, asked in the two places that need it rather than
     * spelled twice.
     */
    private function isEvaluable(PhoneNumber $number): bool
    {
        return in_array($number->state, self::EVALUABLE, true);
    }

    /**
     * Doc `51` §5.2 — auto-quarantine, and the only path to it in `app/`.
     *
     * Returns the typed reason a quarantine was filed under, or null when
     * nothing fired. Public rather than private so a test can drive it with
     * nothing upstream that could refuse first — 398's rule, and the shape
     * `NumberHealthRollup`'s ownership check already takes.
     *
     * **The two triggers, verbatim from §5.2's table:**
     *
     *   | Filter/failure rate 24 h | ≥ `quarantine_failure_pct` (20 %) |
     *   | Per-number STOP rate 24 h | ≥ `quarantine_stop_pct` (3 %) at ≥ `min_sends_stop` (30) |
     *
     * plus the row's own preamble — *"any one, evaluated on the rolling
     * window, ≥ `min_sends_quarantine` sends"* — which is the sample gate the
     * failure-rate trigger carries and the reason the STOP trigger names a
     * *second*, larger one of its own.
     *
     * ⛔ **EACH GATE COUNTS THE RATE'S OWN DENOMINATOR, AND UNTIL THE AG6 FIX
     * WAVE NEITHER DID.** Both gates read `sends` while `filteredRate()`
     * divides by *decided* messages (1645/1648 — a message still awaiting its
     * receipt is not a failure) and `stopRatePercent()` divides by *delivered*.
     * The asymmetry was argued here as doc 51's own, and it was a defect in
     * both triggers: a sample gate that does not gate the denominator gates
     * nothing. 25 sends with one decided message that was filtered is 100% over
     * a 20% threshold, and 30 sends with 30 delivered and **one ordinary
     * opt-out** is 3.33% over a 3% threshold. Both quarantined the number, and
     * on the shared Lane A number both stop every text on the platform.
     *
     * `decided()` and `delivered` are each ≤ `sends`, so gating on them is
     * strictly stronger than the preamble's *"≥ min_sends_quarantine sends"*
     * rather than a departure from it. {@see self::minDeliveredForStopTrigger()}
     * carries the second half of the STOP fix.
     *
     * ⛔ **NOTHING HERE CAN QUARANTINE A NUMBER THAT IS ALREADY RESTING, AND
     * {@see self::EVALUABLE} IS THE WHOLE OF WHY.** It excludes every
     * non-sending state, `recovering` included — a number released moments ago
     * still carries the failures that quarantined it inside the window, and
     * evaluating it would undo the release within the hour.
     *
     * ⚠️ **THIS USED TO CLAIM "TWO INDEPENDENT THINGS SAY SO", NAMING THE
     * SAMPLE GATE AS THE SECOND, AND THE SECOND ONE IS FALSE** (3987). A
     * quarantined number stops sending from the moment it rests — but the
     * window still holds the sends that *caused* the quarantine for a further
     * 24 hours, so the gate would not refuse it at all during the period that
     * matters. 314–316's shape: a second layer asserted rather than checked,
     * in the docblock a reviewer reads instead of looking. **`EVALUABLE` is
     * load-bearing and alone.**
     *
     * @param  ?NumberHealthSignals  $stopWindow  The window the STOP trigger
     *                                            divides in — see
     *                                            {@see self::stopWindow()}. It
     *                                            is a **required** parameter
     *                                            rather than a default of
     *                                            `$rollingDay`, because a
     *                                            default is how the mismatched
     *                                            populations 3985 fixed would
     *                                            come back the first time a
     *                                            caller forgot.
     *                                            ⛔ **`null` MEANS THE STOP
     *                                            TRIGGER HAS NO WINDOW AND IS
     *                                            OFF FOR THIS RUN — IT DOES
     *                                            NOT MEAN A QUIET ONE**
     *                                            (10080). It is nullable so
     *                                            that an unobserved yesterday
     *                                            cannot be handed in as an
     *                                            empty `NumberHealthSignals`
     *                                            and answered as though the
     *                                            number had been watched.
     */
    public function evaluateQuarantine(PhoneNumber $number, NumberHealthSignals $rollingDay, ?NumberHealthSignals $stopWindow): ?string
    {
        if (! $this->isEvaluable($number)) {
            return null;
        }

        $reason = $this->quarantineReason($rollingDay, $stopWindow);

        if ($reason === null) {
            return null;
        }

        // The one writer of a state, so the transition is legality-checked,
        // appended to `number_state_changes` and audited exactly as an Ops
        // action would be. `system:` is the actor prefix doc 51 §2.4 asks for
        // — "actor (`system:trigger-name` or user id)".
        $this->lifecycle->transitionTo(
            $number,
            NumberState::Quarantined,
            'system:number-health-quarantine',
            $reason,
        );

        return $reason;
    }

    /**
     * Which of §5.2's triggers fired, as the typed reason it is filed under.
     *
     * ⚠️ **THE FILTER TRIGGER IS CHECKED FIRST AND THE ORDER IS NOT COSMETIC.**
     * A number can trip both at once, and only one reason lands in
     * `number_state_changes`; carrier filtering is the finding that changes
     * what an operator does next, where a STOP spike is a list-quality signal
     * §7's pool alarm is the right home for. Both figures go in the string
     * either way, so nothing is lost by ordering them.
     */
    private function quarantineReason(NumberHealthSignals $rollingDay, ?NumberHealthSignals $stopWindow): ?string
    {
        $failurePct = $this->threshold('numbers.quarantine_failure_pct');
        $filteredPercent = $rollingDay->filteredRate() * 100;

        // ⚠️ **`decided()`, NOT `sends` — AND THAT IS A ONE-WORD BLOCKER FIX**
        // (AG6 fix wave). The gate read `sends` while the rate divided by
        // `decided()`, so nothing required the denominator to be large: 25 sends
        // whose receipts had not landed except one — and that one filtered —
        // is 100% of a sample of one, over the 20% threshold, and quarantined
        // the number. **The realistic way to reach it is our own DLR webhook
        // failing**: receipts stop arriving, the handful that do land includes
        // one `EC_REJECTED_SPAM_BY_OPERATOR`, and the platform halts on a
        // denominator of one. `decided() <= sends` always, so this is strictly
        // stronger than the gate it replaces and doc 51 §5.2's *"≥
        // min_sends_quarantine sends"* preamble is still satisfied.
        if ($failurePct !== null && $rollingDay->decided() >= $this->registry->int('numbers.min_sends_quarantine') && $filteredPercent >= $failurePct) {
            return sprintf(
                'Carrier filter rate %.1f%% over 24h (threshold %.1f%%) across %d decided messages.',
                $filteredPercent,
                $failurePct,
                $rollingDay->decided(),
            );
        }

        // ⛔ **A NULL WINDOW IS THIS TRIGGER BEING OFF, NOT THIS NUMBER BEING
        // FINE** (10080). `stopWindow()` answers null when yesterday's daily
        // row was never written, because the numerator and the denominator are
        // then gathered over different spans again — the condition 3985
        // widened this window to fix. **Returning here rather than dividing in
        // a one-day window reaches both halves of the trigger at once**, the
        // rate and the sample gate, which is what an absent row used to move
        // in opposite directions depending on how much had gone out today.
        if ($stopWindow === null) {
            return null;
        }

        // ⛔ **`$stopWindow`, NEVER `$rollingDay`** (3985). The STOP counter and
        // the delivered counter are populations gathered on different clocks,
        // and the rolling window puts them out of step by hours — see
        // {@see self::stopWindow()} for the ordinary Monday-then-Tuesday
        // timeline that quarantined the shared number at 15% while nothing
        // abnormal had happened.
        $stopPct = $this->threshold('numbers.quarantine_stop_pct');
        $stopPercent = $stopWindow->stopRatePercent();

        if ($stopPct !== null && $stopWindow->delivered >= $this->minDeliveredForStopTrigger($stopPct) && $stopPercent >= $stopPct) {
            return sprintf(
                'STOP rate %.1f%% over today and yesterday (threshold %.1f%%) across %d delivered messages.',
                $stopPercent,
                $stopPct,
                $stopWindow->delivered,
            );
        }

        return null;
    }

    /**
     * The smallest delivered count at which the STOP trigger is allowed to mean
     * anything.
     *
     * ⛔ **THE SEEDED FIGURES MADE THIS TRIGGER FIRE ON ONE ORDINARY OPT-OUT,
     * ARITHMETICALLY, AT ITS OWN MINIMUM SAMPLE** (AG6 fix wave). `min_sends_
     * stop` is 30 and `quarantine_stop_pct` is 3.0, and **1/30 is 3.33%** — so
     * the rule as shipped read *"quarantine on the first STOP once 30 messages
     * have gone out"*, for every number until its delivered count passed 33.
     * Day one is 30 review invites, one recipient opts out at a completely
     * normal rate, the hourly rollup runs, and **every text on the platform
     * stops** — the shared Lane A number being the one they all ride.
     *
     * ⚠️ **THE FLOOR IS DERIVED FROM THE THRESHOLD RATHER THAN BEING A SECOND
     * CONSTANT, WHICH IS WHAT MAKES IT SURVIVE AN OPS EDIT.**
     * `floor(100·k/pct)+1` is the smallest sample at which `k` STOPs are
     * *strictly* under the threshold, so `k` opt-outs can never trip this
     * trigger at any setting of `quarantine_stop_pct` — including the ones an
     * operator has not chosen yet. A hardcoded "at least three STOPs" would be
     * equivalent today and would come apart the moment somebody moved the
     * percentage; it would also be unfalsifiable sitting beside this, which is
     * 398's shape.
     *
     * ⛔ **`k` IS 2 AND IT WAS 1, WHICH STOPPED ONE INTEGER SHORT** (3986).
     * With `k = 1` the floor at the seeded 3.0% is 34, and `k` STOPs still trip
     * whenever `delivered ≤ 33k`: **2 STOPs in 66 delivered is 3.03% and
     * quarantined the number**, while 2 in 67 held. At a perfectly ordinary 1%
     * opt-out rate that is a coin the platform tosses every rollup — with 34
     * delivered, the chance of seeing two or more opt-outs is roughly one
     * number-day in twenty. 3784's reasoning was right and its arithmetic
     * stopped at the first case.
     *
     * ⚠️ **WHY 2 IS THE STOPPING POINT AND NOT 3 OR 4.** Each step buys less
     * and costs more: at the seeded 3%, `k = 1` leaves about a 5% chance of a
     * spurious trip per number-day at the floor, `k = 2` about 3%, `k = 3`
     * about 2% — while the sample a genuinely bad number must deliver before it
     * can be stopped at all goes 34 → 67 → 101. **The residual is not meant to
     * be driven to zero here**: doc 51 §5.4's second strike and §7's pool alarm
     * are the instruments for a number that keeps looking marginal, and neither
     * is built. What `k = 2` buys is the elimination of the cases that are pure
     * arithmetic — one or two ordinary opt-outs — rather than a statistical
     * claim this codebase has no confidence figure for and must not invent
     * (2409's rule).
     *
     * ⚠️ **AND THE COMPOUNDING IS WHY EAGERNESS IS THE EXPENSIVE DIRECTION**
     * (3988). `recovering` sits outside {@see self::EVALUABLE} and nothing
     * walks a number back to `active` (§5.3, unbuilt), so **the first false
     * positive an operator releases disarms this trigger for that number until
     * somebody builds the recovery path.** An eager containment converts itself
     * into a disabled one, one release at a time.
     *
     * ⚠️ **`min_sends_stop` STILL BINDS WHEN IT IS THE LARGER OF THE TWO** — the
     * registry key is not bypassed, it is floored. At the seeded 3.0% the floor
     * is 67 and the key's 30 is the smaller; at 10% the floor is 21 and the key
     * is what holds.
     *
     * ⚠️ **DELIBERATELY NOT A REGISTRY KEY OF ITS OWN.** I44 registers the
     * document's thresholds; this is a property of the arithmetic, and an
     * Ops-editable "how small a sample may quarantine the platform" is a control
     * whose only useful setting is the one it already computes.
     */
    private function minDeliveredForStopTrigger(float $stopPct): int
    {
        return max(
            $this->registry->int('numbers.min_sends_stop'),
            (int) floor(100 * self::STOP_NOISE_FLOOR_K / $stopPct) + 1,
        );
    }

    /**
     * A percentage threshold an auto-quarantine may be decided on, or null when
     * the configured figure is not one.
     *
     * ⛔ **A THRESHOLD OF ZERO WITH `>=` QUARANTINES EVERY NUMBER OVER THE
     * SAMPLE GATE ON THE NEXT HOURLY RUN** (AG6 fix wave). `quarantine_failure_
     * pct` and `quarantine_stop_pct` are Ops-editable registry rows and nothing
     * bounded them; the manifest's own description carries a warning, and a
     * warning in a description is not a guard. **The failure is total and
     * silent**: a healthy number with a 0% filter rate satisfies `0.0 >= 0.0`,
     * so the first rollup after the edit rests the shared Lane A number and
     * every number behind it.
     *
     * ⚠️ **IT REFUSES TO FIRE RATHER THAN FIRING ON EVERYTHING, AND THAT
     * DIRECTION IS THE WHOLE DECISION.** Both readings lose something: this way
     * the containment is off until somebody fixes the row, the other way the
     * platform is silent until somebody fixes the row. An operator who genuinely
     * wants to rest a number on any filtered message at all sets `0.1`; nobody
     * ever wants `0`. Logged at warning rather than swallowed, because a
     * containment that has quietly stopped containing is exactly the thing
     * `sending_health_windows` taught this codebase to be afraid of.
     */
    private function threshold(string $key): ?float
    {
        $pct = $this->registry->float($key);

        if ($pct > 0.0) {
            return $pct;
        }

        Log::warning('An auto-quarantine threshold is not a usable percentage; that trigger is off.', [
            'key' => $key,
            'value' => $pct,
        ]);

        return null;
    }

    /**
     * Doc 51 §4.3's blend: 70% the 7-day window's score, 30% the rolling
     * 24-hour window's — "so one bad hour dents but one bad day moves."
     *
     * ⚠️ **A SILENT 24-HOUR WINDOW IS DROPPED FROM THE BLEND RATHER THAN
     * SCORED, AND WITHOUT THIS A RESTING NUMBER COULD NEVER RECOVER.** A window
     * with no decided message earns the two *inverse* terms in full — nothing
     * was filtered, nobody sent STOP, because nothing happened — and zero for
     * the two positive ones, which lands it at exactly `100 × (w_f + w_s)`, or
     * 45 on the seeded weights. Blended at 30% that drags a flawless number to
     * 76, under the seeded `degraded_below_score` of 80: **a number is marked
     * unhealthy for not sending.** Phase 3 is what makes that expensive rather
     * than merely wrong — a quarantined number sends nothing *by construction*,
     * so its score would decay while it rested and it could never climb back
     * out, which inverts doc 51 §0's own principle that "a sick number rests
     * and recovers." §4.3 already states the intent for the case one size up —
     * *"small samples neither flatter nor condemn"* — and an empty window is
     * the smallest sample there is.
     */
    private function blend(NumberHealthSignals $sevenDay, NumberHealthSignals $rollingDay): int
    {
        $blended = $rollingDay->hasEvidence()
            ? (self::SEVEN_DAY_WEIGHT * $this->windowScore($sevenDay))
                + (self::ROLLING_DAY_WEIGHT * $this->windowScore($rollingDay))
            : $this->windowScore($sevenDay);

        return (int) max(0, min(100, round($blended)));
    }

    /**
     * Doc 51 §4.3: `100 × (w_d·delivered + w_f·(1−filtered) + w_s·(1−stop_norm)
     * + w_e·min(reply_rate/0.05, 1))`, with `stop_norm = min(stop_rate /
     * quarantine_stop_pct, 1)`. One window's score, before the 7-day/24-hour
     * blend above is applied.
     */
    private function windowScore(NumberHealthSignals $signals): float
    {
        $weightDelivered = $this->registry->float('numbers.health_weight_delivered');
        $weightFiltered = $this->registry->float('numbers.health_weight_filtered');
        $weightStop = $this->registry->float('numbers.health_weight_stop');
        $weightEngagement = $this->registry->float('numbers.health_weight_engagement');
        $quarantineStopPct = $this->registry->float('numbers.quarantine_stop_pct');

        $stopNorm = $quarantineStopPct > 0.0
            ? min($signals->stopRatePercent() / $quarantineStopPct, 1.0)
            : ($signals->stopRatePercent() > 0.0 ? 1.0 : 0.0);

        $replyNorm = min($signals->replyRate() / self::REPLY_RATE_CAP, 1.0);

        return 100 * (
            $weightDelivered * $signals->deliveredRate()
            + $weightFiltered * (1 - $signals->filteredRate())
            + $weightStop * (1 - $stopNorm)
            + $weightEngagement * $replyNorm
        );
    }
}
