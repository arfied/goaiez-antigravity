<?php

declare(strict_types=1);

namespace App\Services\Widgets;

use App\Enums\PluginType;
use App\Models\Location;
use App\Models\Plugin;
use App\Modules\X157\Actions\SiteHostsAction;
use App\Scopes\TenantScope;
use App\Services\AuditService;
use App\Support\Tenancy;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * The only reader and writer of `plugins` (`17` FPR-05, row 3 slice G).
 *
 * ⚠️ **`plugins` EXISTED WITH NO WRITER AT ALL** — a table, a model, a factory,
 * an RLS policy and a schema isolation test, and nothing in `app/` that could
 * create a row. The fifth instance of decision 272's shape after
 * `Business::provision()`, `autopilot_settings` (377), `feedback_pages` and
 * `review_destinations`, and the reason `TenantProvisioner` now calls
 * provisionFor(): a feed endpoint keyed on a row nobody mints answers 404 for
 * every tenant, forever, with nothing to explain it.
 *
 * ---------------------------------------------------------------------------
 * WHY `Plugin` KEEPS ITS GLOBAL SCOPE AND `FeedbackPage` DOES NOT
 * ---------------------------------------------------------------------------
 * Both resolve a tenant from a public opaque key, so both face decision 318's
 * circularity — a scope calling `Tenancy::idOrFail()` cannot run on the query
 * whose answer *establishes* the tenant. `FeedbackPage` answers it by leaving
 * the scope off entirely and joining the ArchitectureTest allowlist.
 *
 * This one answers it by keeping the scope and opting out in exactly one
 * method, and the asymmetry is deliberate rather than an oversight. Because
 * `Tenancy::idOrFail()` **throws** rather than filtering to nothing, a scoped
 * model fails *loudly* on any path that forgot a tenant, while an unscoped one
 * quietly returns whatever `public_read` permits — which is every row. So the
 * scope is worth keeping wherever the circularity does not actually bite, and
 * here it bites in one query. One audited hole beats a ninth allowlist entry.
 */
final class WidgetPlugins
{
    public function __construct(
        private readonly AuditService $audit,
    ) {}

    /**
     * Give a location its review feed, or hand back the one it already has.
     *
     * IDEMPOTENT, because `TenantProvisioner` calls it inside the registration
     * transaction and a retry must not mint a second key. A second key would
     * not merely be untidy: `embed_key` is what a customer pastes into their
     * website, so two rows means one of them is a live feed nobody can find and
     * the other is the one they installed.
     *
     * ONE PLUGIN PER LOCATION, which is narrower than the schema allows —
     * `plugins` has no unique index on `location_id`, because DATA-MODEL §5.11
     * anticipates a badge and a feedback form alongside the review widget. The
     * constraint is on `type` as well, so this stays true when those arrive.
     */
    public function provisionFor(Location $location): Plugin
    {
        $this->assertBelongsToTenant($location);

        $existing = Plugin::query()
            ->where('location_id', $location->id)
            ->where('type', PluginType::ReviewWidget)
            ->first();

        if ($existing instanceof Plugin) {
            return $existing;
        }

        $plugin = new Plugin;

        $plugin->forceFill([
            'business_id' => Tenancy::idOrFail(),
            'location_id' => $location->id,
            'type' => PluginType::ReviewWidget,

            // ALWAYS RANDOM, NEVER DERIVED FROM TENANT DATA — the model's own
            // docblock and the creating migration both say so, for the reason
            // `businesses.pixel_tenant_id` does: this key is presented by an
            // anonymous browser before any tenant is known, so anything
            // recoverable from it is something a stranger learns for free.
            'embed_key' => (string) Str::uuid(),

            // Show everything. The owner's ruling, and decision 110/114's shape:
            // the safe state is the default, so suppression is a deliberate act.
            'min_stars_to_show' => 1,

            // ⚠️ EMPTY MEANS "SERVE NOWHERE", NOT "SERVE ANYWHERE" — see
            // originIsAllowed(). A freshly provisioned feed refuses every
            // request until somebody names a domain, which is the direction a
            // cross-origin allowlist has to fail.
            'allowed_domains' => [],
        ])->save();

        $this->audit->record('widget.provisioned', 'system', $plugin, [
            'location_id' => (int) $location->id,
            'type' => PluginType::ReviewWidget->value,
        ]);

        return $plugin;
    }

