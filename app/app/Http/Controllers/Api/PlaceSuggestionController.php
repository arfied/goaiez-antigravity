<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Contracts\PlacesClient;
use App\Exceptions\PlacesBudgetExhausted;
use App\Exceptions\PlacesRequestFailed;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuggestPlacesRequest;
use App\Http\Resources\PlaceSuggestionResource;
use Illuminate\Http\JsonResponse;

/**
 * The server-side half of the audit input's autocomplete (decision 195).
 *
 * PROXIED RATHER THAN CALLED FROM THE BROWSER, which is decision 195 in one
 * sentence: Google's client-side Maps JS widget fires on every keystroke
 * *before* the consent banner has resolved, and a third-party call made before
 * consent is exactly what `29` §12.1's consent tests fail the build over. It
 * also keeps the API key on the server, where a key restricted to our server IPs
 * can stay restricted.
 *
 * POST FOR WHAT IS ARGUABLY A READ, deliberately. A GET would put the business
 * name a stranger is typing into the query string, and from there into access
 * logs, Referer headers and any analytics on the page. `MagicLinkController`
 * makes the same argument about its token. It also stops a CDN caching
 * per-visitor queries, which is not something we want cached anywhere but here.
 *
 * NEVER FAILS LOUDLY. Every path returns 200 with a list, empty if need be. A
 * dropdown is an assistive flourish on an input the visitor can already use, so
 * a budget ceiling, a Google outage or a malformed response should all end as
 * "no suggestions" and never as an error on a marketing page. The audit itself
 * is unaffected either way: submitting runs a Text Search regardless of whether
 * anything was ever suggested.
 */
final class PlaceSuggestionController extends Controller
{
    public function __invoke(SuggestPlacesRequest $request, PlacesClient $places): JsonResponse
    {
        try {
            $suggestions = $places->autocomplete($request->searchQuery());
        } catch (PlacesBudgetExhausted|PlacesRequestFailed) {
            // Already logged by VendorLog inside the client. Swallowed here
            // rather than rethrown — see the class docblock.
            //
            // ⛔ **`PlacesBudgetExhausted` WAS MISSING AND EVERY SIBLING CALLER
            // CATCHES IT** (9146). `PlaceResolver::searchByName()` and
            // `AuditContextBuilder` both take the two-arm catch; this one took
            // one. It is unreachable *today* only because
            // `GooglePlacesClient::autocomplete()` answers `[]` on an exhausted
            // budget rather than throwing — a property of one method's body,
            // one refactor away from a 500 on the marketing home, and the class
            // docblock above promises the opposite in bold: **NEVER FAILS
            // LOUDLY. Every path returns 200 with a list.** The interface itself
            // declares `@throws PlacesBudgetExhausted` on the other three
            // methods, so the shape is expected here.
            //
            // ⚠️ **A CATCH WITH NO REACHABLE ARM IS NOT 256's VACUITY**, and the
            // distinction is worth stating because this codebase fails a lint
            // that matches nothing. 256 is about a *lint* that can never report;
            // this is a *guard*, and a guard whose arm nothing reaches today is
            // the ordinary shape of defence in depth. What is not permitted is
            // claiming it is tested: nothing here can drive it, and the honest
            // record is this paragraph plus the sibling that can.
            $suggestions = [];
        }

        return response()->json([
            'suggestions' => PlaceSuggestionResource::collection($suggestions)->resolve(),
        ]);
    }
}
