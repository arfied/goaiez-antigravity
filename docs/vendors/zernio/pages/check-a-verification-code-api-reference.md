# Check a verification code API Reference

Verify the code the user typed. Wrong, expired, and exhausted codes
answer 200 with `valid: false` and the settled `status`. Only an
unknown id is a 404. A correct code consumes the verification
(single-use, `status: approved`) and fires the `verification.approved`
webhook; the 5th wrong attempt settles it as `max_attempts_reached`
and fires `verification.failed`.


## POST /v1/verify/verifications/{verificationId}/check

**Check a verification code**

Verify the code the user typed. Wrong, expired, and exhausted codes
answer 200 with `valid: false` and the settled `status`. Only an
unknown id is a 404. A correct code consumes the verification
(single-use, `status: approved`) and fires the `verification.approved`
webhook; the 5th wrong attempt settles it as `max_attempts_reached`
and fires `verification.failed`.


### Parameters

- **verificationId** (required) in path: No description

### Request Body

- **code** (required) `string`: No description

### Responses

#### 200: Check result: the verification plus `valid`.

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
- **valid** `boolean`: No description

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

#### 404: Verification not found (or already reaped).

---
