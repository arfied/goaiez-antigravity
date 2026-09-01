<?php

declare(strict_types=1);

namespace App\Http\Controllers\Pixel;

use App\Http\Controllers\Controller;
use App\Models\PixelBundleVersion;
use App\Services\Pixel\PixelDelivery;
use Illuminate\Http\Response;
use RuntimeException;

/**
 * `GOAIEZ_PIXEL_MASTER_BUILD.md` §10's Delivery paragraph, the two routes:
 * *"immutable `/v/<sha>/p.js` (`max-age=31536000, immutable`); `/p.js` pointer
 * (`max-age=300, stale-while-revalidate=86400`)."*
 *
 * ⚠️ **PUBLIC AND UNAUTHENTICATED, `WidgetScriptController`'s shape exactly**:
 * the caller is a `<script async>` tag on a stranger's page, with no session and
 * no key in the URL — the pixel's own public key travels in `data-k`, read by
 * the bundle at run time, never here.
 *
 * ⛔ **THIS CONTROLLER DECIDES NOTHING ABOUT WHICH VERSION.** Both actions ask
 * {@see PixelDelivery} and return exactly what it names — the canary coin flip,
 * the immutable-sha lookup, and every status rule live there, on
 * `PixelIngestController`'s own precedent for keeping a route thin.
 */
final class PixelBundleController extends Controller
{
    /** `/p.js`'s pointer, §10's own figure. */
    private const string POINTER_CACHE = 'public, max-age=300, stale-while-revalidate=86400';

    /** `/v/<sha>/p.js`'s immutable route, §10's own figure. */
    private const string VERSIONED_CACHE = 'public, max-age=31536000, immutable';

    /**
     * `GET /p.js` — the address every install snippet names.
     *
     * ⚠️ **AN EMPTY 200, NEVER A 500, WHEN NOTHING HAS BEEN PUBLISHED.**
     * `WidgetScriptController::bundle()`'s own reasoning: the visible
     * consequence of our deployment being wrong should be that nothing appears
     * on a stranger's page, never our stack trace.
     */
    public function pointer(PixelDelivery $delivery): Response
    {
        try {
            $version = $delivery->choose();
        } catch (RuntimeException) {
            // R245: Served with no-store and a non-empty body so caches do not hold an empty bundle.
            return $this->script('/* unpublished */', 'no-store');
        }

        return $this->script($version->contents, self::POINTER_CACHE);
    }

    /**
     * `GET /v/{sha}/p.js` — one exact, immutable artefact.
     *
     * Serves a rolled-back or retired version too — see
     * {@see PixelDelivery::versionForSha()}'s docblock for why that is the
     * point of a content-addressed route rather than an oversight.
     */
    public function versioned(string $sha, PixelDelivery $delivery): Response
    {
        $version = $delivery->versionForSha($sha);

        if (! $version instanceof PixelBundleVersion) {
            return response('', 404);
        }

        return $this->script($version->contents, self::VERSIONED_CACHE);
    }

    private function script(string $contents, string $cacheControl): Response
    {
        return response($contents, 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => $cacheControl,

            // A script for anyone's page by definition — `WidgetScriptController`'s
            // one deliberate wildcard, for the same reason: the tenant boundary
            // lives at the collector this bundle calls, never at the address it
            // is served from.
            'Access-Control-Allow-Origin' => '*',

            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
