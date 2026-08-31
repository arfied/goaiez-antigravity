<?php

declare(strict_types=1);

namespace App\Jobs\Voice;

use App\Enums\AutopilotActionType;
use App\Jobs\AutopilotJob;
use App\Models\Business;
use App\Models\Call;
use App\Models\User;
use App\Models\Voicemail;
use App\Notifications\VoicemailReceived;
use App\Services\Mail\PlatformMailer;
use App\Support\Tenancy;

/**
 * Tell the owner somebody left them a message — R7's owner notify.
 *
 * ## ⛔ EMAIL ONLY, AND THE MISSING HALF IS NAMED RATHER THAN QUIETLY DROPPED
 *
 * R7 says *"delivered to the owner by email and/or SMS (per tenant setting)"*.
 * **This job sends email and nothing else, and there is no setting.** Three
 * separate things are missing and each is a decision rather than an oversight:
 *
 * ⛔ **TWO OF THE THREE STOPPED BEING TRUE IN WAVE 38 AND THIS JOB WENT ON
 * SAYING THEM — CORRECTED 2026-08-28 (10949).** They are kept and dated rather
 * than deleted (4368), because they are the argument that was had, and because
 * a docblock that quietly loses a paragraph reads as though nobody ever
 * refused anything:
 *
 *   1. ⛔ **SUPERSEDED.** *"There is no owner-SMS path in this application at
 *      all. Every send goes through `PlatformMessageSender`, which requires a
 *      `SendPermit` from `ConsentService` about a `Customer`. An owner is a
 *      `User`. Building a second send path to reach them would be the thing
 *      2970–2979 records … This job will not be the fourth."* **There is one
 *      now** — `PlatformTexter::sendToOwner()`, taking an `OwnerSendPermit`
 *      minted by `OwnerConsentService` from a recorded consent event, on the
 *      owner ruling of 2026-08-27 (10540). ⚠️ **The paragraph's real worry was
 *      right and is answered rather than overruled**: the new door is not a
 *      second send path of this job's own, it is the one owner-channel door,
 *      and anything here would go through it exactly as this job's mail goes
 *      through `PlatformMailer`.
 *   2. ⛔ **SUPERSEDED.** *"An owner SMS spends the tenant's own credit. 2066
 *      puts review invites, missed-call text-backs and the chat bot on one
 *      shared balance; a notification to the account holder drawing from the
 *      same pool is a commercial decision nobody has made."* **The decision was
 *      made at 10546**: an owner-channel send does **not** debit the tenant's
 *      SMS credit grant, on the precedent of `PlatformMailer::send()`'s already
 *      unmetered account-holder mail. ⚠️ **It is a lane's judgement call and
 *      says so** — the alternative is *"equally defensible and is the owner's
 *      to choose if this is revisited"* — so it is a settled default rather
 *      than a closed question. `tests/Feature/Billing/OwnerChannelGrantTest.php`
 *      is what reddens if the behaviour moves.
 *   3. ✅ **STILL TRUE, AND NARROWER THAN IT LOOKS.** The setting column does
 *      not exist. `owner_notify_numbers` is a number and a `stopped_at` — a
 *      channel-level consent for the account holder, one row per business —
 *      and it is not R7's *"per tenant setting"* for whether **this**
 *      notification takes the SMS half. Adding one is still 272's shape plus a
 *      tenant-facing toggle, which `CLAUDE.md` forbids by default and has
 *      overruled exactly once.
 *
 * ⛔ **AND A FOURTH REASON HAS ARRIVED THAT NONE OF THE THREE HAD**: the
 * disclosure an owner actually agreed to (`OwnerNotifyDisclosure::TEXT`) is
 * *"things like an urgent message from a customer, or something that needs your
 * reply"*, and a voicemail is squarely that — so **unlike the lifecycle rungs
 * (10941), this one is not blocked on the consent wording.** What it is blocked
 * on is 3 above and nothing else. **That makes this the nearest owner-SMS
 * candidate in the tree, and a lane that wants it should read 10941 for what
 * the neighbouring refusal actually rests on.**
 *
 * So: email, unconditionally, which is the half that works and the half every
 * owner already receives everything else through. The rest is recorded as owed.
 *
 * ⚠️ **THE NOTIFICATION NAMES NO NUMBER AND CARRIES NO TRANSCRIPT.** See
 * {@see VoicemailReceived}.
 *
 * ⚠️ **IT DOES NOT WAIT FOR A TRANSCRIPT AND MUST NOT BE MADE TO.** `CLAUDE.md`:
 * *never block on transcription.* It fires on `VoicemailRecorded`, which fires on
 * the audio.
 *
 * ## ⛔ The half that works did not work, and this job's own docblock said so
 * two properties up — 10986
 *
 * ⛔ **`claimIsSpent()` PROMISES *"RELEASED UNTIL THE MAIL HAS ACTUALLY GONE"*
 * AND THE MAIL COULD NOT FAIL, SO THE CLAIM WAS SPENT ON A MESSAGE THAT HAD
 * NOT GONE.** This job sent through `PlatformMailer::send()` — a
 * `DeliverPlatformMail::dispatch()` inside a `catch (Throwable)` that logs and
 * returns `void` (9500) — so a message never handed to the queue at all
 * returned normally. Three things then happened on the next four lines:
 * `$mailed` became true and filed *"Someone left you a voicemail"* to the
 * owner's activity feed, `voicemails.notified_at` was stamped, and `$notified`
 * became true. ⛔ **All three are permanent.** The column is this job's second
 * idempotency layer and is read at the top of `mailOwner()`, so **the owner is
 * never told about that voicemail, by any later dispatch, ever** — which is
 * the outcome `FetchVoicemailRecordingJob` already states in prose as *"the
 * whole feature going quiet"*.
 *
 * ✅ **THE SEND IS NOW {@see PlatformMailer::deliverNow()}, WHICH THROWS.**
 * Nothing after it runs: no feed line, no `notified_at`, no spent claim. The
 * `AutopilotJob` base writes the `automation_runs` row as `Failed` and rethrows,
 * the queue retries with backoff, and the last attempt reaches
 * {@see AutopilotJob::failed()} — which rings
 * `App\Enums\OperatorAlertKind::AutomationAbandoned`, deduped on this job's own
 * `automationKey()`. **That is the property `claimIsSpent()` was written to
 * have.**
 *
 * ⛔ **AND THERE IS DELIBERATELY NO `canDeliver()` GATE IN FRONT OF IT, WHICH IS
 * THE OPPOSITE OF WHAT `RenewalReminders`, `TrialReminders`, `DunningNotices`
 * AND `SendOwnerWeeklyDigests` ALL DO.** Every one of those is a **daily sweep**:
 * a `.env` fault is identical for every business it walks, refusing costs
 * nothing because tomorrow's run asks again, and counting it per business would
 * turn one misconfiguration into thousands of failures. ⚠️ **This job is
 * event-driven and has no sweep behind it — there is no tomorrow.** A gate here
 * would convert a mail system nobody has configured into a clean return, no
 * bell, no `failed_jobs` row and no retry, for a message a named person is
 * waiting on. **So the configuration fault is left loud on purpose**, and what
 * bounds the noise is `AutopilotJob::ABANDONED_REPEAT_HOURS` rather than a
 * threshold (9370).
 */
