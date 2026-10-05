<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Contracts\VoiceProvider;
use App\Enums\CallAnsweredBy;
use App\Enums\CallOutcome;
use App\Enums\CapturedBy;
use App\Enums\CaptureSurface;
use App\Enums\ConsentType;
use App\Enums\OutreachChannel;
use App\Enums\VoiceEventType;
use App\Enums\VoiceIngestOutcome;
use App\Enums\VoicemailAudioState;
use App\Enums\VoiceUsageKind;
use App\Events\Voice\CallMissed;
use App\Jobs\Voice\FetchVoicemailRecordingJob;
use App\Jobs\Voice\NotifyOwnerOfCallMessageJob;
use App\Models\Call;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\Voicemail;
use App\Modules\X121\Actions\PersonLookupAction;
use App\Services\Conversations\ConversationThreads;
use App\Services\Sms\TenantNumbers;
use App\Support\Identifier;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The only writer of `calls` and `voicemails` — T176 P2, the voice forwarding workflow.
 *
 * ## ⛔ THIS IS THE MISSING WRITER, AND ITS ABSENCE WAS THE WHOLE OF P2
 *
 * `App\Events\Voice\CallMissed`, `VoicemailRecorded`, `VoicemailTranscribed`,
 * `App\Services\Voice\MissedCallTextBack`, `App\Listeners\Voice\TextBackMissedCaller`
 * and `App\Jobs\SendMissedCallTextBackJob` all existed before this file did —
 * and **nothing in `app/` dispatched any of the three events.** Verified before
 * a line was written: `CallMissed::dispatch` appeared three times in `tests/`
 * and zero times in `app/`. That is `CLAUDE.md`'s first recurring failure shape
 * (272) one layer up from a column: a whole chain of gates, composers, listeners
 * and idempotency keys, each with tests, each correct, **fed by nothing** —
 * *"an isolation test passes perfectly against a table nothing writes"*, and
 * decision 3112 had already found the previous link of the same chain missing
 * for the same reason.
 *
 * ⚠️ **THE CHAIN IS NOW FED AND IS STILL INERT, AND THE TWO ARE DIFFERENT
 * THINGS.** Infobip Voice/Calls is not activated on the account (T176 §7 item
 * 3), so `VOICE_DRIVER` seeds `null` and {@see NullVoiceProvider} answers
 * nothing. What changed is that there is now a code path from a carrier webhook
 * to `CallMissed`, exercised end to end by the suite against a fake provider,
 * so activation is a credential change rather than a first build.
 *
 * ## The tenant is established from the number, then everything is scoped
 *
 * ⚠️ **A VOICE WEBHOOK ARRIVES WITH NO TENANT**, exactly as a delivery receipt
 * does. `TenantNumbers::tenantFor()` resolves the *called* number to a business
 * — the one query in this application that may look at `phone_numbers` outside a
 * scope — and from that point every read and write happens inside
 * `Tenancy::actingAs()`, under the global scope with row-level security
 * beneath. `InboundCall`'s docblock states the rule: the number **establishes**
 * the tenant and is never trusted as a claim that a row belongs to anybody.
 *
 * ⛔ **A NUMBER NOBODY OWNS IS `UnknownNumber` AND NO `calls` ROW IS WRITTEN.**
 * That is the common case today — the shared Lane A pool number resolves to no
 * business — and `InboundThreading` answers the identical condition the
 * identical way. The alternative, picking a plausible tenant, would file a
 * stranger's call against somebody else's business and text them on that basis.
 *
 * ⚠️ **THIS READ "AND NOTHING IS WRITTEN" UNTIL 2026-08-18, AND THE VOICE METER
 * HAD ALREADY MADE THAT FALSE** (decision 4904). `VoiceSpend::record()` is
 * called from `record()` **above** the `UnknownNumber` return precisely so that
 * a call arriving on a number no account owns is still metered — it is the
 * invisible half of the one billable path a stranger can trigger (4742), and it
 * lands in `voice_usage_events` with a null `business_id` rather than in
 * `calls`. So a platform ledger row **is** written; no tenant-owned row is.
 * ⛔ **The wording mattered because of what it invites**: a docblock saying
 * nothing is written is what stops the next reader looking for the write, which
 * is this codebase's 4606 shape — the paragraph explaining the hazard being
 * what makes the code beside it read as considered.
 *
 * ## ⛔ IT NEVER CREATES A CONTACT
 *
 * `MissedCallTextBack`'s rule, and it has to hold one layer earlier or the rule
 * is defeated before it is asked: *"creating one here would store a stranger's
 * mobile number on a basis nobody has established, in order to send a message
 * that would then be refused anyway for want of a consent record."* An unmatched
 * caller leaves `calls.customer_id` null, which `InboundCall` calls *"the common
 * case and not an error"*.
 *
 * ## ⛔ AND THE TENANT'S OWN ROUTING MODE IS READ HERE (4503)
 *
 * {@see CallForwarding::modeFor()} was read by the screen that displays it and
 * by nothing else, and `CallRoutingMode::touchesLiveCalls()` had **no reader in
 * `app/` at all** — one test and nothing more. That is 272's shape on a control
 * whose whole subject is whether we may answer somebody's phone.
 *
 * On `TrackingOnly` this writes the `calls` row — *measurement is exactly what
 * that mode promises* — and does **nothing else**: no `CallMissed`, so no
 * text-back and none of the tenant's SMS credit spent, and no voicemail row, so
 * no audio fetched and no transcript. `CLAUDE.md`'s 398 names the trap in this
 * shape precisely: the guard looks unnecessary only because an outer one — an
 * un-dialled forwarding code — usually refuses first, and the day it does not is
 * the day a tenant who turned this off finds we never stopped.
 *
 * ## ⛔ AND IT IS THE WRITER OF THE INBOUND VOICE METER (4686)
 *
 * 3297's enumeration found this path **billed by duration and counted nowhere**,
 * and named it the sharpest of three because a call's cost is unbounded above
 * and *a stranger can trigger it by dialling a number*. {@see VoiceSpend} is the
 * counter and the ceiling; this file is where both its writers live, because
 * this is where the vendor's own durations arrive.
 *
 * ⛔ **THE METER IS WRITTEN BEFORE THE `UnknownNumber` RETURN, AND THAT ORDER IS
 * THE WHOLE POINT.** The commonest inbound call today lands on the shared Lane A
 * pool number, resolves to no business, and leaves this method having written
 * nothing — so a counter hung off the `calls` table would have been blind to
 * exactly the calls nobody is commercially responsible for. `voice_usage_events`
 * takes a null `business_id` and a null is an answer.
 */
