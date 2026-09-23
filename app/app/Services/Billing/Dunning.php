<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Enums\DunningOutcome;
use App\Exceptions\AuthorizeNetRequestFailed;
use App\Jobs\AdvanceDunningScheduleJob;
use App\Models\Business;
use App\Models\DunningAttempt;
use App\Models\Subscription;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * The dunning schedule Authorize.Net does not have (T137 SL-11, decision 2108).
 *
 * ⚠️ **THE ROSTER ITSELF SAYS THIS IS NOT ASSUMED**: *"webhook consumer +
 * dunning/retry + suspension logic built explicitly (AuthNet has no native
 * dunning — this is in-scope L1 work, not assumed)"*. Verified against the
 * vendor's live Recurring Billing documentation, read 2026-08-11:
 *
 *   - A declined payment **suspends** the subscription.
 *   - The vendor **does not retry** it and publishes no retry schedule.
 *   - It **terminates** the subscription if "a merchant takes no action on a
 *     suspended account before the next runDate".
 *
 * So the clock is real and it is the vendor's: doing nothing does not leave a
 * tenant in limbo, it ends their subscription. That is why the schedule below is
 * measured in hours rather than in the days a Stripe integration can afford —
 * on Stripe, dunning has as long as Stripe's own retry window; here it has
 * until the next run date.
 *
 * ## What this class does and deliberately does not do
 *
 * **Does**: number the attempts, space them with backoff and jitter, record each
 * one, and hand the terminal case to `Subscriptions::suspendForNonPayment()`.
 *
 * **Does not**: charge anything. There is no `chargeCustomerProfile` call here,
 * and its absence is decision 2143. A retry on this gateway is *not* a fresh
 * charge — the money is owed against an ARB subscription the vendor has already
 * suspended, and charging the profile directly would take the money without
 * reinstating the subscription, leaving a tenant who has paid and is still
 * suspended. The recovery that works is the tenant supplying a new payment
 * method, which points the existing subscription at a new payment profile. **So
 * a "retry" here is a prompt, not a charge**, and what the schedule bounds is
 * how long we ask before the product stops.
 *
 * ⚠️ **AND THAT MAKES THE NOTIFICATION HALF LOAD-BEARING RATHER THAN A NICETY.**
 * A schedule of three silent attempts and then a suspension is a tenant losing
 * the product with no warning.
 * ⛔ **THE NEXT TWO SENTENCES SAID THE NOTIFICATION WAS "NOT BUILT HERE" AND
 * THEY WERE TRUE UNTIL 2026-08-20 — BOTH READINGS KEPT AND DATED** (6400). They
 * read: *"The notification is owed and is **not built here** — `PlatformMailer`
 * is the only sender and open question H still blocks the first customer-facing
 * send… ⚠️ **STILL OWED AFTER 2590's TICK** — the schedule now runs, so the
 * three silent attempts and the suspension are silent and happening, which
 * raises the debt rather than settling it."* ✅ **It is built**:
 * {@see self::announce()} hands every `Declined` and every `Exhausted` to
 * {@see DunningNotices}. ⚠️ **OPEN QUESTION H WAS NEVER THE BLOCKER AND NAMING
 * IT HERE IS WHAT MADE THIS LOOK REFUSED FOR EIGHT DAYS** (6402): H is about a
 * typed bounce feed and SES production access, both external and both the
 * owner's, and `assertCustomerMailPermitted()` guards `sendToCustomer()` — the
 * method this path does not use. `RenewalReminders` has shipped statutory tenant
 * mail down `PlatformMailer::send()` throughout. **It ships dark** (2072): the
 * code is live, and it sends the day the transport does.
 *
 * ## Who calls what
 *
 * `open()` and `closeIfOpen()` are the webhook's ({@see AuthorizeNetWebhooks}).
 * `advanceIfDue()` is the hourly tick's
 * ({@see AdvanceDunningScheduleJob}). `advance()` is the unconditional
 * primitive underneath it and nothing scheduled may call it — see its docblock,
 * which claimed a caller that did not exist until 2590.
 */
