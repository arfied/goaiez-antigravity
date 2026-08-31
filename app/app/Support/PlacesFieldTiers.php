<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\PlacesSku;
use App\Enums\PlacesSkuFamily;
use App\Enums\PlacesSkuTier;
use App\Exceptions\PlacesFieldNotPriced;
use App\Services\Places\PlacesSpend;
use App\Services\Places\PlaceSummary;
use App\Services\Places\PricedFieldMask;

/**
 * Google's field-mask tier table, transcribed, dated, and sourced.
 *
 * ---------------------------------------------------------------------------
 * WHY THIS FILE EXISTS AT ALL
 * ---------------------------------------------------------------------------
 *
 * ⛔ **UNTIL THIS LANDED, THE ONLY AUTHORITY FOR "THE SKU WE BILL MATCHES THE
 * MASK WE SEND" WAS A COMMENT WE WROTE OURSELVES.** `PlacesSpend::record()`
 * wrote the price of the SKU **the caller passed**; Google prices the
 * `X-Goog-FieldMask` **actually sent**. Those were two independent facts kept in
 * agreement by a docblock, dated 2026-07-31, about a vendor price list that
 * moves without telling anybody — and the ledger column that disagreement would
 * corrupt is the one summed by
 * {@see PlacesSpend::dailyTenantSpendCeilingCents()}, the
 * single per-tenant dollar cap that survived decision 3293. A wrong pairing
 * would move the ceiling in the same direction as the error, and nothing would
 * say so.
 *
 * So the pairing is no longer asserted. It is **derived**: a mask goes in, the
 * SKU Google would bill comes out, and {@see PricedFieldMask}
 * makes it impossible to send one mask and meter another, because the header and
 * the ledger price come off the same object.
 *
 * ---------------------------------------------------------------------------
 * PROVENANCE — READ {@see self::FETCHED_ON} BEFORE TRUSTING A ROW
 * ---------------------------------------------------------------------------
 *
 * Every list below was read from the pages in {@see self::SOURCES} on
 * {@see self::FETCHED_ON}. Bump that date only after re-reading all of them;
 * never to silence a warning. `places:verify-pricing` prints this table beside
 * the masks this application actually sends, so re-checking is one command and a
 * page rather than a research project.
 *
 * Google, verbatim, on the page in `SOURCES['usage_and_billing']`: *"You are
 * then billed at the highest SKU applicable to your request. That means if you
 * select fields in both the Essentials and the Pro SKUs, you are billed based on
 * the Pro SKU."*
 *
 * ⚠️ **THE TIER NAMES REPEAT ACROSS FAMILIES AND THE PRICES DO NOT.** That is
 * the trap decision 253 already paid for once. The table is keyed by family
 * first for that reason, and {@see PlacesSku::fromFamilyAndTier()} is the only
 * thing that turns a `(family, tier)` pair into money.
 *
 * ⚠️ **TWO FAMILIES ARE MISSING TIERS AND IT IS GOOGLE THAT IS MISSING THEM.**
 * Text Search has no mid `Essentials` tier — `formattedAddress`, `location` and
 * `types` are Text Search **Pro** — and Nearby Search's cheapest tier is Pro,
 * `places.id` included. A reader who assumes the five tiers are universal will
 * under-bill a Text Search by 13.8×, which is not hypothetical: see
 * {@see PlacesSku::TextSearchEssentials}.
 *
 * ⛔ **WHAT THIS TABLE DOES NOT PRICE, STATED RATHER THAN IMPLIED.** Fetching a
 * photo's bytes bills **Place Details Photos** (SKU DCD1-FE97-8C71, $7.00/1k,
 * 1,000 free a month), which is a separate request to a separate endpoint and
 * has no case on {@see PlacesSku} because nothing here makes one — the `photos`
 * field in a Place Details mask returns *references* and is IDs-Only, i.e. free.
 * A lane that starts fetching photo media is spending on a SKU with no meter and
 * no ceiling, which is decision 3297's precondition unmet; it needs a case, a
 * price and a row here before the first call, not after.
 */
