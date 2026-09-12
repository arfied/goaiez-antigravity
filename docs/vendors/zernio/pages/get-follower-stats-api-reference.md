# Get follower stats API Reference

Returns follower count history and growth metrics for connected accounts.
Requires analytics add-on subscription. Follower counts are refreshed once per day.


## GET /v1/accounts/follower-stats

**Get follower stats**

Returns follower count history and growth metrics for connected accounts.
Requires analytics add-on subscription. Follower counts are refreshed once per day.


### Parameters

- **accountIds** (optional) in query: Comma-separated list of account IDs (optional, defaults to all user's accounts)
- **profileId** (optional) in query: Filter by profile ID
- **fromDate** (optional) in query: Start date in YYYY-MM-DD format (defaults to 30 days ago)
- **toDate** (optional) in query: End date in YYYY-MM-DD format (defaults to today)
- **granularity** (optional) in query: Data aggregation level

### Responses

#### 200: Follower stats

**Response Body:**

- **accounts** `array[AccountWithFollowerStats]`: 
- **stats** `object`: No description
- **dateRange** `object`: 
  - **from** `string` (date-time): No description
  - **to** `string` (date-time): No description
- **granularity** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description (example: "Analytics add-on required")
- **message** `string`: No description (example: "Follower stats tracking requires the Analytics add-on. Please upgrade to access this feature.")
- **requiresAddon** `boolean`: No description (example: true)

---
