<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveWidget;
use App\Http\Resources\WidgetReviewResource;
use App\Models\Plugin;
use App\Models\Review;
use App\Services\Widgets\WidgetInstalls;
use App\Services\Widgets\WidgetPlugins;
use App\Support\Tenancy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * The public review feed (`17` FPR-05) — `GET /api/widget/{embed_key}/reviews`.
 *
 * The server half of FPR-06. ⚠️ **The script that would consume this does not
 * exist**: `BUILD-PLAN` §2.6.4 conflict 3 defers the embeddable bundle, because
 * it is the same problem as the pixel — versioning, a CDN, a size budget — that
 * decision 89 pushed out. What ships is the half that is server-side, testable,
 * and the useful one.
 *
 * ---------------------------------------------------------------------------
 * THREE FILTERS, AND THEY ARE NOT INTERCHANGEABLE
 * ---------------------------------------------------------------------------
 *   1. `Review::displayable()` — the **moderation** gate. Approved or displayed,
 *      never flagged, and for a first-party review actually analysed (decisions
 *      345, 358). Slice D and E own it and this slice does not touch it.
 *   2. `display_on_website` — the **per-review switch** the owner sets by
 *      approving. Dead until slice G gave it a writer; see ReviewDisplay.
 *   3. `min_stars_to_show` — the **owner's filter**, defaulting to 1 so nothing
 *      is suppressed until somebody deliberately raises it.
 *
 * Three conditions rather than one composite scope, so that a change to any of
 * them cannot silently widen the others — decision 345's reasoning, which is
 * why display fails closed while routing fails open.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ TWO GATES THAT ARE EASY TO CONFUSE, AND THE WIDGET NEEDS BOTH
 * ---------------------------------------------------------------------------
 * `allowed_domains` is what makes *us* willing to serve. **CORS is what makes
 * the browser willing to let the embedding page read what we served**, and it
 * is not configured here — it comes from Laravel's default `api/*` +
 * `allowed_origins: ['*']`, because this repository publishes no
 * `config/cors.php`. Failing either gate produces an identical empty widget and
 * only one of them appears in our logs, so `WidgetFeedTest` asserts the CORS
 * header explicitly: publishing a cors config to tighten some *other* endpoint
 * would otherwise break every embedded widget in production silently.
 *
 * ---------------------------------------------------------------------------
 * ⚠️ NO AGGREGATE RATING IS EMITTED, AND THAT IS A COMPLIANCE DECISION
 * ---------------------------------------------------------------------------
 * `29` §2 and SEO-01 make "never a filtered or 5-star-only aggregate" a
 * build-failing test. A widget that returns an average alongside a
 * `min_stars_to_show`-filtered list is one careless line away from computing
 * that average over the filtered set — which is precisely the forbidden number,
 * arrived at by accident. With no consumer built yet there is nothing to be
 * gained by shipping the field and a real way to be wrong, so the response
 * carries a list and nothing else. A test asserts no average or count of any
 * kind appears in the payload.
 */
final class WidgetReviewController extends Controller
{
    /**
     * How many reviews one response carries.
     *
     * A widget renders a strip or a wall, not an archive, and this is an
     * uncached-worst-case query on a public endpoint. Fixed rather than
     * caller-controlled: a `?limit=` would let an anonymous request choose how
     * much work we do.
     */
    private const int MAX_REVIEWS = 20;

    /**
     * ⚠️ TTL-ONLY, WITH NO INVALIDATION ON APPROVAL, ON PURPOSE.
     *
     * FPR-05 asks for "under 250 ms cached". Sixty seconds gets that without
     * coupling `ReviewDisplay` to this cache — and the cost is bounded and
     * boring: an owner who approves a review waits up to a minute to see it on
     * their site. Wiring invalidation in would make every future writer of
     * review state responsible for remembering a cache key in another module,
     * which is the kind of coupling that gets forgotten exactly once and then
     * serves stale data nobody can explain.
     */
    private const int CACHE_SECONDS = 60;

