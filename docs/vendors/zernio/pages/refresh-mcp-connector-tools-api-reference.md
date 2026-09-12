# Refresh MCP connector tools API Reference

Re-discovers the tools of an MCP connector. A failed discovery keeps the previous tool set and reports an ERROR sync status inside a 200.

## POST /v1/accounts/{accountId}/business-agent/connectors/{connectorId}/refresh-tools

**Refresh MCP connector tools**

Re-discovers the tools of an MCP connector. A failed discovery keeps the previous tool set and reports an ERROR sync status inside a 200.

### Parameters

- **undefined** (optional): No description
- **undefined** (optional): No description

### Responses

#### 200: Connector with updated tool sync metadata

**Response Body:**

- **name** (required) `string`: Unique per number.
- **description** `string`: Tell the agent what the service provides.
- **base_url** (required) `string` (uri): Public HTTPS URL reachable from Meta.
- **connector_protocol** `string`: No description (example: "HTTP")
- **auth_type** (required) `string`: No description - one of: OAUTH2_CLIENT_CREDENTIALS, API_KEY, NONE
- **auth_config** `object`: 
  - **oauth2_client_credentials**: `BusinessAgentOAuthClientCredentials` - See schema definition
  - **api_key**: `BusinessAgentApiKeyConfig` - See schema definition
- **user_auth_injection_config** `object`: 
  - **location** (required) `string`: No description (example: "headers")
  - **field_name** (required) `string`: No description
  - **prefix** `string`: No description
- **requires_certificate** `boolean`: No description
- **id** (required) `string`: No description
- **mcp_tool_sync** `object`: No description
- **mtls_config** `object`: No description
- **connection_status** `object`: 
  - **status** `string`: ACTIVE, PENDING_OAUTH, EXPIRED or ERROR.
  - **error_message** `string`: No description

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
