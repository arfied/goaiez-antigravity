# Get TikTok account-level insights API Reference

Returns account-level TikTok insights from /v2/user/info/ (live) plus historical
time series joined from Zernio's daily snapshotter (AccountStats).

Response shape matches /v1/analytics/instagram/account-insights. Max 89 days,
defaults to last 30 days. Requires the Analytics add-on and the user.info.stats
scope on the account (412 if missing).

Scope intentionally narrow. TikTok's public API exposes only the four counter
metrics below. The deep metrics that live in TikTok Studio are NOT available on any
public TikTok API, even for Business accounts:
  - profile_views
  - account-level impressions / reach
  - follower inflow / outflow breakdown
  - video watch time, average watch time, full-watched rate
  - impression_sources (FYP / Following / Hashtag / Search / Personal profile)

TikTok's Research API doesn't expose those fields either, and is restricted to
non-commercial academic use per TikTok's eligibility policy. There is no public
API workaround. Post-level metrics (views, likes, comments, shares per video) are
available via /v1/analytics?postId=... from TikTok's /v2/video/query/.


## GET /v1/analytics/tiktok/account-insights

**Get TikTok account-level insights**

Returns account-level TikTok insights from /v2/user/info/ (live) plus historical
time series joined from Zernio's daily snapshotter (AccountStats).

Response shape matches /v1/analytics/instagram/account-insights. Max 89 days,
defaults to last 30 days. Requires the Analytics add-on and the user.info.stats
scope on the account (412 if missing).

Scope intentionally narrow. TikTok's public API exposes only the four counter
metrics below. The deep metrics that live in TikTok Studio are NOT available on any
public TikTok API, even for Business accounts:
  - profile_views
  - account-level impressions / reach
  - follower inflow / outflow breakdown
  - video watch time, average watch time, full-watched rate
  - impression_sources (FYP / Following / Hashtag / Search / Personal profile)

TikTok's Research API doesn't expose those fields either, and is restricted to
non-commercial academic use per TikTok's eligibility policy. There is no public
API workaround. Post-level metrics (views, likes, comments, shares per video) are
available via /v1/analytics?postId=... from TikTok's /v2/video/query/.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the TikTok account.
- **metrics** (optional) in query: Comma-separated list. Defaults to
"follower_count,likes_count,video_count,followers_gained,followers_lost".

Live from /v2/user/info/ (requires user.info.stats scope):
  - follower_count  (cumulative; time series joined from AccountStats)
  - following_count (cumulative; time series joined from AccountStats.metadata)
  - likes_count     (cumulative; time series joined from AccountStats.metadata)
  - video_count     (cumulative; time series joined from AccountStats.metadata)

Zernio-synthesized:
  - followers_gained  (sum of positive daily follower deltas)
  - followers_lost    (sum of absolute negative daily deltas)

- **since** (optional) in query: Start date (YYYY-MM-DD). Defaults to 30 days ago.
- **until** (optional) in query: End date (YYYY-MM-DD). Defaults to today.
- **metricType** (optional) in query: "total_value" returns the latest cumulative counter value.
"time_series" returns daily values joined from AccountStats snapshots.


### Responses

#### 200: Account insights data

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

#### 400: Bad request (invalid accountId / metrics / metricType / date range, or account is not a TikTok account)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

#### 404: Account not found

#### 412: Missing user.info.stats scope

---
