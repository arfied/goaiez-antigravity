# Get Google Business Profile performance metrics API Reference

Returns daily performance metrics for a Google Business Profile location.
Metrics include impressions (Maps/Search, desktop/mobile), website clicks,
call clicks, direction requests, conversations, bookings, and food orders.
Data may be delayed 2-3 days. Max 18 months of historical data.
Requires the Analytics add-on.


## GET /v1/analytics/googlebusiness/performance

**Get Google Business Profile performance metrics**

Returns daily performance metrics for a Google Business Profile location.
Metrics include impressions (Maps/Search, desktop/mobile), website clicks,
call clicks, direction requests, conversations, bookings, and food orders.
Data may be delayed 2-3 days. Max 18 months of historical data.
Requires the Analytics add-on.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the Google Business Profile account.
- **metrics** (optional) in query: Comma-separated metric names. Defaults to all available metrics.
Valid values: BUSINESS_IMPRESSIONS_DESKTOP_MAPS, BUSINESS_IMPRESSIONS_DESKTOP_SEARCH,
BUSINESS_IMPRESSIONS_MOBILE_MAPS, BUSINESS_IMPRESSIONS_MOBILE_SEARCH,
BUSINESS_CONVERSATIONS, BUSINESS_DIRECTION_REQUESTS, CALL_CLICKS, WEBSITE_CLICKS,
BUSINESS_BOOKINGS, BUSINESS_FOOD_ORDERS, BUSINESS_FOOD_MENU_CLICKS

- **startDate** (optional) in query: Start date (YYYY-MM-DD). Defaults to 30 days ago. Max 18 months back.
- **endDate** (optional) in query: End date (YYYY-MM-DD). Defaults to today.

### Responses

#### 200: Performance metrics with daily time series

**Response Body:**

- **success** `boolean`: No description (example: true)
- **accountId** `string`: No description
- **platform** `string`: No description (example: "googlebusiness")
- **dateRange** `object`: 
  - **startDate** `string` (date): No description (example: "2026-03-01")
  - **endDate** `string` (date): No description (example: "2026-03-31")
- **metrics** `object`: Each key is a metric name containing total and daily values.
- **dataDelay** `string`: No description (example: "Data may be delayed 2-3 days")

#### 400: Invalid parameters

**Response Body:**

- **error** `string`: No description (example: "Invalid metrics: INVALID_METRIC")
- **validMetrics** `array[string]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **code** `string`: No description (example: "analytics_addon_required")

#### 403: Access denied

**Response Body:**

- **error** `string`: No description (example: "Access denied to this account")

#### 502: The platform returned a server error.

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
