<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Services\Fetch\FetchResult;
use App\Services\Places\PlaceSummary;

/**
 * Everything the four checks read, gathered once.
 *
 * THE CHECKS ARE PURE FUNCTIONS OVER THIS OBJECT, and that is the design rather
 * than a convenience. Two consequences follow, and both are requirements
 * elsewhere:
 *
 * COST. Place Details is the dearest call the audit makes — 4.00¢, Atmosphere,
 * because reply-rate needs `reviews` (PlacesSku). Two checks need it. If each
 * check fetched its own data, the audit would bill Details twice and the 9.2¢
 * model in PlacesSpend — which decision 210 just had the owner accept in dollars
 * — would be wrong by 37% on the first day. Gathering once makes the cost of an
 * audit a property of this class instead of an emergent sum of whatever the
 * checks happen to ask for.
 *
 * POLITENESS. The same argument applies to the business's own web server, which
 * is not ours and did not ask to be audited. NAP and site basics both need the
 * page; they get one request between them.
 *
 * DETERMINISM. `BUILD-PLAN` §2.5.3 requires that "scoring is deterministic for a
 * fixed fixture". With the network confined to the builder, a check has no way
 * to be non-deterministic — there is nothing in it but arithmetic over this
 * struct. That test is then a real assertion about the scoring model rather than
 * a hopeful assertion about mocks.
 *
 * EVERY FIELD CAN BE ABSENT, AND ABSENCE CARRIES A REASON. A null here is never
 * "nothing to report"; it is "we could not look", and the paired reason is what
 * a check turns into CheckResult::unavailable(). See that class for why the
 * distinction is load-bearing.
 */
final readonly class AuditContext
{
    /**
     * @param  ?PlaceSummary  $place  The subject listing. Null when Details could not run.
     * @param  ?string  $placeUnavailableReason  Short code — 'budget_exhausted', 'places_error'.
     * @param  list<PlaceSummary>  $nearby
     *                                      Same-category neighbours, for the aggregate
     *                                      comparison. Never rendered by name (decision 196).
     * @param  ?string  $nearbyUnavailableReason  Short code, or null when nearby ran.
     * @param  ?string  $siteUrl  The website on the listing, if it has one.
     * @param  ?FetchResult  $siteFetch  One fetch, shared by both site-backed checks.
     * @param  ?float  $siteFetchSeconds  Wall time of that fetch — `29` §6.2's "speed hint".
     */
    public function __construct(
        public ?PlaceSummary $place = null,
        public ?string $placeUnavailableReason = null,
        public array $nearby = [],
        public ?string $nearbyUnavailableReason = null,
        public ?string $siteUrl = null,
        public ?FetchResult $siteFetch = null,
        public ?float $siteFetchSeconds = null,
    ) {}

    /**
     * Whether the audit has a subject at all.
     *
     * False means every check is unavailable and the audit itself failed — there
     * is no listing to report on, which AuditStatus::Failed exists for.
     */
    public function hasPlace(): bool
    {
        return $this->place instanceof PlaceSummary;
    }

    /**
     * The average rating across the neighbours, or null when there are none.
     *
     * The aggregate decision 196 permits: "3 nearby businesses average 4.6★"
     * carries the comparison without naming anyone, which keeps a defamation
     * surface off a page anybody can share.
     */
    public function nearbyAverageRating(): ?float
    {
        $ratings = array_values(array_filter(
            array_map(
                static fn (PlaceSummary $place): ?float => $place->rating,
                $this->nearby,
            ),
            static fn (?float $rating): bool => $rating !== null,
        ));

        if ($ratings === []) {
            return null;
        }

        return array_sum($ratings) / count($ratings);
    }

    public function nearbyCount(): int
    {
        return count($this->nearby);
    }

    /**
     * The page body, when there is one to read.
     *
     * Null covers three different situations the checks must keep apart: no
     * website on the listing, a fetch the gateway refused on policy, and a fetch
     * that failed. Each has its own reason code; none of them is an empty page.
     */
    public function siteBody(): ?string
    {
        if (! $this->siteFetch instanceof FetchResult) {
            return null;
        }

        return $this->siteFetch->successful() ? $this->siteFetch->body : null;
    }

    /**
     * Why the site-backed checks cannot run, or null when they can.
     */
    public function siteUnavailableReason(): ?string
    {
        if ($this->siteUrl === null) {
            return 'no_website';
        }

        if (! $this->siteFetch instanceof FetchResult) {
            return 'not_fetched';
        }

        if ($this->siteFetch->wasRefused()) {
            return 'fetch_refused';
        }

        if (! $this->siteFetch->successful()) {
            return 'fetch_'.$this->siteFetch->outcome->value;
        }

        return null;
    }
}
