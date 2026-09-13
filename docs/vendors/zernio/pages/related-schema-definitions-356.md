# Related Schema Definitions

## PostRetryResponse

### Properties

- **message** `string`: No description
- **post**: No description

## Post

### Properties

- **_id** `string`: No description
- **userId**: No description
- **title** `string`: Stored on the post for reference/display only. This field is NOT used as the video title when publishing. To set a YouTube video title, use platformSpecificData.title on the youtube platform target (falls back to the first line of content when omitted).
- **content** `string`: No description
- **mediaItems** `array`: No description
- **platforms** `array`: No description
- **scheduledFor** `string`: No description
- **timezone** `string`: No description
- **status** `string`: `cancelled` is set by DELETE /v1/posts/{postId}/unpublish once every platform entry has been removed from its platform (a post with published entries left becomes `partial`); cancelled posts can be edited and rescheduled like drafts. - one of: draft, scheduled, publishing, published, partial, failed, cancelled
- **tags** `array`: YouTube constraints: each tag max 100 chars, combined max 500 chars, duplicates removed.
- **hashtags** `array`: Stored for reference only. Hashtags are NOT automatically appended to the caption when publishing. Include hashtags directly in the content field (platforms like Instagram only support hashtags as caption text). For YouTube keywords, use the tags field instead.
- **mentions** `array`: Stored for reference only. This field does NOT automatically create @mentions when publishing. For LinkedIn @mentions, use the /v1/accounts/{accountId}/linkedin-mentions endpoint to resolve profile URLs to URNs, then embed the returned mentionFormat directly in the post content field.
- **visibility** `string`: No description - one of: public, private, unlisted
- **metadata** `object`: No description
- **recycling**: No description
- **recycledFromPostId** `string`: ID of the original post if this post was created via recycling
- **queuedFromProfile** `string`: Profile ID if the post was scheduled via the queue
- **queueId** `string`: Queue ID if the post was scheduled via a specific queue
- **createdAt** `string`: No description
- **updatedAt** `string`: No description

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

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
