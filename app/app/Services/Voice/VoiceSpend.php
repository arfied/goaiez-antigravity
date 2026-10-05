<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Enums\OperatorAlertKind;
use App\Enums\VoiceUsageKind;
use App\Jobs\Voice\FetchVoicemailRecordingJob;
use App\Jobs\Voice\TranscribeVoicemailJob;
use App\Models\VoiceUsageEvent;
use App\Services\Billing\SendCredits;
use App\Services\Config\DefaultsRegistry;
use App\Services\Ops\OperatorAlerts;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Throwable;

/**
 * The counter on inbound voice, and the one brake it can honestly hold —
 * decision 4686.
 *
 * ## What was missing, and why it was the sharpest of the three
 *
 * 3297: *"a path with no debit does not look uncapped, it looks free"*, and
 * 3293 deleted the per-tenant dollar cap on the express condition that every
 * money path debit the ledger first. The enumeration at 4684 found inbound voice
 * with **no meter, no budget, no ceiling and no debit**, and named two things
 * that make it worse than the other two gaps beside it:
 *
 *   - **It is billed by duration**, so the cost of a single event is unbounded
 *     above. A text is one message at one price; a call is however long somebody
 *     talks.
 *   - **It is the one uncapped path a stranger can trigger.** Nobody has to be a
 *     tenant, logged in, or consented to make it cost money. They dial a number.
 *
 * ⚠️ **AND IT IS BOUNDED COMMERCIALLY RATHER THAN TECHNICALLY**, which 4686 says
 * plainly: a tenant has one number and the plan covers it. That is an argument
 * about the ordinary case and not a ceiling, and the case this exists for is the
 * one that is not ordinary.
 *
 * ## ⛔ WHY THIS IS `PlacesSpend`'s SHAPE AND NOT `SendCredits`'
 *
 * The two existing shapes are a **per-tenant spend ceiling over a platform
 * ledger** (`PlacesSpend`) and a **per-event credit debit transactional with the
 * spend** (`SendCredits`). This is the first, and three separate arguments point
 * the same way.
 *
 * **1. A debit is refused on product grounds, and this codebase already refused
 * it once.** {@see SendCredits::debitsACredit()} excludes
 * `MessageCostKind::InboundSms` and `InboundMms` with the reason written down:
 * *"charging a tenant because their customer replied is the wrong product, and
 * it would be a charge driven entirely by somebody else's behaviour."* An
 * inbound **call** is that argument at its strongest — the caller is a stranger
 * and the duration is theirs to choose. 4686 adds the consequence: refusing to
 * take a customer's call for want of a balance is *the missed-call failure this
 * product exists to fix*, arriving from inside the product.
 *
 * **2. A debit needs a decision point and the minutes do not have one.** The
 * billable minutes are incurred at the carrier, by a conditional-forwarding code
 * the tenant dialled into their own handset. There is no call site in `app/`
 * that decides to answer a telephone, and there cannot be: `VoiceProvider` has
 * no method that touches a call at all, held there by two lints (4403). An
 * `allows()` before *that* spend is unspellable, so a debit would be a bill
 * rather than a ceiling.
 *
 * **3. The duration paradox resolves by splitting the path in two.** The brief's
 * own words: *a per-event debit taken at answer time cannot know the duration; a
 * debit at hangup knows it but has already spent it.* Both are true of the
 * minutes and **neither is true of what follows them**. At hangup the duration
 * is known *and* every remaining duration-billed line on that call is still
 * ahead of us — the recording, its storage, and the transcription that is only
 * ever dispatched from a successful fetch. So:
 *
 *   **the observed half is metered, and the chosen half is gated.**
 *
 * The gate is not late. It is exactly on time for the only spend it can refuse.
 *
 * ## What the brake actually stops, said without overclaiming (314–316)
 *
 * ⛔ **IT DOES NOT STOP THE MINUTES AND IT DOES NOT STOP THE RECORDING FEE.**
 * Infobip records the call because the Calls configuration on the account says
 * to (4423's operator steps); nothing in this repository asks for it per call,
 * so nothing here can decline it. What {@see self::allowsRecordingFetch()}
 * refuses is the **download and the object write** — bandwidth and R2 storage,
 * which 4687 ranks honestly as *small and real* — and, transitively, the
 * transcription, because {@see TranscribeVoicemailJob} is
 * dispatched only from a fetch that stored bytes. That last one is the
 * expensive one: Infobip publishes speech transcription at **€0.0402/min**
 * against recording's **€0.0021/min** (`https://www.infobip.com/voice/pricing`,
 * read 2026-08-17), **nineteen times dearer**.
 *
 * ⚠️ **SO THE ALERT IS THE CONTAINMENT FOR THE HALF THE BRAKE CANNOT REACH**,
 * and it is a real one rather than a consolation: the action a strange night
 * calls for — pulling the number, or turning the Calls configuration off in the
 * vendor's portal — is an operator's, in a console this application does not
 * have. 2102 is why the bell is not the whole answer either (*"a kill switch
 * that needs somebody awake is the mitigation this cannot rely on"*), which is
 * why there is an automatic arm at all.
 *
 * ## Minutes, not money, and that is a vendor fact
 *
 * ⛔ **NOTHING HERE GUESSES A RATE.** Infobip does not publish a base voice
 * per-minute figure: *"We display the average price across all supported
 * networks for each country. Per-network pricing is available in Portal"* (same
 * page, same date). `MessageRates` reached the identical conclusion about this
 * vendor's SMS rate — *"the mechanism is the deliverable; the number is not
 * ours"* — and `CLAUDE.md` records four burns from writing a plausible vendor
 * figure from memory. The ceilings are therefore denominated in **minutes**,
 * which is the unit the vendor bills in and the one an operator can reason about
 * without a rate.
 *
 * ## ⚠️ A THRESHOLD ON A DEAD COUNTER IS A DECORATION
 *
 * `sending_health_windows` shipped its thresholds, its trip and its screens with
 * **no caller of `recordDelivered()` anywhere in `app/`**, so every rate was
 * permanently zero and every test passed because every test seeded the counters
 * by hand (2496–2499). 4686 names that lesson as the one this path is walking
 * into. **The writers therefore ship in the same slice as the ceiling**, and
 * they are named here so the next reader can check they are still there rather
 * than assume it:
 *
 *   `InboundMinutes`  {@see VoiceCalls::record()} — at settlement, from the
 *                     vendor's own `startTime`/`endTime`, **including the
 *                     `UnknownNumber` path**, which is the one that writes no
 *                     `calls` row at all.
 *   `Recording`       {@see VoiceCalls::attachRecording()} — from the vendor's
 *                     own per-file `duration`, when it tells us a recording
 *                     exists.
 */
