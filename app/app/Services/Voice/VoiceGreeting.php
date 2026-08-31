<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Enums\VoiceEventType;
use InvalidArgumentException;

/**
 * What a caller hears before they can speak — and the one place 2104's recording
 * announcement is enforced rather than remembered.
 *
 * ## ⛔ THE ANNOUNCEMENT IS UNCONDITIONAL, IN EVERY STATE, AND THE ENFORCEMENT
 * IS THAT IT CANNOT BE LEFT OUT
 *
 * `CLAUDE.md`: *"Announce recording on every recorded call, in every state."*
 * R7 restates it — *"the recording announcement is unconditional, in every
 * state, on the greeting (decision 2104)"*. Every previous statement of that
 * rule in this codebase has been prose:
 * {@see VoiceEventType::Answered} explains it, `InboundCall` explains
 * why it is not a field, `VoicemailRecorded` explains why it is not a listener's
 * check. **None of them is a mechanism**, and `CLAUDE.md` names that shape
 * exactly (314–316): *a protection layer asserted before it is true*.
 *
 * This class is the mechanism, and it is deliberately a small one:
 *
 *   - the constructor is **private**, so there is exactly one way to build a
 *     greeting;
 *   - {@see self::forRecordedCall()} always puts the announcement first;
 *   - there is **no `withoutAnnouncement()`, no flag, no nullable and no
 *     setter** — the only lever is which *business* greeting follows it;
 *   - an empty announcement throws, so a misconfiguration is a loud failure at
 *     the moment of composition rather than a silent one on a live call.
 *
 * ⚠️ **AND HERE IS WHAT IT DOES NOT COVER, STATED RATHER THAN GLOSSED.** Nothing
 * in this repository plays audio today: Infobip Voice/Calls is not activated on
 * the account (T176 §7 item 3), so the greeting a caller actually hears is
 * configured on the vendor's own number setup. What this class guarantees is
 * that **the only object in this application that describes a greeting cannot
 * describe one without the announcement**, and that the activation lane has one
 * thing to wire rather than a rule to re-derive. Claiming more than that would
 * be the very failure the paragraph above cites.
 *
 * ⛔ **AND IT HAD NO CALLER AT ALL UNTIL 4505, WHICH MADE THE PARAGRAPH ABOVE
 * THE THING IT WARNS ABOUT.** *"This class is the mechanism"* was written about
 * a class that nothing in `app/` constructed: the only reference outside it was
 * the `ANNOUNCEMENT` constant printed on a screen. **A value object nobody
 * builds enforces nothing** — 314–316 landing inside the slice that quotes them.
 * {@see RecordingAnnouncement} is the caller: an operator's attestation names
 * the clip, and it is composed *through here* so the claim "the announcement is
 * what a caller hears first" is checked by the one object that cannot describe a
 * greeting without it.
 *
 * ## ⛔ NO TTS CALL DURING A CALL
 *
 * `CLAUDE.md`: *"Greetings are pre-rendered static files."* Both parts of this
 * playlist are **file references** — pre-rendered 8kHz μ-law audio in object
 * storage, per the stack notes — and neither is a string handed to a
 * text-to-speech vendor at call time. ⚠️ **`support_settings.voicemail_greeting_type`
 * DEFAULTS TO `'tts'` AND HAS NO WRITER AND NO READER** (its model's docblock
 * lists it among the writerless columns). That default reads as an instruction
 * to synthesise during a call, which the rule above forbids; it is left
 * untouched here rather than quietly reinterpreted, and recorded as owed.
 */
final readonly class VoiceGreeting
{
    /**
     * The sentence every caller hears, and the fallback if nothing is configured.
     *
     * ⚠️ **A NON-EMPTY DEFAULT RATHER THAN A REQUIRED SETTING.** A required one
     * fails closed into *no announcement*, which is the opposite of what failing
     * closed means here: the safe direction is to say it, always, even if what
     * is said is generic.
     *
     * ⚠️ **NOT A TENANT SETTING AND NOT A REGISTRY KEY** — `CLAUDE.md`: never
     * add a tenant-facing toggle. A wording an owner can edit is a wording an
     * owner can edit the announcement out of, which is
     * `MissedCallTextBack::compose()`'s argument about the disclosure, one
     * channel over.
     */
    public const string ANNOUNCEMENT = 'This call is recorded.';

    /**
     * @param  list<string>  $playlist  Pre-rendered audio references, in the
     *                                  order they play. Index 0 is always the
     *                                  announcement.
     */
    private function __construct(
        public array $playlist,
        public string $announcement,
    ) {}

    /**
     * The greeting for a call this platform is going to record.
     *
     * @param  string  $announcementMediaId  The pre-rendered announcement clip.
     * @param  string|null  $businessMediaId  The tenant's own greeting, when they
     *                                        have recorded one. Null is ordinary
     *                                        — a caller then hears the
     *                                        announcement and the platform's own
     *                                        message, never silence.
     *
     * @throws InvalidArgumentException when the announcement is missing — see the
     *                                  class docblock for why this is loud
     */
    public static function forRecordedCall(
        string $announcementMediaId,
        ?string $businessMediaId = null,
        string $announcement = self::ANNOUNCEMENT,
    ): self {
        if (trim($announcementMediaId) === '') {
            throw new InvalidArgumentException(
                'A recorded call has no greeting without its recording announcement. `29` §2 and '
                .'decision 2104 make the announcement unconditional, in every state — so a greeting '
                .'that cannot name its announcement clip is a misconfiguration to fix, never a '
                .'greeting to play.'
            );
        }

        if (trim($announcement) === '') {
            throw new InvalidArgumentException(
                'The recording announcement may not be blank. An empty string here is a silent '
                .'announcement, which is the same thing as none.'
            );
        }

        $playlist = [trim($announcementMediaId)];

        if ($businessMediaId !== null && trim($businessMediaId) !== '') {
            $playlist[] = trim($businessMediaId);
        }

        return new self($playlist, trim($announcement));
    }
}
