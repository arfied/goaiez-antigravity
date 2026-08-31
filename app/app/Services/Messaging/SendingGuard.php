<?php

declare(strict_types=1);

namespace App\Services\Messaging;

use App\Enums\OutreachChannel;
use App\Enums\SendingPauseReason;
use App\Enums\SendRefusalReason;
use App\Models\SendingPause;
use App\Services\AuditService;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use LogicException;

/**
 * May this tenant send, right now — the containment 2101 and 2102 make
 * load-bearing.
 *
 * ## Why this is a precondition of every send rather than a dashboard
 *
 * 2101 records the structural consequence of R8 with the cost named: attested
 * lists go out over the **GOAIEZ** 10DLC brand from **our own** number pool, so
 * *"the tenant carries the legal basis while the platform carries the carrier
 * reputation"* — across every tenant at once. R6 answers that with per-tenant
 * isolation and monitoring, which **contains damage rather than preventing it**,
 * and 2102 draws the conclusion: the kill switches trip *"on a complaint-rate
 * threshold without a human, because the failure mode is a campaign running
 * overnight while the queue nobody is watching fills."*
 *
 * ⚠️ **A KILL SWITCH THAT NEEDS SOMEBODY AWAKE IS THE MITIGATION THIS OVERRIDE
 * CANNOT RELY ON.** So the check runs on the **hot path, per message** — not per
 * campaign, not per batch, not on a schedule.
 *
 * ## Mid-flight, and why per-message is the only version that works
 *
 * ⛔ **A CAMPAIGN RUNNER THAT CHECKED ONCE AT ENQUEUE WOULD AUTHORISE THE WHOLE
 * RUN ON ONE READING**, taken before a single message had gone out and therefore
 * before any complaint could exist. The complaints arrive *because* the campaign
 * is running. Because {@see SendingHealth}'s counters update as it runs and this
 * guard is consulted for every message, **a campaign trips itself partway
 * through** and the remaining messages refuse. That is the entire value; a
 * per-batch check is a slower way of not having one.
 *
 * ## The order of the three checks is the design
 *
 *   1. **The global halt**, so an operator who stopped the platform gets a
 *      refusal naming the switch they actually threw, without ten thousand
 *      tenant rows having to agree.
 *   2. **This tenant's pause**, which is the durable state — it survives the
 *      window rolling forward, and it is what an operator resumes.
 *   3. **The rate**, last, because it is the only one that can *create* a pause.
 *      Asked first, a tenant already paused would have their rate recomputed on
 *      every refused send for no reason.
 *
 * ## Fail open on a missing pause row, fail closed nowhere here
 *
 * ⚠️ **THE OPPOSITE OF THIS CODEBASE'S USUAL DIRECTION, AND IT IS DELIBERATE.**
 * An unconfigured webhook secret refuses everything; an unknown state refuses
 * every marketing send. Here a missing row means *sending*, because a missing
 * row is the state of every healthy tenant — failing closed would mean no tenant
 * could send until something wrote them a row, which is `CLAUDE.md`'s
 * writerless-control failure wearing a safety hat. **The safety comes from the
 * write being automatic**, not from the read being paranoid.
 */
final class SendingGuard
{
    /**
     * The actor recorded on an automatic trip.
     *
     * A named constant because an operator reading `tripped_by` on a pause row
     * needs to tell a person from the platform at a glance, and a free-text
     * string typed at the one call site drifts the moment there are two.
     */
    public const string SYSTEM_ACTOR = 'system:sending-guard';

    /**
     * The platform halt a **person** throws, on the sending-controls screen.
     *
     * ⚠️ **THE ONE COMPLIANCE REPLIES HONOUR.** `ComplianceReplies` reads this
     * key and not the automatic one, and its docblock carries the argument:
     * an operator who halts the platform accepts that HELP goes unanswered,
     * *knowingly*, and that is a trade a person may make.
     */
    public const string OPERATOR_HALT_KEY = 'messaging.global_halt';

