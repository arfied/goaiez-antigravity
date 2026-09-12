# Get Facebook Page insights API Reference

Returns page-level Facebook insights (media views, views, post engagements, video metrics,
follower counts). Response shape matches /v1/analytics/instagram/account-insights so the
same client handling works across platforms.

Metric names track the current (post-November 2025) Meta Graph API. The legacy
page_impressions / page_fans / page_fan_adds / page_fan_removes metrics were deprecated
by Meta on November 15, 2025 and are NOT accepted by this endpoint. Use the replacements
below. Because Meta did not provide direct adds/removes replacements, Zernio synthesizes
followers_gained / followers_lost from the daily follower snapshotter.

Max 89 days, defaults to last 30 days. Requires the Analytics add-on.


## GET /v1/analytics/facebook/page-insights

**Get Facebook Page insights**

Returns page-level Facebook insights (media views, views, post engagements, video metrics,
follower counts). Response shape matches /v1/analytics/instagram/account-insights so the
same client handling works across platforms.

Metric names track the current (post-November 2025) Meta Graph API. The legacy
page_impressions / page_fans / page_fan_adds / page_fan_removes metrics were deprecated
by Meta on November 15, 2025 and are NOT accepted by this endpoint. Use the replacements
below. Because Meta did not provide direct adds/removes replacements, Zernio synthesizes
followers_gained / followers_lost from the daily follower snapshotter.

Max 89 days, defaults to last 30 days. Requires the Analytics add-on.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the connected Facebook Page.
- **metrics** (optional) in query: Comma-separated list of metrics. Defaults to
"page_media_view,page_post_engagements,page_follows,followers_gained,followers_lost".

Live Meta metrics (current names, post-Nov-2025):
  - page_media_view       (replaces deprecated page_impressions)
  - page_views_total
  - page_post_engagements
  - page_video_views
  - page_video_view_time
  - page_follows          (replaces deprecated page_fans)

Zernio-synthesized from daily follower snapshots (filling the Nov-2025 gap
left by the page_fan_adds / page_fan_removes deprecation):
  - followers_gained
  - followers_lost

Monetization (opt-in, not in the defaults):
  - content_monetization_earnings
  - monetization_approximate_earnings

Each monetization metric is fetched with its own separate Graph call, so requesting both
adds two calls. Values are approximate and Meta restates them after the fact.

content_monetization_earnings returns an object per day and always carries unit
"micro_amount" plus an ISO 4217 "currency". monetization_approximate_earnings returns a bare
number per day, so its unit is always "unspecified" and its "currency" is always null. The two
are on different scales and are not comparable to each other. Both keep their daily "values"
on every metricType and are never rescaled by Zernio.

Earnings here are Page-level daily buckets and "total" is their sum. Meta does not
document whether a bucket carries that day's earnings or a running total, and every
Page measured so far earned exactly 0, so reconcile "total" against the Page's own Meta
export before relying on it; the daily "values" are always returned for that purpose.
Per-post lifetime earnings are served by GET /v1/analytics/facebook/post-earnings.

A Page that is not enrolled in monetization, or that earned nothing, returns normal daily
buckets of 0 in "metrics": Meta does not distinguish the two, so a 0 total here does NOT mean
the Page is enrolled. "unavailableMetrics" covers the narrower case where Meta returned no
bucket for the metric at all ("no_data") or rejected the request outright, and the metric is
then omitted from "metrics" rather than reported as 0.

- **since** (optional) in query: Start date (YYYY-MM-DD). Defaults to 30 days ago.
- **until** (optional) in query: End date (YYYY-MM-DD). Defaults to today.
- **metricType** (optional) in query: "total_value" (default) returns aggregated totals only.
"time_series" returns daily values in the "values" array.


### Responses

#### 200: Page insights data

**Response Body:**

- **success** `boolean`: No description (example: true)
- **accountId** `string`: The Zernio SocialAccount ID
- **platform** `string`: Platform that served this response. - one of: facebook, instagram, youtube, linkedin, tiktok
- **dateRange** `object`: 
  - **since** `string` (date): No description
  - **until** `string` (date): No description
- **metricType** `string`: No description - one of: time_series, total_value
- **breakdown** `string`: Breakdown dimension used (only present when breakdown was requested)
- **metrics** `object`: Object keyed by metric name. For time_series: each metric has "total" (number) and "values" (array of {date, value}).
For total_value: each metric has "total" (number) and optionally "breakdowns" (array of {dimension, value}).

Monetary metrics additionally carry "unit" and "currency". Zernio never rescales money:
"total" and every "values[].value" are the platform's raw numbers in the stated unit.
Monetary metrics also keep "values" on metricType=total_value, because their "total" is the
sum of the daily buckets the platform returned over the range: keep the series so you can
reconcile that sum against the platform's own reporting before invoicing on it.
A metric that could not be served is absent from this object and listed in
"unavailableMetrics" instead, so an unavailable metric is never reported as a zero.

- **unavailableMetrics** `array[object]`: Requested metrics that could not be served. Present only when at least one metric is
unavailable, and absent otherwise. Each listed metric is OMITTED from "metrics" rather than
reported as 0, which is how an unavailable metric is distinguished from a genuine zero.
The request itself still succeeds with HTTP 200.

  - **metric** `string`: The requested metric name.
  - **reason** `string`: "not_enrolled": the account is not enrolled in the program behind this metric.
"permission_missing": the connected user lacks access to this metric.
"unsupported_metric": the platform does not accept this metric name on the API version Zernio uses.
"no_data": the platform returned no bucket for this metric over the requested range.
"unreadable_value": the platform returned a value shape Zernio cannot read, so no total is reported.
"mixed_currency": readable values disagree on currency or unit within the range.
"upstream_error": any other platform failure.

"no_data" is the common case in practice. The others are defensive: "not_enrolled" and
"unsupported_metric" in particular have not been observed on live Facebook traffic, since
a non-enrolled Page returns zeros rather than an error and metric names are validated
before any platform call.
 - one of: not_enrolled, permission_missing, unsupported_metric, no_data, unreadable_value, mixed_currency, upstream_error
  - **message** `string`: Platform-provided explanation when available (access tokens redacted), otherwise Zernio copy.
- **dataDelay** `string`: No description (example: "Data may be delayed up to 48 hours")

#### 400: Bad request. Common cases:
  - Requested a deprecated metric (page_impressions, page_fans, page_fan_adds, page_fan_removes) - use current names instead
  - Account has no Page selected (metadata.pageAccessToken missing)
  - Invalid accountId / metrics / metricType / date range
  - Account is not a Facebook account


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

#### 404: Account not found

---