final class Dunning
{
    /**
     * Hours after each attempt before the next one is due.
     *
     * ⚠️ **THREE ATTEMPTS AND THEN SUSPENSION IS A POLICY, AND IT IS OURS RATHER
     * THAN THE OWNER'S — WHICH IS WHY IT IS SHAPED TO BE CHANGED IN ONE PLACE.**
     * `CLAUDE.md` reserves "what a delinquent tenant loses and when" for the
     * owner and it is genuinely undecided; what is *not* undecided is that
     * Authorize.Net terminates a suspended subscription before its next run
     * date, so a schedule longer than the billing cycle is a schedule the vendor
     * ends first. These are hours inside a 30-day cycle with room to spare.
     *
     * ⚠️ **NOT A REGISTRY KEY YET, DELIBERATELY.** `38`'s rule would put it
     * there and it belongs there eventually — but `DefaultsRegistry` seeds are
     * the owner's numbers, and putting a figure nobody has chosen into the place
     * the owner's figures live is how a guess acquires their authority (502's
     * shape). It moves when the owner sets it.
     *
     * @var list<int>
     */
    public const array SCHEDULE_HOURS = [24, 72, 120];

    /**
     * How long to wait after an attempt that could not be made at all.
     *
     * ⚠️ **SHORTER THAN THE DECLINE SCHEDULE, ON PURPOSE.** An unreachable
     * gateway is our problem and not the tenant's, and the vendor's own
     * termination clock keeps running while we wait — so the ordinary backoff
     * would spend the tenant's grace period on our outage. One hour plus jitter
     * retries fast enough to matter and slow enough not to hammer a gateway that
     * is already unwell.
     */
    private const int UNREACHABLE_RETRY_HOURS = 1;

    public function __construct(
        private readonly Subscriptions $subscriptions,
        private readonly DunningNotices $notices,
        private readonly AuthorizeNetApi $api = new AuthorizeNetApi,
    ) {}

    private function scheduleHours(): array
    {
        return app(DefaultsRegistry::class)->intList('billing.dunning.schedule_hours');
    }

    /**
     * Open a schedule after a declined payment, or do nothing if one is running.
     *
     * ⚠️ **IDEMPOTENT ON THE FIRST ATTEMPT, WHICH IS §3 RAIL 1 ON THIS PATH.**
     * The vendor can send `subscription.failed` and `subscription.suspended` for
     * one decline, and a redelivery is possible with no documented window. Two
     * schedules for one failure would double the attempt count and halve the
     * tenant's grace period, silently.
     */
    public function open(Business $business): void
    {
        $this->announce($business, DB::transaction(function () use ($business): array {
            if ($this->isOpen($business)) {
                return ['outcome' => null, 'endsOn' => null];
            }

            $sequence = $this->latestSequence($business) + 1;

            $nextAttemptAt = $this->nextAttemptAt(1);

            $this->record(
                sequence: $sequence,
                attempt: 1,
                outcome: DunningOutcome::Declined,
                reasonCode: null,
                nextAttemptAt: $nextAttemptAt,
            );

            return [
                'outcome' => DunningOutcome::Declined,
                'endsOn' => $this->endsOnAfter(1, $nextAttemptAt),
            ];
        }));
    }

