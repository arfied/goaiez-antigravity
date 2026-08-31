<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StorePixelBatchRequest;
use App\Services\Pixel\PixelCollector;
use App\Services\Pixel\PixelEnrichment;
use Illuminate\Http\Response;

/**
 * `POST /api/pixel/e` — the collector's front door.
 *
 * ⚠️ **THE URI IS `/api/pixel/e` BECAUSE THE BUNDLE ALREADY SAYS SO, AND THE
 * BUNDLE SAYS SO IN ORDER TO BE CAUGHT** (decision 4576). `PixelTest`'s first
 * tripwire matches route URIs containing `pixel` or `ingest` and its own docblock
 * names its limit: *"a collector mounted at `/api/collect`, `/api/e`, or on a
 * subdomain … passes it in silence."* W2 chose the client's constant so that the
 * server half could not be mounted anywhere the tripwire cannot see. **That
 * tripwire is now reddened by this file, and it was replaced rather than deleted
 * — its own instruction.**
 *
 * ⛔ **AND SINCE 10061 THE BUNDLE READS THE STATUS CLASS, SO THIS FILE'S
 * *"ALWAYS 204"* IS NOW LOAD-BEARING FOR A TENANT'S DATA RATHER THAN ONLY FOR
 * THE ORACLE.** The bundle this platform ships treats a resolved response
 * **under 500** as final and discards the batch; it treats a rejection or a
 * `5xx` as *we do not know* and offers the same events again, up to its own
 * `SEND_ATTEMPTS`. That is
 * correct **because** every decision this endpoint makes — accepted, unknown
 * key, rule 24, a validation failure, a rate-limit refusal — is one `204`: a
 * refusal re-sent would earn the same refusal for ever, so retrying it would be
 * load with no chance of success.
 *
 * ⛔ **THE CONSEQUENCE IS THAT A `4xx` ADDED HERE WOULD SILENTLY DESTROY DATA.**
 * A branch answering `413`, `422` or `404` for a condition that is **transient
 * or ours** — a body over a limit, a key not yet propagated, a route missing
 * during a deploy — reads on a diff as being more informative and would tell
 * every bundle on every tenant's website to give up on those events
 * immediately. **If a path here ever needs to say *try again*, it says it with a
 * `5xx` or it says nothing.** `tests/Browser/PixelTransportFailureTest.php`
 * drives both arms against a real browser, and its `413` row exists for exactly
 * this reason.
 *
 * ⚠️ **THE BUNDLE IS NAMED BY DESCRIPTION AND NOT BY PATH, DELIBERATELY.**
 * `Architecture/PixelTest`'s *"exactly one file may deliver the pixel bundle"*
 * matches raw file contents across `app/`, `routes/` and `resources/views/`,
 * comments included, so spelling the path here reddens the build — 9914's
 * finding, one wave old, in the next file to write the sentence. Rewording
 * costs nothing and an exception would be 511's failure.
 *
 * ⚠️ **`PixelRateLimits::refusal()` IS THE ONE TO READ BEFORE CHANGING ANY OF
 * IT** — *"204, NOT 429"* — because a throttle is the single most tempting place
 * to start answering something else, and a `429` would be both the oracle that
 * file refuses **and** a retry storm this one would be inviting.
 *
 * ⛔ **THIS CONTROLLER DECIDES NOTHING.** Validation is
 * [[StorePixelBatchRequest]] and every gate is [[PixelCollector]]; what is left
 * here is the one thing §11 asks of the transport — *"Always 204"* — on every
 * path, including the refusals. A branch here that answered differently for one
 * outcome would be the key-enumeration oracle
 * [[\App\Enums\PixelRefusal::UnknownKey]] exists to refuse.
 */
final class PixelIngestController extends Controller
{
    public function __invoke(StorePixelBatchRequest $request, PixelCollector $collector): Response
    {
        // ⚠️ **THE RETURN VALUE IS DISCARDED HERE AND THAT IS NOT A DROPPED
        // ERROR.** `receive()` answers with a named refusal or null, and the
        // refusal has already been recorded where somebody can see it — see
        // `PixelCollector::refuse()`. What the caller may not learn is *which*
        // outcome it got, because the caller is an anonymous browser and the
        // difference between "unknown key" and "accepted" is the whole of what an
        // attacker probing this endpoint wants.
        //
        // ⚠️ **`PixelEnrichment::fromRequest($request)` IS THE ONE PLACE THIS
        // CONTROLLER TOUCHES THE REQUEST'S ADDRESS, AND IT DOES SO INDIRECTLY —
        // decision 5000s.** This file's own source names no `->ip(`, no
        // `HashedIp`, nothing the raw-address lint forbids; the actual read
        // happens inside `PixelEnrichment::fromRequest()`, which discards it in
        // the same function scope. `$request` itself is never passed to the
        // collector or the job — only the already-computed enrichment is.
        $collector->receive(
            $request->batch(),
            $request->rawBody(),
            $request->headers->get('Origin'),
            PixelEnrichment::fromRequest($request),
            $request->headers->get('Sec-GPC'),
        );

        return response()->noContent();
    }
}
