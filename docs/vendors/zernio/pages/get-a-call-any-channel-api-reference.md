# Get a call (any channel) API Reference

Channel-agnostic call detail: works for both WhatsApp and regular
phone (PSTN) calls, so any row from `GET /v1/calls` can be opened
without branching on `channel`. Returns the full call including
transcript segments, with `contactId`/`contactName` set when the
counterparty matches a CRM contact.


## GET /v1/calls/{id}

**Get a call (any channel)**

Channel-agnostic call detail: works for both WhatsApp and regular
phone (PSTN) calls, so any row from `GET /v1/calls` can be opened
without branching on `channel`. Returns the full call including
transcript segments, with `contactId`/`contactName` set when the
counterparty matches a CRM contact.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Call

**Response Body:**

- **call**: No description

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

#### 404: Call not found

---
