# Get LinkedIn aggregate stats API Reference

Returns aggregate analytics across all posts for a LinkedIn personal account. Only includes posts published through Zernio (LinkedIn API limitation). Org accounts should use /v1/analytics instead. Requires r_member_postAnalytics scope. Saves (POST_SAVE) and sends (POST_SEND) are available for personal accounts; organization pages always return 0 for these two metrics because LinkedIn does not expose them on the organization analytics endpoint.

## GET /v1/accounts/{accountId}/linkedin-aggregate-analytics

**Get LinkedIn aggregate stats**

Returns aggregate analytics across all posts for a LinkedIn personal account. Only includes posts published through Zernio (LinkedIn API limitation). Org accounts should use /v1/analytics instead. Requires r_member_postAnalytics scope. Saves (POST_SAVE) and sends (POST_SEND) are available for personal accounts; organization pages always return 0 for these two metrics because LinkedIn does not expose them on the organization analytics endpoint.

### Parameters

- **accountId** (required) in path: The ID of the LinkedIn personal account
- **aggregation** (optional) in query: TOTAL (default, lifetime totals) or DAILY (time series). MEMBERS_REACHED not available with DAILY.
- **startDate** (optional) in query: Start date (YYYY-MM-DD). If omitted, returns lifetime analytics.
- **endDate** (optional) in query: End date (YYYY-MM-DD, exclusive). Defaults to today if omitted.
- **metrics** (optional) in query: Comma-separated metrics: IMPRESSION, MEMBERS_REACHED, REACTION, COMMENT, RESHARE, POST_SAVE, POST_SEND. Omit for all.

### Responses

#### 200: Aggregate analytics data

**Response Body:**

*One of the following:*
- `LinkedInAggregateAnalyticsTotalResponse`
- `LinkedInAggregateAnalyticsDailyResponse`

#### 400: Invalid request

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description
- **validOptions** `array[string]`: 

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Analytics access required. Legacy plans need the Analytics add-on; included by default on usage-based plans.

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description

#### 403: Missing required LinkedIn scope

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description (example: "missing_scope")
- **requiredScope** `string`: No description (example: "r_member_postAnalytics")
- **action** `string`: No description (example: "reconnect")

#### 404: Account not found

---