final class PlacesFieldTiers
{
    /**
     * The date every list in this file was read from the pages in SOURCES.
     *
     * Shaped after {@see PlacesSku::VERIFIED_ON} deliberately, and separate from
     * it deliberately: prices and tier membership are two things Google can move
     * independently, and one date covering both is a date that is half true.
     */
    public const string FETCHED_ON = '2026-08-29';

    /**
     * @var array<string, string>
     */
    public const array SOURCES = [
        'place_details' => 'https://developers.google.com/maps/documentation/places/web-service/place-details',
        'text_search' => 'https://developers.google.com/maps/documentation/places/web-service/text-search',
        'nearby_search' => 'https://developers.google.com/maps/documentation/places/web-service/nearby-search',
        'autocomplete' => 'https://developers.google.com/maps/documentation/places/web-service/place-autocomplete',
        'pricing' => 'https://developers.google.com/maps/billing-and-pricing/pricing',
        'usage_and_billing' => 'https://developers.google.com/maps/documentation/places/web-service/usage-and-billing',
    ];

    /**
     * The fields every family shares in its Enterprise tier.
     *
     * Shared because Google's three pages list them identically, and named once
     * because three transcriptions of one list is three chances to mistype it.
     * The `places.` prefix is stripped everywhere — see {@see self::leaves()}.
     *
     * @return list<string>
     */
    private static function enterpriseFields(): array
    {
        return [
            'currentOpeningHours',
            'currentSecondaryOpeningHours',
            'internationalPhoneNumber',
            'nationalPhoneNumber',
            'priceLevel',
            'priceRange',
            'rating',
            'regularOpeningHours',
            'regularSecondaryOpeningHours',
            'transitStation',
            'userRatingCount',
            'websiteUri',
        ];
    }

    /**
     * The fields every family shares in its Enterprise + Atmosphere tier.
     *
     * @return list<string>
     */
    private static function atmosphereFields(): array
    {
        return [
            'allowsDogs',
            'curbsidePickup',
            'delivery',
            'dineIn',
            'editorialSummary',
            'evChargeAmenitySummary',
            'evChargeOptions',
            'fuelOptions',
            'generativeSummary',
            'goodForChildren',
            'goodForGroups',
            'goodForWatchingSports',
            'liveMusic',
            'menuForChildren',
            'neighborhoodSummary',
            'outdoorSeating',
            'parkingOptions',
            'paymentOptions',
            'reservable',
            'restroom',
            'reviewSummary',
            'reviews',
            'routingSummaries',
            'servesBeer',
            'servesBreakfast',
            'servesBrunch',
            'servesCocktails',
            'servesCoffee',
            'servesDessert',
            'servesDinner',
            'servesLunch',
            'servesVegetarianFood',
            'servesWine',
            'takeout',
        ];
    }

