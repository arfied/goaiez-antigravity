# Create invite link API Reference

Create a new invite link for a WhatsApp group. The previous link is revoked.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


## POST /v1/whatsapp/wa-groups/{groupId}/invite-link

**Create invite link**

Create a new invite link for a WhatsApp group. The previous link is revoked.

Not available on [Coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) numbers. Requires a Cloud API-only number.


### Parameters

- **groupId** (required) in path: Group ID
- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Invite link created

**Response Body:**

- **success** `boolean`: No description
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
