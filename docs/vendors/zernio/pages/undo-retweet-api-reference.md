# Undo retweet API Reference

Undo a retweet (un-repost a tweet).


## POST /v1/twitter/retweet

**Retweet a post**

Retweet (repost) a tweet by ID.
Rate limit: 50 requests per 15-min window. Shares the 300/3hr creation limit with tweet creation.


### Request Body

- **accountId** (required) `string`: The account ID
- **tweetId** (required) `string`: The ID of the tweet to retweet

### Responses

#### 200: Tweet retweeted

**Response Body:**

- **status** `string`: No description (example: "success")
- **tweetId** `string`: No description
- **retweeted** `boolean`: No description
- **platform** `string`: No description (example: "twitter")

#### 400: Bad request or platform limitation

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: X rejected the request (e.g. suspended account, missing OAuth scope)

#### 404: Account not found

---

## DELETE /v1/twitter/retweet

**Undo retweet**

Undo a retweet (un-repost a tweet).


### Parameters

- **accountId** (required) in query: No description
- **tweetId** (required) in query: The ID of the original tweet to un-retweet

### Responses

#### 200: Retweet undone

**Response Body:**

- **status** `string`: No description (example: "success")
- **tweetId** `string`: No description
- **retweeted** `boolean`: No description (example: false)
- **platform** `string`: No description (example: "twitter")

#### 400: Bad request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: X rejected the request (e.g. suspended account, missing OAuth scope)

#### 404: Account not found

---

---
