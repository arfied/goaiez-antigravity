# Get Facebook post monetization earnings API Reference

Returns lifetime monetization earnings for ONE Facebook post, read live from Meta on every
request. Requires the Analytics add-on.

Earnings are CUMULATIVE since the post was published, not earnings within a date range, so
this endpoint takes no since/until and the totals must not be summed across dates or across
posts. Page-level daily earnings live on /v1/analytics/facebook/page-insights.

A post on a Page that is not enrolled in monetization, or that earned nothing, returns
"total": 0 rather than an error: Meta does not distinguish the two. A metric Meta returned no
bucket for at all is reported in "unavailableMetrics" and omitted from "metrics", never as a 0.

Amounts are the platform's raw numbers in the stated "unit" and are never rescaled by Zernio.
Breakdown dimensions are not exposed and a "breakdown" param is rejected with 400. So are
"since", "until", "period", and "metricType": scoping this endpoint to a window is not
possible, and silently returning the lifetime total for one would let a caller sum a year of
weekly requests into a figure ~52x the post's real earnings.


## GET /v1/analytics/facebook/post-earnings

**Get Facebook post monetization earnings**

Returns lifetime monetization earnings for ONE Facebook post, read live from Meta on every
request. Requires the Analytics add-on.

Earnings are CUMULATIVE since the post was published, not earnings within a date range, so
this endpoint takes no since/until and the totals must not be summed across dates or across
posts. Page-level daily earnings live on /v1/analytics/facebook/page-insights.

A post on a Page that is not enrolled in monetization, or that earned nothing, returns
"total": 0 rather than an error: Meta does not distinguish the two. A metric Meta returned no
bucket for at all is reported in "unavailableMetrics" and omitted from "metrics", never as a 0.

Amounts are the platform's raw numbers in the stated "unit" and are never rescaled by Zernio.
Breakdown dimensions are not exposed and a "breakdown" param is rejected with 400. So are
"since", "until", "period", and "metricType": scoping this endpoint to a window is not
possible, and silently returning the lifetime total for one would let a caller sum a year of
weekly requests into a figure ~52x the post's real earnings.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the connected Facebook Page.
- **postId** (required) in query: The platform post ID, exactly as returned in platformAnalytics[].platformPostId by
/v1/analytics: "{pageId}_{postId}", or the bare video ID for Reels.

- **metrics** (optional) in query: Comma-separated list of monetization metrics. Defaults to both:
  - content_monetization_earnings
  - monetization_approximate_earnings

content_monetization_earnings always carries unit "micro_amount" plus an ISO 4217
"currency". monetization_approximate_earnings is always a bare number, so its unit is
"unspecified" and its "currency" is null. The two are on different scales and are not
comparable to each other. Any other metric name is rejected with 400.


### Responses

#### 200: Lifetime per-post monetization earnings

**Response Body:**

- **success** `boolean`: No description (example: true)
- **accountId** `string`: No description (example: "64e1a2b3c4d5e6f7a8b9c0d1")
- **postId** `string`: The platform post ID that was queried, echoed back. (example: "123456789_987654321")
- **platform** `string`: No description (example: "facebook")
- **period** `string`: Always "lifetime": the total is cumulative since publication and must not be summed
across dates or across posts.
 - one of: lifetime
- **metrics** `object`: One entry per served metric. A metric reported here with "total": 0 genuinely earned
nothing (or its Page is not enrolled, which Meta reports identically).

- **unavailableMetrics** `array[object]`: Requested metrics Meta could not serve. Present only when at least one metric is
unavailable, and absent otherwise. Each listed metric is OMITTED from "metrics" rather than
reported as 0. The request itself still succeeds with HTTP 200.

  - **metric** `string`: The requested metric name.
  - **reason** `string`: "not_enrolled": the account is not enrolled in the program behind this metric.
"permission_missing": the connected user lacks access to this metric.
"unsupported_metric": Meta does not accept this metric name on the API version Zernio uses.
"no_data": Meta returned no bucket for this metric.
"unreadable_value": Meta returned a value shape Zernio cannot read, so no total is reported.
"mixed_currency": readable values disagree on currency or unit.
"upstream_error": any other platform failure.

"no_data" is the common case in practice; the others are defensive.
 - one of: not_enrolled, permission_missing, unsupported_metric, no_data, unreadable_value, mixed_currency, upstream_error
  - **message** `string`: Platform-provided explanation when available (access tokens redacted), otherwise Zernio copy.
- **dataDelay** `string`: No description

#### 400: Bad request. Common cases:
  - Invalid accountId format, or a metric name that is not a monetization metric
  - A "breakdown" param was supplied (breakdown dimensions are not exposed)
  - A "since", "until", "period", or "metricType" param was supplied (this endpoint returns a lifetime total and takes no date range)
  - Account has no Page access token (metadata.pageAccessToken missing)
  - Account is not a Facebook account


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

#### 404: Account not found

---
