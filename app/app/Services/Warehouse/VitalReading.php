<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\DeviceClass;
use App\Enums\VitalRating;
use App\Enums\VitalSampleState;
use App\Enums\WebVital;
use App\Support\CoreWebVitals;
use App\Support\VitalThreshold;
use Carbon\CarbonImmutable;
use LogicException;

/**
 * One answer to "how fast is this site for real people", including the two
 * answers that are not a number.
 *
 * ⛔ **`p75` IS NULL UNLESS THE STATE IS `Measured`, AND THE ACCESSOR THROWS
 * RATHER THAN RETURNING A ZERO.** That is decision 229's rule made mechanical:
 * a caller who forgets to check the state gets an exception naming the mistake,
 * where a `?? 0` would quietly publish "your pages load instantly" to a tenant
 * nobody has ever visited. `BUILD-PLAN.md` §2.11.5 conflict 6 is the same rule
 * from the other side — a signal that cannot exist must not be reported as a
 * measured zero.
 *
 * ⚠️ **THE UNIT IS THE METRIC'S SMALLEST UNIT, NOT A HUMAN ONE** —
 * milliseconds for LCP, INP and TTFB; thousandths for CLS. See
 * [[\App\Enums\WebVital]] for why nothing here is a float.
 */
final readonly class VitalReading
{
    public function __construct(
        public WebVital $metric,
        public ?DeviceClass $deviceClass,
        public VitalSampleState $state,
        public int $samples,
        private ?int $p75 = null,
    ) {}

    /**
     * The 75th percentile, in the metric's smallest unit.
     *
     * @throws LogicException when this reading is not a measurement
     */
    public function p75(): int
    {
        if ($this->state !== VitalSampleState::Measured || $this->p75 === null) {
            throw new LogicException(
                'This '.$this->metric->value.' reading is "'.$this->state->value.'", so it has no '
                .'percentile to give. Check the state before asking: a thin sample reported as a '
                .'p75 is manufactured evidence (decision 229), and it is the expected answer for '
                .'most tenants today rather than an edge case.',
            );
        }

        return $this->p75;
    }

    public function isMeasured(): bool
    {
        return $this->state === VitalSampleState::Measured;
    }

    /**
     * Where this measurement sits against Google's published boundary for the
     * metric — `null` when there is nothing honest to say.
     *
     * ⛔ **THREE WAYS THIS IS `null`, AND ALL THREE ARE THE FAIL-CLOSED
     * DIRECTION** (decision 5724). The reading is not a measurement, so there
     * is no figure to rate; or the metric has no published boundary, so there
     * is nothing to rate it against; or the citation has gone stale, so nobody
     * can vouch for the boundary any more. **A caller may not tell the three
     * apart from the return value and does not need to** — every one of them
     * means the same thing to a person reading a report, which is that this
     * application is not going to tell them how fast their site is.
     *
     * ⚠️ **A RATING IS NOT A VERDICT.** This says how fast the site is; a
     * [[\App\Enums\SpeedVerdict]] says what a change did. `28` §4.3's report
     * needs both, and 5610's whole point was that the second was being asked to
     * stand in for the first.
     */
    public function rating(?CarbonImmutable $asOf = null): ?VitalRating
    {
        if ($this->state !== VitalSampleState::Measured || $this->p75 === null) {
            return null;
        }

        $threshold = CoreWebVitals::for($this->metric);

        if (! $threshold instanceof VitalThreshold || CoreWebVitals::isStale($asOf)) {
            return null;
        }

        return $threshold->rate($this->p75);
    }
}