final class VoiceSpend
{
    /**
     * `platform_settings` keys. Namespaced by area, per that table's convention.
     */
    public const string TENANT_CEILING_KEY = 'voice.tenant_daily_inbound_minutes_ceiling';

    public const string UNATTRIBUTED_CEILING_KEY = 'voice.unattributed_daily_inbound_minutes_ceiling';

    /**
     * The alert's subject when the minutes belong to nobody.
     *
     * ⚠️ **A LITERAL RATHER THAN AN EMPTY STRING.** `OperatorAlerts` de-duplicates
     * on `(kind, subject)`, and an empty subject would put the unattributed pool
     * in the same bucket as a tenant whose id had failed to render — so two
     * different incidents would silence each other.
     */
    public const string UNATTRIBUTED_SUBJECT = 'unattributed';

    public function __construct(
        private readonly DefaultsRegistry $registry = new DefaultsRegistry,
    ) {}

    /**
     * Record what the vendor is billing us for, and ring the bell if this puts
     * the day over its ceiling.
     *
     * ⚠️ **THE INSERT IS THE IDEMPOTENCY.** `insertOrIgnore` on
     * `(provider_call_id, kind)` rather than a check-then-insert, which decision
     * 350 records as holding only sequentially — and two workers handling one
     * redelivered voice event are exactly that race. A double count would report
     * a busy afternoon as an incident and trip a ceiling nobody crossed.
     *
     * ⛔ **A REDELIVERY RINGS NO SECOND BELL**, because the ceiling check is
     * inside the `false` branch's mirror: it runs only when a row was actually
     * written. Alerting on a no-op would page an operator for a webhook retry.
     *
     * @param  int  $seconds  Billable seconds. **Zero is written and is not an
     *                        error** — a call that rang out has a real duration
     *                        of zero and the ceiling should say so. A negative
     *                        is refused by the unsigned column and clamped here
     *                        so the caller gets a row rather than an exception
     *                        on a path that must never throw.
     * @return bool false when this call and kind were already metered.
     */
    public function record(
        VoiceUsageKind $kind,
        string $providerCallId,
        int $seconds,
        ?int $businessId,
        CarbonImmutable $occurredAt,
    ): bool {
        if (trim($providerCallId) === '') {
            // No handle means no idempotency, and a meter that can double-count
            // is worse than one row missing. There is no path that produces this
            // today — `VoiceCallFacts` carries the handle it was fetched by —
            // and it is refused rather than trusted.
            return false;
        }

        $written = VoiceUsageEvent::query()->insertOrIgnore([
            'kind' => $kind->value,
            'provider_call_id' => $providerCallId,
            'billable_seconds' => max(0, $seconds),
            'business_id' => $businessId,
            'occurred_at' => $occurredAt,
            'created_at' => Carbon::now(),
        ]) === 1;

        if ($written && $kind === VoiceUsageKind::InboundMinutes) {
            // ⚠️ **ONLY THE CALL LEG RINGS.** A recording's seconds overlap the
            // call's, so alerting on both would page twice for one incident —
            // and it is the call time that is unbounded, since a recording is
            // capped by the vendor's own maximum message length.
            $this->alertIfOverCeiling($businessId, $occurredAt);
        }

        return $written;
    }

