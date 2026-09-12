# Get ad analytics API Reference

Returns detailed performance analytics for an ad. Includes summary metrics, a daily timeline
over the requested date range, and optional demographic breakdowns (Meta and TikTok only).
If no date range is provided, defaults to the last 90 days. Date range is capped at 730 days max.


## GET /v1/ads/{adId}/analytics

**Get ad analytics**

Returns detailed performance analytics for an ad. Includes summary metrics, a daily timeline
over the requested date range, and optional demographic breakdowns (Meta and TikTok only).
If no date range is provided, defaults to the last 90 days. Date range is capped at 730 days max.


### Parameters

- **adId** (required) in path: No description
- **fromDate** (optional) in query: Start of date range (YYYY-MM-DD). Defaults to 90 days ago.
- **toDate** (optional) in query: End of date range (YYYY-MM-DD). Defaults to today. Max 730-day range.
- **breakdowns** (optional) in query: Comma-separated breakdown dimensions.

**Meta**: age, gender, country, publisher_platform, device_platform, region.

**TikTok**: gender, age, country_code, platform, ac, language.

**LinkedIn** (firmographics): job_title, job_function, seniority, industry,
company, company_size, country, region. Rows carry the raw pivot `value`
plus a resolved `name`. LinkedIn serves these aggregated over the whole
range, delays the data 12-24h, and omits segments with fewer than 3 events.


### Responses

#### 200: Ad analytics

**Response Body:**

- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **ad** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **platform** `string`: No description
  - **status** `string`: No description
  - **currency** `string,null`: ISO 4217 code of the ad account that owns this ad (e.g. USD, THB, INR). All money values in `summary` and `daily` are in this currency. Null only on legacy ads synced before currency was persisted.
- **analytics** `object`: 
  - **summary**: `AdMetrics` - See schema definition
  - **daily** `array[items]`: 
  - **breakdowns** `object`: No description

#### 202: Historical data is incomplete and backfill remains pending.

**Response Body:**

- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **ad** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **platform** `string`: No description
  - **status** `string`: No description
  - **currency** `string,null`: ISO 4217 code of the ad account that owns this ad (e.g. USD, THB, INR). All money values in `summary` and `daily` are in this currency. Null only on legacy ads synced before currency was persisted.
- **analytics** `object`: 
  - **summary**: `AdMetrics` - See schema definition
  - **daily** `array[items]`: 
  - **breakdowns** `object`: No description
- **backfillPending** (required) `boolean`: Always true on this response. Part of the requested range is still being backfilled; retry until the request returns 200.

#### 400: Invalid parameter (e.g. an unknown `breakdowns` dimension). The message lists the offending value(s) and the supported set.

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
