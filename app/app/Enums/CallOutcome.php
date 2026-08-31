<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * What happened to one inbound call, in this application's own vocabulary.
 *
 * **Every case is inbound.** {@see VoiceEventType} carries the same reason —
 * `29` §2.3 rule 13, **overridden by an owner ruling on 2026-08-25** (9363),
 * with the build still refused because a ruling is not a build (9366).
 * ⚠️ **There is deliberately no `Dialled` and no `Placed`**, and adding one is
 * not an enum change: it is a request to build outbound calling.
 *
 * ⛔ **AND NOTHING PINS THIS LIST — THIS PARAGRAPH IS THE WHOLE OF THE
 * ENFORCEMENT** (11562). `CallOutcome::cases()` has **zero** occurrences in
 * `tests/` and in `app/`, and `app/Enums/` is outside
 * `voiceFilesUnderTest()`, so unlike {@see VoiceEventType} — whose five values
 * `SendContractTest` asserts literally — nothing here fails a build. Raised
 * rather than closed: the file that would hold the lint belongs to another
 * lane.
 *
 * ## This vocabulary is ours, and {@see self::fromProviderState()} is the ONE
 * place the vendor's is read
 *
 * Infobip's `CallState` is a nine-value enum, **verified against the live
 * reference on 2026-08-16** rather than written from memory:
 *
 *   `CALLING` · `RINGING` · `PRE_ESTABLISHED` · `ESTABLISHED` · `FINISHED` ·
 *   `FAILED` · `CANCELLED` · `NO_ANSWER` · `BUSY`
 *
 *   https://www.infobip.com/docs/api/channels/voice/calls/call-legs/get-calls
 *
 * ⚠️ **`FINISHED` IS THE AMBIGUOUS ONE AND IT IS WHY THE ANSWER TIME IS READ
 * TOO.** A call that rang, was answered by the business and then ended is
 * `FINISHED`; so is one that reached our voicemail and ended. The state alone
 * therefore cannot answer *"did anybody speak to this caller"*, and answering it
 * wrongly is `29` §19.6's build-failing gate in both directions — an apology
 * texted to somebody who just spoke to a human, or a missed call nobody is told
 * about. So this enum is derived from the state **and** from whether the leg was
 * ever answered.
 *
 * ⛔ **255, 277, 684, 1349 AND 4256–4261 ARE FIVE RECORDED OCCASIONS WHERE A
 * VENDOR STRING WAS WRITTEN FROM MEMORY AND WAS WRONG.** A vendor string spread
 * across the application is five more chances at it, so the mapping lives here,
 * once, with its citation.
 */
enum CallOutcome: string
{
    /**
     * The call is still up, or we have not been told how it ended.
     *
     * ⚠️ **THE STARTING STATE AND NOT AN ERROR.** `CALL_RECEIVED` arrives before
     * anything has happened; a row that sits here is a call whose terminal event
     * has not been delivered yet, which a carrier retry or the next event fixes.
     */
    case InProgress = 'in_progress';

    /**
     * Somebody spoke to the caller.
     *
     * ⛔ **NO TEXT-BACK IS OWED**, and `29` §19.6 makes that build-failing. See
     * {@see VoiceEventType::owesTextBack()}, which is the predicate every sender
     * on this path asks.
     */
    case Answered = 'answered';

    /**
     * The business did not speak to the caller.
     *
     * **This is the outcome the product turns on.** It fires the SM-001
     * text-back from the tenant's own number and hands the resulting SMS thread
     * to the conversation lane.
     */
    case Missed = 'missed';

    /**
     * The leg never connected at all — a carrier rejection, a network failure.
     *
     * ⚠️ **NOT `Missed`, DELIBERATELY.** A caller whose call failed on the way in
     * may never have heard a ring, and texting them *"sorry we missed your
     * call"* is a message about an event that, from their side, did not happen.
     * The honest answer is a recorded row and no outreach.
     */
    case Failed = 'failed';

    /**
     * Read Infobip's `CallState` into ours.
     *
     * @param  string|null  $providerState  The vendor's `state`, or null when the
     *                                      call could not be read back.
     * @param  bool  $answered  Whether the leg was ever answered — the vendor's
     *                          `answerTime` being present. See the class
     *                          docblock for why `FINISHED` cannot answer that
     *                          alone.
     */
    public static function fromProviderState(?string $providerState, bool $answered): self
    {
        // ⚠️ **AN UNREADABLE STATE IS `InProgress`, NEVER `Missed`.** The
        // permissive direction here sends a text to a member of the public on
        // the strength of a field we could not read, which is the one outcome
        // that cannot be taken back. A row that stays `in_progress` is visible
        // on the owner's own screen and costs nobody anything.
        if ($providerState === null) {
            return self::InProgress;
        }

        // ⚠️ **`CANCELLED` IS `Missed` AND IT IS THE ONE WORTH ARGUING** (4516).
        // It reads as a contradiction of `Failed`'s note above — *"a caller
        // whose call failed on the way in may never have heard a ring"* — and
        // the discriminator is exactly that ring. A cancelled call is one the
        // caller placed, heard ringing, and hung up on: from their side it
        // happened, and *"sorry we missed you"* is about an event they took
        // part in. A `FAILED` leg is one they may never have experienced.
        //
        // ⛔ **WHAT IT COSTS IS A ONE-RING DIALLER GETTING TEXTED**, which is
        // real: with no spam-caller register (3184) that is the likeliest
        // producer of `CANCELLED`, and the text spends the tenant's own SMS
        // credit. It is the cheaper error of the two — the alternative loses a
        // genuine customer who gave up waiting, which is the whole product —
        // and the containment is the register, not this line.
        return match (mb_strtoupper(trim($providerState))) {
            'CALLING', 'RINGING', 'PRE_ESTABLISHED', 'ESTABLISHED' => self::InProgress,
            'NO_ANSWER', 'BUSY', 'CANCELLED' => self::Missed,
            'FINISHED' => $answered ? self::Answered : self::Missed,
            'FAILED' => self::Failed,
            // ⚠️ **A TENTH VALUE IS `InProgress`, FOR THE NULL BRANCH'S REASON.**
            // A vendor adding a state must not be able to make this application
            // text somebody.
            default => self::InProgress,
        };
    }

    /**
     * The event type this outcome corresponds to, or null when nothing is owed.
     *
     * A match with no default, so a fifth case is a compile-time conversation
     * rather than one that quietly inherits "no event" and turns the product's
     * core promise off for a whole category of call.
     */
    public function voiceEvent(): ?VoiceEventType
    {
        return match ($this) {
            self::Missed => VoiceEventType::Missed,
            self::Answered => VoiceEventType::Answered,
            self::InProgress, self::Failed => null,
        };
    }

    /**
     * Whether this outcome is one the vendor can still change its mind about.
     *
     * ⚠️ **THE IDEMPOTENCY RULE FOR A REDELIVERED WEBHOOK LIVES HERE.** Voice
     * events arrive out of order and twice; a `CALL_RECEIVED` replayed after a
     * `CALL_FINISHED` must not walk an `Answered` row back to `InProgress` and
     * fire the whole chain again. Only a call still in progress may be moved.
     */
    public function isSettled(): bool
    {
        return $this !== self::InProgress;
    }

    /**
     * What the owner reads on `Account\Calls`.
     *
     * Outcome language (`22`) — it says what happened to their phone, never what
     * this platform did to route it.
     */
    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'In progress',
            self::Answered => 'Answered',
            self::Missed => 'Missed',
            self::Failed => 'Did not connect',
        };
    }
}