    /**
     * The platform halt the **machine** throws — `WatchPlatformComplaintRate`.
     *
     * ⛔ **SPLIT OUT OF `messaging.global_halt` ON 2026-08-15** (3980–3983).
     * Until then the scheduled sweep wrote the operator's key, so the first
     * automatic trip silenced every carrier-mandated STOP confirmation and
     * HELP answer on the platform — with nobody awake, and at the moment a
     * carrier is already looking at us, because the complaint rate is what
     * tripped it. 2099 makes those two replies unconditional; 2102 makes the
     * trip automatic; this constant is where the two stop colliding.
     *
     * ⚠️ **BOTH KEYS LIVE HERE, ON THE READER**, rather than one on the command
     * that writes it: this class is what every send path asks, and a send path
     * importing a console command to learn which switch stopped it would be the
     * dependency pointing the wrong way.
     */
    public const string AUTOMATIC_HALT_KEY = 'messaging.automatic_halt';

    /**
     * The cap on both note columns, in characters.
     *
     * `TenantPause::normaliseReason()`'s 500, deliberately the same number, and
     * the migration's CHECK is written against it. A pause row outlives its own
     * release now, so a free-text box on one is a box in a record nothing prunes.
     */
    private const int NOTE_LIMIT = 500;

    public function __construct(
        private readonly SendingHealth $health,
        private readonly DefaultsRegistry $defaults,
        private readonly AuditService $audit,
    ) {}

    /**
     * Null means send. Anything else is the reason not to.
     *
     * ⚠️ **RETURNS A REASON RATHER THAN THROWING**, because a paused tenant is
     * an operational state somebody's own threshold produced, not an error —
     * `ReviewInviteSender`'s fourth load-bearing property. The caller records it
     * and moves on; nothing reaches the customer.
     */
    public function refusalFor(OutreachChannel $channel): ?SendRefusalReason
    {
        // ⚠️ **THIS LINE'S FIRST COMMENT WAS WRONG AND THE MUTATION CAUGHT IT —
        // 398'S SHAPE, INSIDE THE SLICE THAT QUOTES 398.** It claimed that
        // without this, a tenantless call would read an empty scope and answer
        // "may send". It would not: `BelongsToTenant`'s global scope throws
        // `TenantNotResolved` on `SendingPause::query()`, so the outer guard
        // already refuses and deleting this line left every test green. **The
        // green suite said nothing.**
        //
        // It stays, and here is the narrower thing that is actually true: the
        // global-halt check below reads a *platform* setting and touches no
        // tenant-scoped query at all. With no tenant established and the halt
        // on, this method would return an answer for a tenant it cannot name —
        // harmless today because that answer is a refusal, and one reordering
        // away from not being. **The claim is now sized to the mechanism**, and
        // the test drives this line rather than the scope beneath it.
        Tenancy::idOrFail();

        // ⛔ **TWO KEYS, ONE REFUSAL** (3980–3983). `messaging.global_halt` is
        // an operator's switch; `messaging.automatic_halt` is the one
        // `WatchPlatformComplaintRate` throws on a schedule with nobody awake.
        // They were one key until the second was split out, because
        // `ComplianceReplies` honours the operator's switch and must not honour
        // a machine's — 2099 makes STOP and HELP unconditional. **Nothing about
        // the containment 2102 asks for is softened by the split**, and this
        // line is where that is true: a send refuses on either.
        //
        // ⚠️ **ONE `SendRefusalReason` FOR BOTH, DELIBERATELY.** A second case
        // would have to be added to `campaign_recipients_reason_belongs_to_a_
        // refusal`'s CHECK and to every reader of the enum, to express a
        // distinction the sender cannot act on: the send is stopped platform-
        // wide either way, and `RunCampaignJob` treats `GlobalHalt` as the
        // reason to stop the whole run for exactly that reason. Which switch it
        // was is one registry read away, and the sending-controls screen names
        // it in words.
        if ($this->haltedPlatformWide()) {
            return SendRefusalReason::GlobalHalt;
        }

        if ($this->isPaused()) {
            return SendRefusalReason::TenantPaused;
        }

        if ($this->shouldTrip($channel)) {
            return SendRefusalReason::TenantPaused;
        }

        return null;
    }

