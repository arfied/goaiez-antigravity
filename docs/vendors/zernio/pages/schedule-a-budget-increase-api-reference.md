# Schedule a budget increase API Reference

Pre-schedule a temporary budget increase (Black Friday, a launch, a sale) instead of
editing the budget by hand on the day. Same target rule as the GET: exactly one of
`campaignId` / `adSetId`.

Two Meta constraints worth knowing before you call it. `timeStart` / `timeEnd` must
fall on a 15-minute boundary, and a campaign cannot mix `ABSOLUTE` and `MULTIPLIER`
across its schedules; the second type is rejected with "Can't mix your budget scaling
selection". Window rules (must sit inside the campaign's run dates, minimum lead time,
no overlap) are Meta's and its message is forwarded verbatim.

## GET /v1/ads/high-demand-periods

**List high-demand periods**

Scheduled budget increases (Meta's budget-scheduling API). The Graph edge lives on the
campaign and ad-set nodes only, so exactly one of `campaignId` / `adSetId` (platform
ids) is required. Rows returned verbatim (budget_value, budget_value_type, time window,
recurrence).

### Parameters

- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.
- **campaignId** (optional) in query: Platform campaign id. Exactly one of campaignId / adSetId.
- **adSetId** (optional) in query: Platform ad set id. Exactly one of campaignId / adSetId.
- **limit** (optional) in query: Rows per page
- **after** (optional) in query: Cursor from paging.after of the previous page.

### Responses

#### 200: Budget schedules (raw Meta shape)

**Response Body:**

- **objectId** `string`: The campaign / ad set id the schedules belong to.
- **data** `array[object]`: 
  Type: `object`
- **paging** `object`: 
  - **after** `string,null`: Cursor for the next page; null when exhausted.

#### 400: Invalid input, or Meta rejected the query

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

#### 501: Only supported on Meta (facebook/instagram)

---

## POST /v1/ads/high-demand-periods

**Schedule a budget increase**

Pre-schedule a temporary budget increase (Black Friday, a launch, a sale) instead of
editing the budget by hand on the day. Same target rule as the GET: exactly one of
`campaignId` / `adSetId`.

Two Meta constraints worth knowing before you call it. `timeStart` / `timeEnd` must
fall on a 15-minute boundary, and a campaign cannot mix `ABSOLUTE` and `MULTIPLIER`
across its schedules; the second type is rejected with "Can't mix your budget scaling
selection". Window rules (must sit inside the campaign's run dates, minimum lead time,
no overlap) are Meta's and its message is forwarded verbatim.

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id used to resolve the Meta token.
- **campaignId** `string`: Platform campaign id. Exactly one of campaignId / adSetId.
- **adSetId** `string`: Platform ad set id. Exactly one of campaignId / adSetId.
- **budgetValue** (required) `number`: With ABSOLUTE, a budget in the ad account's currency in WHOLE units (50 = $50.00). With MULTIPLIER, a factor of the existing budget (2 = double it) and NOT a currency amount.
- **budgetValueType** (required) `string`: No description - one of: ABSOLUTE, MULTIPLIER
- **timeStart** (required) `integer`: Unix seconds, on a 15-minute boundary (:00, :15, :30, :45).
- **timeEnd** (required) `integer`: Unix seconds, on a 15-minute boundary and after timeStart.
- **recurrenceType** `string`: No description - one of: ONE_TIME, WEEKLY, MONTHLY
- **currency** `string`: Ad account currency, for the ABSOLUTE minor-unit conversion. Ignored for MULTIPLIER.

### Responses

#### 201: Budget schedule created

**Response Body:**

- **objectId** `string`: The campaign / ad set the schedule was attached to.
- **id** `string`: Meta budget schedule id.

#### 400: Invalid input, or Meta rejected the schedule

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

#### 501: Only supported on Meta (facebook/instagram)

---
