<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\SpeedVerdict;

/**
 * Two readings and what may honestly be said about the difference.
 *
 * ⚠️ **`changeBasisPoints` IS PRESENT ONLY WHEN BOTH SIDES WERE MEASURED**, and
 * it is signed the way the metric is: **positive is worse**, because every one
 * of these four metrics is a duration or a shift and a bigger number is a worse
 * page. A caller reading it as "improvement" gets the sign backwards, which is
 * why the verdict — not the number — is what [[sentence()]] speaks from.
 */
final readonly class SpeedComparison
{
    public function __construct(
        public SpeedVerdict $verdict,
        public VitalReading $before,
        public VitalReading $after,
        public ?int $changeBasisPoints = null,
    ) {}

    /**
     * The sentence an owner is shown, obeying `28` §4.3's reporting rule.
     */
    public function sentence(): string
    {
        return $this->verdict->sentence();
    }
}
