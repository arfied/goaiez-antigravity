<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Throwable;

/**
 * The review widget bundle, at one address that never changes.
 *
 * ⚠️ **A STABLE URL WITH AN IMMUTABLE BUILD HASH BEHIND IT** (`41` §3.2). The
 * alternative — putting `Vite::asset()` into the snippet — would bake a hashed
 * filename into a `<script>` tag on a website we do not control and cannot edit,
 * so the next `npm run build` would break every install in production at once,
 * silently, on somebody else's page. So the address is fixed and the *contents*
 * move; this controller is the indirection that makes that true.
 *
 * ⚠️ **THE MANIFEST IS THE SOURCE, WITH THE SOURCE FILE AS THE FALLBACK.**
 * `Vite::content()` throws when there is no build — which is every test run and
 * every checkout before `npm run build` — and a 500 on a public asset route
 * would make the whole suite depend on a Node toolchain having run. Falling back
 * to `resources/js/widget.js` serves the same behaviour unminified, and a lint
 * keeps that file free of `import`/`export` so the two are interchangeable.
 *
 * ⚠️ **NO SUBRESOURCE INTEGRITY ON THE SNIPPET, AND THAT IS FORCED RATHER THAN
 * OVERLOOKED.** An `integrity=` hash pins one exact body, and the entire design
 * above is that the body changes behind a fixed URL — so an SRI attribute would
 * turn every deploy into a widget that refuses to load on every tenant's site
 * until each of them edits a line by hand. The trade SRI protects against is a
 * compromised third-party CDN; this file is served by this application, from its
 * own origin, so there is no third party in the path to distrust.
 *
 * ⚠️ **PUBLIC AND UNAUTHENTICATED, WITH NO TENANT AND NO KEY IN THE URL.** The
 * bundle is identical for every tenant — the key travels in the `data-key`
 * attribute and is read by the script at run time — so this route touches no
 * database, resolves no tenant and discloses nothing. Putting the key in the
 * path instead would make one static file into a per-tenant cache entry for no
 * gain whatsoever.
 */
final class WidgetScriptController extends Controller
{
    /**
     * Five minutes, not a year, and the short end is the deliberate one.
     *
     * The URL is stable and the content is not, which is the exact inverse of
     * the case immutable caching is designed for: a bad bundle behind a
     * year-long `max-age` is stranded in every visitor's browser on every
     * tenant's website, with no way to reach it. Five minutes keeps the request
     * cost trivial and keeps a rollback effective within a coffee break.
     */
    private const int CACHE_SECONDS = 300;

    public function __invoke(Request $request, Vite $vite): Response
    {
        return response($this->bundle($vite), 200, [
            'Content-Type' => 'application/javascript; charset=utf-8',
            'Cache-Control' => 'public, max-age='.self::CACHE_SECONDS,

            // It is a script for anyone's page by definition — this is the one
            // response in the application where a wildcard is the answer rather
            // than an oversight. The *feed* it calls is where the domain list
            // and the tenant boundary live.
            'Access-Control-Allow-Origin' => '*',

            // Belt and braces on a route that returns a file from disk: a
            // browser that sniffed this as HTML would execute it as a document
            // on our own origin.
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function bundle(Vite $vite): string
    {
        try {
            return $vite->content('resources/js/widget.js');
        } catch (Throwable) {
            $source = resource_path('js/widget.js');
            $contents = is_file($source) ? file_get_contents($source) : false;

            // An empty body rather than an exception. A missing bundle is our
            // deployment being wrong, and the visible consequence on a
            // stranger's website should be that nothing appears — never our
            // stack trace on a business's homepage.
            return $contents === false ? '' : $contents;
        }
    }
}
