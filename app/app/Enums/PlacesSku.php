<?php

declare(strict_types=1);

namespace App\Enums;

use App\Support\PlacesFieldTiers;

/**
 * The Google Places SKUs the free instant audit can bill, and what each costs.
 *
 * WHY THE PRICES LIVE IN CODE AT ALL. Decision 193 set the free audit's ceiling
 * at 250 audits/day and **deliberately did not record a dollar figure**, leaving
 * the conversion to this slice with the instruction that SKU prices be "verified
 * against live Google docs, never quoted from memory". These figures were read
 * from Google's own pricing and field-mask pages on 2026-07-31 (see
 * VERIFIED_ON). They are here rather than in config because a price is not a
 * setting — nobody should be able to make the audit look cheaper by editing an
 * environment variable.
 *
 * THE BILLING MODEL, WHICH IS THE PART THAT SURPRISES PEOPLE. Places (New) bills
 * per request at **the highest SKU any requested field belongs to**, decided by
 * the `X-Goog-FieldMask` header: "if you select fields in both the Essentials and
 * the Pro SKUs, you are billed based on the Pro SKU". So the cost of a call is
 * set by its single most expensive field, and adding one innocuous-looking field
 * to a field mask can double the price of every call that uses it.
 *
 * The three fields that do exactly that, and which the audit genuinely wants:
 *
 *   rating, userRatingCount, regularOpeningHours   Enterprise    $20.00/1k
 *   reviews, editorialSummary            Ent + Atmosphere        $25.00/1k
 *
 * THE ATMOSPHERE ROW WAS WRONG UNTIL 2026-07-31, AT $40.00, AND THE SHAPE OF
 * THE ERROR IS WORTH KEEPING. $40.00 is a real published price — it belongs to
 * **Text Search** Enterprise + Atmosphere and **Nearby Search** Enterprise +
 * Atmosphere, two rows away in the same table. Every other figure here was read
 * correctly. So the failure was not carelessness about prices; it was reading
 * the right column of the wrong row in a table where three SKU families each
 * have a tier called "Enterprise + Atmosphere". Verify by full SKU name, never
 * by tier (decisions 239, 253).
 *
 * `reviews` is the expensive one and it is not optional: reply-rate — "12 reviews
 * have no reply" in `29` §6.2 — cannot be computed without it. There is no
 * cheaper field that carries it.
 *
 * WHAT THIS COSTS AT THE CEILING. See PlacesSpend. The short version: 250
 * audits/day is roughly **$470/month** of Google spend after the free monthly
 * allowances, and that figure was not visible when 193 was taken. It is an owner decision, not ours, so nothing here changes 193 — the
 * budget still seeds at 250. What this slice adds is that the spend is metered,
 * bounded by a derived dollar ceiling, and visible.
 *
 * ⚠️ **THE $20.00 AND $25.00 ON THOSE TWO ROWS ARE THE *PLACE DETAILS* FAMILY'S
 * FIGURES**, because the audit's Enterprise and Atmosphere fields arrive on a
 * Place Details call. The same two tier NAMES cost $35.00 and $40.00 on Text
 * Search and on Nearby Search. Stated here because reading a tier name without
 * its family is the exact move that produced the $40.00 error above, and the
 * corrected block was still one family-name short of saying so.
 *
 * ⛔ **A SKU IS NO LONGER CHOSEN BY HAND ANYWHERE — 2026-08-29.** A caller names
 * a field mask; {@see PlacesFieldTiers::skuFor()} derives the SKU
 * Google bills for it, from a table transcribed from Google's own field-mask
 * pages and carrying its own fetch date. Before that, `PlacesSpend::record()`
 * wrote the price of the SKU **the caller passed** while Google priced the mask
 * **actually sent**, and the only authority for the two agreeing was this
 * docblock — a record of what we intended standing in for a record of what
 * happened, on the ledger column the one surviving per-tenant dollar cap sums.
 *
 * PRICES DRIFT. Google restructured Places pricing once already (the shared $200
 * monthly credit became per-SKU free allowances). Anything reading these numbers
 * should check VERIFIED_ON before trusting them, and `places:verify-pricing`
 * prints every price, SKU code and allowance here beside Google's own page, so
 * re-checking is a command and a page rather than a research project.
 *
 * ⚠️ **GOOGLE NOW PUBLISHES A VOLUME BAND** — "$32.00–$2.40 per 1K" — where the
 * lower figure applies above 100,000 calls a month. Every figure here is the
 * top of the band, which is the conservative planning price and the one a
 * re-verification must be careful to read.
 */
