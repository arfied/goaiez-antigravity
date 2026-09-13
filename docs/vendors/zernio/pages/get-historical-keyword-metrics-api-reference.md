# Get historical keyword metrics API Reference

Google Ads only. Runs Keyword Planner's generateKeywordHistoricalMetrics for up to 1,000
exact keywords: historical search volume, competition and top-of-page bid ranges, plus
averageCpcMicros when includeAverageCpc is set. Rows come back verbatim; counters are int64s
encoded as strings, bid/CPC values are micros of the account currency.


## POST /v1/ads/keywords/historical-metrics

**Get historical keyword metrics**

Google Ads only. Runs Keyword Planner's generateKeywordHistoricalMetrics for up to 1,000
exact keywords: historical search volume, competition and top-of-page bid ranges, plus
averageCpcMicros when includeAverageCpc is set. Rows come back verbatim; counters are int64s
encoded as strings, bid/CPC values are micros of the account currency.


### Request Body

- **accountId** (required) `string`: Zernio googleads SocialAccount id.
- **customerId** `string`: Numeric Google Ads customer id (no dashes); only needed when the connection has several accounts.
- **keywords** (required) `array`: No description
- **countries** `array`: ISO 3166-1 alpha-2 country codes. Omitted = worldwide.
- **languageConstantId** `string`: Google languageConstant id (1000 = English).
- **network** `string`: No description - one of: GOOGLE_SEARCH, GOOGLE_SEARCH_AND_PARTNERS
- **includeAdultKeywords** `boolean`: No description
- **includeAverageCpc** `boolean`: Adds averageCpcMicros to each row's keywordMetrics.

### Responses

#### 200: Historical metric rows (raw Keyword Planner shape)

**Response Body:**

- **customerId** `string`: The customer the request ran against.
- **data** `array[object]`: 
  Type: `object`
- **aggregateMetricResults** `object,null`: No description

#### 400: Invalid input, or Google rejected the request; the message carries Google's error

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

#### 429: Per-user Google Ads operations budget or the shared Google quota reached; the message says which and when it resets.

#### 501: Only supported on Google Ads

---