final class VoiceCalls
{
    /**
     * The `provider_call_id` prefix of a call the AI receptionist answered, keyed on the voice worker's own call id so a
     * retried call start finds the row it already wrote. The carrier's ids carry no prefix.
     */
    public const string LIVE_PREFIX = 'livekit:';

    public function __construct(
        private readonly VoiceProvider $provider,
        private readonly TenantNumbers $numbers,
        private readonly CallForwarding $forwarding,
        private readonly VoiceSpend $spend = new VoiceSpend,
    ) {}

    /**
     * Record what the vendor says about this call, and fire what it owes.
     *
     * ⚠️ **THE VENDOR IS READ BACK RATHER THAN THE WEBHOOK BODY PARSED, AND THAT
     * IS A DOCUMENTATION FACT RATHER THAN A PREFERENCE.** Infobip's Calls event
     * webhook publishes eight top-level fields — `conferenceId`, `callId`,
     * `timestamp`, `callsConfigurationId`, `platform`, `bulkId`, `dialogId`,
     * `type` — and **no per-event payload at all**. The caller's number is not in
     * the published schema, so reading it out of the notification would be a
     * guess of exactly the shape 4256–4261 was bitten by, where both guessed
     * spellings of an inbound MMS media key turned out to be wrong and nothing
     * broke — it simply captured nothing. `GET /calls/1/calls/{callId}` is fully
     * documented, so that is what is read.
     *
     *   https://www.infobip.com/docs/api/channels/voice/calls/calls-applications/calls-event-webhook
     *   https://www.infobip.com/docs/api/channels/voice/calls/call-legs/get-call-history
     */
    public function record(string $providerCallId): VoiceIngestOutcome
    {
        $facts = $this->provider->call($providerCallId);

        if (! $facts instanceof VoiceCallFacts) {
            return VoiceIngestOutcome::ProviderUnavailable;
        }

        $businessId = $this->numbers->tenantFor($facts->to);

        // ⛔ **METERED BEFORE THE TENANT BRANCH, AND THAT ORDER IS THE WHOLE
        // POINT OF THE METER** (4686). The `UnknownNumber` arm below writes
        // nothing at all — no `calls` row, no voicemail, no event — so a counter
        // that sat inside it would be blind to precisely the case 4686 calls
        // *the one uncapped path a stranger can trigger*: a call to a number no
        // account owns still costs us the minutes.
        //
        // ⚠️ **OUTSIDE `Tenancy::actingAs()` DELIBERATELY.** `voice_usage_events`
        // carries no global scope and no row-level security, because a ceiling
        // that cannot see the rows with no tenant on them is not a ceiling; the
        // `business_id` passed here is the whole of the attribution.
        //
        // ⛔ **ONLY A SETTLED CALL IS METERED, AND GETTING THIS WRONG WOULD HAVE
        // MADE THE METER READ ZERO FOR EVER.** Voice events arrive more than
        // once per call — see `VoiceIngestOutcome::Duplicate` — and an early
        // delivery has no `endTime`, so its duration is unknown. The meter's
        // idempotency is a unique index on `(provider_call_id, kind)` and **the
        // first write wins**, so metering an in-progress read as nought seconds
        // would permanently lock out the real duration that arrives seconds
        // later. `duration()` answers null until both ends are known, which is
        // the brief's own observation — *a debit at answer time cannot know the
        // duration* — turned into the condition on writing at all.
        $seconds = $this->duration($facts);

        if ($seconds !== null && $facts->endedAt instanceof CarbonImmutable) {
            $this->spend->record(
                VoiceUsageKind::InboundMinutes,
                $facts->providerCallId,
                $seconds,
                $businessId,
                $facts->endedAt,
            );
        }

        if ($businessId === null) {
            return VoiceIngestOutcome::UnknownNumber;
        }

        return Tenancy::actingAs(
            $businessId,
            fn (): VoiceIngestOutcome => $this->write($businessId, $facts),
        );
    }

