<?php

declare(strict_types=1);

namespace App\Services\Places;

use App\Contracts\PlacesClient;
use App\Enums\PlacesSku;
use App\Enums\PlacesSkuFamily;
use App\Exceptions\PlacesBudgetExhausted;
use App\Exceptions\PlacesFieldNotPriced;
use App\Exceptions\PlacesRequestFailed;
use App\Modules\X206\Actions\CredentialFetchAction;
use App\Services\Config\DefaultsRegistry;
use App\Support\PlacesFieldTiers;
use App\Support\PlatformCredentials;
use App\Support\Tenancy;
use App\Support\VendorLog;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Google Places API (New), metered and budgeted.
 *
 * Endpoints, headers and body fields read from Google's live documentation on
 * 2026-07-31. Two things that reading rather than remembering caught:
 *
 *   - Text Search takes **`pageSize`**; `maxResultCount` is deprecated there.
 *     Nearby Search still takes `maxResultCount`. They are not interchangeable
 *     and the wrong one is silently ignored, which reads as "Google returned 20
 *     results and ignored my limit" — a cost bug, not a correctness bug, and
 *     therefore one nobody notices until the bill.
 *   - The field mask is **mandatory**: "if you omit the field mask, the method
 *     returns an error". There is no default, so there is no accidental cheap
 *     call and no accidental expensive one — the mask is always deliberate.
 *
 * WHY THIS DOES NOT EXTEND ProviderClient. That base class exists to guarantee
 * every provider call carries a tenant's OAuth token from the vault, and takes a
 * Business to do it. Since 2026-09-23 a tenant may store their own key (X-206 google_places); it is preferred under tenancy, the platform key remains the default.
 *
 * WHAT IT KEEPS FROM ProviderClient, deliberately: Laravel's HTTP client so
 * Http::fake() and preventStrayRequests() work; VendorLog so the call shape is
 * logged and the payload never is; and no synchronous retry — one attempt, then
 * a classified failure, with retrying left to the job that has backoff.
 */
final class GooglePlacesClient implements PlacesClient
{
    private const string BASE = 'https://places.googleapis.com/v1';

    /**
     * The one platform key this client runs on.
     *
     * ⛔ **NAMED ONCE BECAUSE IT IS NOW ASKED TWICE** — `has()` before every
     * call and `get()` when the request is built. Two typed literals is how the
     * guard and the read drift apart, and a guard that checks a different key
     * from the one that is read is worse than no guard: it reads as considered.
     */
    private const string CREDENTIAL = 'google_places_key';

    public const string TENANT_SERVICE = 'google_places';

    /**
     * `29` §6.2: "cache by place_id 24h (repeat lookups free)".
     */
    public const int CACHE_TTL_SECONDS = 86400;

    /**
     * Enough to compute a reply rate without paying for a second page.
     *
     * Places returns at most five reviews per place and there is no paging to a
     * sixth, so reply-rate here is "of the reviews Google shows", which is what
     * the finding must say. It is not "of all your reviews", and the copy in
     * slice E must not imply that it is.
     */
    private const int REVIEW_SAMPLE = 5;

    /**
     * ⛔ **EVERY FIELD MASK THIS CLIENT SENDS, AS A CONSTANT, BECAUSE A MASK IS
     * A PRICE.** They were locals until 2026-08-29, which meant nothing outside
     * the method that built one could ask what it costs — so the only check that
     * the SKU beside it was the right SKU was a comment. They are constants so
     * that {@see self::pricedMasks()} can hand the whole population to a lint
     * without anybody re-typing one, which is 8460's shape.
     *
     * ⚠️ **THE `places.` PREFIX IS PART OF THE MASK AND NOT PART OF THE
     * PRICE.** Google tiers a request by its top-level field either way; see
     * {@see PlacesFieldTiers::leaves()}, which is the single
     * normaliser both the pricing table and {@see PricedFieldMask::fields()}
     * use.
     */
    private const string AUTOCOMPLETE_MASK =
        'suggestions.placePrediction.placeId,suggestions.placePrediction.structuredFormat';

