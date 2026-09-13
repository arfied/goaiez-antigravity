# Approve join requests API Reference

Approve pending join requests for a WhatsApp group.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


## GET /v1/whatsapp/wa-groups/{groupId}/join-requests

**List join requests**

List pending join requests for a WhatsApp group (only for groups with approval_required mode).

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Parameters

- **groupId** (required) in path: Group ID
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Join requests

**Response Body:**

- **success** `boolean`: No description
- **joinRequests** `array[object]`: 
  - **user** `string`: Phone number
  - **timestamp** `integer`: UNIX timestamp of request

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

---

## POST /v1/whatsapp/wa-groups/{groupId}/join-requests

**Approve join requests**

Approve pending join requests for a WhatsApp group.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Parameters

- **groupId** (required) in path: Group ID
- **accountId** (required) in query: WhatsApp account ID

### Request Body

- **phoneNumbers** (required) `array`: Phone numbers to approve

### Responses

#### 200: Requests approved

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

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

---

## DELETE /v1/whatsapp/wa-groups/{groupId}/join-requests

**Reject join requests**

Reject pending join requests for a WhatsApp group.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Parameters

- **groupId** (required) in path: Group ID
- **accountId** (required) in query: WhatsApp account ID

### Request Body

- **phoneNumbers** (required) `array`: Phone numbers to reject

### Responses

#### 200: Requests rejected

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description

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

---
