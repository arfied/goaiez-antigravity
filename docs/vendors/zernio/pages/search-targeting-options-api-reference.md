# Search targeting options API Reference

Resolve a human-readable query into the platform's opaque targeting ids used in
the `TargetingSpec` (`countries`/`regions`/`cities`/`zips`/`metros` geo keys, and
`interests`/`behaviors` entity ids) on `POST /v1/ads/create`,
`POST /v1/ads/targeting/reach-estimate`, and `saved_targeting` audiences.

The `dimension` param selects what is searched:

- `geo`: locations, further scoped by `geoType`
- `interest`
- `behavior`
- `income`
- `language`: Google-only
- `workPosition`, `workEmployer`, `workIndustry`: the Meta-only work demographics, whose
  ids feed `TargetingSpec.workPositions`/`workEmployers`/`workIndustries`
- `industry`, `jobFunction`, `seniority`, `companySize`: the LinkedIn-only B2B facets, whose
  URNs feed `TargetingSpec.industries`/`jobFunctions`/`seniorities`/`companySizes`

Availability of each dimension varies by platform (e.g. behaviours are Meta/TikTok only).
Work industries are a fixed ~30-entry Meta catalog with no server-side query,
so `workIndustry` matching, ranking and `limit` happen in Zernio. `language`
is likewise a fixed, checked-in table of Google's targetable
`language_constant` rows (id, ISO code, name) matched by name or code, capped
at 20, with no network call; its ids feed `TargetingSpec.languages`.

Results are normalized across platforms into a single shape, so the same client code
consumes Meta, TikTok, LinkedIn, X, Pinterest, and Google results.

TikTok geo searches return every matching level in one list (`type` is
`country`, `region`, `city`, `district`, or `metro` for DMA areas), and
`geoType` is not applied. Results are scoped to the advertiser's targetable
markets, and every id is usable in `regions`/`cities`/`metros` keys on
`POST /v1/ads/create`.

LinkedIn geo searches also return every matching level in one list, and
neither `geoType` nor `countryCode` is applied: LinkedIn's typeahead only
returns a name and a URN per result, with no level or country field to
filter on. Every result has `type` set to `location`, and its id is a
`urn:li:geo:*` URN usable as a `regions[].key` on `POST /v1/ads/create`,
`POST /v1/ads/boost` and `POST /v1/ads/targeting/reach-estimate`.

