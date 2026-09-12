# Unpublish post API Reference

Deletes a published post from the specified platform. The post record in Zernio is kept but its status is updated to cancelled.
Not supported on Instagram, TikTok, or Snapchat. Threaded posts delete all items. YouTube deletion is permanent.


## POST /v1/posts/{postId}/unpublish

**Unpublish post**

Deletes a published post from the specified platform. The post record in Zernio is kept but its status is updated to cancelled.
Not supported on Instagram, TikTok, or Snapchat. Threaded posts delete all items. YouTube deletion is permanent.


### Parameters

- **postId** (required) in path: No description

### Request Body

- **platform** (required) `string`: The platform to delete the post from - one of: threads, facebook, twitter, linkedin, youtube, pinterest, reddit, bluesky, googlebusiness, telegram

### Responses

#### 200: Post deleted from platform

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

#### 400: Invalid request: platform not supported for deletion, post not on that platform, not published, no platform post ID, or no access token.

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

#### 403: Forbidden

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

#### 500: Platform API deletion failed

---
