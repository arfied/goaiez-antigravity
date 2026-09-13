# Get campaign analytics API Reference

Returns performance analytics for a whole campaign in one call: summary metrics, a daily
timeline over the requested date range (summed across the campaign's ads), and optional
demographic breakdowns. Breakdowns are fetched live from Meta at the campaign level (one call
per dimension, no per-ad fan-out), so an agency dashboard gets campaign-level age/gender/etc.
without summing thousands of per-ad reads. `campaignId` is the platform campaign id; pass
`platform` when a campaign id could be ambiguous across platforms. If no date range is provided,
defaults to the last 90 days. Date range is capped at 730 days max.
Google adds searchImpressionShare, searchBudgetLostImpressionShare,
searchRankLostImpressionShare, searchTopImpressionShare and searchAbsoluteTopImpressionShare
under analytics.summary for the requested inclusive range. These ratios are queried
together without daily segmentation and cached for 10 minutes. Unavailable values are
null. analytics.impressionShareCache reports cachedAt and stale independently of synced metrics.


## GET /v1/ads/campaigns/{campaignId}/analytics

**Get campaign analytics**

Returns performance analytics for a whole campaign in one call: summary metrics, a daily
timeline over the requested date range (summed across the campaign's ads), and optional
demographic breakdowns. Breakdowns are fetched live from Meta at the campaign level (one call
per dimension, no per-ad fan-out), so an agency dashboard gets campaign-level age/gender/etc.
without summing thousands of per-ad reads. `campaignId` is the platform campaign id; pass
`platform` when a campaign id could be ambiguous across platforms. If no date range is provided,
defaults to the last 90 days. Date range is capped at 730 days max.
Google adds searchImpressionShare, searchBudgetLostImpressionShare,
searchRankLostImpressionShare, searchTopImpressionShare and searchAbsoluteTopImpressionShare
under analytics.summary for the requested inclusive range. These ratios are queried
together without daily segmentation and cached for 10 minutes. Unavailable values are
null. analytics.impressionShareCache reports cachedAt and stale independently of synced metrics.


### Parameters

- **campaignId** (required) in path: Platform campaign id (platformCampaignId).
- **platform** (optional) in query: Disambiguate when the campaign id exists across platforms (e.g. facebook, instagram).
- **fromDate** (optional) in query: Start of date range (YYYY-MM-DD). Defaults to 90 days ago.
- **toDate** (optional) in query: End of date range (YYYY-MM-DD). Defaults to today. Max 730-day range.
- **breakdowns** (optional) in query: Comma-separated breakdown dimensions.

**Meta**: age, gender, country, publisher_platform, device_platform, region,
platform_position, impression_device, video_asset, image_asset, body_asset, title_asset.

**LinkedIn** (firmographics): job_title, job_function, seniority, industry,
company, company_size, country, region. Rows carry the raw pivot `value`
plus a resolved `name`. LinkedIn serves these aggregated over the whole
range, delays the data 12-24h, and omits segments with fewer than 3 events.


### Responses

#### 200: Campaign analytics

**Response Body:**

- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **campaign** `object`: 
  - **id** `string`: No description
  - **name** `string,null`: No description
  - **platform** `string`: No description
  - **status** `string,null`: Effective campaign status (ACTIVE when any child ad is active).
  - **budget**: Google only. Latest synced campaign budget, or null before sync.
  - **currency** `string,null`: ISO 4217 code of the ad account (e.g. USD, THB). All money values in `summary` and `daily` are in this currency.
- **analytics** `object`: 
  - **summary**: No description
  - **impressionShareCache** `object`: Google only. Cache status of the single date-range impression-share query.
    - **cachedAt** `string,null` (date-time): No description
    - **stale** `boolean`: No description
  - **daily** `array[items]`: 
  - **breakdowns** `object`: No description

#### 202: Historical data is incomplete and backfill remains pending.

**Response Body:**

- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **campaign** `object`: 
  - **id** `string`: No description
  - **name** `string,null`: No description
  - **platform** `string`: No description
  - **status** `string,null`: Effective campaign status (ACTIVE when any child ad is active).
  - **budget**: Google only. Latest synced campaign budget, or null before sync.
  - **currency** `string,null`: ISO 4217 code of the ad account (e.g. USD, THB). All money values in `summary` and `daily` are in this currency.
- **analytics** `object`: 
  - **summary**: No description
  - **impressionShareCache** `object`: Google only. Cache status of the single date-range impression-share query.
    - **cachedAt** `string,null` (date-time): No description
    - **stale** `boolean`: No description
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

#### 429: Google operations budget or quota exhausted without a cached impression-share result.

---
