# Get frequency vs engagement API Reference

Returns the correlation between posting frequency (posts per week) and engagement rate, broken down by platform.
Helps find the optimal posting cadence for each platform. Each row represents a specific (platform, posts_per_week) combination
with the average engagement rate observed across all weeks matching that frequency.
Requires the Analytics add-on.


## GET /v1/analytics/posting-frequency

**Get frequency vs engagement**

Returns the correlation between posting frequency (posts per week) and engagement rate, broken down by platform.
Helps find the optimal posting cadence for each platform. Each row represents a specific (platform, posts_per_week) combination
with the average engagement rate observed across all weeks matching that frequency.
Requires the Analytics add-on.


### Parameters

- **platform** (optional) in query: Filter by platform (e.g. "instagram", "tiktok"). Omit for all platforms.
- **profileId** (optional) in query: Filter by profile ID. Omit for all profiles.
- **accountId** (optional) in query: Filter by account ID. Omit for all accounts.
- **source** (optional) in query: Filter by post origin. "late" for posts published via Zernio, "external" for posts imported from platforms.

### Responses

#### 200: Posting frequency data

**Response Body:**

- **frequency** `array[object]`: 
  - **platform** `string`: No description (example: "instagram")
  - **posts_per_week** `integer`: Number of posts published that week
  - **avg_engagement_rate** `number`: Average engagement rate as percentage (0-100)
  - **avg_engagement** `number`: Average raw engagement (likes+comments+shares+saves)
  - **weeks_count** `integer`: Number of calendar weeks observed at this frequency

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **requiresAddon** `boolean`: No description (example: true)

---

---
