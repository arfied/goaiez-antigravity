# Flexible live insights query API Reference

Live, flexible insights query. The account's platform picks the contract:

**Meta (facebook/instagram)**: forwards caller-chosen `fields`, `breakdowns` and `filtering`
to any Meta insights node and returns Meta's rows verbatim. `objectId` (required) selects the
node; `level` sets row granularity. Semantic validation is Meta's: an unknown field or invalid
breakdown combination returns a 400 carrying Meta's message. For long ranges or agency-scale
accounts prefer the async variant (POST /v1/ads/insights/reports).

**Google Ads (googleads)**: raw GAQL passthrough. Send any read-only GAQL SELECT via `query`
(campaign/keyword/search-term/geo/demographic/asset/shopping resources, `change_event`, any
`segments.*`) and rows come back verbatim (camelCase, counters as strings). Results are paged
at a fixed 10,000 rows; follow `paging.nextPageToken` with `pageToken`. `customerId` is only
needed when the connection has several Google Ads accounts. Semantic validation is Google's:
an invalid query returns a 400 carrying Google's message (note: selecting `segments.date`
requires a finite date filter).


## GET /v1/ads/insights

**Flexible live insights query**

Live, flexible insights query. The account's platform picks the contract:

**Meta (facebook/instagram)**: forwards caller-chosen `fields`, `breakdowns` and `filtering`
to any Meta insights node and returns Meta's rows verbatim. `objectId` (required) selects the
node; `level` sets row granularity. Semantic validation is Meta's: an unknown field or invalid
breakdown combination returns a 400 carrying Meta's message. For long ranges or agency-scale
accounts prefer the async variant (POST /v1/ads/insights/reports).

**Google Ads (googleads)**: raw GAQL passthrough. Send any read-only GAQL SELECT via `query`
(campaign/keyword/search-term/geo/demographic/asset/shopping resources, `change_event`, any
`segments.*`) and rows come back verbatim (camelCase, counters as strings). Results are paged
at a fixed 10,000 rows; follow `paging.nextPageToken` with `pageToken`. `customerId` is only
needed when the connection has several Google Ads accounts. Semantic validation is Google's:
an invalid query returns a 400 carrying Google's message (note: selecting `segments.date`
requires a finite date filter).


### Parameters

- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant); its platform selects the Meta or Google contract.
- **objectId** (optional) in query: Meta only (required there): insights node (act_<n>, campaign id, ad set id or ad id).
- **query** (optional) in query: Google only (required there): the GAQL SELECT statement to run.
- **customerId** (optional) in query: Google only: numeric customer id (no dashes) when the connection has several Google Ads accounts.
- **pageToken** (optional) in query: Google only: cursor from paging.nextPageToken of the previous page.
- **level** (optional) in query: Row granularity
- **fields** (optional) in query: Comma-separated Graph insights fields (e.g. spend,impressions,frequency,website_purchase_roas). Omitted = Meta's default set.
- **breakdowns** (optional) in query: Comma-separated Graph breakdowns (e.g. age,gender or publisher_platform).
- **actionBreakdowns** (optional) in query: Comma-separated Graph action breakdowns. Segments the actions[] arrays in each row.
- **actionAttributionWindows** (optional) in query: Comma-separated Meta attribution windows. Action values are returned keyed per window.
- **actionReportTime** (optional) in query: When actions are counted: impression, conversion or mixed.
- **useUnifiedAttributionSetting** (optional) in query: Use the ad sets' own attribution settings for action counting.
- **filtering** (optional) in query: JSON array of Meta filter objects: [{"field", "operator", "value"}]. Applied server-side by Meta.
- **datePreset** (optional) in query: Meta date_preset (e.g. last_7d, last_30d, this_month). Mutually exclusive with fromDate/toDate.
- **fromDate** (optional) in query: Start of range (YYYY-MM-DD); requires toDate.
- **toDate** (optional) in query: End of range (YYYY-MM-DD); requires fromDate.
- **timeIncrement** (optional) in query: Days per row (1-90), monthly, or all_days.
- **limit** (optional) in query: Rows per page
- **after** (optional) in query: Cursor from paging.after of the previous page.

### Responses

#### 200: Insight rows (raw platform shape)

**Response Body:**

- **objectId** `string`: Meta responses only.
- **customerId** `string`: Google responses only: the customer the query ran against.
- **fieldMask** `string,null`: Google responses only: the selected fields echoed by Google.
- **data** `array[object]`: 
  Type: `object`
- **paging** `object`: 
  - **after** `string,null`: Meta cursor for the next page; null when exhausted.
  - **nextPageToken** `string,null`: Google cursor for the next page; null when exhausted.

#### 400: Invalid input, or the platform rejected the query (unknown field, invalid breakdown combo, malformed GAQL); the message carries the platform's error

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

#### 429: Platform rate limit reached. For Google this is the per-user operations budget or the shared quota; the message says which and when it resets.

#### 501: Only supported on Meta (facebook/instagram) and Google Ads

---