    /**
     * Is every tenant stopped, by either switch?
     *
     * ⚠️ **THE ONE PLACE THE PLATFORM-HALT PREDICATE IS WRITTEN**, for
     * {@see self::livePause()}'s reason one level up: a screen that asked only
     * about `messaging.global_halt` would show "running for everyone" while
     * this guard refused every send, which is the shape 3418 records — both
     * halves right on their own screen. `SendingControls` renders this answer
     * and clears both keys when an operator releases.
     *
     * ⚠️ **NO TENANT NEEDED.** Both are platform settings, so this is
     * answerable before a tenant is established — unlike everything else on
     * this class.
     */
    public function haltedPlatformWide(): bool
    {
        return $this->defaults->value(self::OPERATOR_HALT_KEY) === true
            || $this->defaults->value(self::AUTOMATIC_HALT_KEY) === true;
    }

    /**
     * ⚠️ **"A LIVE ROW EXISTS", NOT "A ROW EXISTS"** (2470). Before 2119(a) a
     * resume deleted the row, so those two were the same question; retaining
     * released rows makes them different, and the difference is the whole
     * feature. `released_at IS NULL` is the same predicate the partial unique
     * index uses, so the guard's idea of "paused" and the database's cannot
     * drift apart.
     *
     * Still fails open on no row at all, which is the state of every healthy
     * tenant — see this class's docblock.
     */
    public function isPaused(): bool
    {
        return $this->livePause() !== null;
    }

    /**
     * Every pause this tenant has ever had, newest first — the incident series.
     *
     * ⚠️ **THIS IS THE POINT OF RETAINING THE ROWS AND IT HAS NO SCREEN YET.**
     * Said plainly rather than left to be discovered: nothing in `app/` calls
     * this today, and a retained history nothing can read is 2119(a)'s
     * requirement half-met. The alternative — building an admin screen inside
     * this slice — was refused because none exists to extend and a new one is
     * its own slice. What is owed is recorded in 2478.
     *
     * ⚠️ Ordered by `id`, not by `created_at`. Two trips inside one second have
     * an order that a timestamp cannot express, and `id` is the one descending
     * sort `ConventionsTest`'s NULLS-LAST lint permits without saying it out
     * loud — `created_at` is nullable on this table, as it is on forty others.
     *
     * @return list<SendingPause>
     */
    public function history(int $limit = 50): array
    {
        return array_values(
            SendingPause::query()
                ->orderByDesc('id')
                ->limit($limit)
                ->get()
                ->all()
        );
    }

    /**
     * Stop this tenant, deliberately.
     *
     * ⚠️ **IDEMPOTENT, AND THE FIRST PAUSE WINS — MEANING THE FIRST *LIVE* ONE.**
     * A tenant tripped automatically at 3am and then paused by an operator at 9am
     * keeps the 3am row, because that row carries the rate that caused it and the
     * operator's action does not. A tenant released last week and tripped again
     * today gets a **new row**: that is a second incident, not a continuation of
     * the first, and its own rate belongs to it.
     *
     * ⚠️ **THE DUPLICATE IS REFUSED BY THE DATABASE AND CAUGHT HERE, RATHER THAN
     * AVOIDED BY THE READ ABOVE.** The read closes the ordinary case; it does not
     * close the window between it and the insert, and the write that runs in that
     * window is 2102's automatic trip on the send path — unattended, concurrent
     * with itself across queue workers, and concurrent with an operator pausing
     * the same tenant by hand.
     */
    public function pause(
        SendingPauseReason $reason,
        string $trippedBy,
        ?int $observedRateBp = null,
        ?string $note = null,
    ): SendingPause {
        $existing = $this->livePause();

        if ($existing !== null) {
            return $existing;
        }

        $pause = $this->insert($reason, $trippedBy, $observedRateBp, $note);

        if ($pause === null) {
            // Somebody else won the race. Their row is the first pause, so it is
            // the one that wins — and it is not audited a second time, because
            // the tenant was stopped once.
            return $this->livePause() ?? throw new LogicException(
                'A sending pause was refused as a duplicate and then could not be read back. '
                .'That is only reachable if something released it between the two statements, '
                .'and a tenant must not be recorded as paused on the strength of that race.'
            );
        }

        // Sensitive: this stops every message for a tenant, and the append-only
        // log is what answers "who stopped us and when" for an owner who noticed
        // their campaign died overnight.
        $this->audit->record(
            action: 'sending.paused',
            actor: $trippedBy,
            entity: $pause,
            metadata: [
                'reason' => $reason->value,
                'observed_rate_bp' => $observedRateBp,
            ],
        );

        return $pause;
    }

