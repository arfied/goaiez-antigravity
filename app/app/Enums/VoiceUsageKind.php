<?php

declare(strict_types=1);

namespace App\Enums;

use App\Contracts\VoiceProvider;
use App\Services\Voice\VoiceRecording;
use App\Services\Voice\VoiceSpend;

/**
 * The duration-billed lines an inbound call runs up — decision 4686.
 *
 * ⛔ **INBOUND ONLY, AND THERE IS NO OUTBOUND CASE TO ADD.** `29` §2.3 rule 13
 * was **overridden by an owner ruling on 2026-08-25** (9363) and a ruling is
 * not a build (9366). `Call`'s own docblock makes the same point about the
 * absence of a direction column: adding a case here is not a vocabulary change,
 * it is a request to build outbound calling.
 *
 * ⛔ **AND NOTHING PINS THIS LIST EITHER** (11562). `VoiceUsageKind::cases()`
 * has **zero** occurrences in `tests/` and in `app/`, and `app/Enums/` is
 * outside `voiceFilesUnderTest()` — the identical hole to {@see CallOutcome}'s,
 * raised beside it and left as a finding.
 *
 * ⛔ **THE TWO CASES ARE NEVER SUMMED, AND THE READER'S SIGNATURE IS WHAT STOPS
 * IT.** A recording is made *of* a call, so its seconds overlap the call's
 * almost exactly — adding them would double a bill that was only ever charged
 * once. Every reader on {@see VoiceSpend} therefore takes a
 * kind and **none of them has an "everything" mode**, which is deliberately the
 * opposite of `PlacesSpend::spentTodayCents(?string $purpose = null)`: there,
 * three purposes bill three distinct requests and the total is a real number an
 * operator wants. Here it would be a fiction.
 *
 * ⚠️ **A THIRD CASE FOR TRANSCRIPTION IS DELIBERATELY ABSENT** (256). It is the
 * most expensive per-minute line on this whole path — Infobip publishes speech
 * transcription at **€0.0402/min** against recording's **€0.0021/min**, read
 * from `https://www.infobip.com/voice/pricing` on 2026-08-17, nineteen times
 * dearer — and this application spends none of it: decision 4412 leaves the
 * transcription vendor undecided and `NullTranscriber` answers nothing. A case
 * with no writer is the meter reading zero for ever while looking configured,
 * which is `sending_health_windows` exactly (2496–2499) and is the failure 4686
 * says this counter exists to avoid. It arrives with its vendor.
 */
enum VoiceUsageKind: string
{
    /**
     * The call leg itself, from the vendor's `startTime` to its `endTime`.
     *
     * ⚠️ **NOBODY IN THIS APPLICATION CHOSE TO SPEND THIS AND NOTHING HERE CAN
     * REFUSE IT.** The minutes are incurred at the carrier by a forwarding rule
     * the tenant dialled into their own handset; there is no call site in `app/`
     * that decides to answer a telephone, and {@see VoiceProvider}
     * has no method that could. So this case is **observed, never gated** — it
     * is the number that makes the alert possible, and the alert is the
     * containment for the half of this path a ceiling cannot reach.
     */
    case InboundMinutes = 'inbound_minutes';

    /**
     * The recording the vendor made of that call, at its own per-minute rate.
     *
     * ⚠️ **THE VENDOR'S OWN `duration`, NOT OUR ARITHMETIC.**
     * `GET /calls/1/recordings/calls/{callId}` returns a `duration` per file and
     * {@see VoiceRecording::$seconds} carries it. It is close
     * to the call's own length and is not the same number, because recording
     * starts after the greeting and the announcement.
     *
     * ⚠️ **IT IS BILLED WHETHER OR NOT WE FETCH THE BYTES**, which is why it is
     * metered where the vendor tells us it exists rather than where we download
     * it. Refusing the download saves storage and egress; it does not unmake the
     * recording.
     */
    case Recording = 'recording';

    /**
     * How an operator reads this line on a screen — `22`'s outcome language.
     */
    public function label(): string
    {
        return match ($this) {
            self::InboundMinutes => 'Inbound call time',
            self::Recording => 'Recorded message time',
        };
    }
}
