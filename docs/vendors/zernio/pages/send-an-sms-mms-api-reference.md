# Send an SMS/MMS API Reference

Sends an SMS (or MMS when `mediaUrls` is set) from one of your
SMS-enabled numbers. At least one of `text` / `mediaUrls` is required.
Both numbers are normalized to E.164, so `from` matches regardless of
formatting and replies thread into the same inbox conversation.

US numbers must have an approved carrier registration
(`/v1/sms/registrations`) before messages deliver.

**Replies and delivery status arrive as webhooks**, not by polling:
an inbound reply fires `message.received` with `platform: "sms"`, the
first message of a new thread also fires `conversation.started`, and
this message's own outcome fires `message.delivered` or
`message.failed` (the latter carrying the carrier's error code).

**Opted-out recipients:** a send to a number that replied STOP is
refused with `409`, never silently dropped.

**Idempotency:** send an `Idempotency-Key` header to make retries safe:
same key + same body replays the original response instead of sending a
second message; same key + different body returns 422; a key still in
flight returns 409.


## POST /v1/sms/messages

**Send an SMS/MMS**

Sends an SMS (or MMS when `mediaUrls` is set) from one of your
SMS-enabled numbers. At least one of `text` / `mediaUrls` is required.
Both numbers are normalized to E.164, so `from` matches regardless of
formatting and replies thread into the same inbox conversation.

US numbers must have an approved carrier registration
(`/v1/sms/registrations`) before messages deliver.

**Replies and delivery status arrive as webhooks**, not by polling:
an inbound reply fires `message.received` with `platform: "sms"`, the
first message of a new thread also fires `conversation.started`, and
this message's own outcome fires `message.delivered` or
`message.failed` (the latter carrying the carrier's error code).

**Opted-out recipients:** a send to a number that replied STOP is
refused with `409`, never silently dropped.

**Idempotency:** send an `Idempotency-Key` header to make retries safe:
same key + same body replays the original response instead of sending a
second message; same key + different body returns 422; a key still in
flight returns 409.


### Parameters

- **undefined** (optional): No description

### Request Body

- **from** (required) `string`: One of your SMS-enabled numbers (E.164; formatting is normalized).
- **to** (required) `string`: Recipient number (E.164).
- **text** `string`: Message body. Required unless `mediaUrls` is set. Max 10 SMS segments (1530 GSM-7 or 670 unicode characters).
- **mediaUrls** `array`: Public media URLs to attach (sends as MMS). Max 10.
- **sendAt** `string`: Optional. Schedule the send for a future time (ISO 8601 with offset, e.g. `2026-08-01T12:00:00Z`). Must be in the future. The message is queued and the `message.delivered` webhook fires when it actually sends.

### Responses

#### 200: Message accepted for delivery.

**Response Body:**

- **id** `string`: Message ID
- **conversationId** `string`: Inbox conversation the message was threaded into.
- **status** `string`: No description - one of: sent

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

#### 404: No SMS-enabled number matches `from`

#### 409: Recipient has opted out (replied STOP), or the same Idempotency-Key is still in flight

#### 422: Idempotency-Key reused with a different request

#### 502: Carrier-side send failed

---
