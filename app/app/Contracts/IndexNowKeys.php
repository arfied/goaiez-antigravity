<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Location;
use App\Services\Indexing\IndexNowKeyOutcome;

/**
 * Where a location's IndexNow key file lives — or why it does not exist.
 *
 * ⛔ **THE SEAM EXISTS BECAUSE THE KEY FILE IS THE ONE THING CORE REST CANNOT
 * REACH** (decision 5581). IndexNow verifies ownership by fetching a text file
 * from the tenant's own host, and *"the location of a key file determines the
 * set of URLs that can be included with this key"* — so a key uploaded through
 * WordPress's REST media endpoint lands under `/wp-content/uploads/` and can
 * submit uploads and nothing else. Serving `/{key}.txt` from the site root is
 * the WordPress plugin's job (F2), and this interface is the shape of the
 * question F2 will answer.
 *
 * ⚠️ **ONE IMPLEMENTATION TODAY AND IT ALWAYS REFUSES.** That is deliberate and
 * it is checked: a lint asserts the container still resolves
 * `App\Services\Indexing\UnhostedIndexNowKeys`, so a later slice that binds a
 * real provider has to say so out loud rather than silently switching on a
 * submitter that posts a key nothing serves — which IndexNow answers with a 403
 * and which would look, from the rows, exactly like a vendor outage.
 */
interface IndexNowKeys
{
    public function for(Location $location): IndexNowKeyOutcome;
}
