# Google Ads search terms report API Reference

The actual search queries that triggered your ads, with matched-keyword
status and spend metrics, the raw material for wasted-spend analysis and
negative-keyword lists. Reads Google's `search_term_view`, cached for
the quota window; defaults to the last 30 days. Rows are ordered by
cost, descending. Draws on the shared Google Ads operations budget.
The response carries `cachedAt` and `stale`, set when a quota-exhausted
call falls back to the last-good copy instead of a live read.

## GET /v1/ads/search-terms

**Google Ads search terms report**

The actual search queries that triggered your ads, with matched-keyword
status and spend metrics, the raw material for wasted-spend analysis and
negative-keyword lists. Reads Google's `search_term_view`, cached for
the quota window; defaults to the last 30 days. Rows are ordered by
cost, descending. Draws on the shared Google Ads operations budget.
The response carries `cachedAt` and `stale`, set when a quota-exhausted
call falls back to the last-good copy instead of a live read.

### Parameters

- **accountId** (required) in query: Google ads SocialAccount id.
- **customerId** (optional) in query: Numeric Google Ads customer id (no dashes). Defaults to the account's connected customer.
- **fromDate** (optional) in query: Defaults to 30 days ago.
- **toDate** (optional) in query: Defaults to today.
- **campaignId** (optional) in query: Numeric Google campaign id filter.
- **adGroupId** (optional) in query: Numeric Google ad group id filter.
- **pageToken** (optional) in query: Cursor from paging.nextPageToken of the previous page.

### Responses

#### 200: Search terms

**Response Body:**

- **customerId** `string`: No description
- **data** `array[object]`: 
  - **searchTerm** `string,null`: No description
  - **status** `string,null`: ADDED / EXCLUDED / ADDED_EXCLUDED / NONE: whether the term is already a keyword or a negative.
  - **matchType** `string,null`: How the term matched (BROAD, PHRASE, EXACT, NEAR_PHRASE, NEAR_EXACT).
  - **campaignId** `string,null`: No description
  - **campaignName** `string,null`: No description
  - **adGroupId** `string,null`: No description
  - **adGroupName** `string,null`: No description
  - **impressions** `integer`: No description
  - **clicks** `integer`: No description
  - **costMicros** `integer`: Cost in micros of the account currency (divide by 1,000,000).
  - **conversions** `number`: No description
  - **conversionsValue** `number`: No description
- **paging** `object`: 
  - **nextPageToken** `string,null`: Null when the last page was returned.
- **cachedAt** `string,null` (date-time): When this data was fetched from Google. Null when it was never served from cache.
- **stale** `boolean`: True when Google's daily API quota was exhausted and this is the last successful fetch, not a live read.

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

#### 429: Google Ads operations budget exhausted; retry later.

#### 501: Only available on Google Ads accounts

---