    /**
     * The plugin behind a public embed key, or null.
     *
     * ⚠️ **THE ONE PLACE IN THIS CODEBASE THAT DROPS `TenantScope` ON PURPOSE.**
     * Called with no tenant established, because this is the query whose answer
     * establishes it — `ResolveWidget` calls it and then sets the tenant from
     * what comes back. The `public_read` policy is what permits it at the
     * database layer.
     *
     * That makes this method the whole attack surface of the change, so it is
     * deliberately incapable of being anything but a single-row lookup by exact
     * key: no `where` a caller can influence, no ordering, no list. A UUID is
     * the only input, and one that does not parse never reaches the database.
     */
    public function resolve(string $embedKey): ?Plugin
    {
        $embedKey = trim($embedKey);

        // Checked before querying, so a malformed key is a cheap null rather
        // than a database round trip — and so Postgres never has to reject a
        // non-UUID cast on a public, unauthenticated path.
        if ($embedKey === '' || ! Str::isUuid($embedKey)) {
            return null;
        }

        return Plugin::query()
            ->withoutGlobalScope(TenantScope::class)
            ->where('embed_key', $embedKey)
            ->first();
    }

    /**
     * The review feed already minted for a location, or null.
     *
     * ⚠️ THIS EXISTS BECAUSE `ReviewsTest`'s lint MAKES THIS CLASS THE ONLY
     * READER OF `Plugin`, so a screen cannot query the model itself. It is a
     * plain scoped read — `TenantScope` is left on, unlike `resolve()`, because
     * this one runs for a signed-in owner whose tenant is already established.
     *
     * READ-ONLY ON PURPOSE, AND NOT `provisionFor()`. The install screen renders
     * on a `GET`, and `provisionFor()` writes a row and an audit entry; calling
     * it from a renderer would mint keys as a side effect of somebody looking at
     * a page. A location with no feed shows the honest "not ready yet" instead.
     */
    public function forLocation(Location $location): ?Plugin
    {
        return Plugin::query()
            ->where('location_id', $location->id)
            ->where('type', PluginType::ReviewWidget)
            ->first();
    }

    /**
     * The lowest rating this feed will serve.
     *
     * ⚠️ RAISING THIS SUPPRESSES NEGATIVE REVIEWS ON THE OWNER'S OWN WEBSITE,
     * and that is a legal surface rather than a preference: the FTC's 2024 Rule
     * on Consumer Reviews and Testimonials addresses review suppression. The
     * database refuses anything outside 1–5, this refuses it with an error
     * somebody can act on, and the audit row records who moved it — decision
     * 216's three-layer reasoning applied to the one widget setting that has a
     * consequence outside the page it renders on.
     *
     * @throws InvalidArgumentException
     */
    public function setMinStars(Plugin $plugin, int $minStars, string $actor): Plugin
    {
        if ($minStars < 1 || $minStars > 5) {
            throw new InvalidArgumentException(
                'min_stars_to_show must be between 1 and 5. 0 is not "show everything" — '
                .'no review can hold a rating of 0, so it would read as a disabled feed '
                .'while behaving exactly like 1.',
            );
        }

        $before = (int) $plugin->min_stars_to_show;

        $plugin->forceFill(['min_stars_to_show' => $minStars])->save();

        $this->audit->recordChange(
            'widget.min_stars_changed',
            $actor,
            ['min_stars_to_show' => $before],
            ['min_stars_to_show' => $minStars],
            $plugin,
        );

        return $plugin;
    }

    /**
     * The origins this feed may be embedded on.
     *
     * Stored as bare hosts, lowercased — never scheme, port or path. The feed
     * compares against the host it parses out of the request's `Origin`, and
     * comparing two things normalised differently is how an allowlist quietly
     * matches nothing.
     *
     * @param  array<int|string, mixed>  $domains
     */
    public function setAllowedDomains(Plugin $plugin, array $domains, string $actor): Plugin
    {
        $normalised = [];

        foreach ($domains as $domain) {
            // Same reasoning as the read path: this is the boundary a repair
            // script or an admin screen crosses, so a non-string here is a real
            // input rather than an impossible one.
            $host = is_string($domain) ? self::normaliseHost($domain) : null;

            if ($host !== null && ! in_array($host, $normalised, true)) {
                $normalised[] = $host;
            }
        }

        $before = $plugin->allowed_domains;

        $plugin->forceFill(['allowed_domains' => $normalised])->save();

        $this->audit->recordChange(
            'widget.allowed_domains_changed',
            $actor,
            ['allowed_domains' => $before],
            ['allowed_domains' => $normalised],
            $plugin,
        );

        return $plugin;
    }

