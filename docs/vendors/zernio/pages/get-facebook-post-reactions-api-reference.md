# Get Facebook post reactions API Reference

Returns the reaction breakdown for a Facebook Page post: a count per reaction type
plus the overall total.

The whole breakdown is fetched in a single Graph call. The post analytics
endpoint reports only an aggregate reaction count (surfaced there as `likes`), so use
this endpoint when you need per-type counts.


## GET /v1/accounts/{accountId}/facebook-post-reactions

**Get Facebook post reactions**

Returns the reaction breakdown for a Facebook Page post: a count per reaction type
plus the overall total.

The whole breakdown is fetched in a single Graph call. The post analytics
endpoint reports only an aggregate reaction count (surfaced there as `likes`), so use
this endpoint when you need per-type counts.


### Parameters

- **accountId** (required) in path: The ID of the Facebook Page account
- **postId** (required) in query: The Facebook post ID

### Responses

#### 200: Reaction breakdown for the post

**Response Body:**

- **accountId** `string`: No description
- **platform** `string`: No description (example: "facebook")
- **username** `string`: No description
- **postId** `string`: No description
- **total** `integer`: Total reactions across all types
- **breakdown** `object`: Count per reaction type. A type with no reactions returns 0.
  - **like** `integer`: No description
  - **love** `integer`: No description
  - **haha** `integer`: No description
  - **wow** `integer`: No description
  - **sad** `integer`: No description
  - **angry** `integer`: No description
  - **care** `integer`: No description
- **lastUpdated** `string` (date-time): No description

#### 400: Invalid accountId format, not a Facebook account, or missing postId parameter

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

#### 502: Facebook rejected the request

---

---
