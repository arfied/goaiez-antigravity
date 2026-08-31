<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Support\RatingSummary;

/**
 * The `application/ld+json` block on the hosted review hub.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE ONE THING THIS CLASS EXISTS TO GET RIGHT
 * ---------------------------------------------------------------------------
 * `29` §2 rule 5 and §12.1: **accurate schema only, never a filtered or
 * 5-star-only aggregate** — a build-failing test. Decision 405 is the widget
 * feed declining to emit any average at all, because *"a feed returning an
 * average beside a `min_stars_to_show`-filtered list is one careless line from
 * computing it over the filtered set."*
 *
 * This class takes a {@see RatingSummary} and nothing else. **It cannot see a
 * review, a list, a query or a filter**, so the careless line 405 fears is not
 * expressible here: there is nothing in scope to compute an average from.
 * {@see TrueRating} is the only thing that produces a `RatingSummary`, and a
 * lint holds it to an unfiltered population.
 *
 * ---------------------------------------------------------------------------
 * ⛔ NO `review` ARRAY, AND IT IS NOT AN OMISSION
 * ---------------------------------------------------------------------------
 * Two reasons, either sufficient:
 *
 *   1. **PII.** A `review` array would put every reviewer's display name and
 *      every review's text into a machine-readable block designed to be
 *      harvested. The visible page already shows them, to a human, in prose;
 *      restating them as structured data is a second, more extractable
 *      publication of somebody else's words for no gain the visible page does
 *      not already give. `CLAUDE.md`'s tiebreaker: less exposed PII.
 *   2. **Consistency.** The visible list is what the owner approved; the
 *      aggregate is over everything received. Emitting both as structured data
 *      would publish, in machine-readable form, an aggregate that its own
 *      accompanying reviews do not add up to — which reads as a discrepancy
 *      even though both numbers are honest. The aggregate stands alone, with
 *      `ratingCount` stating its own population.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ NO ADDRESS, NO PHONE, NO URL, AND NO `LocalBusiness`
 * ---------------------------------------------------------------------------
 * The type is `Organization`, not `LocalBusiness`, and it carries a name and a
 * rating and nothing else. A `LocalBusiness` block invites `address`,
 * `telephone` and `geo`, and `locations.address` and `locations.primary_phone`
 * are guarded columns whose docblocks record that a stranger is at the end of
 * them (decision 6100). Restating them here would publish a tenant's contact
 * details, in a harvestable block, on a page whose subject is their reviews.
 * The business's name is on the page already; the rest is not this page's to
 * say.
 */
final class ReviewHubSchema
{
    /**
     * The JSON-LD for this page, or null when there is nothing truthful to say.
     *
     * ⛔ **NULL WHEN THERE IS NO RATING**, rather than a block with a zero or an
     * omitted `aggregateRating`. A business with no reviews yet has no rating,
     * and publishing a structured-data block asserting an organisation exists
     * with no rating buys nothing while a `0` would assert a rating nobody gave.
     *
     * ⚠️ **THE FOUR `JSON_HEX_*` FLAGS ARE LOAD-BEARING.** This string is
     * rendered inside a `<script>` element with `{!! !!}`, so a business named
     * `</script><script>alert(1)</script>` would otherwise close the element and
     * execute in the browser of every visitor to that page. The flags escape
     * `<`, `>`, `&`, `'` and `"` to `\uXXXX`, which is valid JSON and inert in
     * HTML. `JSON_UNESCAPED_UNICODE` is deliberately **not** set for the same
     * reason — plain ASCII escapes cannot be misread by any parser.
     *
     * ⚠️ **`JSON_PRESERVE_ZERO_FRACTION` IS THE FIFTH FLAG AND IT IS NOT
     * COSMETIC.** Without it `json_encode()` emits a whole-numbered average as
     * `5` rather than `5.0`, so the type of `ratingValue` would change with the
     * data — an integer on a location whose reviews happen to average exactly
     * five, a float on every other. A consumer that type-switches on that, and
     * a test asserting one shape, would both be right about one population and
     * wrong about the other.
     *
     * ⚠️ **`bestRating` AND `worstRating` ARE STATED RATHER THAN IMPLIED.**
     * schema.org defaults them to 5 and 1, which happens to be right, and a
     * consumer that assumed a 10-point scale would read 4.2 as poor. The
     * `reviews_rating_between_1_and_5` CHECK is what makes the claim true.
     */
    public function forBusiness(string $businessName, ?RatingSummary $rating): ?string
    {
        if (! $rating instanceof RatingSummary) {
            return null;
        }

        $encoded = json_encode([
            '@context' => 'https://schema.org',
            '@type' => 'Organization',
            'name' => $businessName,
            'aggregateRating' => [
                '@type' => 'AggregateRating',
                'ratingValue' => $rating->average,
                'ratingCount' => $rating->count,
                'bestRating' => 5,
                'worstRating' => 1,
            ],
        ], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_PRESERVE_ZERO_FRACTION);

        // json_encode() answers false only on malformed UTF-8 or recursion,
        // neither of which a business name out of Postgres can be. Handled
        // rather than asserted, because the alternative is `false` reaching a
        // string return type and fataling on a public page.
        return $encoded === false ? null : $encoded;
    }
}
