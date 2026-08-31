<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Middleware\ResolveFeedbackPage;
use App\Http\Resources\HubReviewResource;
use App\Models\Location;
use App\Models\ReviewHubPage;
use App\Services\Reviews\ReviewHubPages;
use App\Services\Reviews\ReviewHubSchema;
use App\Services\Reviews\TrueRating;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The hosted review hub (`29` §7.6, Appendix A's `/r/{slug}`) — one public page
 * per location, listing the reviews its owner has approved for publication.
 *
 * ---------------------------------------------------------------------------
 * ⛔ WHICH PIPELINE THIS RENDERS, SAID PLAINLY BECAUSE THE TWO MUST NEVER BE
 * CONFUSED
 * ---------------------------------------------------------------------------
 * **First-party only.** Every review on this page came through `/f/{slug}` —
 * this platform's own capture form — and every rating in the aggregate came
 * from the same place. **No Google review is on this page, in the list or in the
 * number**, and `locations.current_rating`, the column whose migration calls it
 * *"Google's own rating"*, is neither read nor rendered here.
 *
 * That is not `29` §2 rule 1's prohibition being satisfied by omission; rule 1
 * forbids *holding, hiding, approving or moderating* a Google review, and a
 * business's own marketing page is not a place anybody is entitled to appear
 * (decision 409). It is stated because a page headed with a business's name and
 * a star rating is exactly where a reader would assume they were looking at the
 * Google figure, and they are not.
 *
 * ---------------------------------------------------------------------------
 * ⛔ THE AGGREGATE AND THE LIST ARE COMPUTED FROM DIFFERENT POPULATIONS, ON
 * PURPOSE, AND THAT IS THE WHOLE COMPLIANCE SUBSTANCE OF THIS FILE
 * ---------------------------------------------------------------------------
 * The list is what the owner approved. The rating is over **every first-party
 * review the location has ever received**, approved or not. They are different
 * numbers on any location where the owner has declined a review, and the page
 * says so in visible copy rather than leaving a reader to assume the star
 * rating describes the reviews beneath it.
 *
 * `29` §2 rule 5 and §12.1 forbid a filtered aggregate; decision 405 is the
 * widget feed refusing to emit any average rather than risk computing one over
 * its filtered list. {@see TrueRating} is this slice's answer to the same
 * problem — one class, no filters, held there by a lint.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ NOT LIVEWIRE, and for `FeedbackPageController`'s reason (decision 259):
 * there is no server-held state here worth a round trip, and this page is
 * read-only. It has no JavaScript at all, so it renders identically with
 * scripting disabled and there is nothing for a crawler to execute.
 */
final class ReviewHubController extends Controller
{
    /**
     * ⚠️ **`ResolveFeedbackPage` IS THE MIDDLEWARE, AND THE NAME IS NOT A
     * MISTAKE.** It is the one file in this application permitted to set a
     * tenant on an unauthenticated request — its own docblock calls that *"a
     * privilege-escalation primitive by any other name"* and confines it to
     * *"one narrow file with one input and no branches"*. Adding a second such
     * file for this route would have doubled that surface to serve a page that
     * hangs off the very same slug, so this route joins its group instead.
     */
    public function __invoke(
        Request $request,
        ReviewHubPages $pages,
        TrueRating $trueRating,
        ReviewHubSchema $schema,
    ): View {
        $location = $this->location($request);
        $slug = $this->slug($request);

        $page = $pages->publishedFor($location, $slug);

        if (! $page instanceof ReviewHubPage) {
            // ⚠️ **THE SAME 404 AS AN UNKNOWN SLUG, WITH ONE INFERENCE THIS
            // CANNOT CLOSE AND WHICH IS THEREFORE WRITTEN DOWN RATHER THAN
            // GLOSSED.** `/f/{slug}` answering 200 while `/r/{slug}` answers 404
            // tells a stranger that this location has no published hub. That is
            // unavoidable — a page either exists at an address or it does not —
            // and it is bounded: it says nothing about *why*, nothing about the
            // reviews, and nothing about any other tenant.
            throw new NotFoundHttpException;
        }

        $businessName = $location->businessName();

        // ⛔ **THE UNFILTERED POPULATION, AND THE ONLY PLACE THIS METHOD MAY GET
        // A RATING FROM.** Computing it from the list below would be one line
        // shorter and would be the forbidden number. The two are fetched in this
        // order so that the rating is in hand before the list exists, which is
        // the small structural reason nobody reaches for the wrong one.
        $rating = $trueRating->forLocation($location);

        $reviews = $pages->displayedFor($location);

        return view('review-hub.show', [
            'businessName' => $businessName,
            'page' => $page,
            'rating' => $rating,
            'jsonLd' => $schema->forBusiness($businessName, $rating),
            'reviews' => HubReviewResource::collection($reviews)->resolve(),
        ]);
    }

    /**
     * The location `ResolveFeedbackPage` put on the request.
     *
     * Typed rather than read inline, because the attribute bag returns mixed and
     * Larastan level 8 is right to insist. Unreachable while the middleware is
     * attached; it exists so that detaching it fails loudly rather than
     * rendering a public page with no tenant established.
     */
    private function location(Request $request): Location
    {
        $location = $request->attributes->get(ResolveFeedbackPage::LOCATION);

        return $location instanceof Location
            ? $location
            : throw new RuntimeException('ResolveFeedbackPage did not run on this route.');
    }

    /**
     * The slug this request resolved under.
     *
     * Read back off the route rather than off the feedback page on the request,
     * so the comparison in {@see ReviewHubPages::publishedFor()} is against what
     * the visitor actually asked for.
     */
    private function slug(Request $request): string
    {
        $slug = $request->route('slug');

        return is_string($slug)
            ? $slug
            : throw new RuntimeException('ResolveFeedbackPage did not run on this route.');
    }
}
