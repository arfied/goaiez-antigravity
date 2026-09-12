# Get attribution metrics API Reference

LinkedIn-only today. Returns conversion-attribution metrics
(`externalWebsiteConversions`, `externalWebsitePostClickConversions`,
`externalWebsitePostViewConversions`, `conversionValueInLocalCurrency`,
`qualifiedLeads`, `costInLocalCurrency`) bucketed by date.

Date-range constraints (passed through from LinkedIn):
- `granularity=DAILY` is retained for ~6 months only
- `granularity=ALL` with a range > 6 months auto-rounds to month boundaries
- `granularity=MONTHLY`/`YEARLY` retains 24 months

Throttle: LinkedIn caps adAnalytics at 45M metric values per 5-minute
window across the calling token. Single-rule queries are well within
that limit; surfaces as 429 if hit.


## GET /v1/accounts/{accountId}/conversion-destinations/{destinationId}/metrics

**Get attribution metrics**

LinkedIn-only today. Returns conversion-attribution metrics
(`externalWebsiteConversions`, `externalWebsitePostClickConversions`,
`externalWebsitePostViewConversions`, `conversionValueInLocalCurrency`,
`qualifiedLeads`, `costInLocalCurrency`) bucketed by date.

Date-range constraints (passed through from LinkedIn):
- `granularity=DAILY` is retained for ~6 months only
- `granularity=ALL` with a range > 6 months auto-rounds to month boundaries
- `granularity=MONTHLY`/`YEARLY` retains 24 months

Throttle: LinkedIn caps adAnalytics at 45M metric values per 5-minute
window across the calling token. Single-rule queries are well within
that limit; surfaces as 429 if hit.


### Parameters

- **accountId** (required) in path: No description
- **destinationId** (required) in path: No description
- **adAccountId** (required) in query: No description
- **startDate** (required) in query: No description
- **endDate** (optional) in query: No description
- **granularity** (optional) in query: No description

### Responses

#### 200: Metrics rows

**Response Body:**

- **platform** `string`: No description - one of: linkedinads
- **granularity** `string`: No description - one of: ALL, DAILY, MONTHLY, YEARLY
- **rows** `array[object]`: 
  - **start** `string`: YYYY-MM-DD
  - **end** `string`: YYYY-MM-DD (inclusive)
  - **metrics** `object`: No description

#### 400: Validation error or invalid date range.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads add-on or LinkedIn reconnect required.

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

#### 405: Platform does not support metrics readback.

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

#### 429: LinkedIn analytics rate limit hit.

---
