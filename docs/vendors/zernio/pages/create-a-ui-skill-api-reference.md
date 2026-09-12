# Create a UI skill API Reference

Tells the agent when to send a rich component (CTA URL button, image, carousel, list, reply buttons, location, Flow) and what to put in it. Not idempotent.

## GET /v1/accounts/{accountId}/business-agent/ui-skills

**List UI skills**

Cursor paged; follow `paging.cursors.after` until `paging.next` is absent.

### Parameters

- **undefined** (optional): No description
- **before** (optional) in query: No description
- **after** (optional) in query: No description
- **limit** (optional) in query: No description

### Responses

#### 200: UI skills

**Response Body:**

- **uiSkills** (required) `array[BusinessAgentUiSkill]`: 
- **paging** `object`: No description

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

#### 403: Inbox add-on required, the WhatsApp token lacks the Business Agent permissions (code reconnect_required), or the merchant has not accepted the Meta Business Agent terms in WhatsApp Manager (code business_agent_terms_not_accepted).

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

#### 404: Account not found, or no agent exists on the number yet or the referenced item does not exist (code business_agent_not_found).

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

---

## POST /v1/accounts/{accountId}/business-agent/ui-skills

**Create a UI skill**

Tells the agent when to send a rich component (CTA URL button, image, carousel, list, reply buttons, location, Flow) and what to put in it. Not idempotent.

### Parameters

- **undefined** (optional): No description

### Request Body

- **title** `string`: No description
- **component_type** (required) `string`: No description - one of: carousel_quick_reply, carousel_url, cta_url, flow, image, interactive_list, interactive_reply_buttons, location, location_request
- **status** (required) `string`: No description - one of: enabled, disabled
- **instruction** (required) `string`: When to send the component and everything needed to fill its fields.
- **flow_id** `integer`: Required for component_type flow, rejected otherwise.

### Responses

#### 201: UI skill created

**Response Body:**

- **title** `string`: No description
- **component_type** (required) `string`: No description - one of: carousel_quick_reply, carousel_url, cta_url, flow, image, interactive_list, interactive_reply_buttons, location, location_request
- **status** (required) `string`: No description - one of: enabled, disabled
- **instruction** (required) `string`: When to send the component and everything needed to fill its fields.
- **flow_id** `integer`: Required for component_type flow, rejected otherwise.
- **id** (required) `string`: No description
- **created_at** `integer`: Unix seconds.
- **updated_at** `integer`: Unix seconds.

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

#### 403: Inbox add-on required, the WhatsApp token lacks the Business Agent permissions (code reconnect_required), or the merchant has not accepted the Meta Business Agent terms in WhatsApp Manager (code business_agent_terms_not_accepted).

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

#### 404: Account not found, or no agent exists on the number yet or the referenced item does not exist (code business_agent_not_found).

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

---
