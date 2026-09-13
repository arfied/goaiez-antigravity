# Bookmark a tweet API Reference

Bookmark a tweet by ID.
Requires the bookmark.write OAuth scope.
Rate limit: 50 requests per 15-min window.


## POST /v1/twitter/bookmark

**Bookmark a tweet**

Bookmark a tweet by ID.
Requires the bookmark.write OAuth scope.
Rate limit: 50 requests per 15-min window.


### Request Body

- **accountId** (required) `string`: The account ID
- **tweetId** (required) `string`: The ID of the tweet to bookmark

### Responses

#### 200: Tweet bookmarked

**Response Body:**

- **status** `string`: No description (example: "success")
- **tweetId** `string`: No description
- **bookmarked** `boolean`: No description
- **platform** `string`: No description (example: "twitter")

#### 400: Bad request or platform limitation

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: X rejected the request (e.g. suspended account, missing OAuth scope)

#### 404: Account not found

---

## DELETE /v1/twitter/bookmark

**Remove bookmark**

Remove a bookmark from a tweet.


### Parameters

- **accountId** (required) in query: No description
- **tweetId** (required) in query: The ID of the tweet to unbookmark

### Responses

#### 200: Bookmark removed

**Response Body:**

- **status** `string`: No description (example: "success")
- **tweetId** `string`: No description
- **bookmarked** `boolean`: No description (example: false)
- **platform** `string`: No description (example: "twitter")

#### 400: Bad request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: X rejected the request (e.g. suspended account, missing OAuth scope)

#### 404: Account not found

---

---
