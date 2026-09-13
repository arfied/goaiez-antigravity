# Delete group API Reference

Delete a WhatsApp group and remove all participants.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


## GET /v1/whatsapp/wa-groups/{groupId}

**Get group info**

Retrieve metadata about a WhatsApp group including subject, description,
participants, and settings.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Parameters

- **groupId** (required) in path: Group ID
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Group info

**Response Body:**

- **success** `boolean`: No description
- **group** `object`: 
  - **id** `string`: No description
  - **subject** `string`: No description
  - **description** `string`: No description
  - **joinApprovalMode** `string`: No description
  - **participants** `array[object]`: 
    - **user** `string`: Phone number
    - **admin** `string`: No description
  - **participantCount** `integer`: No description
  - **createdAt** `integer`: UNIX timestamp
  - **isSuspended** `boolean`: No description

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

---

## POST /v1/whatsapp/wa-groups/{groupId}

**Update group settings**

Update the subject, description, or join approval mode of a WhatsApp group.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Parameters

- **groupId** (required) in path: Group ID
- **accountId** (required) in query: WhatsApp account ID

### Request Body

- **subject** `string`: No description
- **description** `string`: No description
- **joinApprovalMode** `string`: No description - one of: approval_required, auto_approve

### Responses

#### 200: Group updated

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/whatsapp/wa-groups/{groupId}

**Delete group**

Delete a WhatsApp group and remove all participants.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Parameters

- **groupId** (required) in path: Group ID
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Group deleted

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
