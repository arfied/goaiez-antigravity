<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\ReviewSentiment;
use App\Enums\ReviewTheme;

/**
 * What a model understood a review to be about.
 *
 * NOT NAMED ReviewAnalysis, deliberately: `AiTask::ReviewAnalysis` already
 * exists and both would be imported into the same call stack, where the wrong
 * one resolves silently.
 *
 * NOTHING HERE ROUTES. Slice E reads the customer's own star rating and the
 * per-destination threshold (decision 111). A model disagreeing with a
 * customer's stars must never change where that customer is sent — that would be
 * a gate nobody acknowledged, which is what decision 114 forbids.
 */
final readonly class ReviewInsights
{
    /**
     * @param  list<ReviewTheme>  $themes
     */
    public function __construct(
        public ReviewSentiment $sentiment,
        public array $themes,
    ) {}

    /**
     * @return list<string>
     */
    public function themeValues(): array
    {
        return array_map(static fn (ReviewTheme $theme): string => $theme->value, $this->themes);
    }
}
