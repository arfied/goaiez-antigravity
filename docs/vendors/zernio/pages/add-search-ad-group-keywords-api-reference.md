# Add Search ad-group keywords API Reference

Adds one or more keyword criteria to an existing Google Search ad group,
without touching the keywords already there (unlike the whole-set diff on
`PUT /v1/ads/{adId}`, `keywords`/`negativeKeywords` in `platformSpecificData`,
which replaces the set). Set `negative: true` to add ad-group-level negatives
instead of positive keywords.


## GET /v1/ads/keywords

**List Search keywords**

Returns the Google Search keyword criteria (positive and negative) synced from
connected Google Ads accounts, one row per ad-group keyword. Refreshed about
once a week per Google Ads customer (the keyword sweep rides the ads discovery
pass on a slower slot, to stay inside Google's shared daily API quota), so
keywords added on Google can take several days to appear. A customer synced
for the first time is populated on the next discovery pass rather than
waiting for its weekly slot, and connecting an account or triggering a
manual sync refreshes it immediately.
Campaign-level negative keywords are not included; only ad-group-level
criteria are.


### Parameters

- **undefined** (optional): No description
- **limit** (optional) in query: No description
- **accountId** (optional) in query: Account ID
- **adAccountId** (optional) in query: Platform ad account ID (Google customer ID). Mirrors the same filter on /v1/ads.
- **profileId** (optional) in query: Profile ID
- **campaignId** (optional) in query: Platform campaign ID
- **adSetId** (optional) in query: Platform ad group ID (Google ad group)
- **status** (optional) in query: Keyword criterion status
- **matchType** (optional) in query: No description
- **negative** (optional) in query: true = negative keywords only, false = positive only. Omit for both.
- **search** (optional) in query: Case-insensitive substring match on the keyword text

### Responses

#### 200: Paginated keywords

**Response Body:**

- **keywords** `array[AdKeyword]`: 
- **pagination**: `Pagination` - See schema definition

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

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

---

## POST /v1/ads/keywords

**Add Search ad-group keywords**

Adds one or more keyword criteria to an existing Google Search ad group,
without touching the keywords already there (unlike the whole-set diff on
`PUT /v1/ads/{adId}`, `keywords`/`negativeKeywords` in `platformSpecificData`,
which replaces the set). Set `negative: true` to add ad-group-level negatives
instead of positive keywords.


### Request Body

- **accountId** (required) `string`: Account ID (Google Ads)
- **adSetId** (required) `string`: Google ad group ID to add the keywords to
- **keywords** (required) `array`: No description
- **negative** `boolean`: Add as ad-group-level negatives instead of positive keywords

### Responses

#### 201: Keywords added

**Response Body:**

- **keywords** `array[AdKeyword]`: 

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

#### 501: Only available on Google Ads accounts

---
