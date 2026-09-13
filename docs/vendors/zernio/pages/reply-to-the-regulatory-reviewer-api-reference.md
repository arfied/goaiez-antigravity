# Reply to the regulatory reviewer API Reference

Post a free-text reply (with optional file attachments) to the reviewer
on a number awaiting remediation, for asks the structured form can't
express (e.g. "is this personal or business?"). Attachments are stored by
us and their links are added to the reviewer's comment thread (the
carrier's number order takes no loose files). A reply to a comment-style
ask moves the number back to "in review"; a reply on a formal decline is
supplementary and you must still resubmit the fix. Requires text or at
least one attachment.


## POST /v1/phone-numbers/{id}/remediate/reply

**Reply to the regulatory reviewer**

Post a free-text reply (with optional file attachments) to the reviewer
on a number awaiting remediation, for asks the structured form can't
express (e.g. "is this personal or business?"). Attachments are stored by
us and their links are added to the reviewer's comment thread (the
carrier's number order takes no loose files). A reply to a comment-style
ask moves the number back to "in review"; a reply on a formal decline is
supplementary and you must still resubmit the fix. Requires text or at
least one attachment.


### Parameters

- **id** (required) in path: No description

### Request Body

- **text** `string`: The reply message to the reviewer.
- **attachments** `array`: Files (PDF/JPG/PNG/WEBP, max 10 MB each) whose links are added to the reply.

### Responses

#### 200: Reply posted.

**Response Body:**

- **posted** `boolean`: No description
- **attachments** `integer`: Number of attachments uploaded.

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

#### 404: Number not found

#### 502: Couldn't deliver the reply to the reviewer; retry.

---
