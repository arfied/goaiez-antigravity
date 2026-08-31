<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\Contracts\VoiceProvider;
use App\Enums\AutopilotActionType;
use App\Enums\VoiceEventType;
use App\Enums\VoicemailAudioState;
use App\Events\Voice\VoicemailRecorded;
use App\Jobs\AutopilotJob;
use App\Listeners\Voice\NotifyOwnerOfVoicemail;
use App\Models\Voicemail;
use App\Services\AuditService;
use App\Services\Voice\InboundCall;
use App\Services\Voice\VoiceSpend;
use App\Support\Tenancy;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Pull the recording off the vendor and onto our own storage — T176 P2.
 *
 * ⛔ **THE BYTES ARE FETCHED AND THE URL IS NEVER KEPT.** `VoicemailRecorded`'s
 * rule, verbatim: *"a path, never a vendor URL — a vendor's recording URL
 * expires, is often unauthenticated, and would be emailed to an owner as a link
 * anybody who saw it could open."* The provider seam answers bytes for exactly
 * that reason, and `VoiceRecording` deliberately does not carry Infobip's
 * documented `location` field.
 *
 * ⛔ **AND {@see VoicemailRecorded} FIRES HERE, ON THE AUDIO — NEVER ON A
 * TRANSCRIPT.** `CLAUDE.md`: *never block on transcription; STT failure still
 * delivers the audio.* The owner notification hangs off this event; the
 * transcript is a later, optional enrichment dispatched afterwards and never
 * waited on.
 *
 * ⚠️ **A FETCH THAT FAILS STILL NOTIFIES THE OWNER.** `VoicemailAudioState::Unavailable`
 * is a voicemail whose audio we could not retrieve, and the missed call, the
 * caller's number and the time are all facts this application already holds. An
 * implementation that returned early on a failed fetch would turn a vendor's bad
 * minute into *"nobody told me somebody rang"*.
 *
 * ⛔ **AND THAT SENTENCE WAS TRUE OF EVERY BRANCH THIS CLASS WRITES AND FALSE
 * OF THE ONE IT DOES NOT — CORRECTED 2026-08-25 (9586).** All seven outcomes of
 * {@see self::fetch()} end in {@see self::announce()}, so the promise held for
 * every fault the code *names*. A **throw** names nothing: `AutopilotJob` closes
 * the `automation_runs` row as `Failed` and rethrows, the ladder is spent, the
 * job lands in `failed_jobs` — **and {@see VoicemailRecorded} never fires, so
 * {@see NotifyOwnerOfVoicemail} never runs and the owner
 * is never told anybody rang at all.** That is the exact outcome the paragraph
 * above forbids, reached by the one path it did not consider, on a job whose
 * `claimIsSpent()` docblock already says keeping the claim through a fault
 * *"would mean the owner is never told about this voicemail, silently, which is
 * the whole feature going quiet"*.
 *
 * ⛔ **`automation_runs` IS NOT THE ANSWER TO IT, WHICH IS WHY THIS IS NOT A
 * SECOND RECORD** (9370's refusal, and the standing rule about not putting a
 * `failed()` on an `AutopilotJob`). That row is the operator's, is behind an
 * admin screen, and says a piece of plumbing failed. The thing missing here is
 * a **notification to a small business that a customer left them a message** —
 * a different fact, for a different person, on a different surface.
 * {@see self::failed()} settles the state this class already knows how to say
 * honestly and announces, exactly as the six named faults do.
 *
 * ⚠️ **`handoff()` IS THE SAME WORK, AND THAT IS NOT AN EVASION.** `CLAUDE.md`
 * requires both paths in the same ticket so nobody retrofits them; here they are
 * genuinely identical, because nothing on this path touches the Google Business
 * Profile API — `SendMissedCallTextBackJob` records the same argument for the
 * same reason.
 *
 * ## ⛔ A PHI-CLASSIFIED TENANT'S VOICEMAIL AUDIO IS NEVER FETCHED (4500)
 *
 * **4166's ruling for inbound MMS, applied to the wider surface.** *"A
 * PHI-classified tenant's inbound media is never fetched and never stored"* — and
 * a member of the public describing their condition into a dental practice's
 * voicemail is strictly wider than a photograph, because there is **no surface on
 * which to ask a caller anything before they speak**, so 2079–2081's per-review
 * checkbox has no equivalent here and the tenant-level rule stands. The
 * `voicemails` migration wrote that rule down on the day it landed and nothing
 * implemented it; this is the implementation.
 *
 * ⛔ **"NO SUCH TENANT EXISTS YET" IS NOT THE ARGUMENT**, exactly as
 * `InboundMediaCapture` says: `TenantClassification` raises a business to `Phi`
 * from Google's own categories at provisioning, so the flag arrives without
 * anybody deciding to set it. That is why the refusal is code rather than an
 * onboarding checklist.
 *
 * ⚠️ **AND THE REFUSAL IS A ROW, NOT A SILENCE** — the same shape, one channel
 * over. `VoicemailAudioState::Unavailable`, an audit line naming the reason, and
 * `VoicemailRecorded` fired anyway so the owner still learns somebody rang. A
 * refusal that wrote nothing would be indistinguishable from a call that left no
 * message.
 */