    /**
     * Write the row for a call the AI receptionist is answering now (AI receptionist plan, wave 2, 2026-10-05) — this file
     * stays the only writer of `calls`.
     *
     * A worker that retries call start for the same call gets the same row back rather than a second one. No contact is
     * created here: a first-time caller stays unmatched (`customer_id` null, as `InboundCall` allows) until the call ends and
     * wave 3 records what they left.
     *
     * ⚠️ If the carrier's own call webhook also fires for a call routed to the voice worker, it arrives under the carrier's
     * call id and would be a second row for one call. Whether it fires at all is a Phase 0 measurement on the real trunk,
     * not something this method assumes either way.
     */
    public function answerLive(int $businessId, string $transportCallId, string $from, string $to): Call
    {
        return Tenancy::actingAs($businessId, fn (): Call => DB::transaction(function () use ($transportCallId, $from, $to): Call {
            $providerCallId = self::LIVE_PREFIX.$transportCallId;

            $call = Call::query()
                ->where('provider_call_id', $providerCallId)
                ->lockForUpdate()
                ->first();

            if ($call instanceof Call) {
                return $call;
            }

            $customer = $this->contactFor($from);
            $now = CarbonImmutable::now();

            // forceFill through the model, for write()'s reason: `business_id` is guarded and filled from context.
            $call = new Call;
            $call->forceFill([
                'provider_call_id' => $providerCallId,
                'customer_id' => $customer?->getKey(),
                'location_id' => $customer?->location_id,
                'from_e164' => $from,
                'to_e164' => $to,
                'outcome' => CallOutcome::InProgress,
                'answered_by' => CallAnsweredBy::Agent,
                'started_at' => $now,
                'answered_at' => $now,
            ])->save();

            return $call;
        }));
    }