final class NotifyOwnerOfVoicemailJob extends AutopilotJob
{
    private bool $notified = false;

    /**
     * Whether *this* attempt put the mail on its way.
     *
     * ⛔ **A SECOND FLAG, BECAUSE {@see self::$notified} ANSWERS A DIFFERENT
     * QUESTION AND THE FEED WAS READING THE WRONG ONE** (7224). That one means
     * *"the claim is spent — do not come back"*, and three arms set it: the
     * send, a call that could not be found, and a voicemail somebody had
     * already been told about. Filing the feed item off it meant a row for a
     * call that does not exist, and a **second** row for a voicemail already
     * announced — on a job whose own docblock promises *"one feed item per
     * voicemail"*. The second is reachable exactly as the `notified_at` column
     * is: a released claim, a second dispatch path, a replayed webhook.
     */
    private bool $mailed = false;

    public function __construct(
        int $businessId,
        ?int $locationId,
        public readonly string $providerCallId,
    ) {
        parent::__construct($businessId, $locationId);
    }

    public function automationKey(): string
    {
        return 'voice.voicemail.notify_owner';
    }

    /**
     * One notification per voicemail, ever.
     */
    protected function idempotencyKey(): string
    {
        return 'voicemail-notify:'.$this->providerCallId;
    }

    /**
     * ⚠️ **RELEASED UNTIL THE MAIL HAS ACTUALLY GONE**, which is
     * `SummariseClosedThreadJob`'s exact posture and its exact argument: nothing
     * on this path reaches a customer, every failure before the send is
     * transient, and keeping the claim through one would mean the owner is never
     * told — silently.
     *
     * ⛔ **THAT SENTENCE WAS FALSE OF THE SEND ITSELF UNTIL 2026-08-28 AND IS
     * NOW TRUE OF IT** (10986). `PlatformMailer::send()` could not fail on its
     * caller, so *"has actually gone"* meant *"was pushed at a queue, or was
     * not, and this application cannot tell"* — and `$notified` was set either
     * way, one line later. `deliverNow()` throws, so the flag is not reached.
     * **This property is the reason the repair is the one it is**, rather than a
     * consequence of it.
     */
    protected function claimIsSpent(): bool
    {
        return $this->notified;
    }

    /**
     * @return array<string, mixed>
     */
    protected function execute(): array
    {
        return $this->mailOwner();
    }

    /**
     * @return array<string, mixed>
     */
    protected function handoff(): array
    {
        return $this->mailOwner();
    }

