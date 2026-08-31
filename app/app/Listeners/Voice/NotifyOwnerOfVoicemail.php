<?php

declare(strict_types=1);

namespace App\Listeners\Voice;

use App\Events\Voice\VoicemailRecorded;
use App\Jobs\Voice\NotifyOwnerOfVoicemailJob;
use App\Models\Call;
use App\Support\Tenancy;

/**
 * Voicemail owner notification — *"delivered to the owner by email and/or SMS"*.
 *
 * ⚠️ **IT DISPATCHES AND DOES NOTHING ELSE**, which is
 * {@see TextBackMissedCaller}'s design and its reason: the event fires from a
 * queued job, and every gate, vendor call and retry belongs in a job with a
 * backoff ladder rather than in a listener.
 *
 * ⚠️ **SYNCHRONOUS RATHER THAN `ShouldQueue`, DELIBERATELY.** Queuing a listener
 * whose entire body is "queue a job" buys one extra place for the work to be lost
 * and one extra serialisation of a payload carrying a caller's mobile number.
 *
 * ⚠️ **AUTO-DISCOVERED BY TYPE HINT.** There is no explicit registration to keep
 * in step, and `VoiceIngestTest` fails the build if this class ever stops being
 * wired to the event — *"the sender exists and nothing calls it"* is precisely
 * the shape decision 3112 found on the neighbouring path.
 */
final class NotifyOwnerOfVoicemail
{
    public function handle(VoicemailRecorded $event): void
    {
        NotifyOwnerOfVoicemailJob::dispatch(
            $event->businessId,
            $this->locationOf($event),
            $event->call->providerCallId,
        );
    }

    /**
     * Which of the tenant's locations this voicemail belongs to.
     *
     * ⚠️ **OFF THE CALL ROW, WHICH IS WHERE IT WAS ALREADY RESOLVED.**
     * `TextBackMissedCaller::locationOf()` goes through the contact because it
     * runs before any row exists; by the time a voicemail is recorded the `calls`
     * row is written and carries the answer. Null is an ordinary answer.
     *
     * ⚠️ **THE TENANT IS ESTABLISHED EXPLICITLY**, for that method's reason: a
     * listener on an after-commit event usually inherits its dispatcher's
     * context, but it is also reachable from a console dispatch and a queued
     * replay, and a scoped query with no tenant throws rather than answering
     * wrongly. `actingAs()` restores whatever was there before.
     */
    private function locationOf(VoicemailRecorded $event): ?int
    {
        $locationId = Tenancy::actingAs(
            $event->businessId,
            static fn (): mixed => Call::query()
                ->where('provider_call_id', $event->call->providerCallId)
                ->value('location_id'),
        );

        return is_int($locationId) ? $locationId : null;
    }
}
