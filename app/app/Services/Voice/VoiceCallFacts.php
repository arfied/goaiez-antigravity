<?php

declare(strict_types=1);

namespace App\Services\Voice;

use App\Contracts\VoiceProvider;
use App\Enums\CallOutcome;
use Carbon\CarbonImmutable;

/**
 * What a voice vendor says about one call, in this application's own shape.
 *
 * The read half of the {@see VoiceProvider} seam. Every field
 * here maps to a **documented** field of Infobip's Call object, verified against
 * the live reference on 2026-08-16:
 *
 *   https://www.infobip.com/docs/api/channels/voice/calls/call-legs/get-call
 *
 *   `from` · `to` · `state` · `startTime` · `answerTime` · `endTime` ·
 *   `ringDuration`
 *
 * ⛔ **`direction` IS DOCUMENTED AND IS DELIBERATELY NOT CARRIED.** The vendor's
 * `CallDirection` is `INBOUND` | `OUTBOUND`; this platform has one direction and
 * `CLAUDE.md` says why. Carrying the field would create the first place an
 * outbound call could be recorded, and `InboundCall`'s docblock already refuses
 * it in the neighbouring object for the same reason. What the driver does with
 * the vendor's value instead is **refuse the call**: an `OUTBOUND` leg on this
 * platform is not a row to write, it is a fact that something is wrong.
 */
final readonly class VoiceCallFacts
{
    public function __construct(
        public string $providerCallId,
        public string $from,
        public string $to,
        public ?string $providerState = null,
        public ?CarbonImmutable $startedAt = null,
        public ?CarbonImmutable $answeredAt = null,
        public ?CarbonImmutable $endedAt = null,
        public ?int $ringSeconds = null,
    ) {}

    /**
     * This application's reading of the vendor's state.
     *
     * ⚠️ **`answeredAt` IS WHAT DISAMBIGUATES `FINISHED`** — see
     * {@see CallOutcome::fromProviderState()}, which is the one place the
     * vendor's vocabulary is read and the one place this argument lives.
     */
    public function outcome(): CallOutcome
    {
        return CallOutcome::fromProviderState($this->providerState, $this->answeredAt !== null);
    }
}
