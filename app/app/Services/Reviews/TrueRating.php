<?php

declare(strict_types=1);

namespace App\Services\Reviews;

use App\Enums\ReviewSource;
use App\Models\Location;
use App\Models\Review;
use App\Support\RatingSummary;

/**
 * The one place in this application that computes an average rating.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHY THIS IS A CLASS OF ITS OWN AND NOT A METHOD ON `ReviewHubPages`
 * ---------------------------------------------------------------------------
 * `29` §2 rule 5 and §12.1: never a filtered or 5-star-only aggregate. Decision
 * 405 is `WidgetReviewController` refusing to emit **any** average, on the
 * reasoning that *"a feed returning an average beside a `min_stars_to_show`-
 * filtered list is one careless line from computing it over the filtered set"*.
 *
 * The review hub cannot take that way out — a review page with no rating on it
 * is not the surface `29` §7.6 describes — so the risk 405 avoided has to be
 * engineered against instead. It is engineered against by separation: this
 * class holds the population, `ReviewHubPages` holds the list, and **they never
 * touch each other's query**. The careless line 405 fears is a line that reads
 * a filter, and there is no filter in this file to read.
 *
 * `Review::displayable()`'s own docblock states the rule this implements:
 * *"Use this to choose which reviews to render. Compute any rating average from
 * the unfiltered set."*
 *
 * ⛔ **A LINT IN `tests/Feature/Architecture/ReviewsTest.php` HOLDS BOTH HALVES
 * OF THAT.** It fails the build if any other file in `app/` averages a rating,
 * and it fails the build if *this* file mentions `displayable`,
 * `display_on_website`, `min_stars_to_show`, `flagged_at`, `status` or
 * `approved`. Both directions matter: the first stops a second, filtered
 * average being written somewhere else; the second stops this one quietly
 * acquiring a predicate.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ WHAT "UNFILTERED" MEANS HERE, STATED EXACTLY, BECAUSE EVERY WORD OF IT IS
 * A CHOICE SOMEBODY COULD LATER MISREAD AS AN OVERSIGHT
 * ---------------------------------------------------------------------------
 * **Every first-party review this location has ever received.** Not the
 * approved ones, not the unflagged ones, not the ones the owner chose to quote,
 * not the ones above some star floor. `rating` is `NOT NULL` with a
 * `BETWEEN 1 AND 5` CHECK, so every row in the population has a rating and none
 * of them is imputed.
 *
 * That deliberately includes rows the page does **not** show:
 *
 *   - `rejected` — the owner declined to quote it on their website. That is
 *     theirs to decide (`ReviewDisplay::reject()`); *counting* is not, and an
 *     average that moved when an owner declined a two-star review would be the
 *     exact number the FTC's 2024 Rule on Consumer Reviews is about.
 *   - `pending`, `in_triage`, `resolved` — a decision that has not been taken,
 *     or was taken about recovery rather than display. Neither says anything
 *     about whether the customer's rating happened.
 *   - `flagged` — withheld by moderation. ⚠️ **This is the one inclusion worth
 *     arguing with, and it is included on purpose.** Moderation withholds the
 *     *text* (abuse, personal data, a competitor's rant), and excluding those
 *     ratings would hand this platform a lever that removes a one-star from a
 *     published average — the single most valuable thing a bad actor could ask
 *     us to build, reachable through a support ticket. Nothing in this codebase
 *     can even clear `flagged_at` (decision 408), so the lever would be
 *     one-directional. The honest cost is the other way round: a spam attack of
 *     one-stars moves a real business's published number, and the answer to
 *     that is a moderation pipeline that deletes rather than hides — which is a
 *     conversation with the owner, recorded, not a predicate added here.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ AND IT IS FIRST-PARTY ONLY, WHICH IS A PIPELINE RULE AND NOT A FILTER
 * ---------------------------------------------------------------------------
 * `29` §2 rule 1 makes first-party reviews and Google reviews two pipelines that
 * must never be confused. Averaging them together produces a number that is
 * neither: not what Google shows on the listing, and not what this business's
 * own customers said through this platform. Google's own figure has a column of
 * its own — `locations.current_rating`, *"Google's own rating"* — and the review
 * hub does not read it, publish it, or blend with it.
 *
 * ⚠️ **`source = first_party` IS NARROWER THAN `displayable()`'s EXEMPTION AND
 * THAT ASYMMETRY IS DELIBERATE.** That scope exempts *every* non-first-party
 * source from the moderation requirement, so a future Facebook or Yelp import
 * would render. This population stays first-party because an imported review is
 * another platform's aggregate arriving one row at a time — mixing it in would
 * republish somebody else's number as ours, with none of their weighting.
 */
final class TrueRating
{
    /**
     * The location's rating over every first-party review it has received, or
     * null when it has none.
     *
     * ⚠️ **THE TENANT PREDICATE IS THE GLOBAL SCOPE'S AND `location_id` IS THE
     * SECOND LAYER.** `Review` is `BelongsToTenant`, so the scope adds
     * `business_id`, RLS adds it again in the database, and this adds the
     * location — a caller holding another tenant's `Location` gets an empty
     * population rather than their reviews. That is fail-closed by three
     * independent mechanisms, and it is why this method takes a `Location`
     * rather than an id.
     *
     * ⚠️ **PLUCKED AND SUMMED IN PHP RATHER THAN `AVG()` IN SQL**, which reads
     * like the wrong call and is not. `AVG()` over `smallint` returns a Postgres
     * `numeric` that arrives as a string, so the rounding would happen against a
     * value PHP has already stringified at whatever precision the driver chose,
     * and `serialize_precision` differences between machines are exactly the
     * class of bug the warehouse replay work spent a slice on. The population is
     * bounded by one location's own reviews, and one integer per review is
     * cheaper to hold than the row it came from.
     */
    public function forLocation(Location $location): ?RatingSummary
    {
        /** @var list<int> $ratings */
        $ratings = Review::query()
            ->where('location_id', $location->id)
            ->where('source', ReviewSource::FirstParty)
            ->pluck('rating')
            ->map(static fn (mixed $rating): int => (int) $rating)
            ->values()
            ->all();

        return RatingSummary::over($ratings);
    }
}