    /**
     * Advance the open schedule by one attempt, **whether or not it is due**.
     *
     * Returns the outcome that was recorded, or null when no schedule is open —
     * which is the ordinary case for a caller that arrives after the tenant
     * already fixed their card.
     *
     * ⚠️ **THIS DOCBLOCK SAID "Called from the queued job that runs when
     * `next_attempt_at` comes round" AND THERE WAS NO SUCH JOB** (2590). That is
     * `CLAUDE.md`'s 314–316 shape — a mechanism asserted before it exists —
     * written inside the very slice that quotes it. The job exists now
     * ({@see AdvanceDunningScheduleJob}) and it does **not** call this
     * method: it calls {@see self::advanceIfDue()}, because a due check that
     * lives in the caller is a due check two callers can disagree about.
     *
     * ⚠️ **UNCONDITIONAL, AND KEPT THAT WAY DELIBERATELY** (2592). This is the
     * primitive — one step of the schedule, no clock consulted — and it is what
     * an operator-driven "advance this tenant now" would use. Nothing scheduled
     * may call it. The clock is `advanceIfDue()`'s.
     *
     * ⚠️ **THE ATTEMPT NUMBER IS COMPUTED UNDER A LOCK AND THE INSERT IS UNIQUE
     * ON IT.** Two workers picking up the same due schedule would otherwise both
     * write "attempt 3" and burn two of the tenant's three chances at once.
     */
    public function advance(Business $business): ?DunningOutcome
    {
        return $this->announce(
            $business,
            DB::transaction(fn (): array => $this->advanceUnderLock($business, requireDue: false)),
        );
    }

    /**
     * Advance the open schedule by one attempt, but only once it is due.
     *
     * ⚠️ **THE DUE CHECK IS INSIDE THE TRANSACTION AND UNDER THE SAME LOCK, AND
     * THAT PLACEMENT IS THE WHOLE IDEMPOTENCY GUARANTEE OF THE TICK** (2593).
     * `29` §2 rule 40 wants every job idempotent, and the shape of a double-run
     * here is not a duplicate row — the unique key already refuses those — it is
     * a **second escalation**: the first run writes attempt 3, the second reads
     * that as the head and writes attempt 4, and a tenant who was six days from
     * suspension loses the product in the same minute. A check in the job, or in
     * the command that dispatches it, is `CLAUDE.md`'s decision 398 exactly:
     * two workers both read "due", both pass, and the guard that was supposed to
     * stop the second one never ran inside the transaction that could.
     *
     * Null when nothing is open, when the head is terminal, or when the next
     * attempt is still in the future. All three are ordinary and none is an
     * error — the tick runs hourly against every tenant that has a schedule.
     */
    public function advanceIfDue(Business $business): ?DunningOutcome
    {
        return $this->announce(
            $business,
            DB::transaction(fn (): array => $this->advanceUnderLock($business, requireDue: true)),
        );
    }

    /**
     * The body both entry points share. Always called inside a transaction.
     *
     * @return array{outcome: ?DunningOutcome, endsOn: ?Carbon}
     */
    private function advanceUnderLock(Business $business, bool $requireDue): array
    {
        // Lock the business row rather than the ledger's head, for
        // `CreditLedger::record()`'s reason: the first movement of a
        // sequence locks an empty set and therefore locks nothing.
        Business::query()->whereKey($business->id)->lockForUpdate()->first();

        $head = $this->head($business);

        if (! $head instanceof DunningAttempt || $head->outcome->isTerminal()) {
            return ['outcome' => null, 'endsOn' => null];
        }

        if ($requireDue && ! $this->headIsDue($head)) {
            return ['outcome' => null, 'endsOn' => null];
        }

        return $this->recordAdvance($business, $head);
    }

