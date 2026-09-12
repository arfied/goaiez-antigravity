# Set default subreddit API Reference

Sets the default subreddit used when publishing posts for this Reddit account.

## GET /v1/accounts/{accountId}/reddit-subreddits

**List Reddit subreddits**

Returns the subreddits the connected Reddit account can post to. Use this to get a subreddit name when creating a Reddit post.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Subreddits list

**Response Body:**

- **subreddits** `array[object]`: 
  - **id** `string`: Reddit subreddit ID
  - **name** `string`: Subreddit name without r/ prefix
  - **title** `string`: Subreddit title
  - **url** `string`: Subreddit URL path
  - **over18** `boolean`: Whether the subreddit is NSFW
- **defaultSubreddit** `string`: Currently set default subreddit for posting

#### 400: Not a Reddit account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

## PUT /v1/accounts/{accountId}/reddit-subreddits

**Set default subreddit**

Sets the default subreddit used when publishing posts for this Reddit account.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **defaultSubreddit** (required) `string`: No description

### Responses

#### 200: Default subreddit set

**Response Body:**

- **success** `boolean`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

---