    private const string TEXT_SEARCH_MASK =
        'places.id,places.displayName,places.formattedAddress,places.location,places.primaryType,places.types';

    private const string DETAILS_MASK =
        'id,displayName,formattedAddress,location,primaryType,types,photos,'
        .'regularOpeningHours,rating,userRatingCount,editorialSummary,'
        .'websiteUri,nationalPhoneNumber,reviews';

    private const string NEARBY_MASK =
        'places.id,places.displayName,places.rating,places.userRatingCount,places.primaryType';

    /**
     * Every mask above with the family it is sent to, keyed by the method that
     * sends it — **unpriced**.
     *
     * ⛔ **DERIVED FROM THE POPULATION AND NEVER RE-TYPED.** A lint asking "does
     * every field in every mask we send have a price?" has to get the masks from
     * here; a lint holding its own copy of them is satisfied by its own copy
     * (8460), and this is the exact axis — a record of what we intended standing
     * in for a record of what happened.
     *
     * ⛔ **UNPRICED, AND THAT SEPARATION IS LOAD-BEARING RATHER THAN TIDY.**
     * {@see self::pricedMasks()} throws for a mask that cannot be priced, so a
     * coverage lint built on it could never observe the state it exists to
     * report: the throw would arrive first and the lint's own sentence — the one
     * naming the field, the family and the page to re-read — would never render.
     * A lint whose failure branch is unreachable is 256's shape, so the raw
     * masks are reachable on their own.
     *
     * @return array<string, array{family: PlacesSkuFamily, mask: string}>
     */
    public static function fieldMasks(): array
    {
        return [
            'autocomplete' => ['family' => PlacesSkuFamily::Autocomplete, 'mask' => self::AUTOCOMPLETE_MASK],
            'textSearch' => ['family' => PlacesSkuFamily::TextSearch, 'mask' => self::TEXT_SEARCH_MASK],
            'details' => ['family' => PlacesSkuFamily::PlaceDetails, 'mask' => self::DETAILS_MASK],
            'nearby' => ['family' => PlacesSkuFamily::NearbySearch, 'mask' => self::NEARBY_MASK],
        ];
    }

    /**
     * The same masks, priced.
     *
     * ⚠️ **CONSTRUCTING THIS IS ITSELF A CHECK** — `PricedFieldMask::for()`
     * throws for a mask nothing can price — but it is the second check rather
     * than the first, for the reason above.
     *
     * @return array<string, PricedFieldMask>
     *
     * @throws PlacesFieldNotPriced
     */
    public static function pricedMasks(): array
    {
        return array_map(
            static fn (array $shape): PricedFieldMask => PricedFieldMask::for($shape['family'], $shape['mask']),
            self::fieldMasks(),
        );
    }

    /**
     * $purpose is the **untenanted** purpose only — see `purpose()`.
     */
    public function __construct(
        private readonly PlacesSpend $spend,
        private readonly DefaultsRegistry $defaults,
        private readonly string $purpose = PlacesSpend::AUDIT_PURPOSE,
    ) {}

    private function cacheTtlSeconds(): int
    {
        return $this->defaults->int('places.cache_ttl_seconds');
    }

    /**
     * Which budget this call answers to, decided per call rather than per
     * instance.
     *
     * ⚠️ **DERIVED FROM TENANCY, DELIBERATELY, AND THIS IS THE LOAD-BEARING
     * LINE.** The alternative — a purpose fixed on the instance, chosen by
     * whoever wired the container — is what put the nightly competitor sync on
     * the visitor audit budget: the binding was written once for the audit, the
     * second caller resolved the same binding, and nothing anywhere said so.
     * That failure is silent, and it is the shape `CLAUDE.md` lists as "a
     * protection layer asserted before it is true".
     *
     * Tenancy is the honest discriminator rather than a convenient proxy: the
     * free audit is an unauthenticated endpoint that runs before signup and
     * therefore has no tenant *by construction*, and anything running inside a
     * tenant session is that tenant's work being done on their behalf. A new
     * tenant-facing caller is attributed correctly without having to know this
     * class exists, which is the property the container binding did not have.
     */
    private function purpose(): string
    {
        return Tenancy::id() !== null ? PlacesSpend::TENANT_PURPOSE : $this->purpose;
    }

