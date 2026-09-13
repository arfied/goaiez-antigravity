# Get best times to post API Reference

Returns the best times to post based on historical engagement data.
Groups all published posts by day of week and hour (UTC), calculating average engagement per slot.
Use this to auto-schedule posts at optimal times. Requires the Analytics add-on.


## GET /v1/analytics/best-time

**Get best times to post**

Returns the best times to post based on historical engagement data.
Groups all published posts by day of week and hour (UTC), calculating average engagement per slot.
Use this to auto-schedule posts at optimal times. Requires the Analytics add-on.


### Parameters

- **platform** (optional) in query: Filter by platform (e.g. "instagram", "tiktok"). Omit for all platforms.
- **profileId** (optional) in query: Filter by profile ID. Omit for all profiles.
- **accountId** (optional) in query: Filter by account ID. Omit for all accounts.
- **source** (optional) in query: Filter by post origin. "late" for posts published via Zernio, "external" for posts imported from platforms.

### Responses

#### 200: Best time slots

**Response Body:**

- **slots** `array[object]`: 
  - **day_of_week** `integer`: 0=Monday, 6=Sunday
  - **hour** `integer`: Hour in UTC (0-23)
  - **avg_engagement** `number`: Average engagement (likes + comments + shares + saves)
  - **post_count** `integer`: Number of posts in this slot

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **requiresAddon** `boolean`: No description (example: true)

---

---
