# Get a specific workflow version API Reference

Returns the full snapshot for a single historical version, including the graph.

## GET /v1/workflows/{workflowId}/versions/{version}

**Get a specific workflow version**

Returns the full snapshot for a single historical version, including the graph.

### Parameters

- **workflowId** (required) in path: No description
- **version** (required) in path: No description

### Responses

#### 200: Version snapshot

**Response Body:**

- **success** `boolean`: No description
- **version** `object`: 
  - **version** `integer`: No description
  - **name** `string`: No description
  - **description** `string,null`: No description
  - **entryNodeId** `string,null`: No description
  - **nodes** `array[WorkflowNode]`: 
  - **edges** `array[WorkflowEdge]`: 
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **profileId** `string`: No description
  - **createdBy** `string,null`: No description
  - **createdByEmail** `string,null`: No description
  - **restoredFromVersion** `integer,null`: No description
  - **createdAt** `string` (date-time): No description

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