    public function autocomplete(string $query, ?string $regionCode = null): array
    {
        // ⛔ **BEFORE THE CACHE, DELIBERATELY.** A cached suggestion would work
        // without a key, and a dropdown that answers for the queries somebody
        // else typed today and not for anything else is a harder thing to
        // report than one that is simply quiet. Uniform beats slightly better.
        if (! $this->isConfigured('POST')) {
            return [];
        }

        // ⚠️ Autocomplete's mask does not choose its price and the waiver is
        // declared in PlacesFieldTiers::familiesWithoutFieldMaskTiers(). It goes
        // through the same seam anyway so that "which SKU does this call bill"
        // has one answer for all four methods rather than three plus a habit.
        $priced = PricedFieldMask::for(PlacesSkuFamily::Autocomplete, self::AUTOCOMPLETE_MASK);
        $purpose = PlacesSpend::AUTOCOMPLETE_PURPOSE;
        $cacheKey = $this->suggestionCacheKey($query, $regionCode);

        /** @var list<array{placeId: string, mainText: string, secondaryText: ?string}>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            $this->spend->record($priced->sku, $purpose, servedFromCache: true);

            return array_map($this->toSuggestion(...), $cached);
        }

        // Degrades rather than throws — see the contract. A visitor who is still
        // typing gets no suggestions and no error, and their submit still works.
        if (! $this->spend->allows($priced->sku, $purpose)) {
            return [];
        }

        // structuredFormat rather than text — see AUTOCOMPLETE_MASK: the dropdown
        // needs the name and the address as separate lines, and Google has
        // already split them. Re-splitting the joined string breaks on any
        // business whose name contains a comma.
        $response = $this->send(
            $priced,
            'POST',
            self::BASE.'/places:autocomplete',
            array_filter([
                'input' => $query,
                'regionCode' => $regionCode,
                // No sessionToken, deliberately. PlacesSku::AutocompleteRequests
                // documents why: requests 1-12 are billed individually with or
                // without one, and nobody types thirteen times.
            ], static fn (mixed $v): bool => $v !== null),
        );

        $suggestions = $this->suggestions($response);

        Cache::put($cacheKey, $suggestions, $this->cacheTtlSeconds());

        $this->spend->record($priced->sku, $purpose);

        return array_map($this->toSuggestion(...), $suggestions);
    }

    public function textSearch(string $query, ?string $regionCode = null): array
    {
        // ⛔ **THE SKU IS DERIVED FROM THE MASK, NEVER NAMED BESIDE IT.** It
        // resolves to Text Search Pro today; if the mask ever gains an
        // Enterprise or Atmosphere field it resolves upward and the ledger says
        // so, which is the difference between metering a call and recording an
        // intention.
        $priced = PricedFieldMask::for(PlacesSkuFamily::TextSearch, self::TEXT_SEARCH_MASK);

        $this->assertConfigured('POST');
        $this->assertBudget($priced->sku);

        // displayName is a Pro field and is why this is a Pro call. The confirm
        // card has to show a human which business we found, so there is no
        // cheaper mask that still does the job — every other field in
        // TEXT_SEARCH_MASK is Pro too, so nothing here can be traded down.
        //
        // ⛔ **IT ASKS FOR NEITHER `rating` NOR `userRatingCount`, WHICH IS
        // CORRECT AND IS WHY THE MASK NOW TRAVELS WITH THE SUMMARY.** Every
        // summary this method returns therefore has a null rating and a null
        // review count for a reason that has nothing to do with the business,
        // and until 2026-08-26 nothing on the object said so.
        //
        // ⚠️ **THIS COMMENT SAID THOSE TWO FIELDS "WOULD DOUBLE WHAT A NAME
        // SEARCH COSTS" AND THEY WOULD NOT — CORRECTED 2026-08-29 AGAINST
        // GOOGLE'S OWN PRICE LIST.** Text Search Pro is $32.00/1,000 and Text
        // Search Enterprise is $35.00/1,000: adding `rating` costs 9.4% more,
        // not 100%. The conclusion is unchanged and the reason was wrong by an
        // order of magnitude, which is the shape this whole slice is about — a
        // guard whose only authority was itself. The refusal is no longer
        // carried by this sentence anyway: the mask is what prices the call.
        $response = $this->send(
            $priced,
            'POST',
            self::BASE.'/places:searchText',
            array_filter([
                'textQuery' => $query,
                'regionCode' => $regionCode,
                // pageSize, not maxResultCount — see the class docblock.
                'pageSize' => 5,
            ], static fn (mixed $v): bool => $v !== null),
        );

        $this->spend->record($priced->sku, $this->purpose(), businessId: Tenancy::id());

        return array_map(
            fn (array $place): PlaceSummary => $this->toSummary($place, $priced),
            $this->places($response),
        );
    }

    public function details(string $placeId): ?PlaceSummary
    {
        $this->assertConfigured('GET');

        // `reviews` is what makes this an Atmosphere call, and reply-rate cannot
        // be computed without it. `editorialSummary` is in the same tier, so the
        // description check rides along at no extra cost — which is the only
        // good news in this field mask.
        //
        // ⚠️ `photos` is Place Details **Essentials (IDs Only)** and is free
        // here: it returns photo *references*. Fetching a photo's bytes is a
        // different endpoint on a SKU this application has no case and no meter
        // for — see PlacesFieldTiers' docblock.
        $priced = PricedFieldMask::for(PlacesSkuFamily::PlaceDetails, self::DETAILS_MASK);

        $cacheKey = $this->cacheKey($placeId, $priced->mask);

        /** @var array<string, mixed>|null $cached */
        $cached = Cache::get($cacheKey);

        if ($cached !== null) {
            // Metered at zero rather than not metered at all: the cache's value
            // has to be provable, not assumed (BUILD-PLAN §2.5.3).
            $this->spend->record(
                $priced->sku,
                $this->purpose(),
                placeId: $placeId,
                servedFromCache: true,
                businessId: Tenancy::id(),
            );

            return $this->toSummary($cached, $priced);
        }

        $this->assertBudget($priced->sku);

        $response = $this->send(
            $priced,
            'GET',
            self::BASE.'/places/'.rawurlencode($placeId),
        );

        /** @var array<string, mixed> $place */
        $place = $response->json();

        if (! isset($place['id'])) {
            return null;
        }

        Cache::put($cacheKey, $place, $this->cacheTtlSeconds());

        $this->spend->record($priced->sku, $this->purpose(), placeId: $placeId, businessId: Tenancy::id());

        return $this->toSummary($place, $priced);
    }

