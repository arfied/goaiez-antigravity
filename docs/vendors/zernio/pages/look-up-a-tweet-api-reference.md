# Look up a tweet API Reference

Resolve a single tweet by ID or URL into its text, author and public metrics.

Use this to render a post you are referencing, e.g. the tweet quoted by a quote-style post.
Unlike `/v1/twitter/search` this is not limited to the last 7 days and works for any tweet
visible to the connected account.

Billed as an X posts read ($0.005). Repeat lookups of the same tweet within the same UTC day
are charged once.


## GET /v1/twitter/tweet

**Look up a tweet**

Resolve a single tweet by ID or URL into its text, author and public metrics.

Use this to render a post you are referencing, e.g. the tweet quoted by a quote-style post.
Unlike `/v1/twitter/search` this is not limited to the last 7 days and works for any tweet
visible to the connected account.

Billed as an X posts read ($0.005). Repeat lookups of the same tweet within the same UTC day
are charged once.


### Parameters

- **accountId** (required) in query: The account ID whose X token is used for the lookup
- **id** (required) in query: Numeric tweet ID or a tweet URL (e.g. https://x.com/user/status/123...)

### Responses

#### 200: The resolved tweet

**Response Body:**

- **status** `string`: No description (example: "success")
- **tweet** `object`: 
  - **id** `string`: No description
  - **text** `string`: No description
  - **created** `string` (date-time): No description
  - **conversationId** `string`: No description
  - **inReplyToTweetId** `string,null`: Parent tweet ID when the tweet is itself a reply
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

#### 400: Invalid request

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: X API spend cap reached for this billing period

#### 403: X analytics capability not enabled for this account (code X_ANALYTICS_NOT_ENABLED), or the tweet author is protected or suspended

#### 404: Account not found, or the tweet was deleted or never existed

#### 429: X rate limit exceeded

---
