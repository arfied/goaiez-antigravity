# Respond to the regulatory reviewer (message + corrections) API Reference

Send a single response to the reviewer on a number awaiting remediation:
a free-text message and/or corrected requirement documents, in one call.
If corrections are present they are PATCHed onto the requirement group and
re-submitted (the number goes back to "in review"); if a message or file
attachments are present they are posted to the reviewer's comment thread.
When both are present, your message is the thread comment and the resubmit
drives the state change. At least one of message, corrections, or
attachments is required. `documents` correct requirement slots; `attachments`
are loose files (their links are added to your message).


## POST /v1/phone-numbers/{id}/remediate/respond

**Respond to the regulatory reviewer (message + corrections)**

Send a single response to the reviewer on a number awaiting remediation:
a free-text message and/or corrected requirement documents, in one call.
If corrections are present they are PATCHed onto the requirement group and
re-submitted (the number goes back to "in review"); if a message or file
attachments are present they are posted to the reviewer's comment thread.
When both are present, your message is the thread comment and the resubmit
drives the state change. At least one of message, corrections, or
attachments is required. `documents` correct requirement slots; `attachments`
are loose files (their links are added to your message).


### Parameters

- **id** (required) in path: No description

### Request Body

- **message** `string`: Your message to the reviewer.
- **documents** `array`: Corrected requirement documents, each keyed to its requirement.
- **address** `object`: A corrected address record, keyed to its requirement.
- **entityType** `string,null`: No description - one of: individual, business, 
- **attachments** `array`: Loose files (PDF/JPG/PNG/WEBP, max 10 MB each) whose links are added to your message.

### Responses

#### 200: Response sent.

**Response Body:**

- **status** `string`: `resubmitted` when corrections were submitted, `replied` when it was message-only. - one of: resubmitted, replied
- **posted** `boolean`: Whether a message/attachments were posted to the reviewer.
- **phoneNumber** `object`: 
  - **id** `string`: No description
  - **status** `string`: No description
- **siblingsResubmitted** `integer`: Other numbers on the same registration the correction fanned out to.

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

#### 409: Number's registration is held under our own carrier registration; nothing for you to correct.

#### 502: Couldn't deliver your response to the reviewer; retry.

---
