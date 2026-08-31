<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Services\Pixel\GeoIpResult;
use App\Services\Pixel\NullGeoIpLookup;

/**
 * The seam §11 row 8's geo/ASN half binds to, once a real IP-geolocation
 * database exists to bind it to.
 *
 * `GOAIEZ_PIXEL_MASTER_BUILD` §11 row 8 asks for *"GeoLite2 geo/ASN"* and §2.1
 * lists MaxMind GeoLite2 as the vendor. ⛔ **NO SUCH DATABASE, OR ANY
 * IP-GEOLOCATION PACKAGE, EXISTS IN THIS ENVIRONMENT** — verified rather than
 * assumed: `composer.json` names no MaxMind/geoip dependency and no `.mmdb`
 * file exists anywhere in the checkout. `CLAUDE.md`'s instruction for exactly
 * this shape is to build the seam and say so rather than invent a vendor call,
 * so this interface exists and {@see NullGeoIpLookup} is
 * its only implementation.
 *
 * ⚠️ **UNWIRED, DELIBERATELY.** Nothing in `App\Services\Pixel\PixelEnrichment`
 * or `App\Services\Pixel\PixelCollector` calls this today, and no derived
 * column carries a geo/ASN value — see the migration that added `ip_hash`,
 * `browser` and `os` beside this contract for why. A nullable column with a
 * writer that can only ever answer "unknown" is decision 272's shape wearing a
 * schema: it would look like coverage and would not be any. When a real
 * `.mmdb` reader lands, it implements this interface, is bound in
 * `AppServiceProvider` beside `L0Archive`'s own seam binding, and the migration
 * that adds the columns is written in the same commit as the code that fills
 * them — `L0Line::keysFor()` is the precedent for versioning them into the
 * archive rather than looking them up at derivation time.
 */
interface GeoIpLookup
{
    /**
     * The country/ASN answer for one address, or every field unknown when
     * nothing can resolve it.
     *
     * ⚠️ Implementations must never throw on an unresolvable address — a
     * lookup miss is the ordinary case for a datacenter, a VPN or a database
     * that has not shipped a new build, and it must degrade to
     * {@see GeoIpResult::unknown()} rather than fail the request it was
     * enriching.
     */
    public function lookup(string $ip): GeoIpResult;
}