enum PlacesSku: string
{
    /**
     * The date these prices and field-mask tiers were read from Google's live
     * documentation. Bump it only after re-reading, never to silence a warning.
     *
     * ⛔ **RE-READ 2026-08-29, AND THE RE-READ FOUND SOMETHING** — which is the
     * argument for bumping this rather than the reason to be nervous about it.
     * Every price, every SKU code and every free allowance was compared against
     * Google's published list on that date. **Every price but one, every SKU
     * code but one, and every allowance was unchanged.** The one that moved is
     * {@see self::TextSearchEssentials}: a tier Google does not sell, priced at
     * $2.32/1,000, carrying the SKU code of Google's *free* Text Search IDs-only
     * SKU. Two real SKUs the enum had no case for were added with it.
     *
     * ⚠️ **THE THREE PRICES THE AUDIT ARITHMETIC RESTS ON DID NOT MOVE** — Text
     * Search Pro 3200, Place Details Enterprise + Atmosphere 2500, Nearby Search
     * Enterprise 3500 — so `PlacesSpend`'s 9.2c per audit, $23.00/day and
     * ~$470/month all still hold, and decision 224's acceptance is untouched.
     *
     * ⚠️ **THE FIELD-MASK TIERS HAVE THEIR OWN DATE NOW**, on
     * {@see PlacesFieldTiers::FETCHED_ON}, because prices and
     * tier membership move independently at Google and one date covering both
     * is a date that is half true. This constant is the **prices**.
     */
    public const string VERIFIED_ON = '2026-08-29';

    /**
     * Text Search returning place IDs only. Unlimited free usage.
     *
     * Google's full SKU name is **Text Search Essentials (IDs Only)**, code
     * `635D-A9DD-C520` — see {@see self::TextSearchEssentials} for where that
     * code used to be written and what it cost to have it there.
     */
    case TextSearchIdsOnly = 'text_search_ids_only';

    /**
     * ⛔ **A TIER GOOGLE DOES NOT SELL. RETAINED, NOT USED, AND REPRICED.**
     *
     * Found 2026-08-29 by fetching Google's own pages rather than re-reading
     * this file. Text Search has **four** SKUs — Essentials (IDs Only), Pro,
     * Enterprise, Enterprise + Atmosphere — and no mid Essentials tier at all.
     * The address-level fields this case was written for (`formattedAddress`,
     * `location`, `types`) are **Text Search Pro**, at $32.00/1,000.
     *
     * ⚠️ **AND THE SKU CODE ON THE OLD DOCBLOCK BELONGED TO SOMETHING ELSE.**
     * `635D-A9DD-C520` is Google's *Text Search Essentials (IDs Only)*, which is
     * **unlimited free** — so the citation that made this case look verified was
     * pointing at the one Text Search SKU that costs nothing, while the case
     * charged $2.32/1,000. It now sits on {@see self::TextSearchIdsOnly}, where
     * it belongs.
     *
     * ⛔ **IT IS NOT DELETED, FOR {@see self::PlaceDetailsAtmosphere}'s REASON**:
     * `places_api_calls.sku` holds this string on any row ever written with it,
     * and a removed case turns those rows into a cast failure on read. It has no
     * call site in `app/` and {@see self::fromFamilyAndTier()} can never return
     * it, so nothing can produce a new one — a lint asserts both.
     *
     * ⛔ **THE PRICE MOVED 232 -> 3200 AND THAT IS DELIBERATE.** Historic rows
     * are untouched, because `record()` copies the price onto the row at write
     * time. What changes is what a hand-written call would cost the meter: at
     * 232 it under-billed a real Text Search by 13.8× on the column the tenant
     * ceiling sums, and this class's whole design is that a wrong guess costs
     * *more* spend refused, never less.
     */
    case TextSearchEssentials = 'text_search_essentials';

    /** Text Search including displayName / primaryType. SKU 4FDA-34B1-A910. */
    case TextSearchPro = 'text_search_pro';

    /** Text Search including rating / hours / phone. SKU E967-44BC-B44D. */
    case TextSearchEnterprise = 'text_search_enterprise';

    /** Place Details returning ids, name and photo refs only. Free. */
    case PlaceDetailsIdsOnly = 'place_details_ids_only';

    /** Place Details, address-level fields. SKU 6E05-E1C3-8D85. */
    case PlaceDetailsEssentials = 'place_details_essentials';

    /** Place Details including displayName / primaryType. SKU 4ED6-464A-2AFC. */
    case PlaceDetailsPro = 'place_details_pro';

    /** Place Details including rating, userRatingCount, hours. SKU 2D9A-3DE0-3766. */
    case PlaceDetailsEnterprise = 'place_details_enterprise';

