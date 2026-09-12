# Unpin comment API Reference

Unpin a previously pinned comment. TikTok accounts connected through the TikTok for
Business app only.


## POST /v1/inbox/comments/{postId}/{commentId}/pin

**Pin comment**

Pin a top-level comment to the top of a post's comment section. TikTok accounts
connected through the TikTok for Business app only; every other platform returns 400.


### Parameters

- **postId** (required) in path: No description
- **commentId** (required) in path: No description

### Request Body

- **accountId** (required) `string`: The social account ID

### Responses

#### 200: Comment pinned

**Response Body:**

- **status** `string`: No description
- **commentId** `string`: No description
- **pinned** `boolean`: No description
- **platform** `string`: No description

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

#### 403: Inbox addon required

---

## DELETE /v1/inbox/comments/{postId}/{commentId}/pin

**Unpin comment**

Unpin a previously pinned comment. TikTok accounts connected through the TikTok for
Business app only.


### Parameters

- **postId** (required) in path: No description
- **commentId** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Comment unpinned

**Response Body:**

- **status** `string`: No description
- **commentId** `string`: No description
- **pinned** `boolean`: No description
- **platform** `string`: No description

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

#### 403: Inbox addon required

---
