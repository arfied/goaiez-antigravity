<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\Contracts\Transcriber;
use App\Enums\AutopilotActionType;
use App\Enums\VoiceEventType;
use App\Enums\VoicemailAudioState;
use App\Enums\VoicemailTranscriptState;
use App\Events\Voice\VoicemailTranscribed;
use App\Jobs\AutopilotJob;
use App\Models\Voicemail;
use App\Services\Voice\InboundCall;
use App\Services\Voice\Transcript;
use Illuminate\Support\Facades\Storage;

/**
 * Put words to a voicemail — the enrichment, never the precondition.
 *
 * ⛔ **NOTHING WAITS FOR THIS AND NOTHING MAY.** `CLAUDE.md`: *never block on
 * transcription — STT failure still delivers the audio.* By the time this runs,
 * everything R7 promises has already happened: the caller was texted back when
 * `CallMissed` fired, and the owner was notified when `VoicemailRecorded` fired.
 * {@see VoicemailTranscribed}'s own docblock states it as a rule — *"an
 * implementation that waits for this before notifying an owner has made a
 * speech-to-text outage into a silent missed-call outage"*.
 *
 * ⛔ **AND IT DOES NOT FAIL WHEN THERE IS NO TRANSCRIBER.** The vendor is an open
 * decision (`CLAUDE.md`: Whisper against Deepgram, *"still open — decide at Stage
 * 6b"*), so `NullTranscriber` is the bound driver and every voicemail today lands
 * on `VoicemailTranscriptState::Unavailable` with its audio intact. The run row
 * says so; it is not a failure and it does not retry.
 *
 * ⚠️ **THE TRANSCRIPT IS UNTRUSTED INPUT AND MAY BE PHI**, and neither the run
 * row, the activity feed nor any log line carries a word of it. `automation_runs.output`
 * is operator-visible and long-lived; `VoicemailTranscribed` warns that the text
 * is *"a stranger's speech, machine-transcribed"* and that a healthcare tenant's
 * voicemail can carry health information nobody asked for — with 2081's force:
 * **never rewrite a PHI test to assert that unconsented patient text now reaches
 * an AI provider.**
 *
 * ## ⛔ AND FOR A PHI-CLASSIFIED TENANT IT DOES NOT RUN AT ALL (4501)
 *
 * The paragraph above was the whole of the containment until this: a warning,
 * with nothing reading a classification anywhere on the path. **A transcript is
 * the worst artefact this feature produces** — plaintext in a column, of a
 * stranger's speech, sent to a speech-to-text vendor we hold no BAA with to get
 * there. So a covered entity's voicemail is never transcribed: no audio leaves
 * this application, no words are written, and `VoicemailTranscriptState::Unavailable`
 * says so on the row.
 *
 * ⚠️ **BOTH GATES, NOT ONE.** {@see FetchVoicemailRecordingJob} already refuses
 * to hold the audio, so this job would find none — and it asks anyway, because
 * this is the job somebody will one day dispatch from somewhere else, and
 * `CLAUDE.md`'s 398 is about exactly the guard that looks unnecessary while an
 * outer one happens to refuse first.
 */
