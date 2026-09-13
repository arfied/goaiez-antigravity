# Pause workflow API Reference

Stop matching new inbound messages. In-flight executions continue to completion. Idempotent.

## POST /v1/workflows/{workflowId}/pause

**Pause workflow**

Stop matching new inbound messages. In-flight executions continue to completion. Idempotent.

### Parameters

- **workflowId** (required) in path: No description

### Responses

#### 200: Workflow paused

**Response Body:**

- **success** `boolean`: No description
- **workflow** `object`: 
  - **id** `string`: No description
  - **status** `string`: No description

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