    /**
     * Whether this feed may answer a request from the given `Origin` header.
     *
     * ⚠️ **THIS IS NOT A SECURITY CONTROL AND MUST NEVER BE DESCRIBED AS ONE.**
     * `Origin` is set by the browser and cannot be forged *by a page*, which is
     * the whole of what this buys: a script on `evil.test` cannot read this feed
     * from a visitor's browser. It is trivially forged by anything that is not a
     * browser — `curl -H 'Origin: https://ledger.test'` passes — so the feed's
     * contents must be safe to be public regardless, and they are: displayable
     * reviews and a reviewer's display name, which is what the owner is
     * publishing on their own website anyway.
     *
     * Recorded plainly because `17` FPR-05's own wording is "requests from
     * unlisted domains are rejected", which reads like an access control and is
     * not one. The honest claim is *browsers on unlisted origins are refused*.
     *
     * ⚠️ EMPTY LIST REFUSES EVERYTHING. A newly provisioned feed serves nobody
     * until a domain is named — the direction a cross-origin allowlist has to
     * fail, and the opposite of what an empty array usually means in config.
     */
    public function originIsAllowed(Plugin $plugin, ?string $origin): bool
    {
        $allowed = $plugin->allowed_domains;

        if (! is_array($allowed) || $allowed === []) {
            return false;
        }

        $host = self::normaliseHost($origin);

        if ($host === null) {
            return false;
        }

        foreach ($allowed as $entry) {
            if (! is_string($entry)) {
                continue;
            }

            if ($host === $entry) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    public function siteHosts(): array
    {
        $id = Tenancy::id();

        return $id === null ? [] : app(SiteHostsAction::class)->handle((int) $id);
    }

    /**
     * Every origin the pixel accepts for the acting tenant: the websites they listed on their feeds plus every host this platform serves their published site on. ⚠️ The platform host is shared by every tenant with a site there, so a pixel key that leaks works from any tenant's page on it — accepted 2026-09-27 (wave 837); this gate narrows browsers, it is not a security control (see `businessAllowsOrigin`).
     *
     * @return list<string>
     */
    public function acceptedHosts(): array
    {
        $hosts = array_values(array_unique(array_merge($this->listedHosts(), $this->siteHosts())));
        sort($hosts);

        return $hosts;
    }

    /**
     * Whether the acting tenant has named this origin on any of its feeds.
     *
     * ⚠️ **THE PIXEL COLLECTOR'S ORIGIN GATE, ANSWERED FROM THE ALLOWLIST THAT
     * ALREADY EXISTS.** `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 2 wants the origin
     * matched against `tenants.domains`, and this schema has no such column;
     * `PixelCollector::originIsAllowed()` records why a second domain list was
     * refused rather than added. What it comes down to is that `allowed_domains`
     * is already the tenant's written answer to *"which of my websites may run a
     * GO AI EZ script?"*, and two lists answering one question is two screens
     * that can disagree.
     *
     * ⚠️ **ANY FEED OF THE TENANT'S, NOT THE ONE FOR A LOCATION.** A business
     * with three locations has three feeds and usually one website, and asking
     * the caller to pick a location would make the pixel's answer depend on which
     * location a visitor happened to be reading about. **Scoped by
     * `TenantScope`** — no `where('business_id')` is written here, because the
     * scope is the boundary and hand-writing it beside one is how a filter starts
     * looking optional.
     *
     * ⛔ **NOT A SECURITY CONTROL** — `originIsAllowed()`'s docblock above,
     * unchanged and load-bearing. This narrows which *browsers* may post; it
     * stops nothing that is not one.
     */
    public function businessAllowsOrigin(?string $origin): bool
    {
        $host = self::normaliseHost($origin);

        if ($host === null) {
            return false;
        }

        // ⚠️ **ANSWERED FROM {@see self::acceptedHosts()} RATHER THAN BY LOOPING
        // THE FEEDS HERE, SO THE SCREEN THAT REPORTS THIS ANSWER CANNOT DRIFT
        // FROM THE GATE THAT MAKES IT** (7801). The comparison is unchanged —
        // one normalised host against the stored entries, no wildcards — and
        // `Account\PixelInstall` renders the same list this reads. Two methods
        // deriving one answer from one list is the shape 3093 and 2967 both
        // record; two methods deriving it from two reads is how a screen comes
        // to say *"we are listening to ledger.test"* while the collector
        // refuses it.
        return in_array($host, $this->acceptedHosts(), true);
    }

    /**
     * Every website this tenant has named across all of their feeds.
     *
     * ⛔ **THIS IS THE PRECONDITION OF PIXEL COLLECTION AND NOT MERELY OF THE
     * REVIEW WIDGET, WHICH IS WHY IT IS READABLE AT ALL** (7800).
     * {@see self::businessAllowsOrigin()} is the collector's origin gate (4968),
     * so an empty answer here means **the pixel archives nothing for this
     * tenant, whatever their snippet is doing** — and until 7800 no screen could
     * see that, while `OperatorAlertKind::PixelIngestRejects` told an operator
     * about it by name.
     *
     * ⚠️ **BARE HOSTS, EXACTLY AS STORED, DE-DUPLICATED AND SORTED.** They are
     * normalised on the way in by {@see self::setAllowedDomains()}; nothing is
     * re-normalised here, because the gate above compares against the stored
     * strings and a display that normalised differently from the comparison
     * would be a screen showing a website the collector does not actually
     * accept. Sorted in PHP rather than in SQL — the values are inside a `jsonb`
     * column, and the set is bounded by `WidgetInstall::MAX_DOMAINS` per feed.
     *
     * ⚠️ **SCOPED BY `TenantScope`**, like every other read in this class except
     * {@see self::resolve()}. No `where('business_id')` is written here.
     *
     * @return list<string>
     */
    public function listedHosts(): array
    {
        $hosts = [];

        foreach (Plugin::query()->get() as $plugin) {
            $allowed = $plugin->allowed_domains;

            if (! is_array($allowed)) {
                continue;
            }

            foreach ($allowed as $entry) {
                // Same reasoning as the write path: `jsonb` will hold whatever
                // a repair script put there, so a non-string is a real input
                // rather than an impossible one.
                if (is_string($entry) && ! in_array($entry, $hosts, true)) {
                    $hosts[] = $entry;
                }
            }
        }

        sort($hosts);

        return $hosts;
    }

    /**
     * Whether this tenant has any review feed at all.
     *
     * ⛔ **A TENANT WITH NO FEED IS A DIFFERENT STATE FROM ONE WITH AN EMPTY
     * LIST, AND A SCREEN THAT CONFLATES THEM IS WRONG FOR ONE OF THEM** (7803).
     * Both refuse every origin, so the collector cannot tell them apart and does
     * not need to; a person can, because the first has nowhere to type a website
     * — `WidgetInstall` renders *"Not ready yet"* — and the second has a box
     * waiting. Telling the first *"add your website on the reviews screen"*
     * sends somebody to a screen that will not let them.
     *
     * ⚠️ **`exists()`, NOT A COUNT.** Nothing on either screen reports how many
     * feeds a tenant has, and a number nobody renders is a number that goes
     * stale in an argument.
     */
    public function businessHasAnyFeed(): bool
    {
        return Plugin::query()->exists();
    }

    /**
     * A bare, lowercased host from whatever shape the caller had.
     *
     * PARSE, THEN COMPARE — never match a pattern against the whole string,
     * which is how `https://evil.test/?x=ledger.test` gets through. The same
     * reasoning `DestinationSettings::assertUsableLink()` records for outbound
     * links and `GoogleLinkHosts` for inbound ones; this is the third direction.
     *
     * ⚠️ NO SUBDOMAIN WILDCARDS. `ledger.test` does not match
     * `shop.ledger.test`, and that is deliberate: a dot-anchored suffix is what
     * `ReviewDestination::allowedLinkHosts()` uses, but there the entries are
     * *our* list of a platform's own domains, and here they are a tenant's — so
     * a wildcard would let one tenant's typo or a lapsed subdomain serve their
     * feed from somewhere they no longer control. Naming each host is cheap.
     *
     * ⚠️ **PUBLIC SO THAT `WidgetInstalls` CANNOT BECOME A SECOND PARSER OF THIS
     * STRING** (3093). The host recorded against an install has to be the same
     * host this method compared, or the widget screen shows a website the feed
     * does not actually serve. 2967 records the identical hazard one screen over
     * — *"the rule and `normaliseHost()` are two parsers of one string, and two
     * parsers drift"* — and widening one method is a far smaller change than
     * owning a second `parse_url` with its own scheme handling.
     */
    public static function normaliseHost(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        // A bare host has no scheme, and parse_url() will not find a host
        // without one. Adding a placeholder is what makes both shapes parse
        // through the same code rather than through two branches that drift.
        if (! str_contains($value, '://')) {
            $value = 'https://'.$value;
        }

        $host = parse_url($value, PHP_URL_HOST);

        if (! is_string($host) || trim($host) === '') {
            return null;
        }

        return mb_strtolower(trim($host));
    }

    /**
     * Refuse to provision a feed for another tenant's location.
     *
     * Decision 301's shape. RLS would refuse the INSERT anyway, but a SQLSTATE
     * is not an error a caller can act on, and this runs inside
     * `TenantProvisioner`'s registration transaction where a raw constraint
     * violation would abort the whole signup.
     */
    private function assertBelongsToTenant(Location $location): void
    {
        if ((int) $location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. A plugin is filed against the '
            .'acting business, so this would mint one business\'s public embed key '
            .'against another\'s location.',
        );
    }
}
