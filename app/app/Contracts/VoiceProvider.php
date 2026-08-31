<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Voice\RecordingAnnouncement;
use App\Services\Voice\VoiceCallFacts;
use App\Services\Voice\VoiceGreeting;
use App\Services\Voice\VoiceRecording;

/**
 * The seam every voice vendor sits behind — T176 R27(a).
 *
 * *"Infobip sits behind MessagingProvider + VoiceProvider interfaces — the same
 * multi-driver seam pattern the email transport already proves. No core logic
 * assumes a specific carrier vendor."* R27 exists because by Build 3 this
 * platform runs worldwide and other countries need other providers for voice as
 * well as messaging; the seam costs nothing today and is unaffordable later.
 *
 * ## ⛔ EVERY METHOD ON THIS INTERFACE IS A READ, AND THAT IS A RULE RATHER THAN
 * AN OBSERVATION
 *
 * ⛔ **THE RULE THIS PARAGRAPH USED TO QUOTE — `29` §2.3 rule 13, *"No
 * outbound AI calling, ever"* — WAS OVERRIDDEN BY AN OWNER RULING ON
 * 2026-08-25 (9363), AND NOTHING HERE MOVES ON THAT.** A ruling is not a build
 * (9366). ⛔ **What refuses a call today is the lint and not the rule**:
 * `tests/Feature/Architecture/VoiceTest.php` asserts this interface's method
 * set **exactly**, so there is no `dial()`, no `originate()`, no `callBack()`
 * and no `transfer()`, and adding one fails the build — because the interface
 * is exactly where somebody generalising a
 * driver would put it, and an interface method is a promise every future driver
 * is asked to keep.
 *
 * ⚠️ **THE GREETING IS NOT HERE EITHER.** What a caller hears is
 * {@see VoiceGreeting}'s, and 2104's *"the recording announcement is
 * unconditional, in every state"* cannot survive being re-implemented per
 * vendor. A driver that chose its own greeting is a driver that can ship one
 * without the announcement.
 *
 * ⛔ **THIS ROW USED TO SAY THE GREETING WAS "COMPOSED ONCE RATHER THAN PER
 * DRIVER", AND NOTHING COMPOSED ANYTHING** (4505). No caller anywhere in `app/`
 * built a `VoiceGreeting`, so the sentence described a design rather than a
 * mechanism. What is true now: {@see RecordingAnnouncement}
 * composes it, once, from the clip an operator attested — and **the audio is
 * still played by the vendor's own number configuration, not by this
 * application**, which is why the attestation exists at all.
 *
 * ## Failure is a null, never an exception
 *
 * ⚠️ **`CLAUDE.md`: never trust a vendor's uptime, and name the failure mode for
 * each call.** Every method here answers null when the vendor cannot be reached,
 * is not activated, or answered something unreadable — and the caller decides
 * what that means. The three failure modes, named:
 *
 *   - **`call()` unreachable** → the row stays `in_progress` and no text-back
 *     fires. The permissive direction would text a member of the public on the
 *     strength of a field nobody could read.
 *   - **`recording()` unreachable** → the owner is still told somebody rang.
 *     A voicemail we cannot describe is a worse notification, not an absent one.
 *   - **`recordingBytes()` unreachable** → `VoicemailAudioState::Unavailable`,
 *     and the transcript path is skipped rather than blocked.
 */
interface VoiceProvider
{
    /**
     * Whether this deployment can actually reach a voice vendor right now.
     *
     * ⚠️ **THE EXTERNAL GATE, ANSWERED IN CODE.** T176 §7 item 3 —
     * *"Infobip — account steps: activate Voice/Calls API (gates P2)"* — is not
     * something this repository can unblock, so every surface that would
     * otherwise promise a working phone asks this instead and says the honest
     * thing. `Account\Calls` reads it to tell an owner their calls are not being
     * answered yet.
     */
    public function isActivated(): bool;

    /**
     * Everything this application needs to know about one call.
     *
     * ⚠️ **A READ-BACK RATHER THAN A PARSE OF THE WEBHOOK BODY, AND THAT IS A
     * VENDOR-DOCUMENTATION FACT.** Infobip's Calls event webhook documents eight
     * top-level fields and **no per-event payload at all** — the caller's number
     * is not in the published schema, so reading it out of the notification would
     * be a guess of exactly the shape 4256–4261 was bitten by. The call object,
     * by contrast, is fully documented.
     */
    public function call(string $providerCallId): ?VoiceCallFacts;

    /**
     * The recording of this call, when the vendor holds one.
     */
    public function recording(string $providerCallId): ?VoiceRecording;

    /**
     * The audio itself.
     *
     * ⚠️ **BYTES, NOT A URL.** `VoicemailRecorded`'s rule: a vendor's recording
     * URL expires, is often unauthenticated, and would be emailed to an owner as
     * a link anybody who saw it could open. The bytes go to our own storage and
     * the URL never leaves this method.
     */
    public function recordingBytes(string $providerFileId): ?string;
}