    /**
     * Seconds of this kind billed today against one tenant's numbers.
     *
     * ⚠️ **DELIBERATELY UNSCOPED BY TENANCY, WITH THE PREDICATE REQUIRED** —
     * `PlacesSpend::spentTodayCentsForBusiness()`'s rule verbatim: this table
     * carries no global scope and no row-level security, so the explicit
     * `business_id` **is** the isolation here, and a caller that cannot name a
     * tenant must not get a number.
     */
    public function secondsTodayForBusiness(int $businessId, VoiceUsageKind $kind): int
    {
        return (int) VoiceUsageEvent::query()
            ->onDay(Carbon::now())
            ->where('kind', $kind->value)
            ->where('business_id', $businessId)
            ->sum('billable_seconds');
    }

    /**
     * Seconds of this kind billed today against numbers no business owns.
     *
     * ⛔ **THIS IS THE STRANGER'S COLUMN, AND NOTHING ELSE IN THIS APPLICATION
     * CAN SEE IT.** `VoiceCalls::record()` answers `UnknownNumber` and writes no
     * `calls` row, so before this table the calls with nobody to bill were the
     * ones with nobody counting either.
     */
    public function unattributedSecondsToday(VoiceUsageKind $kind): int
    {
        return (int) VoiceUsageEvent::query()
            ->onDay(Carbon::now())
            ->where('kind', $kind->value)
            ->whereNull('business_id')
            ->sum('billable_seconds');
    }

    /**
     * One tenant's daily inbound-minute ceiling, failing closed.
     *
     * `max(0, …)` on `PlacesSpend`'s reasoning: a negative override is a mistake
     * and clamps to zero rather than wrapping into a huge budget, and **zero
     * means zero** — an operator typing it is instructing this platform to stop
     * fetching recordings, without a deploy.
     *
     * ⛔ **AND SOMEWHERE ELSE THIS PLATFORM TELLS THEM TO TYPE IT** (7740–7759).
     * `ops.alert_quiet_minutes`' refusal message says *"to quieten one noisy
     * check rather than all of them, set that check's own threshold to zero"*.
     * Following that here does **not** quieten
     * {@see OperatorAlertKind::InboundVoiceMinutes} and nothing else: it stops
     * every voicemail-recording download **for every account on the platform**,
     * and the bell goes with it, because at a ceiling of zero every call would
     * cross it and a bell on every call is one somebody mutes within a day (511).
     *
     * ⚠️ **NOTHING HERE CHANGED AND THAT IS THE DECISION** (7747). This zero is
     * the operator's brake on the one uncapped path a stranger can start, and
     * removing it would take a control away on the night it is wanted. What
     * changed is that `Admin\OperatorAlertBoard` now says so on the screen, the
     * manifest description says so at the box where it is typed, and
     * `Tests\Feature\ZeroIsNotOffTest` reddens for anybody who "fixes" it into an
     * ordinary off switch. ⛔ **There is deliberately no write guard**: this
     * ceiling has no honest upper bound — a tenant with several numbers can take
     * more inbound minutes in a day than the day has — so it cannot join the
     * bounded-key list that `RegistryTest` drives with `PHP_INT_MAX`, and a
     * half-member would weaken the lint holding the other three.
     */
    public function dailyTenantMinutesCeiling(): int
    {
        return max(0, $this->registry->int(self::TENANT_CEILING_KEY));
    }

    /**
     * The daily ceiling for calls to numbers no business owns.
     */
    public function dailyUnattributedMinutesCeiling(): int
    {
        return max(0, $this->registry->int(self::UNATTRIBUTED_CEILING_KEY));
    }