    /**
     * Decide and write one attempt against an open, locked head.
     *
     * @return array{outcome: DunningOutcome, endsOn: ?Carbon}
     */
    private function recordAdvance(Business $business, DunningAttempt $head): array
    {
        $status = $this->vendorStatus($business);

        if ($status === 'unreachable') {
            /*
             * ⚠️ RECORDED, AND IT DOES NOT COUNT AGAINST THE BUDGET. A
             * vendor outage must never suspend a tenant for something they
             * did not do — `29` §2 rule 43's "never hard-fail", and the
             * failure mode that would hit every tenant at once.
             *
             * ⚠️ **AND IT RETRIES SOONER RATHER THAN ON THE ORDINARY
             * BACKOFF** (decision 2148). The first version reused the
             * decline schedule here and had two defects that only showed up
             * under mutation. The first: the delay was indexed by the raw
             * attempt number, so three outages pushed the index past the end
             * of the schedule, `nextAttemptAt()` returned null, and the
             * schedule **stopped** — non-terminal, unscheduled, invisible.
             * The second: waiting a day per outage spends the tenant's grace
             * period against the vendor's own termination clock, which keeps
             * running whatever our gateway is doing.
             */
            $this->record(
                sequence: $head->sequence,
                attempt: $head->attempt + 1,
                outcome: DunningOutcome::Unreachable,
                reasonCode: null,
                nextAttemptAt: Carbon::now()
                    ->addHours(self::UNREACHABLE_RETRY_HOURS)
                    ->addMinutes(random_int(0, 59)),
            );

            return ['outcome' => DunningOutcome::Unreachable, 'endsOn' => null];
        }

        if ($status === 'active') {
            // The tenant fixed it themselves between attempts. Distinct from
            // `Recovered`, which is our retry succeeding — a sustained run
            // of this with none of that means our prompts are doing nothing.
            $this->record(
                sequence: $head->sequence,
                attempt: $head->attempt + 1,
                outcome: DunningOutcome::ResolvedByTenant,
                reasonCode: null,
                nextAttemptAt: null,
            );

            return ['outcome' => DunningOutcome::ResolvedByTenant, 'endsOn' => null];
        }

        // ⚠️ COUNTED FAILURES, NOT THE ATTEMPT NUMBER, AND THE DIFFERENCE IS
        // THE WHOLE OF THE `Unreachable` RULE. `attempt` counts everything
        // that happened; this counts only what the tenant is answerable for.
        $failures = $this->countedFailures($business, $head->sequence);

        if ($failures >= count($this->scheduleHours())) {
            $now = Carbon::now();

            $this->record(
                sequence: $head->sequence,
                attempt: $head->attempt + 1,
                outcome: DunningOutcome::Exhausted,
                reasonCode: null,
                nextAttemptAt: null,
            );

            // ⚠️ THE ONLY CALLER OF THE ONLY METHOD THAT WITHDRAWS
            // ENTITLEMENT FOR NON-PAYMENT — see decision 2142 on
            // `Subscriptions::suspendForNonPayment()`. Every softer question
            // is still the owner's.
            $this->subscriptions->suspendForNonPayment($business, $now);

            return ['outcome' => DunningOutcome::Exhausted, 'endsOn' => null];
        }

        // Indexed by counted failures rather than by attempt number, so an
        // outage cannot push the schedule off the end of itself.
        $nextAttemptAt = $this->nextAttemptAt($failures + 1);

        $this->record(
            sequence: $head->sequence,
            attempt: $head->attempt + 1,
            outcome: DunningOutcome::Declined,
            reasonCode: null,
            nextAttemptAt: $nextAttemptAt,
        );

        return [
            'outcome' => DunningOutcome::Declined,
            'endsOn' => $this->endsOnAfter($failures + 1, $nextAttemptAt),
        ];
    }

