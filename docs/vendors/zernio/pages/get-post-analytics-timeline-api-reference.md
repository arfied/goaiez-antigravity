# Get post analytics timeline API Reference

Returns a daily timeline of analytics metrics for a specific post, showing how impressions, likes,
and other metrics evolved day-by-day since publishing. Each row represents one day of data per platform.
For multi-platform Zernio posts, returns separate rows for each platform. Requires the Analytics add-on.


## GET /v1/analytics/post-timeline

**Get post analytics timeline**

Returns a daily timeline of analytics metrics for a specific post, showing how impressions, likes,
and other metrics evolved day-by-day since publishing. Each row represents one day of data per platform.
For multi-platform Zernio posts, returns separate rows for each platform. Requires the Analytics add-on.


### Parameters

- **postId** (required) in query: The post to fetch timeline for. Accepts an ExternalPost ID, a platformPostId, or a Zernio Post ID.

- **fromDate** (optional) in query: Start of date range (ISO 8601). Defaults to 90 days ago.
- **toDate** (optional) in query: End of date range (ISO 8601). Defaults to now.

### Responses

#### 200: Daily analytics timeline

**Response Body:**

- **postId** `string`: The postId that was requested
- **timeline** `array[object]`: 
  - **date** `string` (date): Date in YYYY-MM-DD format
  - **platform** `string`: Platform name (e.g. instagram, tiktok)
  - **platformPostId** `string`: Platform-specific post ID
  - **impressions** `integer`: Total impressions on this date
  - **reach** `integer`: Total reach on this date
  - **likes** `integer`: Total likes on this date
  - **comments** `integer`: Total comments on this date
  - **shares** `integer`: Total shares on this date
  - **saves** `integer`: Total saves on this date
  - **clicks** `integer`: Total clicks on this date
  - **views** `integer`: Total views on this date

#### 400: Missing required postId parameter

**Response Body:**

- **error** `string`: No description (example: "Missing required parameter: postId")

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **code** `string`: No description (example: "analytics_addon_required")

#### 403: Forbidden (post belongs to another user or API key scope violation)

**Response Body:**

- **error** `string`: No description (example: "Forbidden")

#### 404: Post not found

**Response Body:**

- **error** `string`: No description (example: "Post not found")

---

---
