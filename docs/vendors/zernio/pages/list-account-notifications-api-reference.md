# List account notifications API Reference

Returns Meta-originated events recorded for a WhatsApp account, newest
first: template review outcomes (approved, rejected, paused, category
changes) and WABA status changes (restricted, disabled, reinstated,
disconnected). Events are captured from Meta webhooks as they happen;
the feed starts at the account's first recorded event and is not
backfilled. Complements the push events `whatsapp.template.status_updated`
and `account.disconnected` with a pollable history.


## GET /v1/whatsapp/account-events

**List account notifications**

Returns Meta-originated events recorded for a WhatsApp account, newest
first: template review outcomes (approved, rejected, paused, category
changes) and WABA status changes (restricted, disabled, reinstated,
disconnected). Events are captured from Meta webhooks as they happen;
the feed starts at the account's first recorded event and is not
backfilled. Complements the push events `whatsapp.template.status_updated`
and `account.disconnected` with a pollable history.


### Parameters

- **accountId** (required) in query: WhatsApp account ID
- **limit** (optional) in query: Maximum events to return

### Responses

#### 200: Recorded events, newest first

**Response Body:**

- **events** `array[object]`: 
  - **id** `string`: No description
  - **accountId** `string`: WhatsApp account the event belongs to
  - **type** `string`: Event kind, e.g. template_approved, template_rejected, account_restricted, account_disconnected
  - **severity** `string`: No description - one of: info, success, warning, critical
  - **title** `string`: No description
  - **detail** `string,null`: No description
  - **createdAt** `string` (date-time): No description

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

#### 404: WhatsApp account not found

---