    /**
     * Let this tenant send again — **without destroying the record of why they
     * stopped**.
     *
     * ⚠️ **THIS METHOD CALLED `$pause->delete()` UNTIL 2119(a), AND THAT WAS THE
     * DEFECT.** The row carries the reason, `tripped_by` — the one thing telling
     * 2102's automatic trip apart from an operator's deliberate pause — and
     * `observed_rate_bp`, which is a snapshot that **cannot be recomputed**
     * because the window has rolled by the time anybody asks. An automatic
     * containment whose incidents cannot be reviewed afterwards is not
     * answerable, and 2101 makes this one a precondition of sending.
     *
     * ## The row and the audit entry are not the same record
     *
     * `audit_log` is the append-only, cross-feature account an auditor reads: who
     * did what, when, across every action this tenant has ever taken. **It is not
     * a series.** Answering *"how many times has this tenant tripped, and at what
     * rate each time"* from it means parsing metadata JSON out of a table that
     * mixes in every other action.
     *
     * ⚠️ **AND IT POINTED AT THIS ROW.** `sending.paused` is recorded with
     * `entity_id` set to the pause's key, so every delete left a **dangling audit
     * reference to a row that no longer existed** — the log naming an entity it
     * could not produce. Retaining the row fixes that as a side effect, and it is
     * worth saying out loud, because "the audit log already records it" is the
     * argument that would otherwise justify deleting the row again.
     *
     * ## Always a person, and nothing self-clears
     *
     * {@see SendingPauseReason::isSelfClearing()} returns false for every case, so
     * nothing in this application calls this on a timer. A complaint rate decays
     * on its own as the window rolls forward whether or not anything was fixed, so
     * an automatic resume restarts the same campaign into the same list — and the
     * second trip looks exactly like the first, hiding that nobody ever looked.
     *
     * @return bool whether anything was actually released
     */
    public function resume(string $actor, ?string $note = null): bool
    {
        $pause = $this->livePause();

        if ($pause === null) {
            return false;
        }

        // ⚠️ **CONDITIONAL ON THE ROW STILL BEING LIVE, AND THE COUNT IS
        // CHECKED.** Two operators clicking resume at once would otherwise both
        // write, and the second would overwrite the first's actor and timestamp —
        // recording the wrong person as having decided sending was safe again,
        // which is the one fact this row exists to hold. Whoever loses is told
        // "nothing was released", which is true.
        //
        // ⛔ **AND NO TEST DRIVES THIS PREDICATE — 398'S SHAPE, SAID OUT LOUD
        // RATHER THAN LEFT FOR THE NEXT REVIEWER.** `livePause()` above already
        // refused, so deleting `whereNull('released_at')` and the `=== 0` branch
        // leaves the whole suite green; that was verified by mutation rather than
        // assumed. It is a **concurrency** guard, and this harness is single
        // threaded inside one transaction, so the second writer it exists for
        // cannot be produced here at all (CLAUDE.md: some claims cannot be proven
        // in this harness, and a test named for one it does not make is worse
        // than none). It stays because the window is real in production — the
        // read and the write are two statements — and it is cheap; what must not
        // happen is somebody deleting it on the strength of a green suite, or
        // adding a test that claims to cover it.
        $released = SendingPause::query()
            ->whereKey($pause->getKey())
            ->whereNull('released_at')
            ->update([
                'released_at' => now(),
                'released_by' => $actor,
                'release_note' => $this->boundedNote($note),
            ]);

        if ($released === 0) {
            return false;
        }

        $this->audit->record(
            action: 'sending.resumed',
            actor: $actor,
            entity: $pause,
            metadata: [
                'paused_reason' => $pause->reason->value,
                'tripped_by' => $pause->tripped_by,
                'note' => $note,
            ],
        );

        return true;
    }

