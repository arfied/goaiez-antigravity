<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Contracts\VoiceProvider;

/**
 * The voice vendor that does not exist — the default, everywhere.
 *
 * ⛔ **THIS IS WHAT SHIPS UNTIL INFOBIP VOICE/CALLS IS ACTIVATED ON THE
 * ACCOUNT** (T176 §7 item 3, the external gate on P2). `VOICE_DRIVER` seeds
 * `null`, so the whole voice path is built, wired, migrated, tested and inert:
 * a webhook that arrives is verified and recorded as unresolvable, no call row
 * gains an outcome, no text-back fires and no owner is mailed.
 *
 * ⚠️ **INERT IS NOT THE SAME AS ABSENT, AND THAT IS THE POINT OF SHIPPING IT.**
 * `LogTexter` is the same shape one channel over: *"the log driver is what makes
 * this row testable at all"*. Every gate, every idempotency key and every screen
 * on this path is exercised by the suite against this driver, so activation is a
 * credential change rather than a first run.
 *
 * ⚠️ **IT ANSWERS NULL RATHER THAN THROWING.** {@see VoiceProvider} names the
 * three failure modes and each of them degrades: a call that cannot be read
 * stays `in_progress`, a recording that cannot be described still notifies the
 * owner, audio that cannot be fetched is `Unavailable` and the transcript is
 * skipped. An exception here would make "not activated yet" indistinguishable
 * from "the vendor is down", and would put both on `failed_jobs` — which on this
 * path means a caller's mobile number in cleartext on a table with no row-level
 * security (3148) that nothing prunes.
 */
final class NullVoiceProvider implements VoiceProvider
{
    public function isActivated(): bool
    {
        return false;
    }

    public function call(string $providerCallId): ?VoiceCallFacts
    {
        return null;
    }

    public function recording(string $providerCallId): ?VoiceRecording
    {
        return null;
    }

    public function recordingBytes(string $providerFileId): ?string
    {
        return null;
    }
}
