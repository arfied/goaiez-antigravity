<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use Carbon\CarbonImmutable;

/**
 * What one window of a site change's measurement actually contained —
 * `site_changes.baseline_metrics` and `measured_metrics`, in the shape they are
 * stored in.
 *
 * ⛔ **A NULL IS "WE COULD NOT SEE" AND A ZERO IS "NOBODY CAME", AND THE WHOLE
 * DOCUMENT EXISTS TO KEEP THEM APART** (229, and 5676 at two addresses in the
 * neighbouring slice). A tenant with no pixel delivered to their pages and a
 * tenant whose page nobody visited would otherwise produce the same row — and
 * the first of those is **every tenant today** (4966), so collapsing them would
 * auto-revert the first change every customer ever receives.
 *
 * ⚠️ **THE WINDOW TRAVELS WITH THE FIGURES.** The dates and the day count are in
 * the document rather than derived from `applied_at` by whoever reads it,
 * because the two windows are different lengths — fourteen days before, days
 * fourteen to thirty after (`29` §2 rule 32) — and a reader that assumed they
 * matched would compare a fortnight's total against seventeen days' and call the
 * difference a result.
 *
 * ⚠️ **`v` IS IN THE DOCUMENT BECAUSE THE COLUMN IS `jsonb` AND WILL OUTLIVE
 * THIS SHAPE.** These rows are the evidence behind a verdict an owner can be
 * shown months later, and a later slice adding a metric must not make an older
 * row unreadable or, worse, silently misread.
 *
 * ⚠️ **CONVERSIONS ARE RECORDED AND ARE DELIBERATELY NOT A SIGNAL** —
 * {@see ChangeComparison} says why: at the volumes a local business's single
 * page sees, a conversion count sits below any honest sample floor for ever, so
 * a trigger built on one would be an arm that can never fire (256). It is kept
 * because it is the number somebody will want when they read the evidence.
 */
final readonly class ChangeMetrics
{
    /** The document shape these figures are stored in. */
    public const int VERSION = 1;

    public function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $days,
        /** Pageviews from the pixel mart, or null when this tenant has none at all. */
        public ?int $pageviews = null,
        /** Conversions on the same page over the same window. Evidence, not a signal. */
        public ?int $conversions = null,
        /** Search clicks for the whole property, or null when there is no usable grant. */
        public ?int $searchClicks = null,
        /** Search impressions for the whole property, over the same window. */
        public ?int $searchImpressions = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'v' => self::VERSION,
            'from' => $this->from->toDateString(),
            'to' => $this->to->toDateString(),
            'days' => $this->days,
            'pageviews' => $this->pageviews,
            'conversions' => $this->conversions,
            'search_clicks' => $this->searchClicks,
            'search_impressions' => $this->searchImpressions,
        ];
    }
}
