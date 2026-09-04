<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\Enums\VoiceIngestOutcome;
use App\Enums\VoiceWebhookEvent;
use App\Jobs\Voice\Concerns\JitteredBackoff;
use App\Services\Config\DefaultsRegistry;
use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\VoiceCalls;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * One voice webhook, off the carrier's request — T176 P2.
 *
 * ⛔ **A PLAIN `ShouldQueue` RATHER THAN `AutopilotJob`, AND THE REASON IS THAT
 * THE TENANT IS NOT KNOWN YET.** `AutopilotJob`'s constructor takes a
 * `businessId` and its `handle()` establishes it before anything else; a voice
 * webhook arrives naming a vendor call id and nothing else, and the tenant is
 * only discovered by reading the call back and resolving the *called* number.
 * Passing a placeholder to satisfy the base class would be a tenant asserted
 * before it is known, on the path that ends in a message to a member of the
 * public. Everything downstream of resolution *is* an `AutopilotJob` — this is
 * the one step that cannot be.
 *
 * ⛔ **AND IT IS THE REASON THE VENDOR READ IS NOT ON THE WEBHOOK REQUEST.** The
 * carrier is waiting on that response; a synchronous read-back would put
 * Infobip's own latency in front of their timeout and earn a **redelivery**,
 * which on this feature is a second apology to a stranger.
 *
 * ## The gates
 *
 * ⚠️ **`voice.enabled` IS THE OPERATIONAL SWITCH AND `VOICE_DRIVER` IS THE
 * DEPLOYMENT ONE**, deliberately separate — `sms.enabled` against `SMS_DRIVER`,
 * one channel over, and decision 193's split. Either one off means nothing is
 * recorded and nothing is texted. The switch is checked *here* rather than
 * inside {@see VoiceCalls} so that flipping it stops the work before a vendor
 * call is made rather than after.
 *
 * ⚠️ **THE PER-TENANT PAUSE AND THE PLATFORM HALT ARE NOT CHECKED HERE, AND
 * THAT IS CORRECT.** They cannot be — there is no tenant yet — and they do not
 * need to be: the only thing on this path that reaches a person is
 * `SendMissedCallTextBackJob`, which is an `AutopilotJob` and asks all three.
 * Recording that a call happened is not an outbound action, and refusing to
 * record it during a halt would lose the owner's own call history for the
 * duration of an incident.
 *
 * ⚠️ **NOT IDEMPOTENT BY A CLAIM ROW — IDEMPOTENT BY THE DATA.** There is no
 * `automation_runs` key because there is no tenant to scope one to;
 * {@see VoiceCalls} upserts on `calls.provider_call_id` under a row lock and
 * refuses to move a settled call, which is the layer that holds. A redelivered
 * webhook therefore answers `Duplicate` and dispatches nothing.
 */
final class IngestVoiceEventJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use JitteredBackoff;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $providerCallId,
        public readonly VoiceWebhookEvent $event,
    ) {}

    public function handle(VoiceCalls $calls, DefaultsRegistry $registry, RecordingAnnouncement $announcement): void
    {
        if (! $registry->value('voice.enabled')) {
            return;
        }

        // ⛔ **NO RECORDING IS STORED WITHOUT A RECORDED OPERATOR ATTESTATION
        // THAT THE ANNOUNCEMENT IS CONFIGURED** (4505). `29` §2 and 2104 make
        // the announcement unconditional in every state, and until this the only
        // thing enforcing it was step 5 of a runbook — while the owner's own
        // screen printed *"every caller is told so before they can speak"* as a
        // fact. California §632 makes that a two-party-consent violation whose
        // penalty lands on the tenant, so the attestation gates the one action
        // that can produce one: keeping the audio.
        //
        // ⚠️ **CHECKED HERE AS WELL AS AT THE REGISTRY WRITE, AND BOTH ARE
        // MEANT.** `voice:announcement-attestation record` is what an operator
        // goes through; a `platform_settings` row edited by hand, or a manifest
        // seed changed in a future branch, reaches `voice.enabled` without
        // passing it. This is the layer nothing bypasses, and it sits where the
        // bytes would otherwise be fetched.
        //
        // ⛔ **THIS SENTENCE NAMED `RecordingAnnouncement::enable()`, A METHOD
        // THAT HAS NEVER EXISTED** (4514) — and the writer it was pointing at
        // did not exist either, so the switch this guard backs up could not be
        // turned on by any path at all. Both are real now.
        if ($this->event === VoiceWebhookEvent::RecordingReady && ! $announcement->isAttested()) {
            Log::warning('A recording event was refused: no operator has attested the recording announcement.', [
                'provider_call_id' => $this->providerCallId,
            ]);

            return;
        }

        try {

            $outcome = match ($this->event) {
                VoiceWebhookEvent::RecordingReady => $calls->attachRecording($this->providerCallId),
                VoiceWebhookEvent::CallEnded => $calls->record($this->providerCallId),
            };

            if ($outcome === VoiceIngestOutcome::AwaitingCall) {
                // ⛔ **RELEASED, WHICH IS WHAT MAKES THE BACKOFF LADDER REAL**
                // (4518). A recording notification overtaking its own call event is
                // the ordinary out-of-order delivery this path was always described
                // as handling — and it was ending the job successfully, so no
                // voicemail row was created and the owner was never told. On the
                // last attempt `release()` stops retrying and the log line below is
                // what somebody reads.
                if ($this->attempts() < $this->tries) {
                    $this->release($this->backoff()[$this->attempts() - 1] ?? 900);

                    return;
                }

                Log::warning('A recording arrived and its call never did.', [
                    'provider_call_id' => $this->providerCallId,
                    'attempts' => $this->attempts(),
                ]);

                return;
            }

            if ($outcome === VoiceIngestOutcome::ProviderUnavailable) {
                // ⚠️ **A LOG LINE AND NO THROW.** Throwing would exhaust the ladder
                // and land the job in `failed_jobs` — which on this path is a record
                // no crypto-shred reaches and no tenant predicate covers (3148), for
                // a condition that is simply *"voice is not activated yet"*. The
                // carrier's own retry is the recovery, and T176 §7 item 3 is the
                // fix.
                //
                // ⚠️ **THIS SAID "A DURABLE RECORD NOTHING PRUNES" AND THAT STOPPED
                // BEING TRUE ON 2026-08-23** (8610-8639): `jobs:prune-failed` bounds
                // it at thirty days. **The refusal to throw is unchanged**, and its
                // real argument was never the retention — this payload is a vendor
                // call handle and an enum, so what a row here would waste is an
                // operator's attention on a switch nobody has turned on yet.
                //
                // ⚠️ **NO NUMBER, ON EITHER SIDE.** The vendor's call handle is
                // opaque; the caller's mobile is not, and it is what this whole path
                // is careful about.
                Log::info('A voice event could not be read back from the provider.', [
                    'provider_call_id' => $this->providerCallId,
                    'event' => $this->event->value,
                ]);
            }

        } catch (\Throwable $e) {
            Log::error('EXCEPTION IN JOB: '.$e->getMessage().' '.$e->getTraceAsString());
        }
    }
}
