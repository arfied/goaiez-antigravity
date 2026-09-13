# Delete a SIP trunk API Reference

Tears down the trunk and its carrier-side objects. Refused while any
number is still attached: detach them first.


## GET /v1/phone-numbers/sip-trunks/{id}

**Get a SIP trunk**

### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Trunk detail, including the attached numbers.

**Response Body:**

- **id** `string`: No description
- **label** `string`: No description
- **sipHost** `string`: No description
- **sipPort** `integer`: No description
- **transport** `string`: No description - one of: tls, tcp, udp
- **termination** `object`: 
  - **uri** `string`: No description
  - **username** `string`: No description
- **numbersAttached** `integer`: No description
- **createdAt** `string,null` (date-time): No description
- **numbers** `array[object]`: 
  - **id** `string`: Phone number record ID.
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

#### 404: SIP trunk not found

---

## DELETE /v1/phone-numbers/sip-trunks/{id}

**Delete a SIP trunk**

Tears down the trunk and its carrier-side objects. Refused while any
number is still attached: detach them first.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Trunk deleted.

**Response Body:**

- **deleted** `boolean`: No description

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

#### 409: Numbers are still attached to this trunk (code invalid_resource_state).

---
