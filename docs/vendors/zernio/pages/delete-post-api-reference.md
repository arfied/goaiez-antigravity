# Delete post API Reference

Delete a draft or scheduled post from Zernio. Published posts cannot be deleted; use the Unpublish endpoint instead. Upload quota is automatically refunded.

## GET /v1/posts/{postId}

**Get post**

Fetch a single post by ID. For published posts, this returns platformPostUrl for each platform.


### Parameters

- **postId** (required) in path: No description

### Responses

#### 200: Post

**Response Body:**

- **post**: `Post` - See schema definition

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

---

## PUT /v1/posts/{postId}

**Update post**

Update an existing post. Draft, scheduled, failed, partial, and cancelled posts can be edited.
Published posts can only have their recycling config updated.

To promote a draft to scheduled, send `isDraft: false` together with `scheduledFor` (or `publishNow: true`,
or `queuedFromProfile`). If `isDraft` is omitted the post keeps its current draft status, so sending only
`scheduledFor` to a draft returns 200 but the post remains a draft.

Non-draft updates run the same per-platform validation as post creation (media requirements, platform-specific
field rules, etc.) against the resulting platforms, returning 400 on failure.


### Parameters

- **postId** (required) in path: No description

### Request Body

- **title** `string`: Stored on the post for reference/display only. This field is NOT used as the video title when publishing. To set a YouTube video title, use platformSpecificData.title on the youtube platform target (falls back to the first line of content when omitted).
- **content** `string`: No description
- **mediaItems** `array`: No description
- **platforms** `array`: Target platforms and accounts for this post. Each item must include platform and accountId.
- **scheduledFor** `string`: No description
- **publishNow** `boolean`: No description
- **isDraft** `boolean`: When omitted, the post keeps its current draft status. Send `false` to promote a draft to scheduled (combined with `scheduledFor`, `publishNow`, or a queue).
- **timezone** `string`: No description
- **visibility** `string`: No description - one of: public, private, unlisted
- **tags** `array`: No description
- **hashtags** `array`: Stored for reference only. Hashtags are NOT automatically appended to the caption when publishing. Include hashtags directly in the content field (platforms like Instagram only support hashtags as caption text). For YouTube keywords, use the tags field instead.
- **mentions** `array`: No description
- **crosspostingEnabled** `boolean`: No description
- **metadata** `object`: No description
- **queuedFromProfile** `string`: Profile ID to schedule via queue.
- **queueId** `string`: Specific queue ID to use when scheduling via queue.
- **tiktokSettings**: Root-level TikTok settings applied to the TikTok platforms sent in the same request. Merged into each platform's platformSpecificData, with platform-specific settings taking precedence. Returns 400 if sent without a platforms array.
- **facebookSettings**: Root-level Facebook settings applied to the Facebook platforms sent in the same request. Merged into each platform's platformSpecificData.facebookSettings, with platform-specific settings taking precedence. Returns 400 if sent without a platforms array.
- **recycling**: No description

### Responses

#### 200: Post updated

**Response Body:**

- **message** `string`: No description
- **post**: `Post` - See schema definition
- **warnings** `array[string]`: 

#### 207: The post was updated, but the inline publish that followed did not fully succeed.

**207 is a 2xx status**, so `fetch(...).ok` is `true` and axios resolves. Branch on the status code explicitly.

Read `post.status`: `partial` (some platforms published), `failed` (none published, terminal), or `scheduled` (transient errors, platforms reset to `pending`, Zernio retries automatically and this is not a failure). `platformResults` is omitted when the attempt aborted before producing per-platform results; `post.platforms[]` is always present.


**Response Body:**

- **post**: `Post` - See schema definition
- **message** `string`: Human-readable summary of the publish outcome.
- **error** `string`: Present when no platform published. Absent on a partial success. Informational only; the per-platform detail is in `platformResults` and in `post.platforms[]`.
- **platformResults** `array[object]`: Per-platform outcome of the publish attempt. Omitted when the attempt aborted before producing per-platform results (for example the post was already being processed); read `post.platforms[]` in that case.
  - **platform** (required) `string`: Platform slug, matching `post.platforms[].platform`.
  - **status** (required) `string`: Per-platform status: pending, processing, published, failed, cancelled, uploading. (example: "failed")
  - **error** (required) `string,null`: Failure detail for this platform, or null when it did not fail.
- **warnings** `array[string]`: Advisory notices about the post that was still created. Absent when there are none.

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

#### 409: The requested scheduledFor collides with another post already occupying that slot in the same queue (code: queue_slot_conflict). Choose a different time, omit scheduledFor and let the queue assign the next open slot, or send queueId: null to schedule this post outside the queue.

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

## DELETE /v1/posts/{postId}

**Delete post**

Delete a draft or scheduled post from Zernio. Published posts cannot be deleted; use the Unpublish endpoint instead. Upload quota is automatically refunded.

### Parameters

- **postId** (required) in path: No description

### Responses

#### 200: Deleted

**Response Body:**

- **message** `string`: No description

#### 400: Cannot delete published posts

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

---
