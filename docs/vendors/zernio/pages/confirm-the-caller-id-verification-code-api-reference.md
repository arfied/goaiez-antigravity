# Confirm the caller-ID verification code API Reference

Submits the one-time code the number received. On success, `tel:`
call forwards present the business number itself as caller ID
(`callerIdMode: business`).


## POST /v1/phone-numbers/{id}/whatsapp/caller-id-verification/verify

**Confirm the caller-ID verification code**

Submits the one-time code the number received. On success, `tel:`
call forwards present the business number itself as caller ID
(`callerIdMode: business`).


### Parameters

- **id** (required) in path: Phone number record ID (from GET /v1/phone-numbers).

### Request Body

- **code** (required) `string`: No description

### Responses

#### 200: Verified

**Response Body:**

- **verified** `boolean`: No description

#### 400: Invalid or expired code, or malformed request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

#### 429: Attempt lockout from the carrier; wait a few minutes, then request a fresh code

---

---