    public function nearby(
        float $latitude,
        float $longitude,
        string $primaryType,
        int $limit = 3,
    ): array {
        $priced = PricedFieldMask::for(PlacesSkuFamily::NearbySearch, self::NEARBY_MASK);

        $this->assertConfigured('POST');
        $this->assertBudget($priced->sku);

        // rating is an Enterprise field and is the whole point of the call.
        // displayName is requested and then deliberately dropped below — it
        // costs nothing extra at this tier, and having it lets slice E dedupe
        // the subject out of its own competitor set.
        //
        // ⚠️ **`reviews` IS ABSENT AND MUST STAY ABSENT** — it is Atmosphere. So
        // a peer summary can corroborate an absent review count against `rating`
        // but never against a review list, which
        // {@see PlaceSummary::knownReviewCount()} is written to survive rather
        // than to require.
        //
        // ⚠️ **THIS SAID IT "WOULD PRICE A THREE-PEER SWEEP LIKE THREE AUDITS"
        // AND IT WOULD NOT — CORRECTED 2026-08-29.** One Nearby request returns
        // all three peers, so adding `reviews` moves that request from Nearby
        // Search Enterprise ($35.00/1,000) to Nearby Search Enterprise +
        // Atmosphere ($40.00/1,000): 14% more, not 3 × $25.00. The figure it
        // quoted belongs to a *different design* — three separate Place Details
        // Atmosphere calls, one per peer, which PlacesSpend's docblock costs at
        // ~$563/month and which decision 196 already refuses. ⛔ **The conclusion
        // stands and the guard no longer rests on the sentence**: adding
        // `places.reviews` here now reprices the call through
        // PlacesSku::NearbySearchAtmosphere, whether or not anybody reads this.
        $response = $this->send(
            $priced,
            'POST',
            self::BASE.'/places:searchNearby',
            [
                'includedTypes' => [$primaryType],
                // Nearby still takes maxResultCount — see the class docblock.
                'maxResultCount' => max(1, min($limit, 20)),
                'locationRestriction' => [
                    'circle' => [
                        'center' => ['latitude' => $latitude, 'longitude' => $longitude],
                        // 5km: wide enough to find peers in a suburb, narrow
                        // enough that "nearby" stays honest in a city.
                        'radius' => 5000.0,
                    ],
                ],
            ],
        );

        $this->spend->record($priced->sku, $this->purpose(), businessId: Tenancy::id());

        return array_map(
            fn (array $place): PlaceSummary => $this->toSummary($place, $priced),
            $this->places($response),
        );
    }

