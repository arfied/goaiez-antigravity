# Get Instagram demographics API Reference

Returns audience demographic insights for an Instagram account, broken down by age, city, country, and/or gender.
Requires at least 100 followers. Returns top 45 entries per dimension.
Data may be delayed up to 48 hours. Requires the Analytics add-on.


## GET /v1/analytics/instagram/demographics

**Get Instagram demographics**

Returns audience demographic insights for an Instagram account, broken down by age, city, country, and/or gender.
Requires at least 100 followers. Returns top 45 entries per dimension.
Data may be delayed up to 48 hours. Requires the Analytics add-on.


### Parameters

- **accountId** (required) in query: The Zernio SocialAccount ID for the Instagram account
- **metric** (optional) in query: "follower_demographics" for follower audience data, or "engaged_audience_demographics" for engaged viewers.

- **breakdown** (optional) in query: Comma-separated list of demographic dimensions: age, city, country, gender.
Defaults to all four if omitted.

- **timeframe** (optional) in query: Time period for demographic data. Defaults to "this_month".


### Responses

#### 200: Demographic insights data

**Response Body:**

- **success** `boolean`: No description (example: true)
- **accountId** `string`: The Zernio SocialAccount ID
- **platform** `string`: No description (example: "instagram")
- **metric** `string`: No description - one of: follower_demographics, engaged_audience_demographics
- **timeframe** `string`: The timeframe used for demographic data - one of: this_week, this_month
- **demographics** `object`: Object keyed by breakdown dimension (age, city, country, gender)
- **note** `string`: No description (example: "Demographics show top 45 entries per dimension. Requires 100+ followers.")

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