LinkedIn B2B searches (`industry`, `jobFunction`, `seniority`, `companySize`) return the
full URN to pass straight back, so no URN id fragment has to be assembled by hand:
`urn:li:industry:4`, `urn:li:function:8`, `urn:li:seniority:6`,
`urn:li:staffCountRange:(51,200)`. Only `industry` is a server-side name search
(LinkedIn's typeahead finder). LinkedIn exposes no typeahead for job functions,
seniorities and company sizes, so Zernio fetches each whole table (26, 10 and 9 entries),
caches it, and does the matching, ranking and `limit` cutoff itself. Those three never
carry `audienceSize`, and `countryCode` and `geoType` are not applied to any of the four.

Google geo searches resolve against Google's geoTargetConstants and return
every matching level in one list; `geoType` is not applied (Google's
`target_type` is an open taxonomy that does not map one-to-one onto the
`geoType` enum), so filter client-side on the returned `type` (`country`,
`region`, `city`, `zip`, `metro`, or the lowercased Google target type for
rarer levels). `countryCode` scopes the search to one country. Each id is
Google's numeric criterion id, usable as a `regions`/`cities`/`zips`/`metros`
`key` on `POST /v1/ads/create`. Google city radius is not supported (pass a
`customLocations` lat/lng pin for a radius); country targeting also accepts
plain ISO codes via `countries` with no search call.

Pinterest resolves against three whole-catalog endpoints (interests, locations,
regions) with no server-side query or pagination, so matching, ranking and the
`limit` cutoff all happen in Zernio; the catalog is independent of any ad account
and results never carry `audienceSize`. Names come back localized to the connected
Pinterest account's language (there is no way to force a locale), so match against
whatever language that account returns.

`geoType` routes to a different Pinterest catalog:

- `country` and `metro_area` read the locations catalog (`type` is `country` or `metro`)
- `region` reads the regions catalog (`type` is `region`, its id a `regions[].key` on
  `POST /v1/ads/create`)
- `all` and the default `city` merge both catalogs with honest per-entry `type`s, since
  Pinterest has no city-level catalog and `city` is an alias for `all`, not a literal
  city search
- `zip`, `subcity`, `neighborhood`, `place` and `geo_market` return a 400: Pinterest
  exposes no postal-code catalog, pass postal codes directly as
  `targeting.zips: [{ key }]` on `POST /v1/ads/create`

For geo queries, `q` should contain only the locality name (e.g. `"Amsterdam"`,
not `"Amsterdam, NL"`). Use `countryCode` to disambiguate.


## GET /v1/ads/targeting/search

**Search targeting options**

Resolve a human-readable query into the platform's opaque targeting ids used in
the `TargetingSpec` (`countries`/`regions`/`cities`/`zips`/`metros` geo keys, and
`interests`/`behaviors` entity ids) on `POST /v1/ads/create`,
`POST /v1/ads/targeting/reach-estimate`, and `saved_targeting` audiences.

The `dimension` param selects what is searched:

- `geo`: locations, further scoped by `geoType`
- `interest`
- `behavior`
- `income`
- `language`: Google-only
- `workPosition`, `workEmployer`, `workIndustry`: the Meta-only work demographics, whose
  ids feed `TargetingSpec.workPositions`/`workEmployers`/`workIndustries`
- `industry`, `jobFunction`, `seniority`, `companySize`: the LinkedIn-only B2B facets, whose
  URNs feed `TargetingSpec.industries`/`jobFunctions`/`seniorities`/`companySizes`

Availability of each dimension varies by platform (e.g. behaviours are Meta/TikTok only).
Work industries are a fixed ~30-entry Meta catalog with no server-side query,
so `workIndustry` matching, ranking and `limit` happen in Zernio. `language`
is likewise a fixed, checked-in table of Google's targetable
`language_constant` rows (id, ISO code, name) matched by name or code, capped
at 20, with no network call; its ids feed `TargetingSpec.languages`.

Results are normalized across platforms into a single shape, so the same client code
consumes Meta, TikTok, LinkedIn, X, Pinterest, and Google results.

TikTok geo searches return every matching level in one list (`type` is
`country`, `region`, `city`, `district`, or `metro` for DMA areas), and
`geoType` is not applied. Results are scoped to the advertiser's targetable
markets, and every id is usable in `regions`/`cities`/`metros` keys on
`POST /v1/ads/create`.

LinkedIn geo searches also return every matching level in one list, and
neither `geoType` nor `countryCode` is applied: LinkedIn's typeahead only
returns a name and a URN per result, with no level or country field to
filter on. Every result has `type` set to `location`, and its id is a
`urn:li:geo:*` URN usable as a `regions[].key` on `POST /v1/ads/create`,
`POST /v1/ads/boost` and `POST /v1/ads/targeting/reach-estimate`.

LinkedIn B2B searches (`industry`, `jobFunction`, `seniority`, `companySize`) return the
full URN to pass straight back, so no URN id fragment has to be assembled by hand:
`urn:li:industry:4`, `urn:li:function:8`, `urn:li:seniority:6`,
`urn:li:staffCountRange:(51,200)`. Only `industry` is a server-side name search
(LinkedIn's typeahead finder). LinkedIn exposes no typeahead for job functions,
seniorities and company sizes, so Zernio fetches each whole table (26, 10 and 9 entries),
caches it, and does the matching, ranking and `limit` cutoff itself. Those three never
carry `audienceSize`, and `countryCode` and `geoType` are not applied to any of the four.

Google geo searches resolve against Google's geoTargetConstants and return
every matching level in one list; `geoType` is not applied (Google's
`target_type` is an open taxonomy that does not map one-to-one onto the
`geoType` enum), so filter client-side on the returned `type` (`country`,
`region`, `city`, `zip`, `metro`, or the lowercased Google target type for
rarer levels). `countryCode` scopes the search to one country. Each id is
Google's numeric criterion id, usable as a `regions`/`cities`/`zips`/`metros`
`key` on `POST /v1/ads/create`. Google city radius is not supported (pass a
`customLocations` lat/lng pin for a radius); country targeting also accepts
plain ISO codes via `countries` with no search call.

Pinterest resolves against three whole-catalog endpoints (interests, locations,
regions) with no server-side query or pagination, so matching, ranking and the
`limit` cutoff all happen in Zernio; the catalog is independent of any ad account
and results never carry `audienceSize`. Names come back localized to the connected
Pinterest account's language (there is no way to force a locale), so match against
whatever language that account returns.

`geoType` routes to a different Pinterest catalog:

- `country` and `metro_area` read the locations catalog (`type` is `country` or `metro`)
- `region` reads the regions catalog (`type` is `region`, its id a `regions[].key` on
  `POST /v1/ads/create`)
- `all` and the default `city` merge both catalogs with honest per-entry `type`s, since
  Pinterest has no city-level catalog and `city` is an alias for `all`, not a literal
  city search
- `zip`, `subcity`, `neighborhood`, `place` and `geo_market` return a 400: Pinterest
  exposes no postal-code catalog, pass postal codes directly as
  `targeting.zips: [{ key }]` on `POST /v1/ads/create`

For geo queries, `q` should contain only the locality name (e.g. `"Amsterdam"`,
not `"Amsterdam, NL"`). Use `countryCode` to disambiguate.


### Parameters

- **accountId** (required) in query: Account ID (a connected account on the target ad platform).
- **q** (required) in query: Search query. For geo, the locality name only (no region/country suffix).
- **dimension** (optional) in query: What to search. `geo` resolves locations (scope further with `geoType`), `interest`/`behavior` resolve audience entities, `income` resolves income-tier options, `language` resolves Google's targetable language_constant table (Google only), `workPosition`/`workEmployer`/`workIndustry` resolve Meta work demographics, `industry`/`jobFunction`/`seniority`/`companySize` resolve LinkedIn B2B facets (LinkedIn only). Defaults to `interest` for backward compatibility with the deprecated /v1/ads/interests alias.
- **geoType** (optional) in query: Only used when `dimension=geo`. The kind of location to resolve. `all` searches every type in one relevance-ranked call. Defaults to `city`.
- **countryCode** (optional) in query: ISO 3166-1 alpha-2 country code (e.g. NL) to scope a geo search.
- **limit** (optional) in query: Maximum results to return.

### Responses

#### 200: Matching targeting options (normalized)

**Response Body:**

- **results** `array[object]`: 
  - **id** (required) `string`: The platform's opaque id. Use as a geo `key` (regions/cities/zips/metros) or an entity `id` (interests/behaviors) in TargetingSpec.
  - **name** (required) `string`: Human-readable label.
  - **type** (required) `string`: What the result is (e.g. city, region, country, zip, metro, location, interest, behavior, income, industry, jobFunction, seniority, companySize).
  - **path** `array[string]`: Optional breadcrumb of parent labels (e.g. ['United States', 'California', 'Los Angeles']). Disambiguates same-named results.
  - **audienceSize** `integer,null`: Optional estimated reachable users for this option, when the platform returns it.

#### 400: Missing or invalid query parameters

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

---
