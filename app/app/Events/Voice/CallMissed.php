<?php

declare(strict_types=1);

namespace App\Events\Voice;

use App\Services\Messaging\Outbound\SendKey;
use App\Services\Voice\InboundCall;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * A caller reached a tenant's number and the business did not speak to them.
 *
 * **This is the event the product turns on**, and it is the one L5 binds to.
 * The missed-call chain: conditional forwarding → the tenant's Infobip number answers →
 * this fires → the SM-001 text-back goes to the caller **from the number they
 * just dialled** (tenant dedicated number allocation puts voice, SMS and MMS on one number per tenant so it
 * can) → the conversation lane takes the resulting SMS thread, 24/7, per the
 * T69 law.
 *
 * ## It fires whether or not a voicemail was left
 *
 * A caller who hangs up during the greeting is still a missed call and still
 * gets the text-back — arguably more urgently, because there is no message for
 * the owner to act on. ⛔ **An implementation that waits for
 * {@see VoicemailRecorded} before firing this has made the product's core
 * promise conditional on the caller's patience.**
 *
 * ## It never waits for a transcript
 *
 * `CLAUDE.md`: *never block on transcription — STT failure still delivers the
 * audio.* {@see VoicemailTranscribed} is a separate, later, optional event.
 *
 * ⛔ **NOT `ShouldBroadcast`, DELIBERATELY.** The payload holds a caller's mobile
 * number, and a broadcast passes through Reverb into browser memory and
 * devtools. `ActivityRecorded` carries ids and a closed vocabulary for exactly
 * this reason; a voice event cannot be reduced that far and still be useful to
 * the lane that consumes it, so it does not go on the wire at all. A staff
 * screen that needs to know a call came in learns it through the activity feed.
 *
 * ## `ShouldDispatchAfterCommit`, always
 *
 * The webhook handler writes the call and its voicemail row inside a
 * transaction. Without this, a listener could fire the text-back — an
 * irreversible message to a member of the public — for a call whose row then
 * rolled back and which no query will ever find. Every connection in
 * `config/queue.php` sets `after_commit` to `false`, so the interface is what
 * does this work rather than the configuration.
 *
 * ⚠️ **A LISTENER IS NOT ALLOWED TO ASSUME IT RUNS ONCE.** Voice webhooks
 * redeliver. The event carries `providerCallId` and every listener keys its work
 * on it — the text-back through a {@see SendKey}
 * whose occasion names this call, so a redelivered webhook produces
 * `SendOutcomeStatus::Duplicate` rather than a second apology to the same person.
 */
final class CallMissed implements ShouldDispatchAfterCommit
{
    use Dispatchable;

    public function __construct(
        public readonly int $businessId,
        public readonly InboundCall $call,
    ) {}

    /**
     * The idempotency occasion every listener on this event keys its work to.
     *
     * ⚠️ **HERE RATHER THAN IN EACH LISTENER.** Two listeners deriving "the
     * occasion of this missed call" independently will eventually derive it
     * differently, and the day they do, a redelivered webhook sends the caller
     * two apologies. One method, one string, and a listener that wants a
     * different scope suffixes this rather than composing its own.
     */
    public function occasion(): string
    {
        return 'missed_call:'.$this->call->providerCallId;
    }
}