    /**
     * Tell the tenant what just happened to them, once the row is committed.
     *
     * ⛔ **THE HALF THIS CLASS SPENT TWO SLICES SAYING IT DID NOT HAVE** (2143,
     * 2590, closed at 6400). Read the class docblock above for the debt; what is
     * worth saying *here* is the shape.
     *
     * ⚠️ **IT HANGS OFF THE WRITTEN OUTCOME AND CARRIES NO GUARD OF ITS OWN,
     * WHICH IS WHERE THE IDEMPOTENCY COMES FROM** (`CLAUDE.md` 398). A tenant is
     * told exactly when an attempt row is written, and an attempt row is written
     * at most once per `(sequence, attempt)` — the unique key and the due check
     * under the lock already guarantee that. A second "have we told them yet?"
     * check would be a guard nothing can drive red, because the thing above it
     * has already refused: {@see self::advanceIfDue()} run twice returns null the
     * second time, and {@see self::open()} run twice returns null the second time.
     * **The absence of a second guard is the design, not an omission.**
     *
     * ⚠️ **TWO OUTCOMES ARE DELIBERATELY SILENT.** `Unreachable` is our gateway
     * being unwell and changes nothing for the tenant — 2148 makes it not count
     * against them, and emailing somebody about our outage is noise with an
     * alarming subject line. `ResolvedByTenant` and `Recovered` close a schedule
     * happily and are the one gap this slice leaves open on purpose — see 6406.
     *
     * @param  array{outcome: ?DunningOutcome, endsOn: ?Carbon}  $step
     */
    private function announce(Business $business, array $step): ?DunningOutcome
    {
        if ($step['outcome'] === DunningOutcome::Declined) {
            $this->notices->paymentFailed($business, $step['endsOn']);
        }

        if ($step['outcome'] === DunningOutcome::Exhausted) {
            $this->notices->planEnded($business);
        }

        return $step['outcome'];
    }

    /**
     * The date the plan ends if nothing changes — on the last warning only.
     *
     * ⚠️ **DERIVED FROM THE SCHEDULE RATHER THAN RESTATED BESIDE IT.** The date
     * is the `next_attempt_at` just written, and the *last* warning is the one
     * after which {@see self::SCHEDULE_HOURS} has no further step — so changing
     * the schedule moves the sentence in the email with it, and no copy anywhere
     * holds a second opinion about how many attempts there are.
     *
     * ⛔ **NULL ON EVERY EARLIER NOTICE, AND THAT IS HONESTY RATHER THAN
     * VAGUENESS.** An `Unreachable` attempt does not count against the tenant
     * (2148), so the suspension moment genuinely slips while our gateway is
     * unwell — later, never earlier. A date promised on the first notice is one
     * this class breaks by design; a date promised on the last one only ever
     * errs in the tenant's favour.
     */
    private function endsOnAfter(int $failuresRecorded, ?Carbon $nextAttemptAt): ?Carbon
    {
        return $failuresRecorded === count($this->scheduleHours()) ? $nextAttemptAt : null;
    }

    /**
     * End an open schedule because the subscription is active again.
     *
     * Called from the webhook path when the vendor reports the subscription
     * running. Silent when nothing is open, which is the common case: most
     * `active` notifications have no schedule behind them at all.
     *
     * ⚠️ **IT TAKES THE SAME LOCK `advance()` DOES** (2594) — see
     * {@see self::closeWith()}, which is where that body now lives, and where the
     * ordering argument is written out. Unlocked, a `Declined` written a
     * microsecond later could bury an `active` the vendor had already reported.
     */
    public function closeIfOpen(Business $business): void
    {
        $this->closeWith($business, DunningOutcome::Recovered);
    }

    /**
     * End an open schedule because the subscription no longer exists.
     *
     * ⚠️ **THE SCHEDULE WAS RUNNING ON WITHOUT THIS, AND THE END OF IT WAS
     * `suspendForNonPayment()` ON AN ALREADY-CANCELLED ROW** (decision 2685).
     * `applyStatus()` closed a schedule only on `active`, so a
     * `subscription.cancelled` — or a `terminated`, which is what this vendor
     * does to a suspended subscription nobody acts on — left the tick stepping
     * through its remaining attempts against a subscription that had stopped
     * existing. Nothing diverged in the end, because the row was already
     * `Canceled` and the suspension writes the same word; what was wrong is the
     * **record**, which said we exhausted our retries when the vendor had ended
     * it days earlier.
     *
     * ⚠️ **IT IS A CLOSE, NOT A SUSPENSION.** Nothing here withdraws entitlement:
     * the webhook that reports a cancellation has already projected it through
     * `Subscriptions::applyAuthorizeNetSubscription()`, and 2142 keeps
     * `suspendForNonPayment()` reachable from `Exhausted` alone.
     */
    public function closeAsCanceledAtGateway(Business $business): void
    {
        $this->closeWith($business, DunningOutcome::CanceledAtGateway);
    }

