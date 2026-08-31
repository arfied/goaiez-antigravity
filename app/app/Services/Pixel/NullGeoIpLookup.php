<?php

declare(strict_types=1);

namespace App\Services\Pixel;

use App\Contracts\GeoIpLookup;

/**
 * The only `GeoIpLookup` this codebase has, because no geo/ASN database is
 * available to build a real one against.
 *
 * See `App\Contracts\GeoIpLookup`'s docblock for the full argument. This class
 * is the honest version of "not built" — every answer is
 * {@see GeoIpResult::unknown()} — rather than a class that pretends to look
 * something up and always misses, which would read as coverage under a test
 * that only ever asserts the shape.
 */
final class NullGeoIpLookup implements GeoIpLookup
{
    public function lookup(string $ip): GeoIpResult
    {
        return GeoIpResult::unknown();
    }
}
