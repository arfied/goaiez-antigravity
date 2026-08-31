<?php

declare(strict_types=1);

namespace App\Services\Destinations;

use InvalidArgumentException;

/**
 * Google's write-a-review URL, derived from a place id.
 *
 * NEVER STORED (decision 308). A listing merge changes the place id — that is
 * why PlaceConfirmation keeps `google_cid` and the originally pasted URL
 * alongside it — so a copy of this string held in `review_destinations` would be
 * a second source of truth with an expiry date on it. Computing it at read time
 * means the link follows the place id automatically.
 *
 * VERIFIED AGAINST LIVE GOOGLE DOCUMENTATION, not written from memory. Slice
 * A0's lesson: OpenAI had deprecated `max_tokens` in favour of
 * `max_completion_tokens` and the deprecated name is exactly what a from-memory
 * implementation writes — it would have failed only in production. The same
 * risk applies here, and the failure is worse in kind: a button that 404s at the
 * one moment a customer is willing to leave a review.
 *
 * Verified 2026-08-01 against
 * https://support.google.com/business/answer/16816815 (Google Business Profile
 * Help, "Create a Google link or QR code to request reviews") and
 * https://developers.google.com/maps/documentation/places/web-service/maps-links
 * (Places API, "Link to Google Maps" — `googleMapsLinks.writeAReviewUri`).
 *
 * ⚠️ WHAT VERIFICATION ACTUALLY FOUND, AND WHY THIS FORM IS STILL THE RIGHT ONE.
 * Neither current live page publishes `search.google.com/local/writereview?
 * placeid=` as a documented template. The Help Center generates a link only
 * through the dashboard's "Get more reviews → Share review form" flow and does
 * not disclose what the copied URL looks like. The Places API (New) now offers
 * a *different*, official mechanism — `Place.googleMapsLinks.writeAReviewUri`,
 * returned by a billed Place Details (New) call in the opaque, CID-encoded form
 * `https://www.google.com/maps/place//data=!4m3!3m2!1s<hex>!12e1` — which does
 * not embed the place id as a readable substring at all.
 *
 * That form is incompatible with this class's contract on two counts: it needs
 * a network call this class must never make (no outbound HTTP outside the
 * gateway and the registered vendor clients), and it cannot be produced from a
 * bare place id string, which is what every caller here actually has. The
 * `?placeid=` form remains independently confirmed as live and functioning in
 * 2026 by Google's own support-community threads (e.g.
 * https://support.google.com/business/thread/132806909) and by the review
 * industry's current tooling (Podium, ReviewTrackers, EmbedSocial), all dated
 * 2026 and describing exactly this template. It is the only form that is both
 * currently working and derivable from a place id with no HTTP call — so it is
 * what this class builds, with the gap between "confirmed working" and
 * "currently documented as the primary path" recorded here rather than papered
 * over. See docs/DECISIONS.md, decision 313.
 */
final class GoogleReviewLink
{
    /**
     * @throws InvalidArgumentException when there is no place id to build from.
     */
    public static function forPlaceId(string $placeId): string
    {
        $placeId = trim($placeId);

        if ($placeId === '') {
            throw new InvalidArgumentException(
                'A Google review link cannot be built without a place id. The link is '
                .'derived rather than stored, so an empty id produces a URL that goes '
                .'nowhere — and it goes nowhere on a page shown to a customer.',
            );
        }

        return 'https://search.google.com/local/writereview?placeid='.rawurlencode($placeId);
    }
}