    /**
     * Settle a call the AI receptionist answered, now that the voice worker says it has ended (AI receptionist plan, wave 3a).
     *
     * The row becomes `Answered` with its end time, and the call's minutes go on the inbound voice meter under the same
     * `livekit:` handle, so the daily ceiling call start asks counts live calls too. Ending a call that is already settled
     * changes nothing and meters nothing again — the meter's own `(provider_call_id, kind)` key would refuse a second row
     * anyway, and the settled check means it is never asked.
     *
     * @return Call|null null when the id names no call the receptionist answered for this tenant
     */
    public function endLive(int $businessId, int $callId): ?Call
    {
        // Set inside the transaction only when this request is the one that settled the call.
        $seconds = null;

        $call = Tenancy::actingAs($businessId, function () use ($callId, &$seconds): ?Call {
            return DB::transaction(function () use ($callId, &$seconds): ?Call {
                $call = Call::query()
                    ->whereKey($callId)
                    ->where('answered_by', CallAnsweredBy::Agent->value)
                    ->lockForUpdate()
                    ->first();

                if (! $call instanceof Call || $call->outcome->isSettled()) {
                    return $call instanceof Call ? $call : null;
                }

                $now = CarbonImmutable::now();
                $answeredAt = $call->answered_at === null ? $now : CarbonImmutable::instance($call->answered_at);

                $call->forceFill(['outcome' => CallOutcome::Answered, 'ended_at' => $now])->save();
                $seconds = max(0, (int) $answeredAt->diffInSeconds($now));

                return $call;
            });
        });

        if (! $call instanceof Call) {
            return null;
        }

        // After the commit, for record()'s reason: the meter is platform-scoped and takes the business id as its whole
        // attribution.
        if ($seconds !== null && $call->ended_at !== null) {
            $this->spend->record(
                VoiceUsageKind::InboundMinutes,
                $call->provider_call_id,
                $seconds,
                $businessId,
                CarbonImmutable::instance($call->ended_at),
            );
        }

        return $call;
    }

    /**
     * Keep the message a caller left with the AI receptionist, and tell the owner (AI receptionist plan, wave 3c, 2026-10-05).
     *
     * One message per call, and the first one stands: a worker retrying the request, or a second message on the same call,
     * changes nothing and tells the owner nothing twice. The owner is told after the commit, by email, and the email carries
     * neither the message nor the number — those are on their calls page, behind their login
     * ({@see NotifyOwnerOfCallMessageJob}).
     *
     * ⛔ It writes no consent and creates no contact: being rung back is what the caller asked for, and nothing more.
     *
     * @return Call|null null when the id names no call the receptionist answered for this tenant
     */
    public function leaveLiveMessage(int $businessId, int $callId, string $text, ?string $name, ?string $callback): ?Call
    {
        $fresh = false;

        $call = Tenancy::actingAs($businessId, function () use ($callId, $text, $name, $callback, &$fresh): ?Call {
            return DB::transaction(function () use ($callId, $text, $name, $callback, &$fresh): ?Call {
                $call = Call::query()
                    ->whereKey($callId)
                    ->where('answered_by', CallAnsweredBy::Agent->value)
                    ->lockForUpdate()
                    ->first();

                if (! $call instanceof Call || $call->message_left_at !== null) {
                    return $call instanceof Call ? $call : null;
                }

                $call->forceFill([
                    'message_text' => $text,
                    'message_name' => $name,
                    'message_callback' => $callback,
                    'message_left_at' => CarbonImmutable::now(),
                ])->save();

                $fresh = true;

                return $call;
            });
        });

        if (! $call instanceof Call) {
            return null;
        }

        if ($fresh) {
            NotifyOwnerOfCallMessageJob::dispatch($businessId, $call->location_id, $call->provider_call_id);
        }

        return $call;
    }

