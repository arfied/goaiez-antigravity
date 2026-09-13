# Rotate a SIP trunk's password API Reference

Mints a new digest password on the trunk. The old password stops
working immediately, so update the destination platform right away.


## POST /v1/phone-numbers/sip-trunks/{id}/rotate-credentials

**Rotate a SIP trunk's password**

Mints a new digest password on the trunk. The old password stops
working immediately, so update the destination platform right away.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: New credentials. The password is shown only here.

**Response Body:**

- **termination** `object`: 
  - **uri** `string`: Telnyx termination host the platform dials for outbound (sip.telnyx.com).
  - **username** `string`: SIP digest username.
- **digestPassword** `string`: No description

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

#### 404: SIP trunk not found

---
