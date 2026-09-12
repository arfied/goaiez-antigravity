# List accounts API Reference

Returns connected accounts. Only includes accounts within the plan limit by default. Follower data requires analytics add-on.
Supports optional server-side pagination via page/limit params. When omitted, returns all accounts (backward-compatible).
page and limit must be supplied together; out-of-range page/limit values are rejected with 400 rather than silently clamped.


## GET /v1/accounts

**List accounts**

Returns connected accounts. Only includes accounts within the plan limit by default. Follower data requires analytics add-on.
Supports optional server-side pagination via page/limit params. When omitted, returns all accounts (backward-compatible).
page and limit must be supplied together; out-of-range page/limit values are rejected with 400 rather than silently clamped.


### Parameters

- **profileId** (optional) in query: Filter accounts by profile ID. Must be a valid ObjectId.
- **platform** (optional) in query: Filter accounts by platform (e.g. "instagram", "twitter").
- **status** (optional) in query: Filter accounts by connection status. `connected` returns healthy accounts; `disconnected` returns accounts that need reconnection (per the same reconnection check surfaced in the dashboard). Omit to return accounts in any status. When combined with page/limit, pagination totals reflect the filtered result set.

- **includeOverLimit** (optional) in query: When true, includes accounts from over-limit profiles.
- **page** (optional) in query: Page number (1-based). Must be provided together with limit to enable server-side pagination; sending only one of the two returns 400. Omit both for all accounts.

- **limit** (optional) in query: Page size. Must be provided together with page; sending only one of the two returns 400.


### Responses

#### 200: Accounts (with optional pagination)

**Response Body:**

- **accounts** (required) `array[SocialAccount]`: 
- **hasAnalyticsAccess** (required) `boolean`: Whether user has analytics add-on access
- **pagination**: `Pagination` - Only present when page/limit params are provided

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

#### 503: An upstream service or database is temporarily unavailable. Retry after the indicated delay. A timed-out write may have completed upstream; check its outcome before resubmitting.

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
