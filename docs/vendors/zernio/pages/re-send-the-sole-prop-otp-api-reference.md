# Re-send the sole-prop OTP API Reference

Re-sends the sole-proprietor verification PIN to the brand's mobile
number. Use it when the original code expired or never arrived. Only
valid while the registration is pending and awaiting its OTP; rate
limited to one send per minute.


## POST /v1/sms/registrations/{id}/resend-otp

**Re-send the sole-prop OTP**

Re-sends the sole-proprietor verification PIN to the brand's mobile
number. Use it when the original code expired or never arrived. Only
valid while the registration is pending and awaiting its OTP; rate
limited to one send per minute.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: A new code was sent

**Response Body:**

- **sent** `boolean`: No description

#### 400: Malformed `id`, or the registration is not awaiting a verification code.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Registration not found

#### 429: A code was sent recently. Wait a minute before requesting another

---

---