    /**
     * Whether this tenant's day is still inside its inbound-minute ceiling.
     *
     * ⛔ **THE ONLY BRAKE ON THIS PATH, AND IT REFUSES A DOWNLOAD RATHER THAN A
     * CALL.** See the class docblock for what it does and does not stop.
     * {@see FetchVoicemailRecordingJob} is the caller, and its
     * refusal is the shape 4500 already built for a PHI-classified tenant:
     * `VoicemailAudioState::Unavailable`, an audit row naming the reason, and
     * `VoicemailRecorded` fired anyway **so the owner is still told somebody
     * rang**. Rule 43's surviving half, applied: graceful degradation, never
     * hard-fail, never bill by surprise.
     *
     * ⚠️ **AND IT NEVER TOUCHES A LIVE CALL ITSELF.** Its only caller runs on a
     * queue, minutes after the caller hung up. Since 2026-10-05 the AI
     * receptionist asks the same ceiling at the ring, through
     * {@see self::allowsLiveAnswer()} — that is the one place it declines a live
     * call, and a declined call still reaches the owner as a message.
     *
     * ⚠️ **THE FAIL-CLOSED DIRECTION COSTS SOMETHING HERE THAT IT DOES NOT COST
     * IN `PlacesSpend`**, and it is worth saying which. There, a closed budget
     * costs a visitor an audit they can retry. Here it costs an owner the
     * *audio* of one message on a day their number took more inbound time than
     * the ceiling — while the missed call, the caller's number, the time, the
     * owner's email and the text-back all survive. That is why the ceiling is
     * seeded with a real number several times a busy day rather than withheld:
     * a withheld figure raises, and the fail-closed state of a raise on this
     * path is *no voicemail audio for anybody*.
     */
    public function allowsRecordingFetch(int $businessId): bool
    {
        return $this->withinTenantCeiling($businessId);
    }

    /**
     * Whether the AI receptionist may pick up another call for this tenant today (AI receptionist plan, 2026-10-05) — the
     * same ceiling, asked at the ring.
     *
     * ⚠️ A declined call is not dropped: the voice worker plays its fallback and takes a message. And until wave 3 meters a
     * live call's own minutes, the count it reads is the carrier-recorded inbound minutes only.
     */
    public function allowsLiveAnswer(int $businessId): bool
    {
        return $this->withinTenantCeiling($businessId);
    }

    /**
     * The arithmetic both ask: today's inbound seconds against the daily ceiling, where a ceiling of zero refuses.
     */
    private function withinTenantCeiling(int $businessId): bool
    {
        $ceiling = $this->dailyTenantMinutesCeiling();

        if ($ceiling === 0) {
            return false;
        }

        return $this->secondsTodayForBusiness($businessId, VoiceUsageKind::InboundMinutes)
            <= $ceiling * 60;
    }

    /**
     * Ring the bell when a day's inbound minutes cross their ceiling.
     *
     * ⚠️ **THE COUNTER AND THE BELL ARE IN ONE PLACE ON PURPOSE.** A watch
     * command sweeping this table would be a second thing to schedule and a
     * second thing to notice had stopped; hanging the check off the write means
     * the counter cannot be live while the alert is dead.
     *
     * ⛔ **AND NOTHING HERE MAY FAIL THE THING IT WAS WATCHING** (R25).
     * `OperatorAlerts::raise()` already contains every failure below it and
     * returns rather than throws; the `catch` here is for the container itself,
     * because the caller is a queued webhook handler whose real work is
     * recording a call.
     */
    private function alertIfOverCeiling(?int $businessId, CarbonImmutable $occurredAt): void
    {
        // ⚠️ **YESTERDAY'S REDELIVERY RINGS NOTHING.** The sums below are today's
        // and a row stamped with an older `occurred_at` is not in them, so an
        // alert about it would name a figure it did not contribute to.
        if (! $occurredAt->isSameDay(CarbonImmutable::now())) {
            return;
        }

        [$seconds, $ceilingMinutes, $subject] = $businessId === null
            ? [
                $this->unattributedSecondsToday(VoiceUsageKind::InboundMinutes),
                $this->dailyUnattributedMinutesCeiling(),
                self::UNATTRIBUTED_SUBJECT,
            ]
            : [
                $this->secondsTodayForBusiness($businessId, VoiceUsageKind::InboundMinutes),
                $this->dailyTenantMinutesCeiling(),
                (string) $businessId,
            ];

        if ($ceilingMinutes === 0 || $seconds <= $ceilingMinutes * 60) {
            return;
        }

        $minutes = intdiv($seconds, 60);

        try {
            app(OperatorAlerts::class)->raise(
                OperatorAlertKind::InboundVoiceMinutes,
                $subject,
                // ⛔ **FIGURES AND IDS OF OURS, NEVER A CALLER'S NUMBER.**
                // `OperatorAlerts::raise()` states the rule and this is a summary
                // that ends up in a text message.
                $businessId === null
                    ? 'Numbers no account owns have taken '.$minutes.' minutes of calls today, over the '
                        .$ceilingMinutes.'-minute ceiling.'
                    : 'Account '.$businessId.' has taken '.$minutes.' minutes of calls today, over the '
                        .$ceilingMinutes.'-minute ceiling.',
                [
                    'business_id' => $businessId,
                    'minutes_today' => $minutes,
                    'ceiling_minutes' => $ceilingMinutes,
                ],
            );
        } catch (Throwable) {
            // Contained deliberately and silently: `raise()` has already written
            // its own `Log::error` for anything it could not do, and a second
            // line here would only ever describe the container failing to build
            // the bell — on the path whose real job is recording a call.
        }
    }
}
