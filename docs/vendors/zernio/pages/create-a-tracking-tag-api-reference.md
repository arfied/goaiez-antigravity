# Create a tracking tag API Reference

Meta: creates a Meta Pixel on the given ad account (`POST /act_{id}/adspixels`,
where `name` is the only input). Returns the created tag including its
install `code`. The pixel is owned by the Business Manager that owns the
ad account; a pixel created on a personal (non-BM) ad account ends up
with `ownerBusinessId: null` and can't be shared with other ad accounts.

Creating a Meta pixel does NOT install it. Install the returned `code`
snippet on the site, or send events server-side via
`POST /v1/ads/conversions`. The check `installed` is derived from
`lastFiredTime`.

OpenAI Ads: creates an OpenAI pixel AND provisions a Conversions API
key for it in the same call (`adAccountId` is required by this
endpoint but ignored: one API key maps to exactly one ad account, so
there's nothing to select). Returns 422 (`FEATURE_NOT_AVAILABLE`) if
the ad account isn't enabled for pixel management; contact your OpenAI
partner representative to enable it. There is no delete API for
OpenAI pixels. If the pixel is created but the Conversions API key
provisioning then fails, the pixel is left live on OpenAI (it cannot
be cleaned up) and the error message names the surviving pixel id and
warns against retrying, since a retry would create a second, orphaned
pixel.

NOT idempotent on either platform: each call creates a new pixel (and,
for OpenAI, a new Conversions API key plus, with `defaultEventType`, a
new conversion event setting). Do not retry blindly on
timeout. Meta (platform `metaads`) and OpenAI Ads (platform
`openaiads`); other platforms return 405.


## GET /v1/accounts/{accountId}/tracking-tags

**List tracking tags**

Returns the tracking tags (Meta Pixels, or OpenAI Ads pixels) the
connected ads account can see. Pass `?adAccountId=act_...` (Meta only)
to scope the list to a single ad account; omit it to list every pixel
reachable by the token (the name is then suffixed with the ad account
it was discovered on, for disambiguation). The list view omits `code`.
Call `getTrackingTag` for the install snippet and full detail (Meta
only; OpenAI Ads has no get-by-id endpoint).

Meta (platform `metaads`) and OpenAI Ads (platform `openaiads`); other
platforms return 405. The `accountId` must be the ads SocialAccount
created by the Ads add-on connect flow (Meta) or the OpenAI Ads
connect flow, not a Facebook/Instagram posting account. Get your Meta
`act_...` ids from `GET /v1/ads/accounts`; `adAccountId` is ignored for
OpenAI Ads (one API key maps to exactly one ad account).


### Parameters

- **accountId** (required) in path: Ads SocialAccount id (platform `metaads` or `openaiads`).
- **adAccountId** (optional) in query: Optional, Meta only. Scope to one ad account, e.g. `act_123456789`. Ignored for OpenAI Ads.

### Responses

#### 200: Tracking tags listed

**Response Body:**

- **platform** `string`: No description - one of: metaads, openaiads
- **tags** `array[TrackingTag]`: 

#### 400: Account platform not supported, or invalid `adAccountId`.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans), or the Meta token lacks ads permissions (reconnect required).

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

#### 405: Platform does not support listing tracking tags.

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

#### 502: Meta was unreachable or returned an unclassified error (type: platform_error; the raw Meta payload is in platformError). Retryable.

---

## POST /v1/accounts/{accountId}/tracking-tags

**Create a tracking tag**

Meta: creates a Meta Pixel on the given ad account (`POST /act_{id}/adspixels`,
where `name` is the only input). Returns the created tag including its
install `code`. The pixel is owned by the Business Manager that owns the
ad account; a pixel created on a personal (non-BM) ad account ends up
with `ownerBusinessId: null` and can't be shared with other ad accounts.

Creating a Meta pixel does NOT install it. Install the returned `code`
snippet on the site, or send events server-side via
`POST /v1/ads/conversions`. The check `installed` is derived from
`lastFiredTime`.

OpenAI Ads: creates an OpenAI pixel AND provisions a Conversions API
key for it in the same call (`adAccountId` is required by this
endpoint but ignored: one API key maps to exactly one ad account, so
there's nothing to select). Returns 422 (`FEATURE_NOT_AVAILABLE`) if
the ad account isn't enabled for pixel management; contact your OpenAI
partner representative to enable it. There is no delete API for
OpenAI pixels. If the pixel is created but the Conversions API key
provisioning then fails, the pixel is left live on OpenAI (it cannot
be cleaned up) and the error message names the surviving pixel id and
warns against retrying, since a retry would create a second, orphaned
pixel.

NOT idempotent on either platform: each call creates a new pixel (and,
for OpenAI, a new Conversions API key plus, with `defaultEventType`, a
new conversion event setting). Do not retry blindly on
timeout. Meta (platform `metaads`) and OpenAI Ads (platform
`openaiads`); other platforms return 405.


### Parameters

- **accountId** (required) in path: Ads SocialAccount id (platform `metaads` or `openaiads`).

### Request Body

- **adAccountId** (required) `string`: Meta ad account id, e.g. `act_123456789`. Required by this endpoint but ignored for OpenAI Ads.
- **name** (required) `string`: No description
- **defaultEventType** `string`: OpenAI Ads only (ignored by Meta). When set, also provisions a standard conversion event setting wired to the new pixel, so `goal: conversions` ad creates on `POST /v1/ads/create` have an event to reference immediately. - one of: order_created, lead_created, items_added, contents_viewed, checkout_started, registration_completed, subscription_created, trial_started, appointment_scheduled, page_viewed, app_installed, app_opened

### Responses

#### 201: Tracking tag created

**Response Body:**

- **platform** `string`: No description - one of: metaads, openaiads
- **tag**: `TrackingTag` - See schema definition

#### 400: Invalid body, invalid `adAccountId`, over the per-business pixel cap, or ad account not in a Business Manager.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans), or the Meta token lacks ads permissions (reconnect required).

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

#### 405: Platform does not support creating tracking tags.

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

#### 422: OpenAI Ads only: the ad account is not enabled for pixel management. Contact your OpenAI partner representative.

#### 502: Meta was unreachable or returned an unclassified error (type: platform_error; the raw Meta payload is in platformError). Creating a pixel is NOT idempotent, so before retrying confirm with GET /v1/accounts/{accountId}/tracking-tags that no pixel was created.

---
