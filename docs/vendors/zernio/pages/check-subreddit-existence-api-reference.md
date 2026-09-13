# Check subreddit existence API Reference

Check if a subreddit exists and return basic info (title, subscriber count, NSFW status, post types allowed).

When accountId is provided, uses authenticated Reddit OAuth API with automatic token refresh (recommended). Falls back to Reddit's public JSON API, which may be unreliable from server IPs. Returns exists: false for private, banned, or nonexistent subreddits.


## GET /v1/tools/validate/subreddit

**Check subreddit existence**

Check if a subreddit exists and return basic info (title, subscriber count, NSFW status, post types allowed).

When accountId is provided, uses authenticated Reddit OAuth API with automatic token refresh (recommended). Falls back to Reddit's public JSON API, which may be unreliable from server IPs. Returns exists: false for private, banned, or nonexistent subreddits.


### Parameters

- **name** (required) in query: Subreddit name (with or without "r/" prefix)
- **accountId** (optional) in query: Reddit account ID for authenticated lookup (recommended for reliable results)

### Responses

#### 200: Subreddit lookup result

**Response Body:**

*One of the following:*
  - **exists** `boolean`: No description
  - **subreddit** `object`: 
    - **name** `string`: No description (example: "programming")
    - **title** `string`: No description (example: "programming")
    - **description** `string`: No description (example: "Computer Programming")
    - **subscribers** `integer`: No description (example: 6844284)
    - **isNSFW** `boolean`: No description
    - **type** `string`: No description - one of: public, private, restricted (example: "public")
    - **allowImages** `boolean`: No description
    - **allowVideos** `boolean`: No description
  - **exists** `boolean`: No description
  - **error** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
