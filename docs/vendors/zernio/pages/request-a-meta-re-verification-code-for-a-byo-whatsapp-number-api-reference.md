# Request a Meta re-verification code for a BYO WhatsApp number API Reference

For a bring-your-own WhatsApp number (its own WABA, migrated off another BSP) that
Meta demoted to re-verification, this requests a new OTP from Meta. The code lands
on the customer's own handset, so verifying it is necessarily self-service; call
POST /v1/accounts/{accountId}/whatsapp/verify-code with the code once it arrives.
Rate-limited to one request per 10 minutes per account, and Meta enforces its own
cooldown on top of that.


## POST /v1/accounts/{accountId}/whatsapp/request-code

**Request a Meta re-verification code for a BYO WhatsApp number**

For a bring-your-own WhatsApp number (its own WABA, migrated off another BSP) that
Meta demoted to re-verification, this requests a new OTP from Meta. The code lands
on the customer's own handset, so verifying it is necessarily self-service; call
POST /v1/accounts/{accountId}/whatsapp/verify-code with the code once it arrives.
Rate-limited to one request per 10 minutes per account, and Meta enforces its own
cooldown on top of that.


### Parameters

- **accountId** (required) in path: The WhatsApp account ID

### Request Body

- **method** `string`: No description - one of: SMS, VOICE
- **language** `string`: Meta locale code for the verification message, e.g. en_US.

### Responses

#### 200: Code requested, or the number was already CONNECTED and no code was needed.

**Response Body:**

- **requested** `boolean`: No description
- **alreadyActive** `boolean`: No description
- **method** `string`: No description
- **accountId** `string`: No description
- **phoneNumberId** `string`: No description

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 409: Meta already reports this number as VERIFIED. Call POST /v1/accounts/{accountId}/whatsapp/register instead.

#### 422: The account has no phone number bound yet, it runs in coexistence with the WhatsApp Business app, or Meta rejected the code request.

#### 429: Our own 10-minute-per-account cooldown is active, or Meta has escalated to a multi-hour lockout after repeated attempts.

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

#### 503: Meta could not dispatch a code for this number yet. Retry shortly.

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

---
