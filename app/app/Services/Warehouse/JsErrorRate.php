<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\VitalSampleState;
use LogicException;

/**
 * How often somebody's website throws at a real visitor — `28` §4.3's fourth
 * rollback trigger, as a reading rather than as a trigger.
 *
 * ⚠️ **A RATE PER THOUSAND PAGEVIEWS, AS AN INTEGER.** The obvious return is a
 * float, and this codebase's rule for anything naturally fractional is an
 * integer in a smallest unit — `CanonicalJson`'s docblock for telemetry,
 * CLAUDE.md's for money. Nothing here is stored, so a float would not break the
 * replay gate; it would just be the one number in the chain that renders
 * differently on a differently configured box, for no benefit.
 *
 * ⛔ **A ZERO DENOMINATOR IS A STATE, NEVER A ZERO RATE.** A page nobody
 * visited has no error rate, and reporting one as `0` says the opposite of what
 * is true — it says the page is clean.
 */
final readonly class JsErrorRate
{
    public function __construct(
        public VitalSampleState $state,
        public int $pageviews,
        public int $errors,
        private ?int $perThousandPageviews = null,
    ) {}

    /**
     * Errors per thousand pageviews.
     *
     * @throws LogicException when this reading is not a measurement
     */
    public function perThousandPageviews(): int
    {
        if ($this->state !== VitalSampleState::Measured || $this->perThousandPageviews === null) {
            throw new LogicException(
                'This error-rate reading is "'.$this->state->value.'", so it has no rate to give. '
                .'A rate over too few pageviews doubles on one unlucky visitor, which is exactly '
                .'the noise `28` §4.3\'s trigger must not fire on.',
            );
        }

        return $this->perThousandPageviews;
    }

    public function isMeasured(): bool
    {
        return $this->state === VitalSampleState::Measured;
    }
}