    /**
     * Whether the platform key is present, recorded when it is not.
     *
     * ⛔ **THE GUARD TWO DOCBLOCKS PROMISED FOR MONTHS AND NOBODY WROTE**
     * (9144). `PlatformCredentials::has()`'s own docblock said *"the Places
     * client uses this to degrade rather than explode: a public visitor on the
     * marketing home must never see a stack trace because our key is missing"*
     * and `CredentialManifest`'s entry said the same, in a `degradation`
     * sentence the Ops board renders to an operator **precisely when the key is
     * absent**. `has('google_places_key')` had zero call sites in the tree, and
     * `google_places_key` was unset in the production registry until
     * 2026-08-24: every visitor who typed a business name on the marketing home
     * got a 500, from whenever the path shipped.
     *
     * ⚠️ **THE ABSENCE IS RECORDED HERE AND NOWHERE ELSE**, because from here
     * down there is no request to attribute it to. `VendorLog::failure()` is
     * the only per-call instrument this client has, and *"a call that produced
     * no usable answer"* covers one that was never attempted for a reason worth
     * a line. The Ops credentials board is the authority for **which** key is
     * absent; this is the line that says somebody tried to use it.
     *
     * ⚠️ **`self::BASE` RATHER THAN THE REAL ENDPOINT.** No URL has been built
     * at the moment this runs, and inventing the one the call *would* have used
     * would put a path in the log for a request that never existed.
     */
    private function isConfigured(string $method): bool
    {
        if ($this->keyInUse() === 'tenant' || PlatformCredentials::has(self::CREDENTIAL)) {
            return true;
        }

        // A fixed label, never a message: VendorLog's rule, and there is
        // nothing here a vendor said anyway.
        VendorLog::failure('google_places', $method, self::BASE, 'credential_missing');

        return false;
    }

    /**
     * The same check, for the three methods that may not answer emptily.
     *
     * ⚠️ **`autocomplete()` IS THE ONE THAT RETURNS `[]` AND THAT ASYMMETRY IS
     * THE CONTRACT'S, NOT THIS SLICE'S.** `PlacesClient::autocomplete()` already
     * documents an empty list as its budget-exhausted answer — "a dropdown that
     * stops suggesting is a feature quietly degrading, while a dropdown that
     * raises an exception is a marketing page throwing errors at someone who is
     * still typing". A missing key is the same shape of nothing, so it takes the
     * same answer. The other three throw, because a text search that answered
     * `[]` would be indistinguishable from *"no such business"* — which is a
     * finding, and this is not.
     *
     * @throws PlacesRequestFailed
     */
    private function assertConfigured(string $method): void
    {
        if (! $this->isConfigured($method)) {
            throw PlacesRequestFailed::unconfigured();
        }
    }

