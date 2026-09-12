# Related Schema Definitions

## BusinessAgentConnector


## BusinessAgentConnectorInput

### Properties

- **name** (required) `string`: Unique per number.
- **description** `string`: Tell the agent what the service provides.
- **base_url** (required) `string`: Public HTTPS URL reachable from Meta.
- **connector_protocol** `string`: No description
- **auth_type** (required) `string`: No description - one of: OAUTH2_CLIENT_CREDENTIALS, API_KEY, NONE
- **auth_config** `object`: 
  - **oauth2_client_credentials**: 
  - **api_key**: 
- **user_auth_injection_config** `object`: 
  - **location** `string`: 
  - **field_name** `string`: 
  - **prefix** `string`: 
- **requires_certificate** `boolean`: No description

## BusinessAgentOAuthClientCredentials

### Properties

- **token_url** (required) `string`: No description
- **scopes_to_request** `array`: No description
- **token_request_content_type** `string`: No description
- **client_id** (required) `string`: No description
- **client_secret** (required) `string`: No description

## BusinessAgentApiKeyConfig

Where the connector injects the API key on each call.

### Properties

- **headers**: No description
- **query_params**: No description
- **body_params**: No description

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

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