    /**
     * Has this tenant's complaint rate crossed the threshold — and if so, stop
     * them.
     *
     * ⚠️ **THIS METHOD HAS A SIDE EFFECT AND THE NAME SAYS `should`.** That is
     * uncomfortable and it is the correct trade: the alternative is a caller
     * that asks and then separately remembers to act, which is one forgotten
     * line away from a threshold that reports a breach and never stops anything.
     * 2102's requirement is that the trip happen without a human, and a human
     * writing the follow-up call is still a human.
     *
     * ⛔ **EITHER FIGURE AT ZERO MEANS NO, AND THE FLOOR HALF OF THAT WAS
     * MISSING UNTIL 2026-08-16** (4492–4495). See the guard below: `$minimum` of
     * `0` reached `hasEnoughVolume(0)`, which is `delivered >= 0` and always
     * true, so the "one setting in two boxes" rule
     * {@see PlatformRateSample::trips()} states explicitly held on one box only.
     *
     * ⛔ **BOTH FIGURES ARE READ WITH `int()`, NOT `intOr(…, 0)`, AND THE
     * DIFFERENCE IS THE WHOLE TRIP** (2861). `intOr()` returns *the caller's*
     * fallback when `platform_settings` has no row, so with a `0` written here
     * the guard read zero and disabled itself — **regardless of what
     * `DefaultsManifest` seeded**. The manifest has said `300` since the trip was
     * written and this method never once saw it. `int()` falls back to the
     * manifest seed, which is what the registry's own docblock says the seed is
     * for: *"the manifest seed IS the conservative default, it is written once,
     * and it is reviewed."* ⚠️ `intOr()` is documented as being for the handful
     * of keys `declaredWithoutSeed()`, and neither of these is one — so this was
     * the wrong accessor rather than a deliberate override.
     */
    private function shouldTrip(OutreachChannel $channel): bool
    {
        $threshold = $this->defaults->int('messaging.complaint_trip_bp');

        if ($threshold <= 0) {
            // ⚠️ Zero or negative disables the automatic trip. Recorded rather
            // than treated as "trip on everything": a misconfigured threshold
            // that paused every tenant on their first message would be
            // indistinguishable from a platform outage, and an operator would
            // fix it by raising the number until nothing ever trips — 511's
            // failure, in a kill switch.
            return false;
        }

        $minimum = $this->defaults->int('messaging.complaint_trip_min_delivered');

        if ($minimum <= 0) {
            // ⛔ **THE TWO ARE ONE SETTING IN TWO BOXES, AND ONLY ONE OF THE TWO
            // BOXES SAID SO UNTIL 2026-08-16** (4492–4495).
            // `PlatformRateSample::trips()` refuses on `$minimumDelivered <= 0`
            // explicitly; this method went straight to `hasEnoughVolume(0)`,
            // which is `delivered >= 0` and is **always true** — so an operator
            // who blanked this key disabled the platform trip and simultaneously
            // armed the per-tenant one on a floor of nothing, pausing a business
            // on its first complaint out of one delivery.
            //
            // ⚠️ **AND THE SCREEN TOLD THEM THE OPPOSITE.** `TripMath::isArmed()`
            // requires a positive floor, so the sending-controls panel read
            // "Will not stop by itself" while this method stood ready to stop
            // them — verbatim the direction `tenantTripMath()`'s own docblock
            // calls the dangerous one and says "has already happened once here".
            // It had happened twice.
            //
            // The safer of the two readings is adopted: a blanked floor is a
            // trip nobody has configured, not a trip with no floor. That is also
            // 2409's posture — the mechanism is ours, the figure is the owner's,
            // and an unset figure is not a licence to invent zero.
            return false;
        }

        $rates = $this->health->rates($channel);

        if (! $rates->hasEnoughVolume($minimum)) {
            // One STOP out of a new tenant's first two deliveries is a 5,000bp
            // rate. Every tenant starts at zero traffic, so without this floor
            // the trip fires for all of them.
            //
            // ⛔ **AND UNTIL 7480 THIS LINE COULD NOT TELL "NOTHING HAS BEEN
            // SENT" FROM "NOTHING HAS COME BACK", WHICH IS THIS CONTAINMENT
            // BEING DISABLED.** The two produce an identical `delivered` of
            // zero. `SendingRates` has always carried `sent` beside it and
            // nothing had ever asked — see
            // {@see SendingRates::trafficWithoutOutcomes()} for the argument.
            $this->reportBlindSpot($channel, $rates, $minimum);

            return false;
        }

        $observed = $rates->complaintRateBp();

        if ($observed < $threshold) {
            return false;
        }

        $this->pause(
            reason: SendingPauseReason::ComplaintRate,
            trippedBy: self::SYSTEM_ACTOR,
            observedRateBp: $observed,
        );

        return true;
    }

