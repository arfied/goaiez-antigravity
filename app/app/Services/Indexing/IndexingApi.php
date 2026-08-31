<?php

declare(strict_types=1);

namespace App\Services\Indexing;

use App\Enums\IndexingEngine;
use App\Enums\IndexingMethod;
use App\Enums\IndexingRefusal;
use App\Models\Location;
use App\Support\PlatformCredentials;

/**
 * Google's Indexing API — built dark, and its guard is the built part.
 *
 * ## The gate this class exists for
 *
 * ⛔ **`29` §11.2 ROW 9: *"no non-JobPosting URL reaches the Indexing API"*, AND
 * IT IS GOOGLE'S OWN RESTRICTION RATHER THAN A POLICY OF OURS**: *"The Indexing
 * API can only be used to crawl pages with either JobPosting or BroadcastEvent
 * embedded in a VideoObject"*, published beside *"Our spam policies apply to
 * content submitted with the Indexing API"* and *"Don't circumvent our
 * submission limits"*
 * (`developers.google.com/search/apis/indexing-api/v3/using-api`, last updated
 * 2026-07-16 UTC, fetched 2026-08-20). Submitting an ordinary page is not a
 * wasted call, it is abuse of an API whose remedy is revoked access — for every
 * tenant at once, because the service account would be ours.
 *
 * ⛔ **THE MARKUP CHECK RUNS FIRST AND THE CREDENTIAL CHECK SECOND, AND THE
 * ORDER IS THE WHOLE TEST DESIGN** (398). No deployment has a service account,
 * so a credential-first ordering would refuse every call for the credential's
 * sake and leave the gate unfalsifiable — a build-failing test that passes
 * because something upstream already said no. Ordered this way, planting a
 * credential lets the gate be driven with every outer condition green, and
 * deleting the markup check turns that test red.
 *
 * ## What "dark" means here, precisely
 *
 * ⚠️ **THE REQUEST-MAKING HALF IS NOT WRITTEN, AND THAT IS A DECISION RATHER
 * THAN AN OMISSION** (5684). Three things would each have to be true before a
 * single byte could leave: a Google service account added as a **site owner** in
 * Search Console, approval and quota granted by Google (*"a default 200 quota
 * for API onboarding and submission testing"*), and a page of one of the two
 * accepted kinds — and **no table in this schema holds a job opening at all**
 * (`BUILD-PLAN` §2.11.1). Writing a service-account JWT flow and an HTTP client
 * against `https://indexing.googleapis.com/v3/urlNotifications:publish` today
 * would add a vendor host to `SUBPROCESSOR-INVENTORY.md` for a flow that cannot
 * exist, which §0 of that document calls *"wrong in the direction that matters
 * legally"*. What is owed, and to whom, is in the decision log.
 *
 * ⚠️ **THE SHAPE IS RECORDED SO THE NEXT SLICE DOES NOT RE-DERIVE IT**: a POST
 * to `.../v3/urlNotifications:publish` with `Content-Type: application/json`
 * and a body of exactly two required fields, `url` (*"the fully-qualified
 * location of the item"*) and `type` (`URL_UPDATED` or `URL_DELETED`); one URL
 * per body, up to 100 combined in a batch, and *"Quota is counted at the URL
 * level"*. All of that is quoted from the page above and none of it is written
 * as code, because a constant nothing reads is a claim nothing checks.
 */
final class IndexingApi
{
    /**
     * The platform credential this waits for. Declared in the manifest and in
     * `config/credentials.php`, unset on every deployment.
     */
    public const string CREDENTIAL = 'google_indexing_service_account';

    /**
     * Ask to announce one URL, and be told why not.
     *
     * ⚠️ **`$location` IS TAKEN AND NOT YET USED FOR ANYTHING BUT THE ROW IT
     * BELONGS TO**, because a real submission is authorised per Search Console
     * property and the property mapping is `SearchConsoleProperties`' — a
     * per-tenant fact this method will need the moment a transport exists.
     */
    public function submit(Location $location, string $url, PageMarkup $markup): IndexingAttempt
    {
        $ineligible = $markup->refusal();

        if ($ineligible !== null) {
            return IndexingAttempt::refused(IndexingEngine::Google, IndexingMethod::IndexingApi, $ineligible);
        }

        if (! PlatformCredentials::has(self::CREDENTIAL)) {
            return IndexingAttempt::refused(
                IndexingEngine::Google,
                IndexingMethod::IndexingApi,
                IndexingRefusal::NoServiceAccount,
            );
        }

        // ⛔ REACHABLE ONLY WITH A PLANTED CREDENTIAL, AND IT IS A REFUSAL
        // RATHER THAN A THROW ON PURPOSE. An eligible page on a configured
        // deployment is an ordinary state of a half-built feature, and
        // `IndexingAttempt`'s rule is that an ordinary state does not fail a
        // queued job. It is also the assertion that proves the gate above let
        // something through, which is the other half of driving it red.
        return IndexingAttempt::refused(
            IndexingEngine::Google,
            IndexingMethod::IndexingApi,
            IndexingRefusal::TransportUnbuilt,
        );
    }
}
