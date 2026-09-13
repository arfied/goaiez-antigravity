# List alphanumeric sender IDs API Reference



## POST /v1/sms/sender-ids

**Create an alphanumeric sender ID**

Registers an alphanumeric sender ID (e.g. `ZERNIO`), a branded `from`
for one-way international SMS. No phone number purchase or carrier
registration is needed; once created, pass it as `from` on
`POST /v1/sms/messages`.

Constraints: 3-11 characters (letters, digits, spaces; at least one
letter). Sends cannot reach the US, Canada, or Puerto Rico, are
text-only, and recipients cannot reply. Sender IDs that impersonate
well-known brands or institutions are rejected. Names are not
exclusive: the same sender ID can be registered by any number of
teams. Creating the same sender ID again is a no-op
(re-activates it after a delete).


### Request Body

- **senderId** (required) `string`: The sender ID recipients will see (3-11 letters/digits/spaces, at least one letter, no leading/trailing space).

### Responses

#### 200: Sender ID created (or re-activated).

**Response Body:**

- **id** `string`: Sender ID resource id.
- **senderId** `string`: No description
- **isActive** `boolean`: No description

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

#### 402: No payment method on file (code `payment_required`). Sender-ID sends incur carrier fees, so the billing owner needs a card before one can be created.

#### 403: The team is not on usage-based billing, or already holds the maximum of 1,000 active sender IDs (code `sender_id_limit_reached`; raisable via support).

#### 409: Billing setup is incomplete for this team (code `billing_setup_incomplete`); contact support.

#### 422: Sender ID rejected: it appears to impersonate a protected brand or institution.

---

## GET /v1/sms/sender-ids

**List alphanumeric sender IDs**

### Responses

#### 200: The team's sender IDs, newest first.

**Response Body:**

- **senderIds** `array[object]`: 
  - **id** `string`: No description
  - **senderId** `string`: No description
  - **isActive** `boolean`: No description
  - **createdAt** `string,null` (date-time): No description
- **budget** `object`: Team-wide daily sending budget, shared by every sender ID (resets midnight UTC).
  - **cap** `integer`: Daily message cap (raisable via `/v1/sms/sender-ids/limit-request`).
  - **usedToday** `integer`: Messages already counted against today's cap.
  - **level** `integer`: Cap tier (Level 1 = 500/day).
  - **pendingRequest** `object,null`: The in-flight cap-raise request awaiting review, or null. While set, further requests return 409.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---