final class FetchVoicemailRecordingJob extends AutopilotJob
{
    /**
     * The disk voicemail audio lives on.
     *
     * ⚠️ **`s3`, WHICH IS CLOUDFLARE R2 IN THIS DEPLOYMENT.** `CLAUDE.md`: R2 is
     * S3-compatible, *"so Laravel's `s3` driver — zero egress matters for
     * greeting audio and replays"*. Named as a constant rather than read from a
     * setting because a voicemail on the wrong disk is a voicemail nobody can
     * find, and there is no per-tenant answer to this question.
     */
    public const string DISK = 's3';

    /** Who the audit row names for a decision nobody in this company took. */
    public const string ACTOR = 'system:voicemail-recording';

    /**
     * The one reason string, shared by the run output and the audit row.
     *
     * `InboundMediaOutcome::RefusedHealthTenant`'s spelling, deliberately, so
     * the two refusals of the same ruling are greppable as one thing.
     */
    public const string REFUSED_HEALTH_TENANT = 'refused_health_tenant';

    /**
     * The other refusal, and the only one on this path about money (4686).
     *
     * ⚠️ **A SEPARATE STRING FROM `REFUSED_HEALTH_TENANT`, NOT A SHARED ONE.**
     * They produce the identical row and the identical owner experience, and
     * they are answers to completely different questions: one is a ruling about
     * patient data that will never change for that tenant, the other is a
     * ceiling that rolls at midnight. A support conversation reading the audit
     * log needs to be able to tell them apart.
     */
    public const string REFUSED_VOICE_CEILING = 'refused_voice_minutes_ceiling';

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly int $voicemailId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'voice.voicemail.fetch_recording';
    }

    /**
     * One fetch per voicemail, ever.
     *
     * ⚠️ **KEYED ON THE VOICEMAIL RATHER THAN ON THE CALL**, because a call and
     * its voicemail are one-to-one by a unique index and the voicemail is what
     * this job acts on. The `automation_runs` row is already tenant-scoped.
     */
    protected function idempotencyKey(): string
    {
        return 'voicemail-recording:'.$this->voicemailId;
    }

    /**
     * ⚠️ **RELEASED UNTIL THE AUDIO IS SETTLED.** `AutopilotJob::claimIsSpent()`
     * defaults to keeping the claim — losing a send is recoverable, sending twice
     * is not — but nothing here reaches a customer: the one message is an email
     * to the account holder about their own account. Keeping the claim through a
     * vendor timeout would mean *"the owner is never told about this voicemail"*,
     * silently, which is the whole feature going quiet.
     *
     * ⚠️ **AND `notified_at` IS WHAT STOPS A SECOND EMAIL** if a retry gets past
     * the fetch and then fails — a column rather than this flag, because the flag
     * lives only as long as the process.
     */
    private bool $settled = false;

    protected function claimIsSpent(): bool
    {
        return $this->settled;
    }

    /**
     * ⛔ **NOTHING, ON EVERY ARM — AND THIS JOB FILED A ROW ON EVERY ARM UNTIL
     * 7320.** It declared no `activityAction()`, so it inherited
     * {@see AutopilotJob::activityAction()}'s `AutomationCompleted` — *"Finished
     * a piece of work for you"* — and {@see AutopilotJob::recordActivity()} is
     * called unconditionally once either arm returns. **All seven outcomes of
     * {@see self::fetch()} wrote it**, including the two refusals: the rule-24
     * PHI refusal at 4500, where we deliberately open no socket at all, and the
     * 4686 voice-minutes brake. *"Finished a piece of work for you"* is the
     * sentence this application showed an owner for **deciding not to fetch a
     * covered entity's audio**.
     *
     * ⛔ **AND THE HONEST ANSWER HERE IS SILENCE RATHER THAN A BETTER
     * SENTENCE.** {@see NotifyOwnerOfVoicemailJob} is the one writer of
     * `VoicemailReceived` — *"Someone left you a voicemail"* — and its docblock
     * promises **one feed item per voicemail**. That promise was already false:
     * this job put a second row beside it, and
     * {@see TranscribeVoicemailJob} a third. Which vendor bytes we did or did
     * not manage to pull down is not an event in an owner's history; it is a
     * fact about our plumbing, and `automation_runs` carries it in full with the
     * reason string on the row.
     *
     * ⚠️ **THE ROWS THIS REMOVES ARE ROWS THAT EXIST TODAY**, deliberately, and
     * for the same reason 7223 removed some: a history of work that was never
     * done is worse than no history.
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
        return $this->fetch();
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->fetch();
    }

    /**
     * @return array<string, mixed>
     */
    private function fetch(): array
    {
        $voicemail = Voicemail::query()->with('call')->find($this->voicemailId);

        if (! $voicemail instanceof Voicemail) {
            $this->settled = true;

            return ['stored' => false, 'reason' => 'voicemail_not_found'];
        }

        if ($voicemail->audio_state === VoicemailAudioState::Stored) {
            // Already done by an earlier delivery. Not an error.
            $this->settled = true;

            return ['stored' => true, 'reason' => 'already_stored'];
        }

        if (! app(VoiceSpend::class)->allowsRecordingFetch($this->businessId)) {
            // ⛔ **THE ONE BRAKE ON THE INBOUND VOICE PATH** (4686). This
            // tenant's numbers have taken more inbound call time today than
            // `voice.tenant_daily_inbound_minutes_ceiling` allows — about six
            // times a busy day — so the download stops, and the transcription
            // behind it stops with it because `TranscribeVoicemailJob` is only
            // ever dispatched from a fetch that stored bytes.
            //
            // ⚠️ **IT REFUSES A DOWNLOAD AND NOT A CALL, WHICH IS THE HONEST
            // LIMIT.** The minutes and the recording fee were billed at the
            // carrier before this job existed; `VoiceSpend`'s docblock says so at
            // length rather than letting this read as a cap on the whole path.
            // The operator has already been paged by the meter, because the
            // action a strange night calls for is in the vendor's portal.
            //
            // ⚠️ **AND IT IS 4500's SHAPE DELIBERATELY, DOWN TO THIS LINE.**
            // Rule 43's surviving half is graceful degradation, never hard-fail:
            // the owner is still told somebody rang, with the caller's number
            // and the time, and the missed-call text-back — which has its own
            // credit ceiling and is the thing this product exists for — is
            // untouched. What is lost is the audio of one message.
            //
            // ⛔ **THE CLAIM IS SPENT, AND THE OTHER READING IS WORTH WRITING
            // DOWN.** Releasing it would let a redelivered recording event try
            // again tomorrow, when the ceiling has rolled — but the retry ladder
            // would first re-run within minutes and refuse three more times, and
            // nothing here holds a queued job across a day boundary. On a day a
            // number has taken four hours of calls, this audio is what is being
            // given up deliberately.
            $voicemail->forceFill(['audio_state' => VoicemailAudioState::Unavailable])->save();
            $this->settled = true;

            app(AuditService::class)->record(
                action: 'voicemail.recording_refused',
                actor: self::ACTOR,
                entity: $voicemail,
                metadata: ['reason' => self::REFUSED_VOICE_CEILING],
            );

            $this->announce($voicemail, null);

            return ['stored' => false, 'reason' => self::REFUSED_VOICE_CEILING];
        }

        $fileId = $voicemail->provider_file_id;

        $bytes = $fileId === null || $fileId === ''
            ? null
            : app(VoiceProvider::class)->recordingBytes($fileId);

        if ($bytes === null) {
            // ⛔ **UNAVAILABLE, AND THE OWNER IS STILL TOLD.** See the class
            // docblock. The state is settled so the ladder stops asking a vendor
            // that has already said no three times.
            $voicemail->forceFill(['audio_state' => VoicemailAudioState::Unavailable])->save();
            $this->settled = true;

            $this->announce($voicemail, null);

            return ['stored' => false, 'reason' => 'recording_unavailable'];
        }

        $format = $voicemail->recording_format ?? 'wav';

        // ⚠️ **THE PATH CARRIES THE TENANT AND THE VENDOR'S CALL HANDLE, AND
        // NOBODY'S NUMBER.** An object key is a string that ends up in logs,
        // storage listings and backup manifests; a caller's mobile in one would
        // be personal data in every one of those places at once.
        $path = 'voicemail/'.$this->businessId.'/'.$voicemail->call->provider_call_id.'.'.$format;

        // ⛔ **THE RETURN IS CHECKED, AND IT WAS NOT UNTIL 4517.** This disk is
        // configured `'throw' => false` (`config/filesystems.php`), so a failed
        // R2 write is a `false` and not an exception — and the row was being set
        // to `Stored` regardless, after which the owner is emailed *"the
        // recording is on your calls page"* about an object that is not there.
        // A write we cannot confirm is `Unavailable`, which is a state this
        // application already knows how to say honestly.
        if (Storage::disk(self::DISK)->put($path, $bytes) === false) {
            $voicemail->forceFill(['audio_state' => VoicemailAudioState::Unavailable])->save();

            // ⚠️ **THE CLAIM IS NOT SPENT, UNLIKE EVERY OTHER FAILURE BRANCH
            // HERE.** A vendor that answered "no recording" will answer the same
            // next time; our own object store failing is the transient one, and
            // the bytes are still on the vendor. Releasing the claim is what
            // lets a redelivered recording event try again.
            $this->announce($voicemail, null);

            return ['stored' => false, 'reason' => 'storage_write_failed'];
        }

        $voicemail->forceFill([
            'recording_path' => $path,
            'recording_bytes' => strlen($bytes),
            'audio_state' => VoicemailAudioState::Stored,
        ])->save();

        $this->settled = true;

        $this->announce($voicemail, $path);

        // ⚠️ **DISPATCHED AFTER THE EVENT, AND NOTHING WAITS ON IT.**
        // `CLAUDE.md`: never block on transcription.
        TranscribeVoicemailJob::dispatch($this->businessId, $this->locationId, $this->voicemailId);

        return ['stored' => true, 'bytes' => strlen($bytes)];
    }

    /**
     * The ladder is spent and no branch of {@see self::fetch()} ever ran: settle
     * the audio and tell the owner somebody rang.
     *
     * ⛔ **THE CLASS DOCBLOCK'S PROMISE, MADE TRUE ON THE PATH THAT BROKE IT.**
     * Without this the whole notification is lost on a thrown fault — no email,
     * no feed item, nothing — while `automation_runs` and `failed_jobs` both
     * record the plumbing correctly and neither is read by or for the person
     * waiting.
     *
     * ⛔ **`Unavailable` IS THE HONEST STATE AND IT ALREADY EXISTS**, with the
     * owner-facing words on the enum: *"No recording"*, and its own docblock's
     * *"a recording we cannot retrieve is a worse notification, not an absent
     * one"*. ⚠️ **Never `Pruned`**, which means we had it and the schedule came
     * round, and never left at `Pending`, which is a promise that bytes are on
     * their way when nothing is coming.
     *
     * ⚠️ **GUARDED ON THE ROW, TWICE.** A voicemail that is already `Stored` had
     * an attempt succeed before the process died and must not be downgraded; one
     * already `Unavailable` or `Pruned` has been announced and settled. Only
     * `Pending` reaches the two writes, so a redelivery, a
     * `MaxAttemptsExceededException` and an ordinary throw all converge on one
     * outcome.
     *
     * ⚠️ **A SECOND EMAIL IS IMPOSSIBLE HERE AND THE COLUMN IS WHY, NOT THIS
     * GUARD.** {@see NotifyOwnerOfVoicemailJob} reads `voicemails.notified_at` —
     * `claimIsSpent()`'s own note — so announcing an already-announced voicemail
     * would cost a queue push and no message. The guard is about the *state*
     * being truthful, which is a separate question.
     *
     * ⛔ **NO VENDOR CALL, NO BYTES, AND SO NOTHING RULE 24 REACHES.** This
     * method opens no socket, which is the same posture as the
     * {@see self::REFUSED_HEALTH_TENANT} branch it borrows its shape from: that
     * branch also sets `Unavailable` and announces, for a tenant whose audio we
     * must never fetch.
     *
     * ⚠️ **`$voicemail->call` IS NOT NULL-CHECKED, ON {@see self::announce()}'s
     * OWN POSTURE.** The relation is declared non-nullable on the model and the
     * column is a foreign key, so a guard here would be an unreachable branch
     * static analysis rejects — and if that ever stops being true it stops being
     * true for `announce()` first, which every other outcome already reaches.
     *
     * ⚠️ **NO AUDIT ROW, DELIBERATELY.** The two `voicemail.recording_refused`
     * entries record a **decision this platform took** — a rule-24 refusal and a
     * spend ceiling — and are what a complaint is answered from. A worker that
     * died is not a decision; `failed_jobs` already carries it **in full** and
     * `automation_runs.error` carries the exception class, and a third copy
     * under an action name meaning *"we chose not to"* would file an accident
     * as a policy. ⚠️ **THE WORDS *"IN FULL"* WERE TRUE OF BOTH UNTIL 11330 AND
     * ARE NOW TRUE OF ONE — 11457.**
     *
     * ⛔ **IT WAS `failed()` UNTIL 2026-08-25 AND IT IS `onAbandoned()` NOW,
     * WHICH IS A CORRECTION RATHER THAN A MOVE** (9700–9719). `AutopilotJob`
     * gained a `failed()` that pages an operator about an automation that has run
     * out of retries — and **this class was the only one of the twenty-six that
     * would have opted out of it, silently**, because a subclass `failed()`
     * shadows the base's entirely and this one called no parent. The base method
     * is `final` now and this is the hook it leaves; the body below is unchanged.
     *
     * ⚠️ **NOTHING HERE IS WEAKER FOR IT.** The queue's route to this is
     * unchanged — `CallQueuedHandler::failed()` → `$command->failed($e)` → the
     * base → here — and the base runs this **before** its own bell and contains a
     * throw from it, so a fault in this method cannot cost the page and the page
     * cannot cost this method.
     */
    protected function onAbandoned(?Throwable $e): void
    {
        Log::warning('A voicemail recording fetch did not finish after every attempt.', [
            'business_id' => $this->businessId,
            'voicemail_id' => $this->voicemailId,
            'reason' => $e === null ? 'unknown' : $e::class,
        ]);

        Tenancy::actingAs($this->businessId, function (): void {
            $voicemail = Voicemail::query()->with('call')->find($this->voicemailId);

            if (! $voicemail instanceof Voicemail || $voicemail->audio_state !== VoicemailAudioState::Pending) {
                return;
            }

            $voicemail->forceFill(['audio_state' => VoicemailAudioState::Unavailable])->save();

            $this->announce($voicemail, null);
        });
    }

    /**
     * Tell the rest of the application a voicemail exists.
     *
     * ⚠️ **FIRED ON BOTH PATHS — STORED AND UNAVAILABLE.** The event's subject is
     * *"a caller left a message"*, which is true either way, and R7's owner
     * notification hangs off it.
     *
     * ⚠️ **THE `InboundCall` IS REBUILT FROM THE STORED ROW RATHER THAN CARRIED
     * ON THE JOB.** A job payload holding a caller's mobile number is a row in
     * `failed_jobs` on the third attempt — a table with no row-level security
     * (3148) — and `TextBackMissedCaller` refuses to create one for precisely
     * that reason.
     *
     * ⚠️ **THIS SAID "THAT NOTHING PRUNES" AND THAT STOPPED BEING TRUE ON
     * 2026-08-23** (8610-8639): `jobs:prune-failed` gives it a thirty-day
     * horizon. **The reason for rebuilding rather than carrying is unchanged**,
     * and the surviving clause is the one that carried the weight: there is no
     * tenant predicate on that table, and thirty days of a caller's mobile is
     * still thirty days of it. ⛔ **A horizon is not an erasure path** — the
     * pruner's own docblock says so — so an erasure request tomorrow still
     * leaves a payload from last week intact.
     * ⚠️ **AND `SendMissedCallTextBackJob` DOES CARRY THE WHOLE `InboundCall`**,
     * which is what makes this paragraph a live rule rather than a historical
     * one: the class it cites as the precedent refuses to *dispatch*, and the
     * job it dispatches when the call does owe a text-back carries the number
     * (8725-8735).
     */
    private function announce(Voicemail $voicemail, ?string $path): void
    {
        $call = $voicemail->call;

        VoicemailRecorded::dispatch(
            $this->businessId,
            new InboundCall(
                type: VoiceEventType::VoicemailRecorded,
                providerCallId: $call->provider_call_id,
                // `InboundCall::$numberId`'s gap, recorded in `VoiceCalls`.
                numberId: 0,
                from: $call->from_e164,
                to: $call->to_e164,
                occurredAt: ($call->ended_at ?? $call->started_at ?? now())->toImmutable(),
                customerId: $call->customer_id,
            ),
            // ⚠️ **AN EMPTY PATH RATHER THAN A NULL**, because `VoicemailRecorded`
            // types it non-nullable and widening a settled event's signature from
            // this lane is the change `CLAUDE.md`'s interface lint exists to catch.
            // The listener reads `audio_state` for the real answer.
            $path ?? '',
            $voicemail->recording_seconds,
        );
    }
}
