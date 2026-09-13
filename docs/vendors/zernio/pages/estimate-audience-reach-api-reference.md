# Estimate audience reach API Reference

Returns a normalized pre-flight audience-size estimate for a targeting spec,
before any campaign is created. Backed by each platform's native reach API
(Meta `delivery_estimate`, LinkedIn `audienceCounts`, X `audience_summary`,
Pinterest `audience_sizing`).

Platforms without a usable pre-flight reach API (Google Search/Display, TikTok)
return `available: false` with no bounds, so clients can hide or grey out the
estimate rather than treat the absence as an error.


## POST /v1/ads/targeting/reach-estimate

**Estimate audience reach**

Returns a normalized pre-flight audience-size estimate for a targeting spec,
before any campaign is created. Backed by each platform's native reach API
(Meta `delivery_estimate`, LinkedIn `audienceCounts`, X `audience_summary`,
Pinterest `audience_sizing`).

Platforms without a usable pre-flight reach API (Google Search/Display, TikTok)
return `available: false` with no bounds, so clients can hide or grey out the
estimate rather than treat the absence as an error.


### Request Body

- **accountId** (required) `string`: Zernio account ID on the target ad platform (the estimate runs against its platform).
- **adAccountId** (required) `string`: Required. The platform ad-account ID the reach call runs against (Meta act_..., LinkedIn numeric sponsoredAccount ID, Pinterest ad-account ID, X account ID) - every backing reach API is scoped to one ad account. Get it from GET /v1/ads/accounts.
- **spec** (required): The targeting spec to estimate. Same shape used by POST /v1/ads/create.
- **optimizationGoal** `string`: Optional. The optimization goal the estimate should assume (platform's
own vocabulary, e.g. Meta `REACH`, `LINK_CLICKS`, `OFFSITE_CONVERSIONS`).
Some platforms vary the estimate by goal; omit to use the platform default.


### Responses

#### 200: Normalized reach estimate

**Response Body:**

- **available** (required) `boolean`: Whether a pre-flight estimate is available on this platform. False for Google and TikTok.
- **lower** `integer,null`: Lower bound of the estimated reachable audience. Present only when available.
- **upper** `integer,null`: Upper bound of the estimated reachable audience. Present only when available.
- **daily** `integer,null`: Optional estimated daily reach/results at the given budget, when the platform returns it.
- **currency** `string,null`: Currency of any monetary fields in the estimate, when applicable.
- **estimateReady** `boolean,null`: Meta only. False when Meta is still computing the estimate (the audience is too new); retry shortly.

#### 400: Missing required fields or a targeting field the platform cannot honour

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
