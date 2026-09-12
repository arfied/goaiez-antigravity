# Send private reply API Reference

Send a direct message to the author of a comment. Supported on Instagram and Facebook only.
One reply per comment, must be sent within 7 days. Optionally attach interactive elements:
`quickReplies` (chips above the keyboard, max 13) or `buttons` (1-3 inline postback/url
buttons rendered in the same bubble via Meta's button_template). Chips do not render in
the Instagram Message Requests folder. Since late August 2026 Instagram refuses buttons,
cards and attachments to commenters who do not follow the account (Meta code 2, subcode
1545133, returned here as a non-retryable 400 that says so), and the failed call still
consumes the comment's single private reply. To reach non-followers send plain text and
add buttons once they reply. `quickReplies` and `buttons` are mutually exclusive. When
the comment's single private reply is spent (by this call or an earlier one) the 400
carries `details.privateReplyConsumed: true`; never retry it.


## POST /v1/inbox/comments/{postId}/{commentId}/private-reply

**Send private reply**

Send a direct message to the author of a comment. Supported on Instagram and Facebook only.
One reply per comment, must be sent within 7 days. Optionally attach interactive elements:
`quickReplies` (chips above the keyboard, max 13) or `buttons` (1-3 inline postback/url
buttons rendered in the same bubble via Meta's button_template). Chips do not render in
the Instagram Message Requests folder. Since late August 2026 Instagram refuses buttons,
cards and attachments to commenters who do not follow the account (Meta code 2, subcode
1545133, returned here as a non-retryable 400 that says so), and the failed call still
consumes the comment's single private reply. To reach non-followers send plain text and
add buttons once they reply. `quickReplies` and `buttons` are mutually exclusive. When
the comment's single private reply is spent (by this call or an earlier one) the 400
carries `details.privateReplyConsumed: true`; never retry it.


### Parameters

- **postId** (required) in path: The media/post ID (Instagram media ID or Facebook post ID)
- **commentId** (required) in path: The comment ID to send a private reply to

### Request Body

- **accountId** (required) `string`: The account ID (Instagram or Facebook)
- **message** (required) `string`: The message text to send as a private DM
- **quickReplies** `array`: Optional quick-reply chips appended to the message. Visible only in the
Instagram and Messenger apps (not on web). Maximum 13 entries. Mutually
exclusive with `buttons`. Note: chips do NOT render in the Instagram
Message Requests folder where DMs from non-followers land. Use `buttons`
instead for cold reach.

- **buttons** `array`: Optional 1-3 inline buttons rendered as part of the same message bubble
via Meta's button_template. Visible in the Instagram Message Requests
folder (unlike quick replies). Mutually exclusive with `quickReplies`.


### Responses

#### 200: Private reply sent successfully

**Response Body:**

- **status** `string`: No description (example: "success")
- **messageId** `string`: The ID of the sent message
- **commentId** `string`: The comment ID that was replied to
- **platform** `string`: No description - one of: instagram, facebook (example: "instagram")

#### 400: Bad request

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

#### 404: Account not found

---