    /**
     * Place Details including reviews and editorialSummary. The dear one.
     *
     * Google's full SKU name is **Place Details Enterprise + Atmosphere**. The
     * case is named for the tier alone, which is how it came to be priced from
     * a different family's row — see the class docblock. The string value is
     * left alone deliberately: it is written into `places_api_calls.sku` on
     * every call ever metered, and renaming it would orphan the ledger.
     */
    case PlaceDetailsAtmosphere = 'place_details_atmosphere';

    /**
     * Text Search including reviews or editorialSummary. SKU 120C-BEC3-B48B.
     *
     * ⚠️ **NOTHING SENDS THIS MASK AND THE CASE EXISTS SO THAT NOTHING CAN SEND
     * IT BY ACCIDENT EITHER.** Without it, adding `places.reviews` to the text
     * search mask would land on a tier {@see self::fromFamilyAndTier()} could
     * not name; with it, the call simply reprices from $32.00 to $40.00 and the
     * ledger says so. $40.00 is the figure decision 253 records being read off
     * this row into a Place Details call — it is a real price, and this is the
     * row it belongs to.
     */
    case TextSearchAtmosphere = 'text_search_atmosphere';

    /** Nearby Search including displayName. SKU 99F9-A108-83A6. */
    case NearbySearchPro = 'nearby_search_pro';

    /** Nearby Search including rating. SKU 772E-9975-BE34. */
    case NearbySearchEnterprise = 'nearby_search_enterprise';

    /**
     * Nearby Search including reviews. SKU F20E-7034-0EF7.
     *
     * ⛔ **THE EXECUTABLE FORM OF `GooglePlacesClient::nearby()`'s STRONGEST
     * COMMENT.** That comment says `reviews` "MUST STAY ABSENT"; before this
     * case existed, adding it would have left the call metered at Nearby Search
     * Enterprise while Google billed Enterprise + Atmosphere. The comment is
     * still right and is now also enforced: the mask decides, and widening it
     * moves 3500 to 4000 on the ledger without anybody remembering to.
     */
    case NearbySearchAtmosphere = 'nearby_search_atmosphere';

    /**
     * One Autocomplete (New) request. SKU 4EF4-B17C-B31A.
     *
     * SESSION TOKENS DO NOT MAKE THIS FREE, WHICH IS THE OPPOSITE OF WHAT
     * EVERYONE ASSUMES — including the first draft of this slice. Google's
     * session-pricing page is explicit: within a session that ends in a Place
     * Details call, "for Autocomplete (New) requests **13 and higher** in the
     * same session: you are billed at the SKU: Autocomplete Session Usage,
     * meaning there is no charge for those requests". Requests **1 through 12
     * are billed individually at this SKU**, session token or not.
     *
     * A visitor typing "joes pizza brook" against a debounced input generates
     * perhaps three to eight requests. All of them are billed. A session token
     * would start paying for itself on the thirteenth keystroke-batch, which is
     * a user who has given up rather than a user we are helping.
     *
     * So this slice does not send `sessionToken` at all, and the absence is a
     * decision rather than an oversight. Threading a token from the browser,
     * through the audit row, into a queued Place Details call that may run
     * minutes later would be real complexity — and at our request volume it
     * would save exactly nothing. `AutocompleteSessionUsage` is deliberately not
     * a case here for the same reason AuditStatus has no `Skipped`: a state
     * nothing can produce is a state that misleads whoever reads the enum next.
     *
     * WHAT IT COSTS INSTEAD. At $2.83/1,000 a typing session runs about 1.4c.
     * That is small per visitor and large in aggregate, because autocomplete
     * bills on *typing* rather than on audits — including the majority of
     * visitors who type a name and never submit. At the decision 193 ceiling
     * with a realistic submit rate it is roughly **$530/month**, comparable to
     * the entire rest of the audit. Which is why it has its own daily budget in
     * PlacesSpend rather than sharing the audit's: on the shared ceiling a heavy
     * typing day would starve the product it exists to sell.
     */
    case AutocompleteRequests = 'autocomplete_requests';

