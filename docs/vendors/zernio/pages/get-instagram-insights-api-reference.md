# Get Instagram insights API Reference

Returns account-level Instagram insights such as reach, views, accounts engaged, and total interactions.
These metrics reflect the entire account's performance across all content surfaces (feed, stories, explore, profile),
and are fundamentally different from post-level metrics. Data may be delayed up to 48 hours.
Max 90 days, defaults to last 30 days. Requires the Analytics add-on.


## GET /v1/analytics/instagram/account-insights

**Get Instagram insights**

Returns account-level Instagram insights such as reach, views, accounts engaged, and total interactions.
These metrics reflect the entire account's performance across all content surfaces (feed, stories, explore, profile),
and are fundamentally different from post-level metrics. Data may be delayed up to 48 hours.
Max 90 days, defaults to last 30 days. Requires the Analytics add-on.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the Instagram account
- **metrics** (optional) in query: Comma-separated list of metrics. Defaults to "reach,views,accounts_engaged,total_interactions".
Valid metrics: reach, views, accounts_engaged, total_interactions, comments, likes, saves, shares,
replies, reposts, follows_and_unfollows, profile_links_taps.
Note: only "reach" supports metricType=time_series. All other metrics (including
follows_and_unfollows) are total_value only. This is an Instagram Graph API limitation,
not a Zernio limitation - the IG API does not return time-series data for these metrics.
For a daily running follower count, use /v1/analytics/instagram/follower-history instead.

- **since** (optional) in query: Start date (YYYY-MM-DD). Defaults to 30 days ago.
- **until** (optional) in query: End date (YYYY-MM-DD). Defaults to today.
- **metricType** (optional) in query: "total_value" (default) returns aggregated totals and supports breakdowns.
"time_series" returns daily values but only works with the "reach" metric.

- **breakdown** (optional) in query: Breakdown dimension (only valid with metricType=total_value).
Valid values depend on the metric: media_product_type, follow_type, follower_type, contact_button_type.


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

#### 400: Bad request (invalid parameters)

**Response Body:**

- **error** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **code** `string`: No description (example: "analytics_addon_required")

#### 403: Access denied to this account

**Response Body:**

- **error** `string`: No description (example: "Access denied to this account")

#### 404: Account not found

**Response Body:**

- **error** `string`: No description (example: "Account not found")

---
