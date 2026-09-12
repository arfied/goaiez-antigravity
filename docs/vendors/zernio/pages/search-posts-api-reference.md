# Search posts API Reference

Search Reddit posts using a connected account. Optionally scope to a specific subreddit.

## GET /v1/reddit/search

**Search posts**

Search Reddit posts using a connected account. Optionally scope to a specific subreddit.

### Parameters

- **accountId** (required) in query: No description
- **subreddit** (optional) in query: No description
- **q** (required) in query: No description
- **restrict_sr** (optional) in query: No description
- **sort** (optional) in query: No description
- **limit** (optional) in query: No description
- **after** (optional) in query: No description

### Responses

#### 200: Search results

**Response Body:**

- **items** `array[RedditPost]`: 
- **after** `string,null`: No description
- **before** `string,null`: No description

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

#### 404: No active Reddit account with this ID is available to the API key.
It may have been disconnected or deleted, or it belongs to a profile
the key cannot access. Re-connecting an account issues a NEW account
ID, so an ID stored from before a reconnect will not resolve.


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

#### 429: The connected account's upstream platform quota is exhausted.

Reddit rate-limits per connected Reddit user (1000 requests per
10-minute window), and that budget is shared by every operation using
that account. Retry after the window resets rather than retrying
immediately; repeated calls while exhausted do not succeed and keep the
budget spent.


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

---
