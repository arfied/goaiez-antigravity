<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Plugin;
use App\Services\Widgets\WidgetPlugins;
use App\Support\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Establishes the tenant for the widget feed from a public embed key.
 *
 * `ResolveFeedbackPage`'s shape, one public surface over (`17` FPR-05). The
 * request arrives from an anonymous browser on somebody else's website with no
 * session and no token, so the only thing that can identify a tenant is the
 * opaque UUID in the URL — and the query that reads it is therefore the one
 * query in this codebase that runs with no tenant set. `WidgetPlugins::
 * resolve()` is where that happens and why.
 *
 * ⚠️ UNCONDITIONALLY CLEARS FIRST. The tenant lives in a PostgreSQL *session*
 * variable that outlives a request whenever a connection is reused, so an early
 * return without clearing would let this request inherit a tenant it has no
 * claim to. `ResolveFeedbackPage` states the same invariant for the same reason,
 * and it matters more here: a signed-in owner's browser could hit a competitor's
 * embedded feed on a third-party page and arrive carrying their own tenant.
 *
 * NO MISS LIMITER, unlike the feedback page. That one counts failed slug
 * resolutions because its slugs carry a readable business-name stem and are
 * therefore guessable in principle. An `embed_key` is a random UUID — 122 bits
 * of entropy, nothing derived from tenant data — so enumeration is not a threat
 * model a counter improves, and the route carries an ordinary per-key limit
 * instead.
 */
final class ResolveWidget
{
    /** Request attribute key, so the controller and this file cannot disagree. */
    public const string PLUGIN = 'widget_plugin';

    public function __construct(
        private readonly WidgetPlugins $plugins,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        Tenancy::forgetAll();

        $key = $request->route('embed_key');

        $plugin = is_string($key) ? $this->plugins->resolve($key) : null;

        if (! $plugin instanceof Plugin) {
            abort(404);
        }

        Tenancy::set($plugin->business_id);

        $request->attributes->set(self::PLUGIN, $plugin);

        return $next($request);
    }
}
