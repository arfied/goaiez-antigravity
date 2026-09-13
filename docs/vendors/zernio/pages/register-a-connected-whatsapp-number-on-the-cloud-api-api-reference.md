# Register a connected WhatsApp number on the Cloud API API Reference

Re-runs Meta's Cloud API registration for a WhatsApp account that is already connected.
Use it when the number has its own two-step verification PIN: the connect flows register
with a default PIN, Meta rejects that with error 133005, and the number then fails every
send with the misleading '(#200) You do not have the necessary permission to send messages'
while the account still shows as connected. The PIN is used for this call only and is not stored.


## POST /v1/accounts/{accountId}/whatsapp/register

**Register a connected WhatsApp number on the Cloud API**

Re-runs Meta's Cloud API registration for a WhatsApp account that is already connected.
Use it when the number has its own two-step verification PIN: the connect flows register
with a default PIN, Meta rejects that with error 133005, and the number then fails every
send with the misleading '(#200) You do not have the necessary permission to send messages'
while the account still shows as connected. The PIN is used for this call only and is not stored.


### Parameters

- **accountId** (required) in path: The WhatsApp account ID

### Request Body

- **pin** `string`: The 6-digit two-step verification PIN set on the number. Omitting it applies Zernio's managed default registration PIN, the same one every Embedded Signup connect sets automatically.

### Responses

#### 200: Number registered on the WhatsApp Cloud API

**Response Body:**

- **registered** `boolean`: No description
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

#### 401: Invalid or expired credentials

#### 404: WhatsApp account not found

#### 422: Meta rejected the registration (e.g. PIN mismatch), or the number cannot be registered through the API.

---