    /**
     * ⚠️ **NOT CALLED `notify()`, AND THE NAME IS THE POINT.** `MailTest`'s
     * *"only the platform mailer sends email"* lint matches `->notify(` on any
     * receiver, because `Notifiable::notify()` needs no facade and no import and
     * is the shortest path around the `PlatformMailer` chokepoint. A private
     * method sharing that spelling reads to the lint exactly like the thing it
     * exists to catch — and a lint widened to admit a false positive is a lint
     * tuned until it catches nothing (511). Renaming the method is the cheap
     * side of that trade.
     *
     * @return array<string, mixed>
     */
    private function mailOwner(): array
    {
        $call = Call::query()
            ->with('voicemail')
            ->where('provider_call_id', $this->providerCallId)
            ->first();

        if (! $call instanceof Call) {
            $this->notified = true;

            return ['notified' => false, 'reason' => 'call_not_found'];
        }

        $voicemail = $call->voicemail;

        if ($voicemail instanceof Voicemail && $voicemail->notified_at !== null) {
            // ⚠️ **THE SECOND IDEMPOTENCY LAYER, AND IT IS A COLUMN RATHER THAN
            // A FLAG.** The run claim lives in `automation_runs`; this survives a
            // released claim, a second dispatch path and a replayed webhook.
            $this->notified = true;

            return ['notified' => false, 'reason' => 'already_notified'];
        }

        $owner = Business::query()->find(Tenancy::idOrFail())?->owner;

        if (! $owner instanceof User) {
            return ['notified' => false, 'reason' => 'no_owner'];
        }

        $address = trim((string) $owner->email);

        if ($address === '') {
            return ['notified' => false, 'reason' => 'no_address'];
        }

        // ⛔ `deliverNow()` RATHER THAN `send()`, AND THE THREE LINES BELOW IT
        // ARE WHY — 10986. `send()` swallows every throwable and returns `void`
        // (9500), so all three of them ran for a message that was never handed
        // to the queue: a feed line claiming the owner was told, a `notified_at`
        // this job reads as *never come back*, and a spent claim. This throws,
        // and the `AutopilotJob` base turns that into a `Failed` run row, a
        // retry ladder and an `AutomationAbandoned` bell. See this class's own
        // docblock for why there is no `canDeliver()` in front of it.
        app(PlatformMailer::class)->deliverNow($address, new VoicemailReceived(
            // ⚠️ **NO NUMBER IN THE MAIL.** See the notification's own docblock.
            audioAvailable: $voicemail?->audio_state->value,
            seconds: $voicemail?->recording_seconds,
        ));

        // Past the send: this attempt is the one that told them, and since
        // 10986 the transport has actually accepted it rather than a queue
        // having accepted a push.
        $this->mailed = true;

        if ($voicemail instanceof Voicemail) {
            $voicemail->forceFill(['notified_at' => now()])->save();
        }

        // From here a retry would mail the owner twice.
        $this->notified = true;

        return ['notified' => true];
    }

    /**
     * ⚠️ **ONE FEED ITEM PER VOICEMAIL, AND ONLY WHEN ONE WENT.**
     * `AutopilotJob`'s counterweight: *"an automation whose only honest title is
     * 'checked something and found nothing' makes the feed worse."*
     *
     * ⛔ **THIS RETURNED `CallMissed` AND THAT SENTENCE IS *"TEXTED BACK A
     * MISSED CALL"*, WHICH THIS JOB HAS NEVER DONE — CORRECTED 7224.** It sends
     * one email and nothing else, and its own docblock spends three numbered
     * paragraphs explaining why. ⚠️ **THOSE PARAGRAPHS ONCE SAID "THERE IS NO
     * OWNER-SMS PATH IN THIS APPLICATION AT ALL" AND TWO OF THE THREE ARE NOW
     * SUPERSEDED** (10949) — **this sentence is unaffected**, because what it
     * turns on is that *this job* sends no text, which is still so. So the feed
     * told the owner a stranger had been texted back, on
     * the run where nobody was: `SendMissedCallTextBackJob` is the job that
     * earns that sentence, and it gates it on `$sent` for exactly this reason.
     * ⚠️ **A caller who is texted back AND leaves a message wrote the identical
     * row twice**, from two jobs, with no way to tell them apart.
     * `AutopilotActionType::VoicemailReceived` — *"Someone left you a
     * voicemail"* — has carried the true sentence since Stage 0 and had no
     * writer.
     *
     * ⚠️ **AND IT READS `$mailed` RATHER THAN `$notified`** — see that
     * property. The old flag is the claim, not the telling.
     */
    protected function activityAction(): ?AutopilotActionType
    {
        return $this->mailed ? AutopilotActionType::VoicemailReceived : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function input(): array
    {
        // The vendor's opaque call handle, never the caller.
        return array_merge(parent::input(), ['provider_call_id' => $this->providerCallId]);
    }
}