    /**
     * List price in **integer cents per 1,000 requests**, USD.
     *
     * Cents per thousand rather than per call because every published figure is
     * exact at this scale ($32.00 -> 3200, $2.83 -> 283) and a per-call integer
     * would round 3.2 cents to 3 and understate the bill by 6%. Money is integer
     * cents in this codebase without exception (`18` §Money handling); this is
     * that rule at the only precision the source data actually has.
     */
    public function centsPerThousand(): int
    {
        return match ($this) {
            self::TextSearchIdsOnly, self::PlaceDetailsIdsOnly => 0,
            // ⛔ 3200, NOT 232 — see the case. Google sells no Text Search tier
            // between IDs-Only and Pro, so the cheapest thing this case could
            // possibly describe is a Pro call.
            self::TextSearchEssentials => 3200,
            self::TextSearchPro => 3200,
            self::TextSearchEnterprise => 3500,
            self::PlaceDetailsEssentials => 500,
            self::PlaceDetailsPro => 1700,
            self::PlaceDetailsEnterprise => 2000,
            self::PlaceDetailsAtmosphere => 2500,
            self::TextSearchAtmosphere => 4000,
            self::NearbySearchPro => 3200,
            self::NearbySearchEnterprise => 3500,
            self::NearbySearchAtmosphere => 4000,
            self::AutocompleteRequests => 283,
        };
    }

    /**
     * Requests Google bills at $0 each month before this SKU starts charging.
     *
     * Not a discount to plan around — it is roughly two days of the 193 ceiling
     * for the Enterprise SKUs, so the honest planning assumption is that every
     * call costs list price and the allowance is a rounding error.
     */
    public function freeRequestsPerMonth(): int
    {
        return match ($this) {
            self::TextSearchIdsOnly, self::PlaceDetailsIdsOnly => PHP_INT_MAX,
            self::PlaceDetailsEssentials, self::AutocompleteRequests => 10_000,
            // TextSearchEssentials sits here rather than on the 10,000 row it
            // used to share: it is repriced as a Text Search Pro call, and Pro's
            // allowance is 5,000.
            self::TextSearchPro, self::TextSearchEssentials, self::PlaceDetailsPro,
            self::NearbySearchPro => 5_000,
            self::TextSearchEnterprise, self::PlaceDetailsEnterprise,
            self::NearbySearchEnterprise, self::PlaceDetailsAtmosphere,
            self::TextSearchAtmosphere, self::NearbySearchAtmosphere => 1_000,
        };
    }

    public function isFree(): bool
    {
        return $this->centsPerThousand() === 0;
    }

    /**
     * Which endpoint family this SKU belongs to.
     *
     * ⛔ **A FAMILY AND A TIER TOGETHER NAME A PRICE; NEITHER DOES ALONE.** Three
     * families each have a tier called "Enterprise" and a tier called
     * "Enterprise + Atmosphere", at six different prices, which is how decision
     * 253's $40.00 came to be written against a $25.00 call.
     */
    public function family(): PlacesSkuFamily
    {
        return match ($this) {
            self::TextSearchIdsOnly, self::TextSearchEssentials, self::TextSearchPro,
            self::TextSearchEnterprise, self::TextSearchAtmosphere => PlacesSkuFamily::TextSearch,
            self::PlaceDetailsIdsOnly, self::PlaceDetailsEssentials, self::PlaceDetailsPro,
            self::PlaceDetailsEnterprise, self::PlaceDetailsAtmosphere => PlacesSkuFamily::PlaceDetails,
            self::NearbySearchPro, self::NearbySearchEnterprise,
            self::NearbySearchAtmosphere => PlacesSkuFamily::NearbySearch,
            self::AutocompleteRequests => PlacesSkuFamily::Autocomplete,
        };
    }

    /**
     * Which field-mask tier this SKU is the price of, or null for a SKU whose
     * price no field mask selects.
     *
     * ⚠️ **`AutocompleteRequests` IS THE `null`, AND IT IS THE ONLY ONE.**
     * Autocomplete (New) bills per request at one SKU whatever its mask asks
     * for — see {@see PlacesFieldTiers::familiesWithoutFieldMaskTiers()},
     * where that waiver is declared rather than left to be inferred from a
     * `null` here.
     *
     * ⚠️ **{@see self::TextSearchEssentials} IS NOT THE OTHER `null`.** It
     * answers `Essentials`, honestly, for a tier Google does not sell on this
     * family — so {@see self::fromFamilyAndTier()} never returns it, and the
     * round trip is asymmetric on purpose.
     */
    public function tier(): ?PlacesSkuTier
    {
        return match ($this) {
            self::TextSearchIdsOnly, self::PlaceDetailsIdsOnly => PlacesSkuTier::IdsOnly,
            self::TextSearchEssentials, self::PlaceDetailsEssentials => PlacesSkuTier::Essentials,
            self::TextSearchPro, self::PlaceDetailsPro, self::NearbySearchPro => PlacesSkuTier::Pro,
            self::TextSearchEnterprise, self::PlaceDetailsEnterprise,
            self::NearbySearchEnterprise => PlacesSkuTier::Enterprise,
            self::TextSearchAtmosphere, self::PlaceDetailsAtmosphere,
            self::NearbySearchAtmosphere => PlacesSkuTier::EnterpriseAtmosphere,
            self::AutocompleteRequests => null,
        };
    }

