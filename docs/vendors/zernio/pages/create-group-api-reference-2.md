# Create group API Reference

Create a new WhatsApp group chat. Returns the group ID and optionally an invite link.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


## GET /v1/whatsapp/wa-groups

**List active groups**

List active WhatsApp group chats for a business phone number.
These are actual WhatsApp group conversations on the platform.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Parameters

- **accountId** (required) in query: WhatsApp account ID
- **limit** (optional) in query: Max groups to return
- **after** (optional) in query: Pagination cursor

### Responses

#### 200: List of active groups

**Response Body:**

- **groups** `array[object]`: 
  - **id** `string`: Group ID
  - **subject** `string`: Group name
  - **createdAt** `string`: Group creation timestamp
- **paging** `object`: 
  - **cursors** `object`: 
    - **after** `string`: No description
    - **before** `string`: No description

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

## POST /v1/whatsapp/wa-groups

**Create group**

Create a new WhatsApp group chat. Returns the group ID and optionally an invite link.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **subject** (required) `string`: Group name (max 128 characters)
- **description** `string`: Group description (max 2048 characters)
- **joinApprovalMode** `string`: Whether users need approval to join via invite link - one of: approval_required, auto_approve

### Responses

#### 201: Group created

**Response Body:**

- **success** `boolean`: No description
- **group** `object`: 
  - **groupId** `string`: No description
  - **inviteLink** `string`: No description

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
