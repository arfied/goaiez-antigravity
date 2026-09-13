# Read a Google campaign's device, location, and language targeting API Reference

Google Ads compliance requires geo, language, budget, and bidding targeting
set at creation to stay editable afterwards; this reads the campaign state
so an integrator can build an editor around it. Cached for the quota window
(10 minutes fresh, up to 7 days last-good), not always a live read. Google
only; every other platform returns 501.

`devices` lists the device criteria the campaign carries, which depends on
its channel: Search campaigns have MOBILE, DESKTOP and TABLET, Display
campaigns also have CONNECTED_TV. `bidModifier` is Google's bid adjustment
for that device, `null` when it has none, and `0` when the device is
switched off; `included` is false for exactly that case.


## GET /v1/ads/campaigns/{campaignId}/targeting

**Read a Google campaign's device, location, and language targeting**

Google Ads compliance requires geo, language, budget, and bidding targeting
set at creation to stay editable afterwards; this reads the campaign state
so an integrator can build an editor around it. Cached for the quota window
(10 minutes fresh, up to 7 days last-good), not always a live read. Google
only; every other platform returns 501.

`devices` lists the device criteria the campaign carries, which depends on
its channel: Search campaigns have MOBILE, DESKTOP and TABLET, Display
campaigns also have CONNECTED_TV. `bidModifier` is Google's bid adjustment
for that device, `null` when it has none, and `0` when the device is
switched off; `included` is false for exactly that case.


### Parameters

- **campaignId** (required) in path: Google platform campaign ID
- **platform** (optional) in query: Disambiguates when the same campaignId string exists on more than one connected platform.

### Responses

#### 200: Current campaign targeting

**Response Body:**

- **devices** `array[object]`: 
  - **device** `string`: No description - one of: MOBILE, DESKTOP, TABLET, CONNECTED_TV
  - **included** `boolean`: No description
  - **bidModifier** `number,null`: Google's bid adjustment for this device: null when it has none, 0 when the device is switched off, otherwise 0.1 to 10.
- **locations** `array[object]`: 
  - **geoTargetId** `string`: Numeric id from Google's geoTargetConstants/{id}.
  - **negative** `boolean`: true = excluded location.
  - **name** `string,null`: Google's geo_target_constant.name, e.g. "United States"; null when the id could not be resolved.
  - **canonicalName** `string,null`: Google's geo_target_constant.canonical_name, e.g. "California, United States"; null when the id could not be resolved.
  - **type** `string,null`: Google's geo_target_constant.target_type, e.g. "Country", "Region", "City"; null when the id could not be resolved.
  - **countryCode** `string,null`: Google's geo_target_constant.country_code, an ISO 3166-1 alpha-2 code; null when the id could not be resolved.
- **languages** `array[object]`: 
  - **code** `string`: Google's language code (ISO 639-1, plus variants such as `zh_CN`). Empty when the campaign's language_constant id is not in Zernio's checked-in table.
  - **id** `string`: Google's languageConstants/{id} numeric id.
  - **name** `string`: No description
- **cachedAt** `string,null` (date-time): When this targeting was fetched from Google. Null when it was never served from cache.
- **stale** `boolean`: True when Google's daily API quota was exhausted and this is the last successful fetch, not a live read.

#### 400: Invalid request

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

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans).

#### 404: Campaign not found

#### 501: Only available on Google Ads campaigns

---

## PUT /v1/ads/campaigns/{campaignId}/targeting

**Edit a Google campaign's device, location, or language targeting**

Google Ads compliance row M.10: geo and language targeting set at
creation must stay editable afterwards. Send at least one of `devices`,
`locations`, `languages`; each provided field REPLACES that field's
existing criteria on the campaign (a full set, not a delta). Fields left
out of the body are untouched. Google only; every other platform returns
501.

`devices` is the full set of device bid modifiers: a supported device you
leave out is switched off with a bid modifier of 0, since Google cannot
remove a device criterion. A device the campaign's channel does not carry,
and a set that switches every device off, both return 422.

`locations` accepts the same shapes as campaign creation: a bare array of
ISO country codes, or an object with `countries`/`regions`/`cities`/`zips`/`metros`
key lists (`key` from GET /v1/ads/targeting/search?dimension=geo). Negative
(excluded) locations are left untouched by this endpoint. An empty location list
returns 400 instead of removing every criterion: a Google campaign with no location
criteria targets every country, so omit `locations` to leave targeting alone.

The removes and the creates go out in ONE Google `googleAds:mutate`, so a failed
edit leaves the campaign's previous set intact rather than a half-applied one.

`languages` is an array of Google's language codes (ISO 639-1, plus variants
such as `zh_CN`); an unknown code returns 400.

The response includes the refreshed `devices`/`locations`/`languages` state
read back from Google after the edit, and invalidates the cached copy
`GET` on this campaign would otherwise keep serving.


### Parameters

- **campaignId** (required) in path: Google platform campaign ID

### Request Body

- **platform** (required) `string`: No description - one of: google
- **targeting** (required) `object`: No description

### Responses

#### 200: Targeting updated

**Response Body:**

- **campaignId** `string`: No description
- **updated** `array[string]`: Which targeting fields were applied.
- **devices** `array[object]`: 
  - **device** `string`: No description - one of: MOBILE, DESKTOP, TABLET, CONNECTED_TV
  - **included** `boolean`: No description
  - **bidModifier** `number,null`: Always null on this read; see GET's description.
- **locations** `array[object]`: 
  - **geoTargetId** `string`: Numeric id from Google's geoTargetConstants/{id}.
  - **negative** `boolean`: true = excluded location.
  - **name** `string,null`: Google's geo_target_constant.name; see GET's description.
  - **canonicalName** `string,null`: Google's geo_target_constant.canonical_name; see GET's description.
  - **type** `string,null`: Google's geo_target_constant.target_type; see GET's description.
  - **countryCode** `string,null`: Google's geo_target_constant.country_code; see GET's description.
- **languages** `array[object]`: 
  - **code** `string`: No description
  - **id** `string`: No description
  - **name** `string`: No description

#### 400: Invalid request

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

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Campaign not found

#### 501: Only available on Google Ads campaigns

---
