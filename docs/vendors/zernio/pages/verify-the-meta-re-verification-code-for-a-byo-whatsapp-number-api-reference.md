# Verify the Meta re-verification code for a BYO WhatsApp number API Reference

Submits the OTP Meta sent in response to POST /v1/accounts/{accountId}/whatsapp/request-code.
This only verifies the number with Meta; it does not register it on the Cloud API.
Call POST /v1/accounts/{accountId}/whatsapp/register afterward to complete activation.


## POST /v1/accounts/{accountId}/whatsapp/verify-code

**Verify the Meta re-verification code for a BYO WhatsApp number**

Submits the OTP Meta sent in response to POST /v1/accounts/{accountId}/whatsapp/request-code.
This only verifies the number with Meta; it does not register it on the Cloud API.
Call POST /v1/accounts/{accountId}/whatsapp/register afterward to complete activation.


### Parameters

- **accountId** (required) in path: The WhatsApp account ID

### Request Body

- **code** (required) `string`: The 6-digit code Meta sent to the phone. Non-digit separators (e.g. "749-456") are stripped automatically.

### Responses

#### 200: Number verified with Meta

**Response Body:**

- **verified** `boolean`: No description
- **accountId** `string`: No description
- **phoneNumberId** `string`: No description

#### 400: The code is malformed, or Meta rejected it as wrong or expired.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 422: The account has no phone number bound to it yet.

---

---