    /**
     * Write one terminal attempt against an open head, under the lock.
     *
     * ⚠️ **THE LOCK IS `advance()`'s AND THAT IS WHAT MAKES "WEBHOOKS ARE THE
     * SOURCE OF TRUTH" TRUE UNDER CONCURRENCY RATHER THAN MERELY ASSERTED**
     * (2594). Both this and the tick write attempt N+1 of the same sequence, so
     * the unique key already guarantees that only one of them lands — but without
     * a shared lock *which* one lands is a race, and the loser is silently
     * discarded. Serialised, both orderings end with the webhook winning: if the
     * close goes first the tick reads a terminal head and returns null; if the
     * tick goes first the close writes its outcome on top of the `Declined`.
     */
    private function closeWith(Business $business, DunningOutcome $outcome): void
    {
        DB::transaction(function () use ($business, $outcome): void {
            Business::query()->whereKey($business->id)->lockForUpdate()->first();

            $head = $this->head($business);

            if (! $head instanceof DunningAttempt || $head->outcome->isTerminal()) {
                return;
            }

            $this->record(
                sequence: $head->sequence,
                attempt: $head->attempt + 1,
                outcome: $outcome,
                reasonCode: null,
                nextAttemptAt: null,
            );
        });
    }

    /**
     * Whether a schedule is currently running for this business.
     */
    public function isOpen(Business $business): bool
    {
        $head = $this->head($business);

        return $head instanceof DunningAttempt && ! $head->outcome->isTerminal();
    }

    /**
     * Whether this business has an attempt whose time has come.
     *
     * ⚠️ **AN EFFICIENCY FILTER FOR THE SWEEP, AND SAYING ANYTHING STRONGER
     * WOULD BE 314–316's MISTAKE** (2595). Nothing a database read can observe
     * changes if the command stops calling this: `advanceIfDue()` re-asks the
     * same question inside its own transaction, under the lock, and that is the
     * answer that decides. What this buys is that the hourly sweep does not
     * dispatch a job — and, on any base class that opened a run row, write a row
     * — for every tenant on the platform every hour to discover that none of
     * them owes anything. Dunning is rare; the tick is not.
     */
    public function isDue(Business $business): bool
    {
        $head = $this->head($business);

        return $head instanceof DunningAttempt
            && ! $head->outcome->isTerminal()
            && $this->headIsDue($head);
    }

    /**
     * Whether the clock has come round on an attempt.
     *
     * ⚠️ **A NULL `next_attempt_at` IS "NEVER", NOT "NOW", AND THE DIRECTION IS
     * DELIBERATE** (2596). Every terminal outcome is written with a null, so the
     * null case is ordinarily a finished schedule. What it must never do is read
     * as due: 2148 records a defect that left a sequence **non-terminal and
     * unscheduled**, and a helper that treated null as due would have turned
     * that stranded row into an immediate escalation the moment this tick shipped
     * — the bug being silently converted into a suspension. `an open schedule
     * always carries a next attempt` is the test that keeps the null case empty.
     *
     * The comparison is deliberately not `isPast()`: an attempt due at exactly
     * this instant is due.
     */
    private function headIsDue(DunningAttempt $head): bool
    {
        $due = $head->next_attempt_at;

        return $due !== null && ! $due->isFuture();
    }