    /**
     * Attach the recording the vendor holds for this call.
     *
     * ⚠️ **THE ROW IS CREATED HERE AND THE AUDIO IS FETCHED LATER**, because the
     * two failures are different: not knowing a voicemail exists is a message
     * the owner never hears about, while not yet holding the bytes is a
     * notification with a recording still on its way. `VoicemailRecorded` fires
     * on the audio, so this method deliberately does **not** fire it — see
     * {@see FetchVoicemailRecordingJob}.
     *
     * @return VoiceIngestOutcome `Recorded` when a row now exists for this call
     */
    public function attachRecording(string $providerCallId): VoiceIngestOutcome
    {
        // ⛔ **THE TENANT IS RE-RESOLVED FROM THE NUMBER RATHER THAN LOOKED UP
        // FROM THE CALL ROW, AND THE FIRST DRAFT OF THIS METHOD DID THE
        // OPPOSITE.** `Call::withoutGlobalScopes()->where('provider_call_id',…)`
        // reads like the obvious resolution path — `ShortLinks` and
        // `WidgetPlugins` do exactly that shape — **and it would have returned
        // null on every single delivery in production.** Dropping the
        // *application* scope does not drop row-level security: `calls` is
        // `ENABLE`+`FORCE`d on `app.business_id`, and with no tenant established
        // that setting is empty, so the policy matches nothing. `CLAUDE.md` says
        // it plainly — *RLS catches a forgotten filter, never a wrong one* — and
        // `AccountDirectory`'s own note records the same trap being hit on
        // `businesses`, where the screen rendered "No accounts yet" against two
        // seeded rows.
        //
        // ⚠️ **THE FIX IS NOT AN RLS POLICY, IT IS ONE MORE VENDOR READ.** The
        // two tables that *do* carry a public-read policy are the two whose whole
        // job is to be reached without a tenant; adding a third to save a lookup
        // would open the boundary this project calls absolute so that a webhook
        // handler could skip a step. So the number resolves the tenant here
        // exactly as it does in `record()`, and nothing in this file drops a
        // scope at all.
        $facts = $this->provider->call($providerCallId);

        if (! $facts instanceof VoiceCallFacts) {
            return VoiceIngestOutcome::ProviderUnavailable;
        }

        $businessId = $this->numbers->tenantFor($facts->to);

        if ($businessId === null) {
            return VoiceIngestOutcome::UnknownNumber;
        }

        // ⛔ **BEFORE THE VENDOR IS ASKED FOR THE RECORDING, NOT AFTER** (4503).
        // The mode is a tenant-scoped read, so it happens inside the tenant; the
        // point of putting the whole branch here is that a business set to
        // *measure only* costs no vendor read and never has a `voicemails` row
        // to hold audio against.
        $refusal = Tenancy::actingAs(
            $businessId,
            fn (): ?VoiceIngestOutcome => $this->refusedByRoutingMode($providerCallId, $businessId),
        );

        if ($refusal instanceof VoiceIngestOutcome) {
            return $refusal;
        }

        $recording = $this->provider->recording($providerCallId);

        if (! $recording instanceof VoiceRecording) {
            return VoiceIngestOutcome::ProviderUnavailable;
        }

        // ⚠️ **THE RECORDING IS A SECOND BILLED LINE AND IT IS METERED WHERE THE
        // VENDOR TELLS US IT EXISTS, NOT WHERE WE DOWNLOAD IT** (4686). Infobip
        // publishes voice and video recording at €0.0021/min
        // (`https://www.infobip.com/voice/pricing`, read 2026-08-17) and it is
        // charged for making the recording, which happens because the Calls
        // configuration on the account says so — refusing the download later
        // saves storage and egress and does not unmake the recording.
        //
        // ⛔ **THE VENDOR'S OWN `duration`, NEVER THE CALL'S.** They differ:
        // recording starts after the greeting and the announcement. A null is a
        // vendor that did not say, and nothing is written rather than a zero
        // that would read as "it was free" — `MessageCostLedger` refuses exactly
        // that reading on its own book.
        if ($recording->seconds !== null) {
            $this->spend->record(
                VoiceUsageKind::Recording,
                $providerCallId,
                $recording->seconds,
                $businessId,
                CarbonImmutable::now(),
            );
        }

        return Tenancy::actingAs($businessId, function () use ($providerCallId, $recording, $businessId): VoiceIngestOutcome {
            $call = Call::query()->where('provider_call_id', $providerCallId)->first();

            if (! $call instanceof Call) {
                // ⚠️ **THE RECORDING EVENT BEAT THE CALL EVENT, WHICH IS
                // ORDINARY** — and the retry that makes it ordinary is
                // {@see IngestVoiceEventJob}'s, which releases this outcome back
                // to the queue rather than finishing on it (4518). ⛔ **The
                // first draft of this comment claimed the backoff handled it
                // while the job returned successfully**, so on the commonest
                // out-of-order delivery no voicemail row was ever created, the
                // owner was never told, and nothing logged it.
                //
                // Creating a call row from a recording notification is still
                // refused: that would be a second writer with a second idea of
                // what a call is.
                return VoiceIngestOutcome::AwaitingCall;
            }

            $voicemail = Voicemail::query()->firstOrCreate(
                ['call_id' => $call->getKey()],
                [
                    // ⚠️ **PRESENT FOR THE READER, INERT AT RUNTIME.**
                    // `business_id` is guarded on the model and `BelongsToTenant`
                    // fills it from the ambient tenant on create, so this value
                    // is discarded — it is the tenant established from the
                    // *called number* that lands in the column, never anything a
                    // webhook could name. Kept because removing it reads as
                    // though the column were unset; see `write()`, which
                    // deliberately does not pass one at all.
                    'business_id' => $businessId,
                    'provider_file_id' => $recording->fileId,
                    'recording_format' => $recording->format,
                    // ⚠️ **CLAMPED, BECAUSE THIS IS THE VENDOR'S NUMBER AND
                    // `unsignedInteger` DOES NOT CONSTRAIN IT ON POSTGRES.**
                    // `voicemails.recording_seconds` is declared unsigned and
                    // Postgres has no unsigned integer type, so the word never
                    // reached the column; the CHECK behind it is in
                    // `2026_08_18_101216_constrain_unsigned_columns_that_postgres_does_not`.
                    // The clamp is here rather than left to that constraint
                    // because a voicemail with a wrong duration is still a
                    // voicemail somebody needs to hear, and aborting the write
                    // to reject the duration would discard the recording with
                    // it. `VoiceSpend::record()` clamps its own
                    // `billable_seconds` the same way and for the same reason.
                    //
                    // ⚠️ **NULL IS PRESERVED RATHER THAN CLAMPED TO ZERO.** The
                    // column is nullable and null means "the vendor did not say
                    // how long this is"; a bare `max(0, …)` would turn that into
                    // a voicemail of zero seconds, which reads as an empty
                    // recording rather than an unknown one.
                    'recording_seconds' => $recording->seconds === null ? null : max(0, $recording->seconds),
                    'audio_state' => VoicemailAudioState::Pending,
                ],
            );

            // ⚠️ **DISPATCHED FROM HERE RATHER THAN FROM THE INGEST JOB, BECAUSE
            // THIS IS WHERE THE TENANT IS KNOWN.** `FetchVoicemailRecordingJob`
            // is an `AutopilotJob` and wants a business id at construction; the
            // ingest job has only a vendor call handle. `AgentThreadStates`
            // dispatches `SummariseClosedThreadJob` from the same position for
            // the same reason.
            //
            // ⚠️ **AND IT IS DISPATCHED EVEN WHEN THE ROW ALREADY EXISTED.** A
            // redelivered recording event whose first fetch failed must get
            // another go, and the job's own idempotency key is what stops a
            // successful one being repeated.
            FetchVoicemailRecordingJob::dispatch($businessId, $call->location_id, (int) $voicemail->getKey());

            return VoiceIngestOutcome::Recorded;
        });
    }

