<?php

declare(strict_types=1);

namespace App\Services\Places;

use App\Enums\PlacesSku;
use App\Enums\PlacesSkuFamily;
use App\Exceptions\PlacesFieldNotPriced;
use App\Support\PlacesFieldTiers;

/**
 * A field mask and the SKU Google bills for it, which are now one object.
 *
 * ⛔ **THE POINT IS THAT THERE IS NOTHING TO KEEP IN AGREEMENT.** The header
 * that goes to Google and the price that goes on `places_api_calls` come off the
 * same instance, so "the SKU we metered" and "the mask we sent" cannot be two
 * facts about one call. Before this they were: the caller named a SKU, the
 * caller named a mask, and a docblock said they matched.
 *
 * ⚠️ **CONSTRUCTED, NOT VALIDATED.** A validator would answer a question after
 * the fact and could be skipped; this cannot be built at all for a mask nothing
 * can price — {@see PlacesFieldTiers::skuFor()} throws. Every mask in this
 * application is a class constant, so that failure is reached by a lint long
 * before a deployment reaches it.
 *
 * ⚠️ **IT DOES NOT CACHE AND DOES NOT NEED TO.** Four masks, four constructions
 * per request at most, and the table is a literal array — measuring this would
 * cost more than the work it saves.
 */
final class PricedFieldMask
{
    public readonly PlacesSku $sku;

    private function __construct(
        public readonly PlacesSkuFamily $family,
        public readonly string $mask,
    ) {
        $this->sku = PlacesFieldTiers::skuFor($family, $mask);
    }

    /**
     * @throws PlacesFieldNotPriced
     */
    public static function for(PlacesSkuFamily $family, string $mask): self
    {
        return new self($family, $mask);
    }

    /**
     * The top-level fields this mask asked for, for
     * {@see PlaceSummary::askedFor()}.
     *
     * @return list<string>
     */
    public function fields(): array
    {
        return PlacesFieldTiers::leaves($this->mask);
    }
}
