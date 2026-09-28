<?php

declare(strict_types=1);

namespace App\Services\Widgets;

use App\Enums\WidgetInstallState;
use App\Models\Plugin;
use App\Models\WidgetInstall;
use App\Services\Config\DefaultsRegistry;
use App\Support\Tenancy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

/**
 * Whether a tenant's review widget is actually working on their website.
 *
 * ⚠️ **THE VERIFICATION IS THE REQUEST THE BUNDLE ALREADY MAKES** (3080).
 * `41` §3.3 asks for a "mark installed" beacon; this needs none, because an
 * installed widget already performs exactly one cross-origin `GET` for its feed,
 * `ResolveWidget` already resolves the tenant from the key in it, and
 * `WidgetPlugins::originIsAllowed()` already parses and matches the `Origin`
 * header. Every fact an install check needs is in a request we answer and then
 * throw away. **`resources/js/widget.js` is unchanged by this file's existence**,
 * so 2961's refusals are untouched and the 14 KB budget is unspent.
 *
 * ⛔ **WHY NOT A CRAWL OF THE TENANT'S SITE** (3081, 3082). It would have called
 * 2950's ten-day outage healthy — the snippet was pasted correctly and present
 * in the HTML the whole time, and the feed answered 403 to every browser in the
 * world. **A crawl verifies the paste; this verifies the product.** It also
 * cannot see a bundle injected at runtime by Wix, Squarespace, a Shopify theme
 * embed or a tag manager, which is most of `41` §3.3's own matrix, and building
 * one means giving a business-agnostic, pre-signup fetch gateway a
 * tenant-supplied URL — a request-forgery surface it was never designed to take.
 *
 * ⚠️ **WHAT IT CANNOT SEE, WHICH THE SCREEN SAYS OUT LOUD** (3084): a correct
 * install on a page nobody has visited. `WidgetInstallState::NotSeenYet` is
 * therefore *not seen*, never *not installed*.
 *
 * ⛔ **AND THIS IS EVIDENCE RATHER THAN PROOF** (3099). `originIsAllowed()` says
 * it plainly one file over: `Origin` is trivially forged by anything that is not
 * a browser, and the embed key is public because it ships in the page. So a
 * sighting means **our feed was asked for from a website the owner named** — not
 * that a widget is rendering there. The consequence is bounded (a forger can
 * only write a host the tenant already named, and discloses nothing by doing
 * it), but no screen may promote this to a guarantee.
 */
final class WidgetInstalls
{
    /**
     * `41` §3.3's own 72 hours — "not seen in 72h → one plain nudge".
     *
     * A PLATFORM setting is exactly an Ops edit, not a tenant's, and the owner's 2026-09-22 ruling supersedes 3092's scope.
     */
    public const int STALE_AFTER_HOURS = 72;

    /**
     * How often one host may refresh its record.
     *
     * ⚠️ **A PRIVACY PROPERTY BEFORE A COST ONE** (3086). The obvious reason is
     * that this sits on a public endpoint hit once per page view. The better one
     * is that a `last_seen_at` accurate to the second on a quiet site *is* a
     * visit log one row deep; at fifteen minutes it answers "is this working"
     * and cannot answer "when did somebody look".
     *
     * It also serialises the insert: the first request through the gate is the
     * only one that can create the row for fifteen minutes, so two simultaneous
     * first visits cannot race the unique index.
     */
    public const int THROTTLE_SECONDS = 900;

    public function __construct(
        private readonly WidgetPlugins $plugins,
        private readonly DefaultsRegistry $defaults,
    ) {}

    private function throttleSeconds(): int
    {
        return $this->defaults->int('widgets.install.throttle_seconds');
    }

    private function staleAfterHours(): int
    {
        return $this->defaults->int('widgets.install.stale_after_hours');
    }

