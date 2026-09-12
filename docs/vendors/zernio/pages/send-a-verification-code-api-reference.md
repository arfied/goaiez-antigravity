# Send a verification code API Reference

Generate a one-time code, deliver it to the recipient, and store only
its hash. Check the user-typed code with
POST /v1/verify/verifications/{verificationId}/check.

Re-POSTing for the same (channel, to) while a verification is active
RESENDS a fresh code on the existing verification (200 with
`resend: true`) instead of creating a new one; resends are limited to
one per 60 seconds (429 with `retryAfterSeconds` inside the cooldown).
The stored brandName/codeLength/ttlMinutes win on a resend.

Codes deliver by SMS from a phone number on your account (`from`
optional when you own exactly one SMS-enabled number) and the message
uses a fixed template. Each accepted send bills one verification fee
plus the standard message rate.


## POST /v1/verify/verifications

**Send a verification code**

Generate a one-time code, deliver it to the recipient, and store only
its hash. Check the user-typed code with
POST /v1/verify/verifications/{verificationId}/check.

Re-POSTing for the same (channel, to) while a verification is active
RESENDS a fresh code on the existing verification (200 with
`resend: true`) instead of creating a new one; resends are limited to
one per 60 seconds (429 with `retryAfterSeconds` inside the cooldown).
The stored brandName/codeLength/ttlMinutes win on a resend.

Codes deliver by SMS from a phone number on your account (`from`
optional when you own exactly one SMS-enabled number) and the message
uses a fixed template. Each accepted send bills one verification fee
plus the standard message rate.


### Request Body

- **channel** (required) `string`: SMS-only for now. - one of: sms
- **to** (required) `string`: E.164 phone number.
- **from** `string`: The SMS-enabled number on your account to send from. Defaults to your only SMS number.
- **brandName** `string`: Your app or business name, rendered in the message. Defaults to your account name. Letters, numbers, and basic punctuation only.
- **codeLength** `integer`: No description
- **ttlMinutes** `integer`: No description

### Responses

#### 200: Active verification found: a fresh code was resent (`resend: true`).

**Response Body:**

- **id** `string`: No description
- **status** `string`: No description - one of: pending, approved, expired, max_attempts_reached, canceled, delivery_failed
- **channel** `string`: No description - one of: sms
- **to** `string`: No description
- **expiresAt** `string` (date-time): No description
- **attempts** `integer`: No description
- **maxAttempts** `integer`: No description
- **sendCount** `integer`: Accepted deliveries (initial send + resends); each bills one verification fee.
- **lastSentAt** `string,null` (date-time): No description
- **createdAt** `string` (date-time): No description
- **resend** `boolean`: Present on create responses: true when an active verification was resent instead of created.

#### 201: Verification created and the code sent.

**Response Body:**

- **id** `string`: No description
- **status** `string`: No description - one of: pending, approved, expired, max_attempts_reached, canceled, delivery_failed
- **channel** `string`: No description - one of: sms
- **to** `string`: No description
- **expiresAt** `string` (date-time): No description
- **attempts** `integer`: No description
- **maxAttempts** `integer`: No description
- **sendCount** `integer`: Accepted deliveries (initial send + resends); each bills one verification fee.
- **lastSentAt** `string,null` (date-time): No description
- **createdAt** `string` (date-time): No description
- **resend** `boolean`: Present on create responses: true when an active verification was resent instead of created.

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

#### 403: Verifications require usage-based billing.

#### 404: The 'from' number is not an SMS-enabled number on this account.

#### 409: The recipient has opted out of messages from your number.

#### 422: Verifications need an SMS-enabled number on your account; add one first.

#### 429: Resend cooldown or a send cap was hit; `retryAfterSeconds` says when to retry.

---
