# Search recent tweets API Reference

Search public tweets from the last 7 days matching an X search query, e.g. to discover tweets to reply to.
The query string is passed through to X unchanged and supports X's search operators
(`from:user`, `-is:retweet`, `is:reply`, `lang:en`, `"exact phrase"`, `conversation_id:123`, boolean `OR`, ...).
Standalone operators like `is:` / `has:` / `lang:` must be combined with a keyword or `from:` clause.

To reply to a found tweet, pass its `id` as the twitter platform entry's `platformSpecificData.replyToTweetId` when creating a post.

Rate limit: 300 requests per 15-min window per connected account.


## GET /v1/twitter/search

**Search recent tweets**

Search public tweets from the last 7 days matching an X search query, e.g. to discover tweets to reply to.
The query string is passed through to X unchanged and supports X's search operators
(`from:user`, `-is:retweet`, `is:reply`, `lang:en`, `"exact phrase"`, `conversation_id:123`, boolean `OR`, ...).
Standalone operators like `is:` / `has:` / `lang:` must be combined with a keyword or `from:` clause.

To reply to a found tweet, pass its `id` as the twitter platform entry's `platformSpecificData.replyToTweetId` when creating a post.

Rate limit: 300 requests per 15-min window per connected account.


### Parameters

- **accountId** (required) in query: The account ID
- **query** (required) in query: X search query, max 512 characters. Operators are passed through unchanged; X rejects malformed queries with a 400.
- **limit** (optional) in query: Results per page. X requires a minimum of 10; values below 10 are rejected.
- **sinceId** (optional) in query: Only return tweets with an ID greater than (more recent than) this numeric tweet ID. Non-numeric values are rejected with 400.
- **untilId** (optional) in query: Only return tweets with an ID less than (older than) this numeric tweet ID. Non-numeric values are rejected with 400.
- **startTime** (optional) in query: Oldest UTC timestamp (ISO 8601, inclusive), within the last 7 days
- **endTime** (optional) in query: Newest UTC timestamp (ISO 8601, exclusive), within the last 7 days
- **cursor** (optional) in query: Pagination cursor from a previous response
- **sortOrder** (optional) in query: No description

### Responses

#### 200: Matching tweets

**Response Body:**

- **status** `string`: No description (example: "success")
- **tweets** `array[object]`: 
  - **id** `string`: No description
  - **text** `string`: No description
  - **created** `string` (date-time): No description
  - **conversationId** `string`: No description
  - **inReplyToTweetId** `string,null`: Parent tweet ID when the result is itself a reply
  - **lang** `string`: No description
  - **author** `object`: 
    - **id** `string`: No description
    - **username** `string`: No description
    - **displayName** `string`: No description
    - **avatar** `string`: No description
    - **verifiedType** `string`: No description
  - **likeCount** `integer`: No description
  - **replyCount** `integer`: No description
  - **retweetCount** `integer`: No description
  - **quoteCount** `integer`: No description
  - **platform** `string`: No description (example: "twitter")
- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **cursor** `string,null`: No description
- **meta** `object`: 
  - **resultCount** `integer`: No description
  - **newestId** `string,null`: No description
  - **oldestId** `string,null`: No description
  - **platform** `string`: No description (example: "twitter")

#### 400: Bad request (invalid params, or X rejected the query as malformed)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: X API spend cap reached for this billing period

#### 403: X analytics capability not enabled for this account (code X_ANALYTICS_NOT_ENABLED)

#### 404: Account not found

#### 429: X search rate limit exceeded (300 requests per 15 minutes)

---

---