    public function __invoke(Request $request, WidgetPlugins $plugins, WidgetInstalls $installs): JsonResponse
    {
        $plugin = $this->plugin($request);

        // ⚠️ THE ORIGIN CHECK IS NOT AN ACCESS CONTROL — see
        // WidgetPlugins::originIsAllowed(). It stops a *browser* on an unlisted
        // site rendering this feed; it stops nothing else, because `Origin` is
        // trivially set by anything that is not a browser. The contents are
        // therefore safe to be public regardless, and they are: reviews the
        // owner has approved for publication on their own website.
        //
        // 403 rather than 404. The key was valid — saying so leaks nothing a
        // holder of the key does not already know, and a 404 here would send
        // whoever is installing the widget hunting for a wrong key instead of a
        // missing domain.
        if (! $plugins->originIsAllowed($plugin, $request->headers->get('Origin'))) {
            return response()->json([
                'message' => 'This widget is not enabled for that domain.',
            ], 403);
        }

        // ⚠️ **THIS REQUEST IS THE INSTALL VERIFICATION** (3080). `41` §3.3 asks
        // for a "mark installed" beacon; none is needed, because a widget that
        // got this far has proved the entire chain — the line is on the page, a
        // browser executed it, the key resolved, and the origin is one the owner
        // named. Recording it costs nothing on the wire and adds not one byte to
        // the bundle, which is what keeps 2961 and the 14 KB budget out of it.
        //
        // ⚠️ **BEFORE THE CACHE, AND NO TEST PROVES THAT MATTERS TODAY** (352,
        // 397 — a claim the suite does not make is worse than none). With
        // `CACHE_SECONDS` at 60 and the recorder's own throttle at 900, the feed
        // cache has always expired by the time a sighting is due, so the
        // ordering has no observable consequence and nothing here asserts one.
        // It is written this way so that raising `CACHE_SECONDS` past the
        // throttle later cannot quietly stop verification on exactly the busy
        // sites where the cache does its work.
        //
        // It re-checks the allowlist itself rather than relying on the refusal
        // above (398, 3090), so the 403 is not the only thing standing between a
        // forged `Origin` and a row.
        $installs->record($plugin, $request->headers->get('Origin'));

        $reviews = Cache::remember(
            $this->cacheKey($plugin),
            self::CACHE_SECONDS,
            fn (): array => $this->reviews($plugin),
        );

        return response()->json(['data' => $reviews]);
    }

    /**
     * The displayable reviews this feed serves, newest first.
     *
     * ⚠️ RETURNS A PLAIN ARRAY RATHER THAN MODELS, because the result goes into
     * the cache. Caching Eloquent models serialises them whole — every column,
     * including the customer id, the moderation flags and the raw payload — into
     * a store that is not tenant-partitioned by anything except the key. The
     * resource has already reduced this to four public fields by the time it is
     * written, so what sits in Redis is what a visitor may see.
     *
     * @return list<array<string, mixed>>
     */
    private function reviews(Plugin $plugin): array
    {
        $query = Review::query()
            ->displayable()
            ->where('display_on_website', true)
            ->where('rating', '>=', $plugin->min_stars_to_show);

        // A plugin may be scoped to one location or stand for the whole
        // business — `plugins.location_id` is nullable, and a single-location
        // tenant is the common case either way.
        if ($plugin->location_id !== null) {
            $query->where('location_id', $plugin->location_id);
        }

        /** @var list<array<string, mixed>> $rows */
        $rows = WidgetReviewResource::collection(
            $query
                // `id`, never `created_at`: Postgres sorts NULL first on a DESC
                // order by and 40 of 43 tables carry a nullable `created_at`.
                // Slice A's lint fails the build on anything else.
                ->orderByDesc('id')
                ->limit(self::MAX_REVIEWS)
                ->get()
        )->resolve();

        return $rows;
    }

    /**
     * A cache key that cannot serve one tenant's reviews to another.
     *
     * `Tenancy::idOrFail()` is in the key rather than only the plugin id, and
     * that is belt-and-braces on purpose: the plugin id alone would be
     * sufficient and correct today, and a key that carries the tenant stays
     * correct if anything ever changes how plugins are identified. A cache is
     * the one store no global scope and no RLS policy reaches, so it is the
     * place to be paranoid rather than clever.
     *
     * `min_stars_to_show` is in the key too — raising it must not serve the
     * looser list from before the change for another minute, because that is
     * the setting with a legal dimension.
     */
    private function cacheKey(Plugin $plugin): string
    {
        return sprintf(
            'widget:%d:%d:min%d:reviews',
            Tenancy::idOrFail(),
            (int) $plugin->id,
            $plugin->min_stars_to_show,
        );
    }

    /**
     * The plugin ResolveWidget put on the request.
     *
     * Typed rather than read inline, because the attribute bag returns mixed and
     * Larastan level 8 is right to insist. Unreachable while the middleware is
     * attached; it exists so detaching it fails loudly rather than serving a
     * feed with no tenant.
     */
    private function plugin(Request $request): Plugin
    {
        $plugin = $request->attributes->get(ResolveWidget::PLUGIN);

        return $plugin instanceof Plugin
            ? $plugin
            : throw new RuntimeException('ResolveWidget did not run on this route.');
    }
}
