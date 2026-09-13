# Retry failed post API Reference

Immediately retries publishing a failed post. Returns the updated post with its new status.

## POST /v1/posts/{postId}/retry

**Retry failed post**

Immediately retries publishing a failed post. Returns the updated post with its new status.

### Parameters

- **postId** (required) in path: No description

### Responses

#### 200: Retry successful

**Response Body:**

- **message** `string`: No description
- **post**: `Post` - See schema definition

#### 207: The retry ran, but publishing did not fully succeed. Covers both a partial publish and a retry in which no platform published.

**207 is a 2xx status**, so `fetch(...).ok` is `true` and axios resolves. Branch on the status code explicitly.

This response carries no `platformResults`. Read `post.status` (`partial`, `failed`, or `scheduled` when transient errors will be retried automatically) and `post.platforms[]` for per-platform detail.


**Response Body:**

- **message** `string`: No description
- **error** `string`: Summary of why the retry did not fully succeed.
- **post**: `Post` - See schema definition

#### 400: Invalid state

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

#### 402: Payment required: the account owner has a failed payment.

**Response Body:**

- **error** `string`: No description

#### 403: Forbidden. Distinguish by the `code` field:
- `ACCOUNT_NOT_ENABLED_FOR_POSTING`: a target account was connected for ads only (`enabled: false`) and cannot be posted to. Connect it as a posting account, then retry.
- `PROFILE_OVER_LIMIT`: a target account belongs to a profile beyond the plan's profile limit.
- `insufficient_permissions`: the post is not accessible to the caller.


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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 409: Post is currently publishing

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

#### 429: Rate limit exceeded. Possible causes: API rate limit (requests per minute), velocity limit (25 posts/hour per account), or account cooldown (temporarily rate-limited due to repeated errors).


**Response Body:**

- **error** `string`: No description
- **details** `object`: No description

---
