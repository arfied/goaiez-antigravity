<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Contracts\IndexNowKeys;
use App\Enums\IndexingRefusal;
use App\Models\Location;
use App\Services\Actuation\WordPress\WordPressCredentials;

/**
 * The only `IndexNowKeys` this build has: nothing serves a key file, and this
 * class says which of the three reasons applies.
 *
 * ⛔ **THE ANSWER IS ALWAYS A REFUSAL AND THAT IS THE HONEST STATE OF THE
 * PRODUCT**, not a placeholder. IndexNow verifies ownership by fetching a file
 * from the tenant's own host; the only thing this platform can put on a tenant's
 * WordPress today is a post, a page or a media upload over core REST, and a key
 * under `/wp-content/uploads/` binds to that directory (5581). The plugin that
 * can serve the root is F2.
 *
 * ## The three arms, and why the middle one is not a tier lookup
 *
 * ⚠️ **THIS ASKS {@see WordPressCredentials::isConnected()} RATHER THAN
 * `ActuationTiers::for()`, AND THE DIFFERENCE IS A REAL FINDING** (5686).
 * `BUILD-PLAN` §2.11.3 describes IndexNow as *"T1-only"*, but
 * `ActuationTiers::for()` **cannot return T1 at all** — it returns null, T3 or
 * T4, and its own docblock records why (5541: there was no credential store when
 * slice B was written). Slice F1 then built the store and did not revisit the
 * derivation, so routing this class on the tier would have produced a branch no
 * test could ever reach — 256's shape, in the guard that decides whether we post
 * a secret to six search engines.
 *
 * What actually matters here is narrower than a tier anyway: *can this platform
 * put a file on that host*. A live WordPress connection is the only thing that
 * can ever make that true, so it is the thing this asks.
 */
final class UnhostedIndexNowKeys implements IndexNowKeys
{
    public function __construct(private readonly WordPressCredentials $credentials) {}

    public function for(Location $location): IndexNowKeyOutcome
    {
        if ($location->website_url === null || $location->website_confirmed_at === null) {
            return IndexNowKeyOutcome::missing(IndexingRefusal::WebsiteNotConfirmed);
        }

        if (! $this->credentials->isConnected($location)) {
            // Permanent for this location until it connects: nothing can write a
            // file to a site we only observe through the pixel.
            return IndexNowKeyOutcome::missing(IndexingRefusal::NoWriteAccessToTheHost);
        }

        // Connected, and still nothing serves `/{key}.txt`. F2's.
        return IndexNowKeyOutcome::missing(IndexingRefusal::KeyFileUnavailable);
    }
}
