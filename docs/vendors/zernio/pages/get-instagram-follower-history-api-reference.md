# Get Instagram follower history API Reference

Returns a daily running Instagram follower count time series, served from Zernio's
cross-platform daily snapshotter. Exists because Meta removed follower_count from
the /insights endpoint in Graph API v22+ and never exposed a historical daily series
via any public API.

Response envelope matches /v1/analytics/instagram/account-insights so the same client
handling works. Max 89 days, defaults to last 30 days. Requires the Analytics add-on.


## GET /v1/analytics/instagram/follower-history

**Get Instagram follower history**

Returns a daily running Instagram follower count time series, served from Zernio's
cross-platform daily snapshotter. Exists because Meta removed follower_count from
the /insights endpoint in Graph API v22+ and never exposed a historical daily series
via any public API.

Response envelope matches /v1/analytics/instagram/account-insights so the same client
handling works. Max 89 days, defaults to last 30 days. Requires the Analytics add-on.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the Instagram account.
- **metrics** (optional) in query: Comma-separated list. Defaults to "follower_count,followers_gained,followers_lost".
  - follower_count   : per-day raw follower count
  - followers_gained : sum of positive daily deltas
  - followers_lost   : sum of absolute negative daily deltas

- **since** (optional) in query: Start date (YYYY-MM-DD). Defaults to 30 days ago.
- **until** (optional) in query: End date (YYYY-MM-DD). Defaults to today.
- **metricType** (optional) in query: "total_value" returns aggregated totals (latest for follower_count, sum for gained/lost).
"time_series" returns per-day values in the "values" array.


### Responses

#### 200: Follower history data

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

#### 400: Bad request (invalid accountId / metrics / date range, or account is not an Instagram account)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

#### 404: Account not found

---
