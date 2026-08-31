<?php

declare(strict_types=1);

namespace App\Http\Controllers\Actuation;

use App\Enums\T3PayloadRefusal;
use App\Http\Controllers\Controller;
use App\Services\Actuation\T3Payload;
use App\Services\Actuation\T3Payloads;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * The two public addresses of T3 injection — `BUILD-PLAN` §2.11.3 slice I.
 *
 * `PixelBundleController`'s shape exactly: public, unauthenticated, thin, and
 * deciding nothing. The caller is a `<script>` tag on a stranger's page.
 *
 * ---------------------------------------------------------------------------
 * ⛔ NO USER-AGENT BRANCHING, ANYWHERE, AND THAT IS A BUILD-FAILING GATE
 * ---------------------------------------------------------------------------
 * `29` §12.1 and §19.7: **no cloaking**. This controller never reads the
 * `User-Agent` header, never asks whether the caller is a crawler, and sends no
 * `Vary: User-Agent` — so the bytes Googlebot receives are the bytes a person
 * receives, and a test asserts exactly that byte-for-byte with two different
 * agents. A lint over this directory and over the client module refuses the
 * header by name, because *"we could serve the crawler the schema and skip the
 * work for everybody else"* is the single most tempting optimisation available
 * at this tier and it is fraud.
 *
 * ⚠️ **ONE RESPONSE SHAPE FOR EVERY REFUSAL.** Unknown key, health-information
 * tenant, paused account, suspended account, nothing live — all five answer the
 * empty payload, with the same `ETag`. A refusal a caller can tell apart is a
 * refusal that discloses something about a business to anybody who can read a
 * page's source, and `data_classification` is the worst of the five to leak.
 * The distinctions live in {@see T3PayloadRefusal}, where the tests
 * drive them.
 */
final class T3InjectionController extends Controller
{
    /**
     * ⚠️ **SHORT, AND THE ROLLBACK PROMISE IS WHY.** Rollback at T3 is *stop
     * serving* ({@see T3Payload}), so a cached payload is the one thing that can
     * keep a reverted change on a page — the cache lifetime is the upper bound
     * on how long an undo takes to be true for a new visitor. A minute is long
     * enough to absorb a page's own repeat views and short enough that nobody
     * has to explain it.
     */
    private const string PAYLOAD_CACHE = 'public, max-age=60';

    /**
     * The module changes only when we deploy, so it caches like an asset. It is
     * deliberately shorter than the pixel's immutable artefact because this
     * route has **no content-addressed sibling and no canary** — see
     * {@see self::module()}.
     */
    private const string MODULE_CACHE = 'public, max-age=300';

    /**
     * `GET /api/site/{key}` — the typed change-set payload for one public key.
     *
     * ⚠️ **`Access-Control-Allow-Origin: *`, DELIBERATELY.** The module runs on
     * the tenant's own domain and has to *read* this response, which the pixel's
     * own beacon never does — so this is the first cross-origin **read** in the
     * pixel family and it needs the header. What it exposes is content this
     * platform is publishing onto that tenant's public pages under their own
     * name: it is public by purpose, not merely by accident. No credentials are
     * accepted and none are sent, so the wildcard grants a reader nothing a page
     * view would not.
     */
    public function payload(string $key, Request $request, T3Payloads $payloads): Response
    {
        $payload = $payloads->forKey($key);

        if (! $payload instanceof T3Payload) {
            $payload = new T3Payload([]);
        }

        $response = response($payload->json(), 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => self::PAYLOAD_CACHE,
            'Access-Control-Allow-Origin' => '*',
        ]);

        // The payload version — the T3 snapshot — doing the job an ETag is for.
        // ⚠️ THE ONLY PLACE THIS RESPONSE READS THE REQUEST, and it reads exactly
        // one header: `If-None-Match`. It cannot become a branch on anything
        // else, because a 304 carries no body to differ.
        $response->setEtag($payload->version());
        $response->isNotModified($request);

        return $response;
    }

    /**
     * `GET /s/{key}.js` — the injection module, for tenants that have something
     * live.
     *
     * ⛔ **THE CODE ITSELF IS WITHHELD FROM EVERYBODY ELSE, AND THAT IS §2.11.5
     * CONFLICT 4's WHOLE POINT.** An empty body is served to a tenant with no
     * live T3 change sets, to a paused or suspended one, to a health-information
     * tenant and to an unknown key — so DOM-writing code reaches a visitor's
     * browser only where this platform is actually injecting something.
     *
     * ⚠️ **THE BYTES ARE THE SAME FOR EVERY TENANT WHO GETS THEM.** The key in
     * the URL selects *whether* to serve the module, never *what* it says: a
     * per-tenant JavaScript build would be this endpoint generating code from
     * data, which is the injection surface the typed payload exists to avoid.
     *
     * ⚠️ **NO CONTENT-ADDRESSED VERSION AND NO CANARY, UNLIKE THE PIXEL.** §10
     * gives the bundle both because it runs on every page of every tenant; this
     * runs on the pages of tenants with live change sets, of which there are
     * none until slice D publishes one. Whoever turns this on for a real site
     * should read `PixelDelivery` first and decide whether it has grown into
     * something that needs the same treatment — the answer today is no, and the
     * reason is the population rather than the risk.
     */
    public function module(string $key, T3Payloads $payloads, Vite $vite): Response
    {
        $payload = $payloads->forKey($key);

        if (! $payload instanceof T3Payload) {
            return $this->script('');
        }

        return $this->script($this->source($vite));
    }

    private function script(string $contents): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => self::MODULE_CACHE,
        ]);
    }

    /**
     * The built module, falling back to the source when there is no build.
     *
     * `PixelDelivery::builtSource()`'s reasoning, verbatim: `Vite::content()`
     * throws when nothing has been built, which is every test run before
     * `npm run build`, and the fallback keeps the route answering rather than
     * putting our stack trace where a customer's website should be.
     */
    private function source(Vite $vite): string
    {
        try {
            return $vite->content('resources/js/actuate.js');
        } catch (Throwable) {
            $path = resource_path('js/actuate.js');
            $contents = is_file($path) ? file_get_contents($path) : false;

            return $contents === false ? '' : $contents;
        }
    }
}