    /**
     * The SKU Google bills for a request of this family landing on this tier,
     * or null where Google sells no such SKU.
     *
     * ⛔ **THE `null` ARMS ARE THE FINDINGS OF 2026-08-29 AND ARE NOT HOLES.**
     * Text Search has no mid `Essentials` SKU and Nearby Search has neither
     * `IdsOnly` nor `Essentials`; {@see PlacesFieldTiers::table()}
     * carries no fields at those tiers for those families, so nothing can ask
     * for one — and a lint asserts the two sides agree in **both** directions,
     * because a table that grew a tier this method cannot answer, and a method
     * that answers for a tier the table cannot reach, are two different failures
     * and only one of them is visible from either side.
     */
    public static function fromFamilyAndTier(PlacesSkuFamily $family, PlacesSkuTier $tier): ?self
    {
        return match ($family) {
            PlacesSkuFamily::TextSearch => match ($tier) {
                PlacesSkuTier::IdsOnly => self::TextSearchIdsOnly,
                PlacesSkuTier::Essentials => null,
                PlacesSkuTier::Pro => self::TextSearchPro,
                PlacesSkuTier::Enterprise => self::TextSearchEnterprise,
                PlacesSkuTier::EnterpriseAtmosphere => self::TextSearchAtmosphere,
            },
            PlacesSkuFamily::PlaceDetails => match ($tier) {
                PlacesSkuTier::IdsOnly => self::PlaceDetailsIdsOnly,
                PlacesSkuTier::Essentials => self::PlaceDetailsEssentials,
                PlacesSkuTier::Pro => self::PlaceDetailsPro,
                PlacesSkuTier::Enterprise => self::PlaceDetailsEnterprise,
                PlacesSkuTier::EnterpriseAtmosphere => self::PlaceDetailsAtmosphere,
            },
            PlacesSkuFamily::NearbySearch => match ($tier) {
                PlacesSkuTier::IdsOnly, PlacesSkuTier::Essentials => null,
                PlacesSkuTier::Pro => self::NearbySearchPro,
                PlacesSkuTier::Enterprise => self::NearbySearchEnterprise,
                PlacesSkuTier::EnterpriseAtmosphere => self::NearbySearchAtmosphere,
            },
            // Autocomplete's price is not selected by a mask at all, so there
            // is no tier to answer for. The waiver is declared in
            // PlacesFieldTiers::familiesWithoutFieldMaskTiers(), which is what
            // stops this arm reading as an oversight.
            PlacesSkuFamily::Autocomplete => null,
        };
    }

    /**
     * Google's own SKU code, as printed on its pricing list.
     *
     * ⚠️ **HERE RATHER THAN IN A DOCBLOCK BECAUSE THAT IS WHERE ONE OF THEM WAS
     * WRONG FOR A MONTH.** `635D-A9DD-C520` sat on {@see self::TextSearchEssentials}
     * — a $2.32 case — and belongs to *Text Search Essentials (IDs Only)*, which
     * is free. A code in prose is checked by whoever happens to read it; a code
     * a command prints beside its price is checked every time somebody
     * re-verifies. `places:verify-pricing` prints these.
     *
     * `null` for the two IDs-only cases where the code is shared with, or
     * absent from, the published list.
     */
    public function skuCode(): ?string
    {
        return match ($this) {
            self::TextSearchIdsOnly => '635D-A9DD-C520',
            self::TextSearchEssentials => null,
            self::TextSearchPro => '4FDA-34B1-A910',
            self::TextSearchEnterprise => 'E967-44BC-B44D',
            self::TextSearchAtmosphere => '120C-BEC3-B48B',
            self::PlaceDetailsIdsOnly => '5C36-E272-E88F',
            self::PlaceDetailsEssentials => '6E05-E1C3-8D85',
            self::PlaceDetailsPro => '4ED6-464A-2AFC',
            self::PlaceDetailsEnterprise => '2D9A-3DE0-3766',
            self::PlaceDetailsAtmosphere => 'EB23-5ECC-F753',
            self::NearbySearchPro => '99F9-A108-83A6',
            self::NearbySearchEnterprise => '772E-9975-BE34',
            self::NearbySearchAtmosphere => 'F20E-7034-0EF7',
            self::AutocompleteRequests => '4EF4-B17C-B31A',
        };
    }
}