    /**
     * Destroy every voicemail recording this account holds.
     *
     * ⛔ **CALLED BY `TenantDeletion::execute()` AND BY NOTHING ELSE.** It is the
     * erasure's arm, not a prune: `StorageRetention` deletes an object and keeps
     * the row on a period for an account that still exists, and this deletes a
     * prefix for an account that is about to stop existing. It runs **before**
     * the cascade, which is the last moment `voicemails` can be read at all.
     *
     * ⛔ **THE ERASURE USED TO MAKE THIS RECORDING PERMANENT** (8871). After the
     * account is destroyed `PruneStoredObjects` has no business to walk and no
     * row naming a path, so a live tenant's voicemail audio was pruned at the
     * operator's period and an erased tenant's was kept for ever. **The voice on
     * the recording belongs to a member of the public**, who is not our customer
     * and was told only that the call may be recorded.
     *
     * ⚠️ **A PREFIX, NOT A ROW SWEEP** — `ExportBuilder::purgeAllFor()`'s
     * reasoning. `FetchVoicemailRecordingJob` puts the bytes and *then* writes
     * `recording_path`, so a worker that dies between the two leaves an object
     * no row names; a row-driven delete cannot see it and this can.
     *
     * ⚠️ **`voicemails` RECORDS NO DISK**, so the constant the writer uses is
     * named through the job rather than repeated as a literal — the same call
     * `StorageRetention::pruneVoicemails()` makes, so moving the bucket moves
     * both halves at once.
     *
     * ⚠️ **NO ROW, NO OBJECT-STORE CALL**, with the signal being the **row**
     * rather than the path, because pruning nulls the path and keeps the row by
     * design. ⛔ **It saves two round trips rather than making an ordinary
     * erasure `s3`-free** (9010): `L0Archive::purgeFor()` reaches that bucket
     * unconditionally on every erasure, so the sentence this guard shape is
     * usually copied for was already untrue of `s3`.
     *
     * @return bool false when anything may still be there, which refuses the
     *              whole deletion rather than completing it with the recording
     *              surviving
     */
    public function purgeVoicemailAudioFor(int $businessId): bool
    {
        if (! Voicemail::query()->exists()) {
            return true;
        }

        $prefix = 'voicemail/'.$businessId;

        try {
            $disk = Storage::disk(FetchVoicemailRecordingJob::DISK);
            $disk->deleteDirectory($prefix);

            return $disk->allFiles($prefix) === [];
        } catch (Throwable) {
            // ⚠️ Refuses rather than throws, decision 823's rule: this is
            // reached from a sweep that walks every due deletion, and one
            // unreachable bucket must not abandon the rest of the queue.
            return false;
        }
    }