    /**
     * ⚠️ **THIS USED TO CHECK THE AUDIT BUDGET WHATEVER `$this->purpose` SAID**,
     * which made the purpose on the ledger and the ceiling it was checked
     * against two different things — so a tenant call could be *recorded* under
     * one budget and *refused* by another. Both now come from `purpose()`.
     *
     * @throws PlacesBudgetExhausted
     */
    private function assertBudget(PlacesSku $sku): void
    {
        if (! $this->spend->allows($sku, $this->purpose(), Tenancy::id())) {
            throw PlacesBudgetExhausted::forSku($sku);
        }
    }

    /**
     * ⛔ **TAKES THE PRICED PAIR RATHER THAN A SKU AND A MASK, SO THAT THE
     * HEADER GOOGLE PRICES AND THE FIGURE THE LEDGER RECORDS CANNOT BE TWO
     * DIFFERENT DECISIONS.** With two parameters, a caller deriving a SKU from
     * mask A and sending mask B compiles, passes review and reads correctly at
     * every call site — and the bill is the only place it shows up.
     *
     * @param  array<string, mixed>  $body
     *
     * @throws PlacesRequestFailed
     */
    private function send(
        PricedFieldMask $priced,
        string $method,
        string $url,
        array $body = [],
    ): Response {
        try {
            $response = VendorLog::timed(
                'google_places',
                $method,
                $url,
                fn (): Response => $method === 'GET'
                    ? $this->request($priced->mask)->get($url)
                    : $this->request($priced->mask)->post($url, $body),
            );
        } catch (ConnectionException) {
            VendorLog::failure('google_places', $method, $url, ConnectionException::class);

            throw PlacesRequestFailed::unreachable();
        }

        if ($response->failed()) {
            throw PlacesRequestFailed::from($response);
        }

        return $response;
    }

    public function keyInUse(): string
    {
        $businessId = Tenancy::id();
        if ($businessId !== null) {
            $own = app(CredentialFetchAction::class)->handle((int) $businessId, self::TENANT_SERVICE);
            if (is_string($own) && trim($own) !== '') {
                return 'tenant';
            }
        }

        return 'platform';
    }

    private function apiKey(): string
    {
        $businessId = Tenancy::id();
        if ($businessId !== null) {
            $own = app(CredentialFetchAction::class)->handle((int) $businessId, self::TENANT_SERVICE);
            if (is_string($own) && trim($own) !== '') {
                return $own;
            }
        }

        return PlatformCredentials::get(self::CREDENTIAL);
    }

    /**
     * The key never reaches a call site — PlatformCredentials is the seam CFG1
     * replaces the inside of (doc 38 D-149, BUILD-PLAN §4.4).
     *
     * ⚠️ **STILL `get()`, AND THAT IS CORRECT.** Every public method on this
     * class asks {@see self::isConfigured()} first, so reaching here with no key
     * is a hole in that guard rather than a deployment state — and a `get()`
     * that raises is how such a hole announces itself instead of sending an
     * empty header to Google and receiving a 401 to blame on the vendor.
     */
    private function request(string $fieldMask): PendingRequest
    {
        return Http::withHeaders([
            'X-Goog-Api-Key' => $this->apiKey(),
            'X-Goog-FieldMask' => $fieldMask,
        ])
            ->timeout((int) config('places.timeout', 8))
            ->acceptJson();
    }

    /**
     * ⛔ **A PLACE WITH NO `id` IS DROPPED, NEVER PASSED THROUGH WITH A BLANK
     * ONE.** `id` is in every field mask this client sends (`:181`, `:222–224`,
     * `:283`), so its absence here can only mean a malformed response — never
     * the "Google omits a field holding its default value" shape
     * `AbsentReviewCountTest`/`AbsentGbpCompletenessTest` are about, because
     * there is no default `id`. Before this, {@see self::toSummary()}'s
     * `(string) ($place['id'] ?? '')` turned that malformed entry into a
     * `PlaceSummary`/`PlaceCandidate` with `placeId === ''` — and
     * `PlaceCandidate::isConfirmable()` reads `displayName`/`formattedAddress`,
     * never `placeId`, so a confirmable-looking candidate with no place id
     * could reach {@see PlaceConfirmation::confirm()} (`24` §1.2.3's own rule:
     * *"Never save a resolved place_id without that confirmation, however
     * confident the match"*), and an id-less peer could collide with another
     * id-less peer under `competitors.place_id`'s dedupe. Wave 37 lane E,
     * decision 10504.
     *
     * @return list<array<string, mixed>>
     */
    private function places(Response $response): array
    {
        $places = $response->json('places');

        if (! is_array($places)) {
            return [];
        }

        return array_values(array_filter(
            $places,
            static fn (mixed $place): bool => is_array($place)
                && is_string($place['id'] ?? null)
                && $place['id'] !== '',
        ));
    }

