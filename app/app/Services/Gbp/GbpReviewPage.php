<?php

declare(strict_types=1);

namespace App\Services\Gbp;

/**
 * One page of reviews, plus the cursor that continues it.
 *
 * A pair rather than a bare list because the cursor is the only thing that makes
 * a sync resumable, and returning the list alone is how a caller ends up
 * counting pages instead — which neither provider supports. Zernio's cursor and
 * Google's `nextPageToken` are both opaque and neither is seekable.
 *
 * **A null cursor means the last page**, not "start again". Callers store it and
 * stop when it is null; a caller that treats null as "begin from the top" builds
 * an infinite sync, which is the failure mode worth naming here because it costs
 * a quota rather than raising an error.
 */
final readonly class GbpReviewPage
{
    /**
     * @param  list<GbpReview>  $reviews
     * @param  int  $dropped  Payload entries that could not be read as a review
     *                        — see {@see GbpReview::fromZernio()}. Surfaced
     *                        rather than swallowed so a provider shape change
     *                        shows up as a number in a log line instead of as a
     *                        review count that is quietly short.
     */
    public function __construct(
        public array $reviews,
        public ?string $cursor = null,
        public int $dropped = 0,
    ) {}

    public function isLastPage(): bool
    {
        return $this->cursor === null;
    }
}
