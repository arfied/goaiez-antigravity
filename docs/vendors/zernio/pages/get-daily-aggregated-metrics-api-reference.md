# Get daily aggregated metrics API Reference

Returns daily aggregated analytics metrics and a per-platform breakdown.
Each day includes post count, platform distribution, and summed metrics (impressions, reach, likes, comments, shares, saves, clicks, views).
Defaults to the last 180 days. Requires the Analytics add-on.


## GET /v1/analytics/daily-metrics

**Get daily aggregated metrics**

Returns daily aggregated analytics metrics and a per-platform breakdown.
Each day includes post count, platform distribution, and summed metrics (impressions, reach, likes, comments, shares, saves, clicks, views).
Defaults to the last 180 days. Requires the Analytics add-on.


### Parameters

- **platform** (optional) in query: Filter by platform (e.g. "instagram", "tiktok"). Omit for all platforms.
- **profileId** (optional) in query: Filter by profile ID. Omit for all profiles.
- **accountId** (optional) in query: Filter by account ID
- **fromDate** (optional) in query: Inclusive start date (ISO 8601). Defaults to 180 days ago.
- **toDate** (optional) in query: Inclusive end date (ISO 8601). Defaults to now.
- **source** (optional) in query: Filter by post origin. "late" for posts published via Zernio, "external" for posts imported from platforms.
- **attribution** (optional) in query: How each post's engagement is attributed to a day.
"publish" (default) sums each post's lifetime total on its publish date.
"received" buckets the per-day increase in engagement by the day it actually arrived (engagement-over-time), so engagement on older posts appears on the day it was gained rather than the post's publish date.


### Responses

#### 200: Daily metrics and platform breakdown

**Response Body:**

- **dailyData** `array[object]`: 
  - **date** `string`: No description (example: "2025-12-01")
  - **postCount** `integer`: No description (example: 3)
  - **platforms** `object`: No description (example: {"instagram":2,"twitter":1})
  - **metrics** `object`: 
    - **impressions** `integer`: No description
    - **reach** `integer`: No description
    - **likes** `integer`: No description
    - **comments** `integer`: No description
    - **shares** `integer`: No description
    - **saves** `integer`: No description
    - **clicks** `integer`: No description
    - **views** `integer`: No description
- **platformBreakdown** `array[object]`: 
  - **platform** `string`: No description (example: "instagram")
  - **postCount** `integer`: No description (example: 142)
  - **impressions** `integer`: No description
  - **reach** `integer`: No description
  - **likes** `integer`: No description
  - **comments** `integer`: No description
  - **shares** `integer`: No description
  - **saves** `integer`: No description
  - **clicks** `integer`: No description
  - **views** `integer`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **code** `string`: No description (example: "analytics_addon_required")

---

---