    /**
     * The whole table: family -> tier -> the fields that put a request in it.
     *
     * ⚠️ **A FAMILY'S KEY SET IS THE STATEMENT.** `TextSearch` has no
     * `Essentials` key and `NearbySearch` has neither `IdsOnly` nor
     * `Essentials`, because Google sells no such SKU — and a lint asserts that
     * the tiers present here are exactly the tiers
     * {@see PlacesSku::fromFamilyAndTier()} can answer for, in both directions,
     * so neither side can quietly grow a tier the other has never heard of.
     *
     * @return array<string, array<string, list<string>>>
     */
    public static function table(): array
    {
        return [
            PlacesSkuFamily::PlaceDetails->value => [
                PlacesSkuTier::IdsOnly->value => [
                    'attributions', 'consumerAlert', 'id', 'movedPlace', 'movedPlaceId', 'name', 'photos',
                ],
                PlacesSkuTier::Essentials->value => [
                    'addressComponents', 'addressDescriptor', 'adrFormatAddress', 'formattedAddress',
                    'location', 'plusCode', 'postalAddress', 'shortFormattedAddress', 'types', 'viewport',
                ],
                PlacesSkuTier::Pro->value => [
                    'accessibilityOptions', 'businessStatus', 'containingPlaces', 'displayName',
                    'googleMapsLinks', 'googleMapsTypeLabel', 'googleMapsUri', 'iconBackgroundColor',
                    'iconMaskBaseUri', 'openingDate', 'primaryType', 'primaryTypeDisplayName',
                    'pureServiceAreaBusiness', 'subDestinations', 'timeZone', 'utcOffsetMinutes',
                ],
                PlacesSkuTier::Enterprise->value => self::enterpriseFields(),
                PlacesSkuTier::EnterpriseAtmosphere->value => self::atmosphereFields(),
            ],

            // ⛔ NO `Essentials` KEY. Google's Text Search page lists four tiers:
            // Essentials (IDs Only), Pro, Enterprise, Enterprise + Atmosphere.
            // `formattedAddress`, `location` and `types` — the address-level
            // fields that ARE Essentials on Place Details — are Pro here.
            PlacesSkuFamily::TextSearch->value => [
                PlacesSkuTier::IdsOnly->value => [
                    'attributions', 'consumerAlert', 'id', 'movedPlace', 'movedPlaceId', 'name', 'nextPageToken',
                ],
                PlacesSkuTier::Pro->value => [
                    'accessibilityOptions', 'addressComponents', 'addressDescriptor', 'adrFormatAddress',
                    'businessStatus', 'containingPlaces', 'displayName', 'formattedAddress',
                    'googleMapsLinks', 'googleMapsTypeLabel', 'googleMapsUri', 'iconBackgroundColor',
                    'iconMaskBaseUri', 'location', 'openingDate', 'photos', 'plusCode', 'postalAddress',
                    'primaryType', 'primaryTypeDisplayName', 'pureServiceAreaBusiness', 'searchUri',
                    'shortFormattedAddress', 'subDestinations', 'timeZone', 'types', 'utcOffsetMinutes',
                    'viewport',
                ],
                PlacesSkuTier::Enterprise->value => self::enterpriseFields(),
                PlacesSkuTier::EnterpriseAtmosphere->value => self::atmosphereFields(),
            ],

            // ⛔ NO `IdsOnly` AND NO `Essentials` KEY. Nearby Search's cheapest
            // tier is Pro, and `places.id` is in it — so there is no free Nearby
            // call and no cheap one.
            PlacesSkuFamily::NearbySearch->value => [
                PlacesSkuTier::Pro->value => [
                    'accessibilityOptions', 'addressComponents', 'addressDescriptor', 'adrFormatAddress',
                    'attributions', 'businessStatus', 'consumerAlert', 'containingPlaces', 'displayName',
                    'formattedAddress', 'googleMapsLinks', 'googleMapsTypeLabel', 'googleMapsUri',
                    'iconBackgroundColor', 'iconMaskBaseUri', 'id', 'location', 'movedPlace',
                    'movedPlaceId', 'name', 'openingDate', 'photos', 'plusCode', 'postalAddress',
                    'primaryType', 'primaryTypeDisplayName', 'pureServiceAreaBusiness',
                    'shortFormattedAddress', 'subDestinations', 'timeZone', 'types', 'utcOffsetMinutes',
                    'viewport',
                ],
                PlacesSkuTier::Enterprise->value => self::enterpriseFields(),
                PlacesSkuTier::EnterpriseAtmosphere->value => self::atmosphereFields(),
            ],
        ];
    }