    /**
     * Say — never do — that this containment currently cannot fire on traffic
     * that has already gone out.
     *
     * ⛔ **R25: AN ALERT IS A BELL AND NEVER A BRAKE.** This method returns
     * `void` and the caller returns `false` either way. Refusing a send here
     * would stop the tenant whose *vendor* is misreporting, punish them for it,
     * and still not stop the tenant who is actually generating complaints —
     * because the same silence hides both. The containment being blind is a
     * platform fault and the remedy is an operator's.
     *
     * ## Why the noise is bounded, and why it is not noise when it happens
     *
     * ⚠️ **THIS RUNS ON THE HOT PATH, ONCE PER MESSAGE**, so a campaign of five
     * thousand in the blind-spot world writes five thousand lines. That is
     * deliberate and it is the cheapest honest answer available here — ⚠️ **and
     * since 2026-08-22 it is no longer the WHOLE answer** (7662, on 7600-7619):
     * a scheduled sweep now rings `OperatorAlertKind` about the same condition
     * at platform scope, so this line is the per-tenant record rather than the
     * only thing that knows. **The sentence below is unchanged and is still why
     * the raise does not live here**: this method is asked before every send,
     * and 7485 refused a cache round trip on this path — a `raise()` is a read,
     * an insert, a mail and a text:
     *
     *   - **It never fires in the ordinary world.** A tenant with no traffic,
     *     a campaign whose receipts are still in flight, and a tenant with any
     *     outcome at all — all three answer false at
     *     {@see SendingRates::trafficWithoutOutcomes()}. The line is not
     *     emitted on a quiet platform, ever.
     *   - **When it does fire, log volume is the record of an incident** rather
     *     than noise — every one of those messages went to a member of the
     *     public with the complaint trip switched off behind it.
     *
     * ⛔ **A RATE LIMITER WAS REFUSED AND THE REASON IS THE PATH, NOT THE
     * VOLUME** (7485). Keying a once-per-window suppression off the cache puts
     * a cache round trip — and a cache **outage** — inside `refusalFor()`, which
     * is asked before every single send. A throw there is a brake on all
     * outbound messaging, arriving from the one method whose whole contract is
     * that it returns a reason instead of throwing. **A containment that fails
     * because its own alerting failed is worse than a loud log.**
     *
     * ⚠️ **NO PERSONAL DATA, ON `DeliveryReceipts`' RULE.** Counts, a channel
     * and our own tenant id — never a recipient, a number or a business name.
     */
    private function reportBlindSpot(OutreachChannel $channel, SendingRates $rates, int $minimum): void
    {
        if (! $rates->trafficWithoutOutcomes($minimum)) {
            return;
        }

        Log::warning(
            'The automatic complaint trip cannot fire: messages have gone out and nothing has been reported back.',
            [
                'business_id' => Tenancy::id(),
                'channel' => $channel->value,
                'sent' => $rates->sent,
                'delivered' => $rates->delivered,
                'failed' => $rates->failed,
                'complaints' => $rates->complaints,
                'floor' => $minimum,
            ],
        );
    }

