# Get LinkedIn org analytics API Reference

Returns aggregate analytics for a LinkedIn organization page. Parallel to
/v1/accounts/{id}/linkedin-aggregate-analytics (which handles personal accounts only).
Backed by LinkedIn's organizationalEntityShareStatistics,
organizationalEntityFollowerStatistics, and organizationPageStatistics endpoints.

Response shape matches /v1/analytics/instagram/account-insights. Max 89 days,
defaults to last 30 days. Requires the Analytics add-on.

Scope requirements: r_organization_social, r_organization_followers, and
r_organization_admin must all be present on the account. Accounts connected before
these scopes were included in the OAuth flow will return 412 with a reauth hint.

Enforced by this endpoint:
  - Page-view metrics accept only metricType=total_value (LinkedIn omits per-day
    segmentation even when the API is called with DAY granularity, so a time-series
    response would be meaningless).
  - Date range capped at 89 days.

LinkedIn-side platform limits (not re-enforced here, but worth knowing for larger
ranges in a future release):
  - Follower stats: rolling 12-month window, end must be no later than 2 days ago.
  - Share stats: rolling 12-month window.


## GET /v1/analytics/linkedin/org-aggregate-analytics

**Get LinkedIn org analytics**

Returns aggregate analytics for a LinkedIn organization page. Parallel to
/v1/accounts/{id}/linkedin-aggregate-analytics (which handles personal accounts only).
Backed by LinkedIn's organizationalEntityShareStatistics,
organizationalEntityFollowerStatistics, and organizationPageStatistics endpoints.

Response shape matches /v1/analytics/instagram/account-insights. Max 89 days,
defaults to last 30 days. Requires the Analytics add-on.

Scope requirements: r_organization_social, r_organization_followers, and
r_organization_admin must all be present on the account. Accounts connected before
these scopes were included in the OAuth flow will return 412 with a reauth hint.

Enforced by this endpoint:
  - Page-view metrics accept only metricType=total_value (LinkedIn omits per-day
    segmentation even when the API is called with DAY granularity, so a time-series
    response would be meaningless).
  - Date range capped at 89 days.

LinkedIn-side platform limits (not re-enforced here, but worth knowing for larger
ranges in a future release):
  - Follower stats: rolling 12-month window, end must be no later than 2 days ago.
  - Share stats: rolling 12-month window.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the LinkedIn organization account.
- **metrics** (optional) in query: Comma-separated list. Defaults to
"impressions,clicks,engagement_rate,organic_followers_gained,followers_gained,followers_lost".

Share statistics (support both total_value and time_series):
  - impressions
  - unique_impressions
  - clicks
  - likes
  - comments
  - shares
  - engagement_rate       (0..1, LinkedIn-computed)

Follower-gain statistics (support total_value and time_series):
  - organic_followers_gained   (per-day organic gains for time_series; sum of organic gains over the range for total_value)
  - paid_followers_gained      (per-day paid gains for time_series; sum of paid gains over the range for total_value)

Page-view statistics (total_value ONLY - LinkedIn platform limit):
  - page_views_total
  - page_views_overview
  - page_views_careers
  - page_views_jobs
  - page_views_life

Zernio-synthesized from daily follower snapshots:
  - followers_gained
  - followers_lost

- **since** (optional) in query: Start date (YYYY-MM-DD). Defaults to 30 days ago.
- **until** (optional) in query: End date (YYYY-MM-DD). Defaults to today.
- **metricType** (optional) in query: No description

### Responses

#### 200: Organization analytics data

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
  - Account is a personal LinkedIn account, not organization (code personal_account_not_supported, use /v1/accounts/{id}/linkedin-aggregate-analytics instead)
  - Invalid metric name, metricType, or date range


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

#### 403: Platform error. The authenticated member lacks the required
ADMINISTRATOR role on the organization. LinkedIn enforces admin-only
access for all three org statistics endpoints. The error envelope is
type platform_error, and the raw LinkedIn error is echoed in the
platformError field.


#### 404: Account not found

#### 412: Missing LinkedIn organization analytics scopes (r_organization_social + r_organization_followers + r_organization_admin)

---