    /**
     * The families whose field mask does not choose a price.
     *
     * ⛔ **DECLARED, BECAUSE AN UNDECLARED WAIVER AND A DECLARED ONE LOOK
     * IDENTICAL TO A GREEN SUITE.** Autocomplete (New) bills one SKU per
     * request; its mask selects what comes back and never what it costs, so
     * there is nothing here to derive and pretending otherwise would invent a
     * table Google does not publish. The safety of the waiver is a property of
     * the family rather than of this sentence: Autocomplete has exactly one
     * billable SKU, so no mask can move its price at all — a lint asserts both
     * that this list has one member and that the member has one billable SKU.
     *
     * @return list<PlacesSkuFamily>
     */
    public static function familiesWithoutFieldMaskTiers(): array
    {
        return [PlacesSkuFamily::Autocomplete];
    }

    /**
     * The top-level field names a mask asks for, `places.` stripped.
     *
     * ⚠️ **ONE NORMALISER, NOT TWO.** {@see PricedFieldMask::fields()} — which
     * decides what {@see PlaceSummary::askedFor()} may
     * infer — delegates here rather than keeping a twin, because a lint or a
     * pricing table holding its own copy of the pattern it reads is 8460's shape
     * even when both copies are correct today. The two questions are genuinely
     * the same question: Google tiers a mask by its top-level field, and
     * `location.latitude` costs what `location` costs.
     *
     * @return list<string>
     */
    public static function leaves(string $fieldMask): array
    {
        $fields = [];

        foreach (explode(',', $fieldMask) as $path) {
            $path = trim($path);

            if (str_starts_with($path, 'places.')) {
                $path = substr($path, 7);
            }

            $leaf = explode('.', $path)[0];

            if ($leaf !== '' && ! in_array($leaf, $fields, true)) {
                $fields[] = $leaf;
            }
        }

        return $fields;
    }

    /**
     * Which tier a single field puts a request in, or null if this table does
     * not price it.
     *
     * ⚠️ **`null` IS "GOOGLE MAY HAVE MOVED", NEVER "FREE".** Callers must treat
     * it as a refusal — see {@see self::skuFor()}, which throws rather than
     * guessing downward. A field this table has never heard of is most likely a
     * field Google added after {@see self::FETCHED_ON}, and the two cheapest
     * assumptions available (drop it, or price it at the bottom) are both the
     * assumption that costs money.
     */
    public static function tierFor(PlacesSkuFamily $family, string $field): ?PlacesSkuTier
    {
        foreach (self::table()[$family->value] ?? [] as $tier => $fields) {
            if (in_array($field, $fields, true)) {
                return PlacesSkuTier::from($tier);
            }
        }

        return null;
    }

    /**
     * The SKU Google bills for this family asked with this field mask.
     *
     * ⛔ **THE ONE PLACE A MASK BECOMES MONEY.** Nothing else in this
     * application may choose a `PlacesSku` for a request; that is what stops the
     * ledger recording an intention instead of a bill.
     *
     * @throws PlacesFieldNotPriced when a field is unknown to this table, or
     *                              when Google sells no SKU at the tier the mask
     *                              lands on
     */
    public static function skuFor(PlacesSkuFamily $family, string $fieldMask): PlacesSku
    {
        if (in_array($family, self::familiesWithoutFieldMaskTiers(), true)) {
            return PlacesSku::AutocompleteRequests;
        }

        $fields = self::leaves($fieldMask);

        if ($fields === []) {
            throw PlacesFieldNotPriced::emptyMask($family);
        }

        $highest = null;

        foreach ($fields as $field) {
            $tier = self::tierFor($family, $field);

            if ($tier === null) {
                throw PlacesFieldNotPriced::unknownField($family, $field);
            }

            $highest = $highest === null ? $tier : $highest->higherOf($tier);
        }

        $sku = PlacesSku::fromFamilyAndTier($family, $highest);

        if ($sku === null) {
            throw PlacesFieldNotPriced::unsoldTier($family, $highest);
        }

        return $sku;
    }
}