    private function write(int $businessId, VoiceCallFacts $facts): VoiceIngestOutcome
    {
        return DB::transaction(function () use ($businessId, $facts): VoiceIngestOutcome {
            // `lockForUpdate()` because two deliveries of the same event race
            // routinely, and the unique index would otherwise present the second
            // one as a constraint violation on a queue worker.
            $call = Call::query()
                ->where('provider_call_id', $facts->providerCallId)
                ->lockForUpdate()
                ->first();

            $outcome = $facts->outcome();

            // ⛔ **AN UNSETTLED READ NEVER OVERWRITES A SETTLED ROW, AND THE ROW
            // IS CHECKED BEFORE ANYTHING ELSE.** See `VoiceIngestOutcome::Duplicate`:
            // voice events arrive twice and out of order, and a replay must not
            // walk an `Answered` call back to `in_progress` and fire the chain
            // again.
            if ($call instanceof Call && $call->outcome->isSettled()) {
                // See `VoiceIngestOutcome::Duplicate`. A settled call never moves
                // again, so a replayed webhook cannot fire a second text-back.
                return VoiceIngestOutcome::Duplicate;
            }

            $customer = $this->contactFor($facts->from);

            if ($customer === null) {
                $normalised = Identifier::normalise($facts->from, OutreachChannel::Sms) ?? $facts->from;
                $personId = app(PersonLookupAction::class)->idForPhone($businessId, $normalised);
                if ($personId === null) {
                    $personId = app(PersonLookupAction::class)->create($businessId, [
                        'first_name' => 'Unknown Caller',
                        'last_name' => '',
                        'phone' => $normalised,
                    ]);
                }

                $customer = Customer::create([
                    'business_id' => $businessId,
                    'phone' => $normalised,
                ]);

                app(ConversationThreads::class)->openFor($customer);

                DB::table('consent_records')->insert([
                    'business_id' => $businessId,
                    'customer_id' => $customer->id,
                    'channel' => OutreachChannel::Sms->value,
                    // The caller rang; they never said yes to texts. Implied by the call, replies only (owner ruling D-1).
                    'consent_type' => ConsentType::ImpliedByCall->value,
                    'captured_by' => CapturedBy::Tenant->value,
                    'capture_surface' => CaptureSurface::Call->value,
                    'disclosure_version' => '1.0',

                    'created_at' => now(),
                ]);
            }

            $attributes = [
                'customer_id' => $customer?->getKey(),
                'location_id' => $customer?->location_id,
                'from_e164' => $facts->from,
                'to_e164' => $facts->to,
                'outcome' => $outcome,
                'provider_state' => $facts->providerState,
                'started_at' => $facts->startedAt,
                'answered_at' => $facts->answeredAt,
                'ended_at' => $facts->endedAt,
                // Clamped for `recording_seconds`' reason one method up: the
                // duration is read out of the vendor's webhook body, the column
                // is an `unsignedInteger` that Postgres does not enforce, and a
                // call record is worth more than the duration on it.
                'ring_seconds' => $facts->ringSeconds === null ? null : max(0, $facts->ringSeconds),
            ];

            if ($call instanceof Call) {
                $call->forceFill($attributes)->save();
            } else {
                // forceFill through the model rather than `create()`: `business_id`
                // is guarded and `BelongsToTenant` fills it from context, so a
                // webhook body can never name the business a call is written
                // against.
                $call = new Call;
                $call->forceFill($attributes + ['provider_call_id' => $facts->providerCallId])->save();
            }

            // ⛔ **THE EVENT TYPE IS DERIVED FROM THE VENDOR'S STATE, NEVER FROM
            // THE NOTIFICATION'S NAME.** `VoiceWebhookEvent`'s docblock records
            // the defect this replaced: `CALL_FINISHED` arrives for a call the
            // business answered *and* for one that rang out, so mapping the
            // notification name onto `VoiceEventType::Missed` would decide `29`
            // §19.6's build-failing gate with a string.
            //
            // ⚠️ **BOTH PREDICATES, NOT ONE.** `owesTextBack()` is the enum's own
            // gate and the one `MissedCallTextBack` asks; asking it here as well
            // is `TextBackMissedCaller`'s belt-and-braces, one layer earlier, so
            // that no queued job carrying a phone number is created for a call
            // that owes nothing.
            $eventType = $outcome->voiceEvent();

            // ⛔ **AFTER THE COMMIT, NEVER INSIDE IT.** `CallMissed` is
            // `ShouldDispatchAfterCommit` and its own docblock says why: without
            // it a listener could fire the text-back — an irreversible message to
            // a member of the public — for a call whose row then rolled back and
            // which no query will ever find.
            // ⛔ **AND THE TENANT'S OWN MODE IS THE THIRD PREDICATE** (4503).
            // The row above is written either way, because measuring is what
            // `TrackingOnly` promises; what it does not promise is a message to
            // the caller, paid for out of the tenant's own SMS balance, on a
            // call they told us not to touch.
            if ($eventType instanceof VoiceEventType
                && $eventType->owesTextBack()
                && $this->forwarding->modeFor()->touchesLiveCalls()
            ) {
                CallMissed::dispatch($businessId, $this->inboundCall($facts, $customer, $eventType));
            }

            return VoiceIngestOutcome::Recorded;
        });
    }

