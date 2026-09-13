# Get a verification API Reference

Current state of a verification. `status` is effective (a pending code
past its expiry reads as `expired`). Verification records are deleted
24 hours after creation, after which this returns 404.


## GET /v1/verify/verifications/{verificationId}

**Get a verification**

Current state of a verification. `status` is effective (a pending code
past its expiry reads as `expired`). Verification records are deleted
24 hours after creation, after which this returns 404.


### Parameters

- **verificationId** (required) in path: No description

### Responses

#### 200: The verification.

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

#### 404: Verification not found (or already reaped).

---
