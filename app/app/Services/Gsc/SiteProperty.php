<?php

declare(strict_types=1);

namespace App\Services\Gsc;

use App\Enums\GscPermissionLevel;
use App\Services\Visibility\SearchConsoleProperties;

/**
 * One entry from `GET webmasters/v3/sites` — a property the connected Google
 * account can see, and what it may do with it.
 *
 * The discovery document names this resource `WmxSite` and gives it exactly two
 * fields, `siteUrl` and `permissionLevel`
 * (https://searchconsole.googleapis.com/$discovery/rest?version=v1, revision
 * 20260804).
 *
 * ⚠️ **`siteUrl` HAS TWO SHAPES AND THEY ARE NOT INTERCHANGEABLE.** A domain
 * property is `sc-domain:example.com`; a URL-prefix property is a full URL with
 * its trailing slash, `https://example.com/`. Both are opaque keys that must be
 * sent back exactly as received — path-encoded, never normalised, never
 * lowercased, never stripped of the slash. A URL-prefix property covers only the
 * prefix it names, so `https://example.com/` and `https://www.example.com/` are
 * two different properties with two different sets of numbers.
 *
 * ⚠️ **AND NOTHING IN THIS STRING IDENTIFIES A TENANT.** Decision 1083 records
 * that hazard as identical in kind to Zernio's opaque `accountId` at 531: one
 * Google account routinely holds properties for many businesses, so a property
 * reaching this application from a request parameter proves nothing about who
 * owns it. The binding is `gsc_site_properties`, tenant-owned and RLS-forced,
 * written only from a list this account was actually shown — which is why
 * {@see SearchConsoleProperties::choose()} re-fetches
 * the list rather than trusting the posted value.
 */
final readonly class SiteProperty
{
    private function __construct(
        public string $siteUrl,
        public GscPermissionLevel $permissionLevel,
    ) {}

    /**
     * @param  array<array-key, mixed>  $entry
     */
    public static function fromApi(array $entry): ?self
    {
        $siteUrl = $entry['siteUrl'] ?? null;

        // An entry with no key is not a property we can ever query. Dropped
        // rather than thrown, so one malformed row does not lose the owner the
        // rest of their list — ZernioGbpClient's reasoning at decision 413.
        if (! is_string($siteUrl) || $siteUrl === '') {
            return null;
        }

        return new self(
            siteUrl: $siteUrl,
            permissionLevel: GscPermissionLevel::fromWire($entry['permissionLevel'] ?? null),
        );
    }

    /**
     * Whether this property is a domain property rather than a URL prefix.
     *
     * Not currently branched on. It is here because the two shapes are the first
     * thing anybody debugging a mismatched property asks about, and deriving it
     * at three call sites is how two of them end up disagreeing.
     */
    public function isDomainProperty(): bool
    {
        return str_starts_with($this->siteUrl, 'sc-domain:');
    }
}