    /**
     * Note that this feed was served to one of the tenant's own websites.
     *
     * ⚠️ **RE-CHECKS THE ALLOWLIST ITSELF RATHER THAN TRUSTING THE CALLER**
     * (3090). `WidgetReviewController` already refuses a disallowed origin with
     * a 403 before reaching here, and that is exactly the arrangement 398 warns
     * about: an outer guard doing the work makes the inner one unfalsifiable,
     * and deleting either would leave a green suite. The re-check costs one scan
     * of an array of at most twenty strings and buys a property that holds
     * however this is called — **a forged `Origin` writes nothing**, and the
     * table can hold at most one row per host the tenant themselves named.
     *
     * Silent on every refusal. This runs inside a public feed request whose job
     * is to render reviews on somebody's website; an install note that could
     * fail that request would be a screen breaking a homepage.
     */
    public function record(Plugin $plugin, ?string $origin): void
    {
        $host = WidgetPlugins::normaliseHost($origin);

        if ($host === null || ! $this->plugins->originIsAllowed($plugin, $origin)) {
            return;
        }

        if (! Cache::add($this->throttleKey($plugin, $host), true, $this->throttleSeconds())) {
            return;
        }

        $now = CarbonImmutable::now();

        $existing = WidgetInstall::query()
            ->where('plugin_id', $plugin->id)
            ->where('host', $host)
            ->first();

        if ($existing instanceof WidgetInstall) {
            // `first_seen_at` is deliberately not touched: an owner wants to see
            // that this has been up for months, not that it was up a minute ago.
            $existing->forceFill(['last_seen_at' => $now])->save();

            return;
        }

        // ⚠️ **AN EXPLICIT WRITE RATHER THAN `updateOrCreate()`**, because
        // `host` and `plugin_id` are deliberately not fillable — the one way
        // this table could come to hold a string a stranger chose is a mass
        // assignment from a public request. `SendingPause`'s release columns
        // take the same shape for the same reason. `business_id` is filled by
        // `BelongsToTenant` from the tenant `ResolveWidget` established.
        (new WidgetInstall)->forceFill([
            'plugin_id' => $plugin->id,
            'host' => $host,
            'first_seen_at' => $now,
            'last_seen_at' => $now,
        ])->save();
    }

    /**
     * What may honestly be said about this feed's installs (3091).
     *
     * Three states, two of them negative. `Stopped` is the one that earns the
     * feature and it exists only because the positive expires — a check that can
     * only ever say yes is worth nothing.
     */
    public function statusFor(Plugin $plugin): WidgetInstallStatus
    {
        /** @var list<WidgetInstall> $sightings */
        $sightings = WidgetInstall::query()
            ->where('plugin_id', $plugin->id)
            // Newest row first for display. `id`, never `last_seen_at`:
            // `ConventionsTest` fails the build on `orderByDesc` over anything
            // but `id`, and the freshest *time* is computed over the bounded set
            // in `WidgetInstallStatus::lastSeenAt()` instead.
            ->orderByDesc('id')
            ->get()
            ->all();

        $status = new WidgetInstallStatus(WidgetInstallState::NotSeenYet, $sightings);

        $latest = $status->lastSeenAt();

        if ($latest === null) {
            return new WidgetInstallStatus(WidgetInstallState::NotSeenYet);
        }

        return new WidgetInstallStatus(
            $latest->greaterThanOrEqualTo(CarbonImmutable::now()->subHours($this->staleAfterHours()))
                ? WidgetInstallState::Working
                : WidgetInstallState::Stopped,
            $sightings,
        );
    }

    /**
     * The throttle key.
     *
     * The tenant is in it as well as the plugin id, which is
     * `WidgetReviewController::cacheKey()`'s reasoning verbatim: a cache is the
     * one store no global scope and no RLS policy reaches, so it is the place to
     * be paranoid rather than clever.
     */
    private function throttleKey(Plugin $plugin, string $host): string
    {
        return sprintf('widget-install:%d:%d:%s', Tenancy::idOrFail(), (int) $plugin->id, $host);
    }
}
