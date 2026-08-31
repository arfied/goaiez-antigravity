<?php

declare(strict_types=1);

namespace App\Enums;

use App\Services\Places\GooglePlacesClient;
use App\Support\PlacesFieldTiers;

/**
 * The Places (New) endpoint families, which is the axis a tier is priced along.
 *
 * ⛔ **A FAMILY IS HALF OF A PRICE AND A TIER IS THE OTHER HALF** — see
 * {@see PlacesSkuTier}. Google publishes three SKU families that share tier
 * *names* and share none of their prices, which is how "Enterprise +
 * Atmosphere" came to be billed here at a figure belonging to a different
 * endpoint (decisions 239, 253).
 *
 * ⚠️ **`Autocomplete` IS A DECLARED EXCEPTION AND NOT A FOURTH TIERED FAMILY.**
 * Autocomplete (New) is billed per request at one SKU; its field mask selects
 * what comes back and does not select a price. It is named here anyway so that
 * every metered call in {@see GooglePlacesClient} goes
 * through the same seam, and the waiver is *declared* rather than implied —
 * {@see PlacesFieldTiers::familiesWithoutFieldMaskTiers()} is the
 * list, and a lint asserts it has exactly one member. An undeclared waiver and
 * a declared one look identical to a green suite.
 */
enum PlacesSkuFamily: string
{
    case TextSearch = 'text_search';

    case PlaceDetails = 'place_details';

    case NearbySearch = 'nearby_search';

    case Autocomplete = 'autocomplete';

    /**
     * The name Google's own pricing list uses, for an operator comparing the
     * two side by side.
     */
    public function label(): string
    {
        return match ($this) {
            self::TextSearch => 'Text Search',
            self::PlaceDetails => 'Place Details',
            self::NearbySearch => 'Nearby Search',
            self::Autocomplete => 'Autocomplete',
        };
    }
}
