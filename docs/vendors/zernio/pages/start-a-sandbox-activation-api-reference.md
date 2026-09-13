# Start a sandbox activation API Reference

Creates (or refreshes) a pending sandbox session for the given phone and
immediately fires the verified sandbox template from the shared sandbox
number to that phone. The session activates when the phone owner replies
to that WhatsApp message: the reply itself is proof of ownership.

One phone per user: if the caller already has a non-expired session for
a DIFFERENT phone, the request is rejected with `invalid_field_value`
(the message names the existing phone so it can be revoked first).
Re-creating a session for the SAME phone is idempotent and refreshes
the verification template.

If Meta rejects the template send (not a WhatsApp number, paused WABA,
token issue), the pending row is rolled back and the Meta error message
is returned in `error` so the caller knows why.


## GET /v1/whatsapp/sandbox/sessions

**List your sandbox sessions**

Returns all of the authenticated user's non-expired sandbox sessions
(pending + active) plus the sandbox phone number. In practice there
is at most one session per user since the sandbox is one-phone-per-user;
the array shape is preserved for forward compatibility.


### Responses

#### 200: Sessions retrieved successfully

**Response Body:**

- **sessions** `array[WhatsAppSandboxSession]`: 
- **sandboxNumber** `string,null`: The shared sandbox phone number in E.164 form. (example: "+12029087457")

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

## POST /v1/whatsapp/sandbox/sessions

**Start a sandbox activation**

Creates (or refreshes) a pending sandbox session for the given phone and
immediately fires the verified sandbox template from the shared sandbox
number to that phone. The session activates when the phone owner replies
to that WhatsApp message: the reply itself is proof of ownership.

One phone per user: if the caller already has a non-expired session for
a DIFFERENT phone, the request is rejected with `invalid_field_value`
(the message names the existing phone so it can be revoked first).
Re-creating a session for the SAME phone is idempotent and refreshes
the verification template.

If Meta rejects the template send (not a WhatsApp number, paused WABA,
token issue), the pending row is rolled back and the Meta error message
is returned in `error` so the caller knows why.


### Request Body

- **phone** (required) `string`: Recipient phone in international format. Digits, spaces, dashes and a leading `+` are all accepted; the server normalizes to E.164 digits-only.

### Responses

#### 200: Session created or refreshed; verification template sent

**Response Body:**

- **session**: `WhatsAppSandboxSession` - See schema definition
- **sandboxNumber** `string`: No description (example: "+12029087457")

#### 400: Returned when (a) phone format is invalid, (b) phone equals the sandbox
number itself, (c) the user already has a session for a different phone,
or (d) Meta rejected the template send. The `error` field contains the
specific reason; `param` is set when a field is at fault.


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---
