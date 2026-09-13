# Start caller-ID verification for a customer-brought number API Reference

Customer-brought (BYO) WhatsApp numbers cannot present themselves as
caller ID on `tel:` call forwards until verified (carrier
anti-spoofing); until then forwarded calls show a Zernio number
(`callerIdMode: platform` on the calling config). This sends a
one-time code to the number by SMS or voice call. Re-POST to resend.
Zernio-purchased numbers never need this and get a 400.


## POST /v1/phone-numbers/{id}/whatsapp/caller-id-verification

**Start caller-ID verification for a customer-brought number**

Customer-brought (BYO) WhatsApp numbers cannot present themselves as
caller ID on `tel:` call forwards until verified (carrier
anti-spoofing); until then forwarded calls show a Zernio number
(`callerIdMode: platform` on the calling config). This sends a
one-time code to the number by SMS or voice call. Re-POST to resend.
Zernio-purchased numbers never need this and get a 400.


### Parameters

- **id** (required) in path: Phone number record ID (from GET /v1/phone-numbers).

### Request Body

- **method** `string`: No description - one of: sms, call

### Responses

#### 200: Code sent (or the number was already verified)

**Response Body:**

- **verified** `boolean`: No description
- **codeSent** `boolean`: No description
- **method** `string`: No description - one of: sms, call

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

#### 429: Too many verification attempts for this number; wait before retrying

---
