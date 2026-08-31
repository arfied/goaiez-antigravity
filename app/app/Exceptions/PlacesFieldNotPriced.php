<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\PlacesSkuFamily;
use App\Enums\PlacesSkuTier;
use App\Support\PlacesFieldTiers;
use Exception;

/**
 * A field mask this application cannot put a price on.
 *
 * ⛔ **RAISED RATHER THAN GUESSED DOWNWARD, AND THAT IS THE WHOLE POINT.** The
 * two available guesses — drop the unknown field, or price it at the cheapest
 * tier — are both the guess that spends money on a ledger that says it did not.
 * `places_api_calls.unit_cents_per_thousand` is summed by the one per-tenant
 * dollar cap that survived decision 3293, so a cheap guess moves the ceiling in
 * the same direction as the error.
 *
 * ⚠️ **THIS IS A DEVELOPER-TIME FAILURE, NOT A RUNTIME ONE.** Every mask in this
 * application is a constant, so a lint reaches all of them before a deployment
 * does. If one of these ever surfaces in production it means a mask was built
 * from something other than a constant, and that is the finding.
 *
 * Carries no vendor payload and no visitor input — a mask is our own string.
 */
final class PlacesFieldNotPriced extends Exception
{
    private function __construct(
        public readonly PlacesSkuFamily $family,
        public readonly ?string $field,
        public readonly ?PlacesSkuTier $tier,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function unknownField(PlacesSkuFamily $family, string $field): self
    {
        return new self(
            $family,
            $field,
            null,
            "The field `{$field}` is not priced for {$family->label()} by the tier table read from "
            .'Google on '.PlacesFieldTiers::FETCHED_ON.'. Re-read '
            .PlacesFieldTiers::SOURCES[$family->value]
            .' and add it at the tier Google lists it under — never drop it and never assume the '
            .'cheapest tier, because both of those bill less than the call costs.',
        );
    }

    public static function unsoldTier(PlacesSkuFamily $family, PlacesSkuTier $tier): self
    {
        return new self(
            $family,
            null,
            $tier,
            "This mask lands on the {$tier->value} tier of {$family->label()}, and PlacesSku has no "
            .'case for that pair. Either Google has started selling it since '
            .PlacesFieldTiers::FETCHED_ON.' — in which case add the case with its price and its SKU '
            .'code — or the tier table has grown a tier that family does not have.',
        );
    }

    public static function emptyMask(PlacesSkuFamily $family): self
    {
        return new self(
            $family,
            null,
            null,
            "An empty field mask cannot be priced for {$family->label()}. Google's field mask is "
            .'mandatory: "if you omit the field mask, the method returns an error", so an empty one '
            .'is a construction fault here rather than a cheap call.',
        );
    }
}
