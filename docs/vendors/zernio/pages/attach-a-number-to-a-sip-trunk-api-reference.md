# Attach a number to a SIP trunk API Reference

Routes the number's calls to the trunk: the external platform receives
its inbound directly and can present it as outbound caller ID. While
attached, Zernio-side voice features are off for this number (call
forwarding, IVR, voicemail, recording, the softphone, and WhatsApp
calling), so the number must have Calls and WhatsApp calling disabled
before attaching. SMS and WhatsApp messaging are unaffected.


## POST /v1/phone-numbers/{id}/sip-trunk

**Attach a number to a SIP trunk**

Routes the number's calls to the trunk: the external platform receives
its inbound directly and can present it as outbound caller ID. While
attached, Zernio-side voice features are off for this number (call
forwarding, IVR, voicemail, recording, the softphone, and WhatsApp
calling), so the number must have Calls and WhatsApp calling disabled
before attaching. SMS and WhatsApp messaging are unaffected.


### Parameters

- **id** (required) in path: Phone number record ID (from GET /v1/phone-numbers).

### Request Body

- **trunkId** (required) `string`: SIP trunk ID (from POST /v1/phone-numbers/sip-trunks).

### Responses

#### 200: Number attached (idempotent for the same trunk).

**Response Body:**

- **attached** `boolean`: No description
- **phoneNumber** `string`: No description
- **trunkId** `string`: No description

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

#### 403: SIP trunking is not enabled for this team, or the team is on legacy (non-usage-based) billing, which cannot invoice trunk call costs (code feature_not_available).

#### 404: Number or trunk not found

#### 409: The number still has Calls or WhatsApp calling enabled, is mid WhatsApp verification, is not active, or is attached to another trunk (code invalid_resource_state).

#### 422: This number is hosted by your own carrier (brought via WhatsApp embedded signup), so it cannot be trunked.

---

## DELETE /v1/phone-numbers/{id}/sip-trunk

**Detach a number from its SIP trunk**

Returns the number's calls to Zernio routing. Idempotent when the
number is not attached to any trunk.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Number detached.

**Response Body:**

- **attached** `boolean`: Always false after a successful detach.
- **phoneNumber** `string`: No description

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

---
