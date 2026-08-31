<?php

declare(strict_types=1);

namespace App\Services\Actuation;

use App\Contracts\FetchGateway;
use App\Models\Location;
use App\Services\Fetch\FetchResult;
use App\Services\Tenant\LocationWebsite;
use App\Support\Tenancy;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * One honest look at a tenant's confirmed website.
 *
 * `BUILD-PLAN` §2.11.3 slice B: *"WordPress detection through the **FetchGateway**
 * (F0, robots honoured — a `/wp-json/` probe is an HTML-adjacent fetch and lives
 * inside the gateway's allowlist, `40` Part 8) · Cloudflare-proxied detection
 * recorded as a fact for Stage 7, actuating nothing"*.
 *
 * ⛔ **EVERY REQUEST GOES THROUGH THE GATEWAY AND THERE IS NO `Http::` CALL IN
 * THIS FILE.** `40` Part 6 — *"No module fetches HTML on its own"* — is held by
 * the outbound-file lint in `OutboundTest`, which fails the build on any file in
 * `app/` outside its allowlist that touches Laravel's HTTP client at all. That
 * lint is what makes robots, the method ceiling, the kill switch, the rate budget
 * and the `fetch_attempts` ledger unavoidable rather than remembered, and slice
 * B's own test plants a bypass to prove it still fires.
 *
 * ## What it refuses to conclude
 *
 * ⛔ **A FAILED OR REFUSED FETCH WRITES NOTHING AT ALL.** Recording
 * `website_scanned_at` after a fetch that never reached the site would turn
 * *"their host was down"* into *"we looked, and this is not WordPress"* — 229's
 * rule, and the CHECK on the table refuses the inverse shape for the same reason.
 *
 * ⛔ **WORDPRESS DETECTED IS NOT T1 AND MUST NEVER BE READ AS T1** (5541). The
 * plugin tier needs a *connection* — credentials a tenant granted, a store that
 * does not exist until slice F. What this finding supports is the sentence `41`
 * Part 1 calls the upgrade offer, and {@see ActuationTiers::upgrade()} is where
 * it is expressed.
 *
 * ⚠️ **CLOUDFLARE IS RECORDED AND ACTUATES NOTHING.** `41` Part 5 makes T2
 * auto-detect-only in v1; the nameserver advisory that would follow from it is
 * row 16h's, not ours, and nothing in this slice surfaces one.
 */
final class SiteProbe
{
    /**
     * The registered fetch-source policy row this probe runs under.
     */
    public const string SOURCE = 'tenant_website';

    public function __construct(private readonly FetchGateway $gateway) {}

    /**
     * Look at the location's confirmed website and record what is there.
     *
     * ⚠️ **RETURNS A READING AND PERSISTS ONE ONLY WHEN IT LOOKED.** The caller is
     * a queued job with nobody watching, so a refusal has to be a value rather
     * than an exception — `FetchResult`'s own argument.
     */
    public function probe(Location $location): SiteProbeReading
    {
        $this->assertBelongsToTenant($location);

        $url = $location->website_url;

        if ($url === null || $location->website_confirmed_at === null) {
            // ⛔ NEVER PROBED FROM AN UNCONFIRMED ADDRESS. There is no such row
            // today — the CHECK makes one unrepresentable — and the guard stays,
            // because the cost of being wrong is fetching a stranger's website on
            // a tenant's behalf.
            return SiteProbeReading::didNotLook('no_confirmed_website');
        }

        $home = $this->gateway->fetch(self::SOURCE, $url);

        if (! $home->successful()) {
            return SiteProbeReading::didNotLook(
                // ⚠️ **`->value` AND NOT `?->value`.** `??` reads its left side in
                // an isset context, so a null `refusalReason` falls through to
                // `'unknown'` without a warning and the nullsafe is redundant —
                // Larastan's `nullsafe.neverNull` fails the build on it.
                $home->wasRefused() ? 'refused:'.($home->refusalReason->value ?? 'unknown') : $home->outcome->value
            );
        }

        $cloudflare = $this->looksProxiedByCloudflare($home);
        $wordpress = $this->looksLikeWordPress($home);

        if (! $wordpress) {
            // ⚠️ THE SECOND FETCH RUNS ONLY WHEN THE FIRST WAS INCONCLUSIVE, AND
            // IT IS THE ONE `40` Part 8 HAD IN MIND. A themed WordPress site can
            // serve markup with no `/wp-content/` in it and no generator meta, so
            // the REST root is the honest second question — asked once, through
            // the same policy gate, and only of a host that has already answered.
            $wordpress = $this->restRootAnswers($location);
        }

        if (! $this->record($location, $url, $wordpress, $cloudflare)) {
            // ⚠️ THE OWNER CHANGED THE ADDRESS WHILE WE WERE LOOKING. What we
            // saw is true of a website this location no longer names, so it is
            // discarded rather than written — the same reasoning as
            // {@see LocationWebsite}'s invalidation, arriving from the other
            // direction. Their confirmation already left the row honestly
            // unknown, and the probe it dispatched is on its way.
            return SiteProbeReading::didNotLook('website_changed_mid_probe');
        }

        return SiteProbeReading::found($wordpress, $cloudflare);
    }

    /**
     * ⚠️ **TWO HEADERS RATHER THAN ONE.** `server: cloudflare` alone is set by
     * more than one thing that is not the proxy in front of an origin; `cf-ray`
     * is present on every proxied response. Either is treated as evidence, which
     * is deliberately the looser reading — the fact actuates nothing in v1, so a
     * false positive costs a row and a false negative costs Stage 7 a tenant.
     */
    private function looksProxiedByCloudflare(FetchResult $result): bool
    {
        return $result->header('cf-ray') !== null
            || str_contains(strtolower($result->header('server') ?? ''), 'cloudflare');
    }

    /**
     * ⚠️ **THE `Link` HEADER IS THE STRONGEST OF THESE AND THE ONLY ONE THE SITE
     * PUBLISHES ON PURPOSE.** WordPress advertises its REST root as
     * `<https://site/wp-json/>; rel="https://api.w.org/"`; the markup signals
     * below are conventions a theme can defeat, which is why a negative here is
     * followed by an actual request rather than treated as an answer.
     */
    private function looksLikeWordPress(FetchResult $result): bool
    {
        if (str_contains(strtolower($result->header('link') ?? ''), 'api.w.org')) {
            return true;
        }

        // The first 200 KB, not the whole document: these markers live in the
        // head and the asset URLs beside it, and a body can be two megabytes.
        $body = strtolower(substr($result->body ?? '', 0, 200_000));

        foreach (['/wp-content/', '/wp-includes/', 'content="wordpress', 'content=\'wordpress'] as $marker) {
            if (str_contains($body, $marker)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Ask the REST root, which is the question the platform answers about itself.
     *
     * ⚠️ **A NEGATIVE HERE IS NOT "NOT WORDPRESS" AND NOTHING TREATS IT AS ONE.**
     * The REST API is routinely disabled or firewalled on hardened installs, so
     * this can only ever raise confidence, never lower it — which is why it is
     * the second question and not the first.
     */
    private function restRootAnswers(Location $location): bool
    {
        $url = $location->website_url;

        if ($url === null) {
            return false;
        }

        $host = LocationWebsite::hostOf($url);

        if ($host === '') {
            return false;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $port = parse_url($url, PHP_URL_PORT);

        // The REST root hangs off the origin, never off the confirmed path: a
        // franchisee's page at /locations/springfield has no `/wp-json/` under
        // it, and asking for one would 404 on every such site.
        $origin = $scheme.'://'.$host.(is_int($port) ? ':'.$port : '');

        $result = $this->gateway->fetch(self::SOURCE, $origin.'/wp-json/');

        if (! $result->successful()) {
            return false;
        }

        $body = strtolower(substr($result->body ?? '', 0, 8_000));

        return str_contains($body, '"wp/v2"') || str_contains($body, 'api.w.org');
    }

    /**
     * ⚠️ **A SECOND PERMITTED WRITER OF THE THREE DETECTION COLUMNS, AND THE
     * OTHER ONE IS THE ADDRESS WRITER** ({@see LocationWebsite}). The split is
     * *observe* against *invalidate*: this class writes what it saw, and that one
     * blanks all three when the address they were about changes. Both are named
     * in the chokepoint lint, and nothing else may touch them.
     *
     * ⚠️ **`wordpress_detected_at` KEEPS ITS ORIGINAL TIMESTAMP WHILE THE FINDING
     * HOLDS.** It answers *"since when"*, and `website_scanned_at` beside it
     * answers *"as of when"* — two different questions that one column cannot
     * carry.
     */
    private function record(Location $location, string $url, bool $wordpress, bool $cloudflare): bool
    {
        $now = Carbon::now();

        // ⚠️ **THE ADDRESS IS IN THE WHERE CLAUSE, NOT ONLY IN THE READ.** Two
        // fetches take seconds and an owner can re-confirm a different website
        // in between; a plain `save()` would then stamp what we saw on the old
        // site against the new one, which is exactly the drift `LocationWebsite`
        // blanks the columns to prevent. Tenant-scoped by the global scope, and
        // keyed on the row as well, so this can only ever touch the row we read.
        return Location::query()
            ->whereKey($location->getKey())
            ->where('website_url', $url)
            ->update([
                'website_scanned_at' => $now,
                'wordpress_detected_at' => $wordpress ? ($location->wordpress_detected_at ?? $now) : null,
                'cloudflare_detected_at' => $cloudflare ? ($location->cloudflare_detected_at ?? $now) : null,
            ]) === 1;
    }

    private function assertBelongsToTenant(Location $location): void
    {
        if ($location->business_id === Tenancy::idOrFail()) {
            return;
        }

        throw new InvalidArgumentException(
            'That location belongs to another tenant. Probing it here would fetch somebody '
            .'else\'s website on this tenant\'s rate budget and write the answer to their row.',
        );
    }
}