    /**
     * Namespaced so a place cached for the audit is the same place cached for
     * row 3's resolver — the point of the 24h cache is that the second lookup
     * is free regardless of who asks.
     *
     * ⛔ **AND KEYED BY THE FIELD MASK SINCE 2026-08-26, BECAUSE A CACHED
     * PAYLOAD IS ONLY MEANINGFUL BESIDE THE MASK THAT PRODUCED IT.** The entry
     * is the raw response array. Change the mask — narrow it to cut cost, widen
     * it for a new check — and for the next 24 hours every deployed instance
     * answers `details()` from bodies shaped by the *previous* mask, while
     * {@see self::toSummary()} interprets them against the new one. Every field
     * the old mask did not carry then reads as "Google had nothing to say",
     * which is the whole subject of this slice arriving through the cache
     * instead of through the wire: a business with 487 reviews reading as zero
     * for a day, on a public page, with nothing in the tree wrong.
     *
     * ⚠️ **The cost of the guard is one Details call per place on the deploy
     * that changes the mask, once.** The alternative — remembering to flush a
     * cache namespace by hand at exactly the right moment — is a procedure, and
     * a procedure that runs after the deploy is the same bug with a person in
     * it.
     */
    private function cacheKey(string $placeId, string $fieldMask): string
    {
        return 'places:details:'.sha1($fieldMask).':'.sha1($placeId);
    }

    /**
     * Normalised, because the cache only pays for itself on collisions.
     *
     * Autocomplete is the one call here made by *strangers typing the same
     * things*: "starbucks", "walmart", and every prefix of both. Case and
     * whitespace differences would fragment that into misses, and each miss is
     * 0.283c. Lowercasing and collapsing runs of whitespace makes "  Joes
     * Pizza" and "joes pizza" one entry.
     *
     * The region code is part of the key because it changes the answer.
     */
    private function suggestionCacheKey(string $query, ?string $regionCode): string
    {
        $normalised = mb_strtolower(trim((string) preg_replace('/\s+/u', ' ', $query)));

        return 'places:autocomplete:'.sha1($normalised.'|'.($regionCode ?? ''));
    }

    /**
     * The raw suggestion rows, in the shape that goes into the cache.
     *
     * Cached as arrays rather than as PlaceSuggestion objects so the cached
     * payload stays a plain structure — a serialised object in a cache is a
     * class-shape migration waiting to break on the next deploy that renames a
     * property.
     *
     * `queryPrediction` entries are dropped: Google mixes them in with
     * `placePrediction`s, they carry no place id, and a dropdown row that
     * resolves to nothing is worse than one fewer row.
     *
     * @return list<array{placeId: string, mainText: string, secondaryText: ?string}>
     */
    private function suggestions(Response $response): array
    {
        $rows = $response->json('suggestions');

        if (! is_array($rows)) {
            return [];
        }

        $out = [];

        foreach ($rows as $row) {
            $prediction = is_array($row) ? ($row['placePrediction'] ?? null) : null;

            if (! is_array($prediction)) {
                continue;
            }

            $placeId = $prediction['placeId'] ?? null;
            $mainText = $this->text($prediction['structuredFormat']['mainText'] ?? null);

            if (! is_string($placeId) || $placeId === '' || $mainText === null) {
                continue;
            }

            $out[] = [
                'placeId' => $placeId,
                'mainText' => $mainText,
                'secondaryText' => $this->text($prediction['structuredFormat']['secondaryText'] ?? null),
            ];
        }

        return $out;
    }