    /**
     * The vendor's own view: `active`, `unreachable`, or anything else.
     *
     * ⚠️ **THE RECONCILIATION READ, AND IT IS WHY DUNNING DOES NOT TRUST ITS OWN
     * RECORD.** A tenant can fix their card in the merchant interface, or
     * through a support call, and no notification of ours would necessarily
     * arrive — the vendor publishes no retry schedule for webhooks either.
     * Asking before each attempt is what stops the schedule suspending somebody
     * who is already paying.
     */
    private function vendorStatus(Business $business): string
    {
        $subscriptionId = $this->subscriptions->for($business)?->authorize_net_subscription_id;

        if ($subscriptionId === null) {
            return 'unknown';
        }

        try {
            $status = $this->api->subscriptionStatus($business->id, $subscriptionId);
        } catch (AuthorizeNetRequestFailed) {
            // ⚠️ EVERY FAILURE READS AS UNREACHABLE HERE, INCLUDING THE ONES
            // THAT ARE OUR FAULT. A wrong API key and a vendor outage are
            // different problems with the same right answer on this path: do not
            // count it against the tenant. `CredentialManifest` is where an
            // operator finds out which one it was.
            return 'unreachable';
        }

        return strtolower((string) $status) === 'active' ? 'active' : 'unknown';
    }

    /**
     * How many attempts in this sequence counted as failures.
     *
     * ⚠️ `DunningOutcome::countsAsFailure()` DECIDES, NOT A `COUNT(*)`. An
     * `Unreachable` row is an attempt that happened and must not shorten the
     * tenant's grace period.
     */
    private function countedFailures(Business $business, int $sequence): int
    {
        return DunningAttempt::query()
            ->where('business_id', $business->id)
            ->where('sequence', $sequence)
            ->get()
            ->filter(fn (DunningAttempt $attempt): bool => $attempt->outcome->countsAsFailure())
            ->count();
    }

    private function head(Business $business): ?DunningAttempt
    {
        // `latest('id')`, never `latest()` — decision 289. The default orders by
        // `created_at`, which does not exist on this table at all, and Postgres
        // sorts NULL first on a descending order. In an append-only table the id
        // is the order things happened in.
        return DunningAttempt::query()
            ->where('business_id', $business->id)
            ->latest('id')
            ->first();
    }

    private function latestSequence(Business $business): int
    {
        return (int) (DunningAttempt::query()
            ->where('business_id', $business->id)
            ->max('sequence') ?? 0);
    }

    /**
     * When the next attempt is due — backoff with jitter, computed now.
     *
     * ⚠️ **THE JITTER IS THE POINT AND IT IS COMPUTED AT WRITE TIME.** Every
     * tenant whose card fails in the same hour would otherwise retry in the same
     * second, which is a thundering herd at a payment gateway — the one place a
     * self-inflicted burst is answered with rate limiting rather than latency.
     * Storing the instant makes the spread real; recomputing it at dispatch
     * would let a busy queue collapse it again.
     *
     * Null when the schedule is finished.
     */
    private function nextAttemptAt(int $attemptJustRecorded): ?Carbon
    {
        $hours = $this->scheduleHours()[$attemptJustRecorded - 1] ?? null;

        if ($hours === null) {
            return null;
        }

        return Carbon::now()
            ->addHours($hours)
            // Up to an hour of spread, in minutes. `random_int` rather than
            // `rand`: this is not cryptography, but the seeded generator is
            // shared with anything else in the process and a test that seeds it
            // would silently remove the jitter.
            ->addMinutes(random_int(0, 59));
    }

    /**
     * Write one attempt.
     *
     * ⚠️ **`insertOrIgnore` ON THE UNIQUE KEY RATHER THAN A `create()`.** A
     * retried job writing attempt 3 of sequence 7 again must land on the row it
     * already wrote, not add a second — 350's lesson, and here the consequence
     * of getting it wrong is a tenant losing the product one attempt early.
     */
    private function record(
        int $sequence,
        int $attempt,
        DunningOutcome $outcome,
        ?string $reasonCode,
        ?Carbon $nextAttemptAt,
    ): void {
        DunningAttempt::query()->insertOrIgnore([
            'business_id' => Tenancy::idOrFail(),
            'sequence' => $sequence,
            'attempt' => $attempt,
            'outcome' => $outcome->value,
            'reason_code' => $reasonCode,
            'attempted_at' => Carbon::now(),
            'next_attempt_at' => $nextAttemptAt,
        ]);
    }
}