final class TranscribeVoicemailJob extends AutopilotJob
{
    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $voicemailId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'voice.voicemail.transcribe';
    }

    protected function idempotencyKey(): string
    {
        return 'voicemail-transcript:'.$this->voicemailId;
    }

    /**
     * ⚠️ **SPENT ONCE THE TRANSCRIPT STATE IS SETTLED, RELEASED OTHERWISE.**
     * A vendor timeout should be retried; a transcriber that answered "I cannot
     * read this" should not, and neither should a successful one.
     */
    private bool $settled = false;

    protected function claimIsSpent(): bool
    {
        return $this->settled;
    }

    /**
     * ⛔ **NOTHING, AND THE `no_transcriber` ARM IS WHY THIS ONE IS THE WORST OF
     * THE FIVE** (7321). This job declared no `activityAction()`, so it
     * inherited `AutomationCompleted` — *"Finished a piece of work for you"* —
     * and {@see AutopilotJob::recordActivity()} wrote it after **every** arm of
     * {@see self::transcribe()}. One of those arms is `no_transcriber`, and this
     * class's own docblock says it is **the shipped outcome for every voicemail
     * today**: the vendor is an open decision, `NullTranscriber` is the bound
     * driver, and nothing has ever been transcribed in production. **So the only
     * thing this job has ever done is announce that it finished a piece of
     * work it has never once performed.** The PHI refusal at 4501 filed the same
     * sentence.
     *
     * ⛔ **SILENCE RATHER THAN A BETTER SENTENCE, ON
     * {@see FetchVoicemailRecordingJob::activityAction()}'s argument**, plus one
     * that is this job's alone: a transcript is *"a stranger's speech,
     * machine-transcribed"* and may be PHI, so the enrichment that succeeded is
     * not something to narrate in an owner's feed either. The owner already has
     * *"Someone left you a voicemail"* from {@see NotifyOwnerOfVoicemailJob},
     * which is the event; `automation_runs` carries the engine and the
     * confidence, and never the words.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->transcribe();
    }

    /**
     * ⚠️ **IDENTICAL TO `execute()`, AND STATED RATHER THAN SHRUGGED AT.** The
     * `handoff()` path is *"the same outcome without provider access"*, and the
     * provider in question is Google Business Profile — which this path never
     * touches. `SendMissedCallTextBackJob` records the same reasoning; `CLAUDE.md`
     * requires both paths in the same ticket so nobody retrofits them.
     *
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->transcribe();
    }

    /**
     * @return array<string, mixed>
     */
    private function transcribe(): array
    {
        $voicemail = Voicemail::query()->with('call')->find($this->voicemailId);

        if (! $voicemail instanceof Voicemail) {
            $this->settled = true;

            return ['transcribed' => false, 'reason' => 'voicemail_not_found'];
        }

        if ($voicemail->audio_state !== VoicemailAudioState::Stored || $voicemail->recording_path === null) {
            // No audio, nothing to read. Settled: a voicemail whose recording is
            // `Unavailable` is never going to gain one.
            $this->settled = true;
            $this->markUnavailable($voicemail);

            return ['transcribed' => false, 'reason' => 'no_audio'];
        }

        $disk = Storage::disk(FetchVoicemailRecordingJob::DISK);

        $audio = $disk->exists($voicemail->recording_path)
            ? $disk->get($voicemail->recording_path)
            : null;

        if ($audio === null || $audio === '') {
            $this->settled = true;
            $this->markUnavailable($voicemail);

            return ['transcribed' => false, 'reason' => 'audio_unreadable'];
        }

        $transcript = app(Transcriber::class)->transcribe($audio, $voicemail->recording_format ?? 'wav');

        if (! $transcript instanceof Transcript) {
            // ⛔ **THE SHIPPED OUTCOME TODAY, AND IT IS NOT A FAILURE.** See the
            // class docblock — the transcription vendor is undecided.
            $this->settled = true;
            $this->markUnavailable($voicemail);

            return ['transcribed' => false, 'reason' => 'no_transcriber'];
        }

        $voicemail->forceFill([
            'transcript' => $transcript->text,
            'transcript_engine' => $transcript->engine,
            'transcript_confidence' => $transcript->confidence,
            'transcript_state' => VoicemailTranscriptState::Transcribed,
            'transcribed_at' => now(),
        ])->save();

        $this->settled = true;

        $call = $voicemail->call;

        VoicemailTranscribed::dispatch(
            $this->businessId,
            new InboundCall(
                type: VoiceEventType::VoicemailTranscribed,
                providerCallId: $call->provider_call_id,
                numberId: 0,
                from: $call->from_e164,
                to: $call->to_e164,
                occurredAt: ($call->ended_at ?? $call->started_at ?? now())->toImmutable(),
                customerId: $call->customer_id,
            ),
            $transcript->text,
            $transcript->engine,
            $transcript->confidence,
        );

        // ⚠️ **THE ENGINE AND THE CONFIDENCE, NEVER THE WORDS.** See the class
        // docblock: `automation_runs.output` is operator-visible.
        return [
            'transcribed' => true,
            'engine' => $transcript->engine,
            'confidence' => $transcript->confidence,
        ];
    }

    private function markUnavailable(Voicemail $voicemail): void
    {
        if ($voicemail->transcript_state === VoicemailTranscriptState::Transcribed) {
            return;
        }

        $voicemail->forceFill(['transcript_state' => VoicemailTranscriptState::Unavailable])->save();
    }
}
