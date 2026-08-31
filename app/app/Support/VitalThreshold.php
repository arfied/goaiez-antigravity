<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\VitalRating;
use App\Enums\WebVital;
use Carbon\CarbonImmutable;

/**
 * One published boundary pair, carried with the document it was read out of.
 *
 * ⛔ **THE CITATION IS PART OF THE VALUE AND NOT A COMMENT BESIDE IT** (decision
 * 5724). A vendor figure in this codebase is verified against the raw artefact
 * or it is not written at all — 255's Atmosphere price, 277's
 * `max_completion_tokens`, 684's `current_period_end` and 1349's
 * `googlebusiness` are four occasions on which a plausible remembered figure
 * was wrong and nothing failed. A number sitting alone in a constant cannot be
 * re-checked by anybody: they would have to guess which page it came from and
 * whether that page has moved since. So the URL, the sentence it was taken
 * from, the date the page itself was last updated and the date **we** read it
 * travel with the number, and [[\App\Support\CoreWebVitals::isStale()]] turns
 * the last of those into a refusal rather than a note.
 *
 * ⚠️ **EVERY FIGURE HERE IS IN THE METRIC'S STORED UNIT, NOT THE UNIT THE PAGE
 * PRINTS.** web.dev writes LCP in seconds and CLS as a decimal; the warehouse
 * stores milliseconds and thousandths, because [[WebVital]]'s docblock explains
 * at length why no float may enter a replayed table. The conversion happens
 * once, in [[CoreWebVitals]], next to the quote it converts — which is the one
 * place a reader can check it.
 */
final readonly class VitalThreshold
{
    /**
     * @param  int  $goodAtOrBelow  the published "good" boundary, **inclusive**, in the metric's stored unit
     * @param  int  $poorAbove  the published "poor" boundary, **exclusive**, in the metric's stored unit
     * @param  bool  $isCoreWebVital  whether Google classes this metric as a Core Web Vital
     * @param  string  $sourceUrl  the page this was read from
     * @param  string  $sourceQuote  the sentence it was read out of, verbatim
     * @param  string  $documentUpdatedOn  the "last updated" the page shows for itself
     * @param  string  $fetchedOn  the day we read it
     */
    public function __construct(
        public WebVital $metric,
        public int $goodAtOrBelow,
        public int $poorAbove,
        public bool $isCoreWebVital,
        public string $sourceUrl,
        public string $sourceQuote,
        public string $documentUpdatedOn,
        public string $fetchedOn,
    ) {}

    /**
     * Which band a measurement falls in.
     *
     * ⚠️ **THE BOUNDARIES ARE INCLUSIVE BELOW AND EXCLUSIVE ABOVE, WHICH IS THE
     * SOURCE'S OWN WORDING**: *"2.5 seconds **or less**"* is good, and *"poor
     * values are **greater than** 4.0 seconds"*. A value exactly on either
     * boundary therefore takes the kinder band, which is what the pages say and
     * not a choice made here.
     *
     * ⚠️ **AND THE BUCKETING MAKES THIS CONSERVATIVE RATHER THAN GENEROUS.**
     * [[WebVital::bucketWidth()]] rounds a measurement **up** to the next
     * multiple, so the p75 handed in here is an upper bound on the real one: a
     * page rated [[VitalRating::Good]] is good on its true figure too, and the
     * rounding can only ever move a page out of `Good` and never into it. That
     * is the direction `28` §4.3's *"never a fabricated win"* asks for, and it
     * is why nothing in this method needs a tolerance.
     */
    public function rate(int $value): VitalRating
    {
        return match (true) {
            $value <= $this->goodAtOrBelow => VitalRating::Good,
            $value > $this->poorAbove => VitalRating::Poor,
            default => VitalRating::NeedsImprovement,
        };
    }

    /**
     * The day this citation stops being usable, and the claim with it.
     */
    public function staleOn(): CarbonImmutable
    {
        return CarbonImmutable::parse($this->fetchedOn)->addDays(CoreWebVitals::REVERIFY_AFTER_DAYS);
    }
}
