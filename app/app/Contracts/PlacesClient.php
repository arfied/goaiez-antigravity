<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Exceptions\PlacesBudgetExhausted;
use App\Exceptions\PlacesRequestFailed;
use App\Services\Places\PlaceSuggestion;
use App\Services\Places\PlaceSummary;

/**
 * The four Google Places calls the free instant audit makes.
 *
 * An interface rather than a concrete service because `29` §11.2 row 2 shares
 * this code path with row 3's GBP-01b, and because the audit engine (slice E)
 * has to be testable without a live key or a live bill. It is also the seam that
 * would let a different provider answer these three questions later — though
 * nothing plans to, and the field-mask cost model in PlacesSku is Google-shaped
 * enough that a second implementation would need its own.
 *
 * EVERY METHOD CAN REFUSE TO SPEND. PlacesBudgetExhausted is not an error in the
 * ordinary sense — it means the daily budget said no, which is the system
 * working. Callers on a visitor-facing path MUST catch it and degrade: `29`
 * §11.2 row 2's gate and BUILD-PLAN §2.5.3 both require that budget exhaustion
 * "degrades, never throws at the visitor". A check that cannot run says so
 * plainly; it never fabricates a finding and never 500s.
 */
interface PlacesClient
{
    /**
     * Suggestions for a partially-typed business name (decision 195).
     *
     * Server-proxied rather than called from the browser, which is decision
     * 195's whole point: the client-side Maps JS widget fires Google on every
     * keystroke *before* the consent banner has resolved, which is precisely the
     * case `29` §12.1 tests for. Proxying also keeps the API key server-side.
     *
     * Bills PlacesSku::AutocompleteRequests, against **autocomplete's own daily
     * budget** rather than the audit's — see PlacesSpend::AUDIT_PURPOSE. Read
     * that SKU's docblock before adding a session token: it does not do what it
     * appears to do.
     *
     * Returns an empty list when the budget is exhausted rather than throwing.
     * That is the difference between this and every other method here, and it is
     * deliberate: a dropdown that stops suggesting is a feature quietly
     * degrading, while a dropdown that raises an exception is a marketing page
     * throwing errors at someone who is still typing.
     *
     * @return list<PlaceSuggestion> Best matches first; at most five.
     *
     * @throws PlacesRequestFailed
     */
    public function autocomplete(string $query, ?string $regionCode = null): array;

    /**
     * Resolve a typed business name to a place.
     *
     * Bills Text Search Pro: the confirm card needs `displayName`, which is a
     * Pro field, so there is no cheaper mask that still shows a human which
     * business we found.
     *
     * @return list<PlaceSummary> Best matches first; empty when nothing matched.
     *
     * @throws PlacesBudgetExhausted
     * @throws PlacesRequestFailed
     */
    public function textSearch(string $query, ?string $regionCode = null): array;

    /**
     * Everything the audit's checks need about one place.
     *
     * Bills Place Details Atmosphere — the dearest SKU we use, and unavoidable:
     * reply-rate needs `reviews`, and `reviews` is an Atmosphere field. See
     * PlacesSku for why one field sets the price of the whole call.
     *
     * Served from a 24h cache keyed on `place_id` (`29` §6.2: "repeat lookups
     * free"). A cache hit is still metered, at zero, so that the cache's value
     * is a query rather than a belief.
     *
     * @throws PlacesBudgetExhausted
     * @throws PlacesRequestFailed
     */
    public function details(string $placeId): ?PlaceSummary;

    /**
     * Same-category businesses near a point, for the aggregate comparison.
     *
     * Bills Nearby Search Enterprise, because the comparison is about ratings
     * and `rating` is an Enterprise field.
     *
     * Decision 196 keeps the result aggregate and unnamed on the public page —
     * "3 nearby businesses average 4.6★" — so this returns what it needs to
     * compute an average and nothing that would name a competitor on a page
     * anyone can share.
     *
     * @return list<PlaceSummary>
     *
     * @throws PlacesBudgetExhausted
     * @throws PlacesRequestFailed
     */
    public function nearby(
        float $latitude,
        float $longitude,
        string $primaryType,
        int $limit = 3,
    ): array;
}
