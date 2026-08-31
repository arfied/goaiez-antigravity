<?php

declare(strict_types=1);

namespace App\Services\Pixel;

/**
 * What a `GeoIpLookup` answers for one address — country/ASN, never the
 * address itself.
 *
 * §12's bot table scores "Datacenter/cloud ASN" at 35 points and §11 row 8
 * asks for geo/ASN enrichment; this is the shape either would consume. ⚠️ **NO
 * IMPLEMENTATION IN THIS CODEBASE PRODUCES A REAL ONE YET** — see
 * `App\Contracts\GeoIpLookup` and `NullGeoIpLookup`'s docblocks. Every field is
 * nullable/false precisely so "unknown" is representable without inventing a
 * sentinel string a real implementation would later have to special-case.
 */
final readonly class GeoIpResult
{
    public function __construct(
        public ?string $country = null,
        public ?string $region = null,
        public ?int $asn = null,
        public ?string $asnOrg = null,
        public bool $isDatacenter = false,
    ) {}

    /**
     * The answer for an address nothing can resolve — every field unknown.
     */
    public static function unknown(): self
    {
        return new self;
    }
}
