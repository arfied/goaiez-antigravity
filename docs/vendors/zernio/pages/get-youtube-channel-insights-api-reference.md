# Get YouTube channel insights API Reference

Returns channel-scoped aggregate metrics from YouTube Analytics API v2. Saves you
from looping /v1/analytics/youtube/daily-views over every video when you only need
channel totals.

Response shape matches /v1/analytics/instagram/account-insights so the same client
handling works. Requires yt-analytics.readonly scope (412 with reauthorizeUrl if
missing). Data has a 2-3 day delay (endDate is clamped accordingly). Max 89 days,
defaults to last 30 days. Requires the Analytics add-on.

NOT exposed: impressions (Studio thumbnail impressions) and impressionsClickThroughRate.
YouTube Analytics API v2 does not expose these for any principal type, not channel
owners, not Partner Program channels, not content owners with CMS access. The only way
to get them is Studio CSV export. This is a Google-side limitation.


## GET /v1/analytics/youtube/channel-insights

**Get YouTube channel insights**

Returns channel-scoped aggregate metrics from YouTube Analytics API v2. Saves you
from looping /v1/analytics/youtube/daily-views over every video when you only need
channel totals.

Response shape matches /v1/analytics/instagram/account-insights so the same client
handling works. Requires yt-analytics.readonly scope (412 with reauthorizeUrl if
missing). Data has a 2-3 day delay (endDate is clamped accordingly). Max 89 days,
defaults to last 30 days. Requires the Analytics add-on.

NOT exposed: impressions (Studio thumbnail impressions) and impressionsClickThroughRate.
YouTube Analytics API v2 does not expose these for any principal type, not channel
owners, not Partner Program channels, not content owners with CMS access. The only way
to get them is Studio CSV export. This is a Google-side limitation.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the YouTube account.
- **metrics** (optional) in query: Comma-separated list. Defaults to "views,estimatedMinutesWatched,subscribersGained,subscribersLost".

Live YouTube Analytics v2 metrics:
  - views
  - estimatedMinutesWatched
  - averageViewDuration          (ratio - weighted mean computed across days)
  - subscribersGained
  - subscribersLost

Zernio-synthesized from daily follower snapshots (cross-platform parity):
  - followers_gained
  - followers_lost

- **since** (optional) in query: Start date (YYYY-MM-DD). Defaults to 30 days ago.
- **until** (optional) in query: End date (YYYY-MM-DD). Defaults to today. YouTube Analytics has a 2-3 day delay,
so the fetch is internally clamped to 3 days ago; any requested range extending
beyond that returns zero values for the tail days. The response's dateRange.until
field reflects your requested value.

- **metricType** (optional) in query: "total_value" (default) returns aggregated totals.
"time_series" returns per-day values in the "values" array.


### Responses

#### 200: Channel insights data

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

#### 400: Bad request (invalid accountId / metrics / metricType / date range, or account is not a YouTube account)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

#### 404: Account not found

#### 412: Missing YouTube Analytics scope

**Response Body:**

- **success** `boolean`: No description (example: false)
- **error** `string`: No description (example: "To access daily video analytics, please reconnect your YouTube account to grant the required permissions.")
- **code** `string`: No description (example: "youtube_analytics_scope_missing")
- **scopeStatus** `object`: 
  - **hasAnalyticsScope** `boolean`: No description (example: false)
  - **requiresReauthorization** `boolean`: No description (example: true)
  - **reauthorizeUrl** `string` (uri): URL to redirect user for reauthorization

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
