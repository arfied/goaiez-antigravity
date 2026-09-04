<?php

declare(strict_types=1);

namespace App\Listeners\Voice;

use App\Events\Voice\CallMissed;
use App\Jobs\SendMissedCallTextBackJob;
use App\Jobs\Voice\IngestVoiceEventJob;
use App\Models\Customer;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;

/**
 * The one listener that turns a missed call into the SM-001 text-back.
 *
 * ⚠️ **IT DISPATCHES AND DOES NOTHING ELSE**, which is the whole design.
 * `CallMissed` is `ShouldDispatchAfterCommit`, and every gate, every vendor call
 * and every retry belongs in {@see SendMissedCallTextBackJob} rather than here.
 *
 * ⚠️ **THE REASON GIVEN FOR THAT USED TO BE A FACT THAT IS NO LONGER TRUE**
 * (4515). This said the event *"fires inside the voice webhook's request, where
 * the carrier is waiting on a response"* — true of the shape anticipated before
 * T176 P2 and false since: `InfobipVoiceController` queues
 * {@see IngestVoiceEventJob} and answers immediately, so this
 * listener runs on a queue worker with nobody waiting. **The reasoning survives
 * the correction** — a listener that sent the message itself would still put a
 * vendor's latency in front of an irreversible message to a member of the
 * public, and would now be doing it where the failure looks like a lost job
 * rather than a refused send.
 *
 * ⚠️ **SYNCHRONOUS RATHER THAN `ShouldQueue`, DELIBERATELY.** Queuing a listener
 * whose entire body is "queue a job" buys one extra place for the work to be
 * lost and one extra serialisation of a payload carrying a caller's mobile
 * number. `RecordSuccessfulLogin` made the same call for the same kind of
 * reason.
 *
 * ⚠️ **AUTO-DISCOVERED BY TYPE HINT**, like the one listener that preceded it —
 * there is no explicit registration to keep in step, and a lint in
 * `tests/Feature/Voice/MissedCallTextBackTest.php` fails the build if this class
 * ever stops being wired to the event, because "the sender exists and nothing
 * calls it" is precisely the shape decision 3112 found here in the first place.
 */
final class TextBackMissedCaller
{
    public function handle(CallMissed $event): void
    {
        // ⛔ **THE SAME GATE AS THE SENDER'S, AND IT IS HERE BECAUSE OF WHAT THE
        // SENDER'S VERSION COSTS WHEN IT FIRES** (3192). `MissedCallTextBack`
        // throws a `LogicException` for a call that owes no text-back, which is
        // the right shape for a wiring mistake — but reached *through a queued
        // job* it is not merely loud, it is durable: `AutopilotJob` rethrows,
        // `$tries = 3` exhausts, and the job lands in `failed_jobs` **carrying
        // the whole `InboundCall`, and therefore the caller's mobile number, in
        // cleartext**. Production runs the database queue driver, `failed_jobs`
        // is one of 3148's thirty-six tables with no row-level security, and no
        // global scope or crypto-shred reaches it.
        //
        // ⚠️ **THIS SAID "NOTHING PRUNES IT" AND THAT STOPPED BEING TRUE ON
        // 2026-08-23** (8610-8639): `jobs:prune-failed` bounds the table at
        // thirty days. ⛔ **THE GATE BELOW IS UNCHANGED AND THE CORRECTION IS
        // ONE CLAUSE OF ONE SENTENCE.** A horizon is not an erasure path, the
        // paragraph below is about *how many* rows rather than how long they
        // live, and thirty days of every caller's mobile number is the same
        // finding at a different scale.
        //
        // ⚠️ **AND THE QUEUED CLASS IS `SendMissedCallTextBackJob`, NOT THIS
        // ONE** (8725-8735). This listener is synchronous by design — its own
        // class docblock says so — so nothing here is ever serialised. The
        // pruner's docblock credited the payload to this class and to the
        // service beside it, and named neither of the jobs that actually holds
        // one; that list is now derived and checked.
        //
        // ⛔ **AND IT WOULD NOT BE ONE ROW.** If the webhook mapper mislabels an
        // answered call as a missed one it does so for *every* answered call on
        // the platform — so the outcome of the exact scenario that gate assumes
        // will happen is **every caller's mobile accumulating in `failed_jobs`**.
        //
        // ⚠️ **BELT AND BRACES, NOT A REPLACEMENT.** The sender keeps its throw.
        // This listener is one call site, and the webhook lane will be written
        // against the *service* contract — a listener-only check is exactly the
        // "check at one call site, absent at the other three" shape this
        // codebase keeps being bitten by. What this adds is that no **queued**
        // job carrying a phone number is ever created for a call that owes
        // nothing, so the throw stays reachable and stops being reachable *with
        // a payload*.
        //
        // ⚠️ **A LOG LINE RATHER THAN A THROW, BECAUSE THIS RUNS INSIDE THE
        // WEBHOOK REQUEST.** Throwing here would fail the carrier's request and
        // take every other listener on the event down with it. The line carries
        // the vendor's call id, the event type and the tenant — and **no phone
        // number**, which is the whole point of the branch.
        if (! $event->call->type->owesTextBack()) {
            Log::warning('A call that owes no text-back reached the missed-call listener', [
                'provider_call_id' => $event->call->providerCallId,
                'type' => $event->call->type->value,
                'business_id' => $event->businessId,
            ]);

            return;
        }

        SendMissedCallTextBackJob::dispatch(
            $event->businessId,
            $this->locationOf($event),
            $event->call,
            // ⚠️ **THE EVENT'S OWN STRING.** Neither this class nor the job may
            // derive a second one — see `CallMissed::occasion()`, which exists
            // to be the single derivation.
            $event->occasion(),
        );
    }

    /**
     * Which of the tenant's locations this call belongs to, when that is
     * knowable.
     *
     * ⚠️ **OFF THE CONTACT, NOT OFF THE NUMBER.** `phone_numbers` carries a
     * `location_id` and would be the more direct answer, but that model is held
     * to eight files by the `MessagingTest` chokepoint so nothing outside the
     * lifecycle and the selector can name it — and widening a chokepoint to
     * populate a reporting dimension is the wrong trade.
     *
     * ⚠️ **NULL IS AN ORDINARY ANSWER.** A first-time caller has no contact row
     * at all, which `InboundCall` calls *"the common case and not an error"*, so
     * the automation run is business-scoped for that call. `AutopilotJob` takes
     * a nullable location precisely because some work cannot name one.
     *
     * ⚠️ **THE TENANT IS ESTABLISHED EXPLICITLY.** A listener on an after-commit
     * event usually inherits the webhook's context, but it is also reachable
     * from a console dispatch and from a queued replay — and a `Customer` query
     * with no tenant throws `TenantNotResolved` rather than answering wrongly.
     * `actingAs()` restores whatever was there before, so this cannot leak a
     * tenant into the rest of the request.
     */
    private function locationOf(CallMissed $event): ?int
    {
        $customerId = $event->call->customerId;

        if ($customerId === null) {
            return null;
        }

        // ⚠️ `is_int()` RATHER THAN A CAST, BECAUSE `actingAs()` IS TYPED
        // `mixed` AND A `(int)` WOULD TURN "no such contact" INTO LOCATION 0 —
        // a foreign key to nothing, on a run row somebody reads.
        $locationId = Tenancy::actingAs(
            $event->businessId,
            static fn (): mixed => Customer::query()->whereKey($customerId)->value('location_id'),
        );

        return is_int($locationId) ? $locationId : null;
    }
}