    /**
     * @param  array{placeId: string, mainText: string, secondaryText: ?string}  $row
     */
    private function toSuggestion(array $row): PlaceSuggestion
    {
        return new PlaceSuggestion(
            placeId: $row['placeId'],
            mainText: $row['mainText'],
            secondaryText: $row['secondaryText'],
        );
    }

    /**
     * ⚠️ **`$priced` IS NOT OPTIONAL AND MUST BE THE MASK THIS BODY CAME BACK
     * FROM.** It is what lets a reader tell "Google sent no number" from "nobody
     * asked for one" — see {@see PlaceSummary::askedFor()}. Passing the wrong
     * one is worse than passing none: it would license an inference the response
     * cannot support. ⚠️ **It is the priced pair rather than a bare string
     * since 2026-08-29**, so the object that answered "what did this cost" is
     * the same object that answers "what did we ask for".
     *
     * ⚠️ **THE LEAF NORMALISER LIVES IN `PlacesFieldTiers` AND IS SHARED WITH
     * THE PRICING TABLE.** Top-level names only, `places.` stripped: the
     * questions this buys are "was `rating` asked for", never "was
     * `location.latitude` asked for". Both spellings of a mask in this class —
     * bare for Place Details, `places.`-prefixed for the two searches —
     * normalise to the same vocabulary, and a second copy of that rule beside
     * the pricing table's is 8460's shape even while both are right.
     *
     * @param  array<string, mixed>  $place
     */
    private function toSummary(array $place, PricedFieldMask $priced): PlaceSummary
    {
        /** @var array<int, array<string, mixed>> $reviews */
        $reviews = is_array($place['reviews'] ?? null) ? $place['reviews'] : [];

        return new PlaceSummary(
            placeId: (string) ($place['id'] ?? ''),
            displayName: $this->text($place['displayName'] ?? null),
            formattedAddress: isset($place['formattedAddress']) ? (string) $place['formattedAddress'] : null,
            latitude: isset($place['location']['latitude']) ? (float) $place['location']['latitude'] : null,
            longitude: isset($place['location']['longitude']) ? (float) $place['location']['longitude'] : null,
            primaryType: isset($place['primaryType']) ? (string) $place['primaryType'] : null,
            types: array_values(array_filter(
                is_array($place['types'] ?? null) ? $place['types'] : [],
                'is_string',
            )),
            rating: isset($place['rating']) ? (float) $place['rating'] : null,
            userRatingCount: isset($place['userRatingCount']) ? (int) $place['userRatingCount'] : null,
            photoCount: is_array($place['photos'] ?? null) ? count($place['photos']) : 0,
            hasOpeningHours: isset($place['regularOpeningHours']),
            editorialSummary: $this->text($place['editorialSummary'] ?? null),
            websiteUri: isset($place['websiteUri']) ? (string) $place['websiteUri'] : null,
            nationalPhoneNumber: isset($place['nationalPhoneNumber']) ? (string) $place['nationalPhoneNumber'] : null,
            // Review *text* is dropped on arrival, deliberately. The audit counts
            // replies; it never stores or republishes what a customer wrote, and
            // `public_audits` is a table with no tenant and a public URL.
            // array_slice already reindexes, so array_map over it is a list.
            reviews: array_map(
                static fn (array $review): array => [
                    'author' => is_string($review['authorAttribution']['displayName'] ?? null)
                        ? $review['authorAttribution']['displayName']
                        : null,
                    'hasOwnerReply' => isset($review['ownerResponse']),
                ],
                array_slice(array_filter($reviews, 'is_array'), 0, self::REVIEW_SAMPLE),
            ),
            requestedFields: $priced->fields(),
        );
    }

    /**
     * Places wraps human-readable strings as {text, languageCode}.
     */
    private function text(mixed $value): ?string
    {
        if (is_array($value) && isset($value['text']) && is_string($value['text'])) {
            return $value['text'];
        }

        return is_string($value) ? $value : null;
    }
}
