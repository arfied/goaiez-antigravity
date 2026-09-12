# Forecast ad delivery API Reference

LinkedIn-only. Forecasted impressions, clicks, spend and ~20 other
metrics for a targeting spec over a time range. Wraps LinkedIn's
`adSupplyForecasts` finder.

Each returned series carries a `metricType` (IMPRESSION, CLICK, SPENDING,
MAX_POTENTIAL_BUDGET, COST_PER_MILLION_IMPRESSIONS, ...) and a
`granularity` (DAILY, SEVEN_DAY, THIRTY_DAY, CUSTOM). LinkedIn caps the
daily spending forecast at 1.2x the daily budget and returns 0 once the
total budget is exhausted.

Non-LinkedIn accounts return `available: false`.


## POST /v1/ads/targeting/supply-forecast

**Forecast ad delivery**

LinkedIn-only. Forecasted impressions, clicks, spend and ~20 other
metrics for a targeting spec over a time range. Wraps LinkedIn's
`adSupplyForecasts` finder.

Each returned series carries a `metricType` (IMPRESSION, CLICK, SPENDING,
MAX_POTENTIAL_BUDGET, COST_PER_MILLION_IMPRESSIONS, ...) and a
`granularity` (DAILY, SEVEN_DAY, THIRTY_DAY, CUSTOM). LinkedIn caps the
daily spending forecast at 1.2x the daily budget and returns 0 once the
total budget is exhausted.

Non-LinkedIn accounts return `available: false`.


### Request Body

- **accountId** (required) `string`: No description
- **adAccountId** (required) `string`: No description
- **spec** (required): No description
- **campaignType** `string`: Defaults to SPONSORED_UPDATES. - one of: SPONSORED_UPDATES, SPONSORED_INMAILS, DYNAMIC
- **timeRangeStart** (required) `integer`: Unix ms. Must be in the future.
- **timeRangeEnd** (required) `integer`: Unix ms. Must be after start and within LinkedIn's max horizon.
- **objectiveType** `string`: No description
- **optimizationTarget** `string`: When set, the forecast assumes auto-bidding. When unset, competingBid is required.
- **dailyBudget** `number`: Either dailyBudget or totalBudget is required.
- **totalBudget** `number`: No description
- **currency** `string`: ISO 4217, defaults to USD.
- **competingBid** `object`: Required for manual-bid forecasts (when optimizationTarget is not set).
- **enableAudienceNetwork** `boolean`: Defaults to false. Required true for connectedTelevisionOnly.
- **enableAudienceExpansion** `boolean`: Defaults to false.
- **connectedTelevisionOnly** `boolean`: Defaults to false.

### Responses

#### 200: Forecast series

**Response Body:**

- **available** (required) `boolean`: No description
- **forecast** `array[object]`: 
  - **metricType** `string`: No description
  - **granularity** `string`: No description - one of: DAILY, SEVEN_DAY, THIRTY_DAY, CUSTOM
  - **timeSeries** `array[object]`: 
    - **timestamp** `integer`: No description
    - **value** `number`: No description
    - **adForecastRange** `object`: 
      - **lowEnd** `number`: No description
      - **highEnd** `number`: No description

#### 400: Invalid targeting, missing budget, or LinkedIn forecast validation error (e.g. END_DATE_MAX_HORIZON_FOR_FORECAST).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required.

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
