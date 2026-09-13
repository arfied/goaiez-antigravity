# Related Schema Definitions

## WorkflowExecutionEvent

One entry in a workflow execution's timeline. Emitted by the executor on every node visit and lifecycle transition, surfaced by `GET /v1/workflows/{workflowId}/executions/ {executionId}/events` for run inspection in the Runs UI.


### Properties

- **action** `string`: No description - one of: execution_started, execution_completed, execution_exited, execution_paused, execution_resumed, node_started, node_completed, node_failed, node_skipped
- **status** `string,null`: No description - one of: success, failed, pending
- **nodeId** `string,null`: Present on `node_*` events
- **nodeType** `string,null`: Present on `node_*` events
- **sourceHandle** `string,null`: The edge handle the executor followed out of this node (see `WorkflowEdge.sourceHandle`)
- **durationMs** `integer,null`: Node run time; present on `node_completed` and `node_failed`
- **errorMessage** `string,null`: Failure detail; present on `node_failed` and `execution_exited`
- **meta** `object,null`: Per-node-type payload. Shape varies; see WorkflowNode `type`. Examples:
  `send_message` → `{ messageType, text, recipient }`,
  `webhook` → `{ url, method, statusCode, responseTimeMs, responsePreview }`,
  `ai` → `{ model, provider, inputTokens, outputTokens, responsePreview }`,
  `condition` → `{ matchedHandle, rulesEvaluated }`,
  `a_b_split` → `{ percentage, chosen }`.

- **at** `string`: Event timestamp (UTC)

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
