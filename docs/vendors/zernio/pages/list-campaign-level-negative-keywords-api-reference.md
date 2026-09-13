# List campaign-level negative keywords API Reference

Returns the campaign-level negative keywords (`campaign_criterion.negative`),
distinct from the ad-group-level negatives under `GET /v1/ads/keywords`. Cached
for the quota window (not synced to Postgres), and gated by the shared Google
Ads operations budget like every other on-demand Google surface. The response
carries `cachedAt` and `stale`, set when a quota-exhausted call falls back to
the last-good copy instead of a live read.

The platform is always discovered from the campaign itself; a non-Google
campaign returns 501 rather than 404, whether or not `platform` was passed.


## GET /v1/ads/campaigns/{campaignId}/negative-keywords

**List campaign-level negative keywords**

Returns the campaign-level negative keywords (`campaign_criterion.negative`),
distinct from the ad-group-level negatives under `GET /v1/ads/keywords`. Cached
for the quota window (not synced to Postgres), and gated by the shared Google
Ads operations budget like every other on-demand Google surface. The response
carries `cachedAt` and `stale`, set when a quota-exhausted call falls back to
the last-good copy instead of a live read.

The platform is always discovered from the campaign itself; a non-Google
campaign returns 501 rather than 404, whether or not `platform` was passed.


### Parameters

- **campaignId** (required) in path: Platform campaign ID
- **platform** (optional) in query: Optional and NOT authoritative: the resolved campaign's own platform decides 200 vs 501, never this hint.

### Responses

#### 200: Campaign-level negative keywords

**Response Body:**

- **keywords** `array[object]`: 
  - **criterionId** `string`: No description
  - **text** `string`: No description
  - **matchType** `string`: No description - one of: exact, phrase, broad
- **cachedAt** `string,null` (date-time): When this list was fetched from Google. Null when it was never served from cache.
- **stale** `boolean`: True when Google's daily API quota was exhausted and this is the last successful fetch, not a live read.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Campaign not found

#### 429: Google Ads operations budget exhausted; retry later

#### 501: Only available on Google Ads campaigns

---

## PUT /v1/ads/campaigns/{campaignId}/negative-keywords

**Replace campaign-level negative keywords**

Replaces the FULL set of campaign-level negative keywords (C.270): the desired
list is diffed against what Google already has, and the difference is applied
as one `create`/`remove` mutate. Send an empty array to clear every campaign
negative.

The platform is always discovered from the campaign itself; a non-Google
campaign returns 501 rather than 404, whether or not `platform` was sent.


### Parameters

- **campaignId** (required) in path: Platform campaign ID

### Request Body

- **platform** `string`: Optional and NOT authoritative: the resolved campaign's own platform decides 200 vs 501, never this hint. - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai
- **keywords** (required) `array`: No description

### Responses

#### 200: Campaign-level negative keywords replaced

**Response Body:**

- **created** `integer`: Negative criteria newly created on Google
- **removed** `integer`: Negative criteria removed from Google
- **keywords** `array[object]`: The full negative-keyword set after the replace
  - **criterionId** `string`: No description
  - **text** `string`: No description
  - **matchType** `string`: No description - one of: exact, phrase, broad

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

#### 404: Campaign not found

#### 429: Google Ads operations budget exhausted; retry later

#### 501: Only available on Google Ads campaigns

---
