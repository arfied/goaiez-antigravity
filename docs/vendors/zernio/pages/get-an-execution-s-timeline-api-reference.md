# Get an execution's timeline API Reference

Returns the per-step run-log for a single workflow execution: trigger fired, each node visited, edge handles taken, errors, and durations. Backed by Tinybird (90-day retention). Used by the Runs UI drawer to render the timeline.


## GET /v1/workflows/{workflowId}/executions/{executionId}/events

**Get an execution's timeline**

Returns the per-step run-log for a single workflow execution: trigger fired, each node visited, edge handles taken, errors, and durations. Backed by Tinybird (90-day retention). Used by the Runs UI drawer to render the timeline.


### Parameters

- **workflowId** (required) in path: No description
- **executionId** (required) in path: No description

### Responses

#### 200: Timeline events for the execution

**Response Body:**

- **success** `boolean`: No description
- **execution** `object`: 
  - **id** `string`: No description
  - **status** `string`: No description - one of: running, waiting, completed, exited, failed
  - **startedAt** `string,null` (date-time): No description
  - **completedAt** `string,null` (date-time): No description
- **events** `array[WorkflowExecutionEvent]`: Events in chronological order (oldest first).

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