    /**
     * The tenant this guard is answering for.
     *
     * Not used to filter — the global scope and RLS both do that — but asked so
     * that a call with no tenant established throws here, loudly, rather than
     * silently reading an empty pause table and answering "may send".
     */
    public function tenantId(): int
    {
        return Tenancy::idOrFail();
    }

    /**
     * The incident currently stopping this tenant, or null.
     *
     * ⚠️ **THE ONE PLACE THE PREDICATE IS WRITTEN.** `isPaused()`, `pause()` and
     * `resume()` all come through here, so there is exactly one definition of
     * "live" in this class and it matches the partial unique index's. A second
     * spelling of `whereNull('released_at')` is how the guard and the constraint
     * start answering different questions.
     */
    private function livePause(): ?SendingPause
    {
        return SendingPause::query()->whereNull('released_at')->first();
    }

    /**
     * Insert one pause, letting the database refuse a second live one.
     *
     * ⚠️ **WRAPPED IN A TRANSACTION SO THE VIOLATION IS SURVIVABLE.** PostgreSQL
     * aborts the whole transaction on any error, so catching a unique violation
     * raised inside a caller's transaction would leave that transaction poisoned
     * and every later statement failing with something unrelated. Laravel opens a
     * **savepoint** when one is already in flight and rolls back to it, which
     * confines the damage to this statement — and it is what makes the duplicate
     * testable at all, because every test body runs inside one.
     *
     * ⛔ **THE CATCH ITSELF IS NOT DRIVEN BY ANY TEST, AND HALF OF IT IS.**
     * `SendingPauseRetentionTest` proves the database really does raise 23505 on
     * a second live insert, so the exception this catches exists and has that
     * SQLSTATE. What no test reaches is `pause()` *arriving* here, because that
     * needs a row to appear between `livePause()` and this insert — two
     * connections, which this harness does not have. Stated rather than papered
     * over: the branch is a concurrency guard whose trigger cannot be produced
     * in-process, and a test named for it would be claiming more than it does.
     *
     * Returns null when the row was refused as a duplicate, so the caller reads
     * the live one back rather than inventing a return value.
     */
    private function insert(
        SendingPauseReason $reason,
        string $trippedBy,
        ?int $observedRateBp,
        ?string $note,
    ): ?SendingPause {
        try {
            return DB::transaction(fn (): SendingPause => SendingPause::query()->create([
                'reason' => $reason,
                'tripped_by' => $trippedBy,
                'observed_rate_bp' => $observedRateBp,
                'note' => $this->boundedNote($note),
            ]));
        } catch (QueryException $exception) {
            // 23505 is unique_violation. Any other SQLSTATE is a real failure and
            // must not be swallowed into "already paused" — a pause that did not
            // write while the caller believes it did is the one outcome this
            // whole mechanism exists to prevent.
            if ($exception->getCode() !== '23505') {
                throw $exception;
            }

            return null;
        }
    }

    /**
     * Bounded, and empty means absent — `TenantPause::normaliseReason()`'s rule.
     *
     * ⚠️ **THE ROW IS NEVER DELETED NOW**, which is what makes this necessary
     * rather than tidy: an unbounded box lets whoever fills it paste a document
     * into a permanent record. A whitespace-only note is stored as null so that
     * "no note given" has one representation instead of two.
     *
     * The database says the same thing (`sending_pauses_note_is_bounded`, and
     * `release_note`'s own column width). This is what stops a caller ever
     * meeting it: truncating here is kinder than refusing there, and the
     * constraint remains as the backstop against a second writer.
     */
    private function boundedNote(?string $note): ?string
    {
        $note = trim((string) $note);

        return $note === '' ? null : mb_substr($note, 0, self::NOTE_LIMIT);
    }
}
