# Get content performance decay API Reference

Returns how engagement accumulates over time after a post is published.
Each bucket shows what percentage of the post's total engagement had been reached by that time window.
Useful for understanding content lifespan (e.g. "posts reach 78% of total engagement within 24 hours").
Requires the Analytics add-on.


## GET /v1/analytics/content-decay

**Get content performance decay**

Returns how engagement accumulates over time after a post is published.
Each bucket shows what percentage of the post's total engagement had been reached by that time window.
Useful for understanding content lifespan (e.g. "posts reach 78% of total engagement within 24 hours").
Requires the Analytics add-on.


### Parameters

- **platform** (optional) in query: Filter by platform (e.g. "instagram", "tiktok"). Omit for all platforms.
- **profileId** (optional) in query: Filter by profile ID. Omit for all profiles.
- **accountId** (optional) in query: Filter by account ID. Omit for all accounts.
- **source** (optional) in query: Filter by post origin. "late" for posts published via Zernio, "external" for posts imported from platforms.

### Responses

#### 200: Content decay buckets

**Response Body:**

- **buckets** `array[object]`: 
  - **bucket_order** `integer`: Sort order (0 = earliest, 6 = latest)
  - **bucket_label** `string`: Human-readable label
  - **avg_pct_of_final** `number`: Average % of final engagement reached (0-100)
  - **post_count** `integer`: Number of posts with data in this bucket

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **requiresAddon** `boolean`: No description (example: true)

---

---
