# List a workflow's version history API Reference

Returns the snapshot history. A new version is recorded automatically before every PATCH to `nodes` / `edges` / `entryNodeId`, and explicitly when a previous version is restored. Lightweight list. Call `getWorkflowVersion` for the full snapshot graph.


## GET /v1/workflows/{workflowId}/versions

**List a workflow's version history**

Returns the snapshot history. A new version is recorded automatically before every PATCH to `nodes` / `edges` / `entryNodeId`, and explicitly when a previous version is restored. Lightweight list. Call `getWorkflowVersion` for the full snapshot graph.


### Parameters

- **workflowId** (required) in path: No description

### Responses

#### 200: Versions list

**Response Body:**

- **success** `boolean`: No description
- **versions** `array[object]`: Versions in reverse chronological order (newest first).
  - **version** `integer`: Monotonically increasing version number
  - **name** `string`: No description
  - **description** `string,null`: No description
  - **createdBy** `string,null`: User id that authored this version
  - **createdByEmail** `string,null`: Denormalized email so the history UI can render without a join
  - **restoredFromVersion** `integer,null`: When non-null, this snapshot was created by restoring that version
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
