<?php

declare(strict_types=1);

namespace App\Services\Audit;

use App\Contracts\FetchGateway;
use App\Contracts\PlacesClient;
use App\Exceptions\PlacesBudgetExhausted;
use App\Exceptions\PlacesRequestFailed;
use App\Services\Fetch\FetchResult;
use App\Services\Places\PlaceSummary;

/**
 * The only part of the audit that touches the network or spends money.
 *
 * EVERY FAILURE HERE BECOMES DATA, NOT AN EXCEPTION. Both Places exceptions are
 * caught and turned into a reason code on the context, because `29` §11.2 row
 * 2's gate requires budget exhaustion to "degrade, never throw at the visitor"
 * and `BUILD-PLAN` §2.5.3 requires a check that cannot run to say so. Letting
 * PlacesBudgetExhausted escape would produce a 500 on a marketing page, from the
 * cost cap working exactly as designed.
 *
 * NEARBY IS OPTIONAL AND DETAILS IS NOT. Losing the neighbours costs one finding
 * out of a dozen; losing Details means there is no subject to audit and every
 * check is unavailable. They are attempted in that dependency order — Details
 * first, and Nearby only if Details gave us somewhere to look.
 *
 * THE SITE FETCH GOES THROUGH THE GATEWAY, WHICH IS NOT A STYLE CHOICE. `40`
 * Part 8's build-failing import lint permits outbound HTTP from exactly seven
 * files in this application and this is not one of them. Everything the gateway
 * brings with it applies here as a result: robots is respected and cannot be
 * disabled (decision 216's CHECK constraint), the `subject_website` rate budget
 * is enforced, and the attempt is written to the ledger whether or not a socket
 * opened.
 */
final class AuditContextBuilder
{
    /**
     * The `fetch_sources` row row 2 uses, seeded by slice D's migration at
     * `light_fetch` with a 6/minute, 500/day budget.
     *
     * The business's own site is the only thing this audit fetches. Google, Yelp
     * and Facebook are seeded `guided_only` and would be refused — see
     * NapQuickScanCheck for why the directory half of `29` §6.2's third check is
     * therefore not built.
     */
    public const string SOURCE_KEY = 'subject_website';

    public function __construct(
        private readonly PlacesClient $places,
        private readonly FetchGateway $gateway,
    ) {}

    public function build(string $placeId): AuditContext
    {
        $place = null;
        $placeReason = null;

        try {
            $place = $this->places->details($placeId);

            if ($place === null) {
                // The API answered and had nothing. A place ID that resolves to
                // nothing is a closed or merged listing, which is a real and
                // reportable state — but not one this slice can distinguish from
                // a bad ID, so it stays an honest "we could not read it".
                $placeReason = 'place_not_found';
            }
        } catch (PlacesBudgetExhausted) {
            $placeReason = 'budget_exhausted';
        } catch (PlacesRequestFailed $e) {
            $placeReason = 'places_'.$e->reason;
        }

        if (! $place instanceof PlaceSummary) {
            return new AuditContext(placeUnavailableReason: $placeReason ?? 'no_place');
        }

        [$nearby, $nearbyReason] = $this->nearby($place);
        [$siteFetch, $seconds] = $this->fetchSite($place->websiteUri);

        return new AuditContext(
            place: $place,
            nearby: $nearby,
            nearbyUnavailableReason: $nearbyReason,
            siteUrl: $place->websiteUri,
            siteFetch: $siteFetch,
            siteFetchSeconds: $seconds,
        );
    }

    /**
     * The three same-category neighbours, or a reason there are none.
     *
     * @return array{0: list<PlaceSummary>, 1: ?string}
     */
    private function nearby(PlaceSummary $place): array
    {
        if ($place->latitude === null || $place->longitude === null || $place->primaryType === null) {
            // Nothing to search around, or nothing to search for. Not a failure
            // — a listing without a category is itself a finding, made by
            // GbpCompletenessCheck.
            return [[], 'no_anchor'];
        }

        try {
            $nearby = $this->places->nearby(
                $place->latitude,
                $place->longitude,
                $place->primaryType,
            );
        } catch (PlacesBudgetExhausted) {
            return [[], 'budget_exhausted'];
        } catch (PlacesRequestFailed $e) {
            return [[], 'places_'.$e->reason];
        }

        // Google returns the subject itself when searching around its own
        // coordinates. Comparing a business against an average that includes
        // itself pulls the comparison toward "you are exactly average" and is
        // wrong in a way nobody would ever notice from the output.
        $others = array_values(array_filter(
            $nearby,
            static fn (PlaceSummary $candidate): bool => $candidate->placeId !== $place->placeId,
        ));

        return [$others, $others === [] ? 'no_neighbours' : null];
    }

    /**
     * One request to the business's own website, timed.
     *
     * @return array{0: ?FetchResult, 1: ?float}
     */
    private function fetchSite(?string $websiteUri): array
    {
        if ($websiteUri === null || trim($websiteUri) === '') {
            return [null, null];
        }

        $startedAt = microtime(true);
        $result = $this->gateway->fetch(self::SOURCE_KEY, $websiteUri);
        $elapsed = microtime(true) - $startedAt;

        // Timing a refusal would be timing our own policy check, and reporting
        // that as a speed hint about somebody's website is meaningless.
        return [$result, $result->successful() ? $elapsed : null];
    }
}