    /**
     * `RefusedByRoutingMode` when this tenant has told us not to touch their
     * calls, and null when they have not.
     *
     * ⚠️ **LOGGED RATHER THAN SILENT, BECAUSE THE CONDITION IS AN OPERATIONAL
     * FACT SOMEBODY NEEDS.** A recording arriving for a `tracking_only` tenant
     * means their carrier forward is still up while their setting says it is
     * not — which is the one thing this application cannot see from the outside
     * and the first thing a support conversation about "why did you text my
     * customer" would need. **No number on either side**: the vendor's call
     * handle is opaque, the caller's mobile is not.
     */
    private function refusedByRoutingMode(string $providerCallId, int $businessId): ?VoiceIngestOutcome
    {
        if ($this->forwarding->modeFor()->touchesLiveCalls()) {
            return null;
        }

        Log::info('A recording arrived for a business whose calls are set to measure only.', [
            'business_id' => $businessId,
            'provider_call_id' => $providerCallId,
        ]);

        return VoiceIngestOutcome::RefusedByRoutingMode;
    }

    /**
     * ⛔ **RESOLVED, NEVER CREATED.** See the class docblock.
     */
    private function contactFor(string $from): ?Customer
    {
        $normalised = Identifier::normalise($from, OutreachChannel::Sms);

        if ($normalised === null) {
            return null;
        }

        return Customer::query()->where('phone', $normalised)->first();
    }

    /**
     * The event payload the conversation lane binds to.
     *
     * ⚠️ **`numberId` IS 0 AND THAT IS A KNOWN GAP RATHER THAN A VALUE.**
     * `InboundCall::$numberId` is typed `int` and documented as *"the
     * `phone_numbers` row the call arrived on"*; resolving it here would mean
     * naming `PhoneNumber` in this file, which `MessagingTest`'s chokepoint lint
     * holds to eight files — and widening a chokepoint to populate a field no
     * consumer reads is the wrong trade (`TextBackMissedCaller::locationOf()`
     * made the same call for the same reason). **The only consumer today is
     * `MissedCallTextBack`, whose own docblock records at 3183 that it discards
     * `numberId` and `to` because `NumberSelector::forSending()` takes no
     * argument.** So the gap is recorded on both sides rather than papered over
     * with a lookup that nothing would read.
     */
    private function inboundCall(VoiceCallFacts $facts, ?Customer $customer, VoiceEventType $type): InboundCall
    {
        return new InboundCall(
            type: $type,
            providerCallId: $facts->providerCallId,
            numberId: 0,
            from: $facts->from,
            to: $facts->to,
            occurredAt: $facts->endedAt ?? $facts->startedAt ?? CarbonImmutable::now(),
            customerId: $customer?->getKey(),
            durationSeconds: $this->duration($facts),
        );
    }

    private function duration(VoiceCallFacts $facts): ?int
    {
        if ($facts->startedAt === null || $facts->endedAt === null) {
            return null;
        }

        $seconds = $facts->startedAt->diffInSeconds($facts->endedAt, absolute: true);

        return (int) $seconds;
    }
}
