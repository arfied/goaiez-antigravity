# Related Schema Definitions

## WorkflowNode

A node in a workflow graph. `config` shape depends on `type`.

### Properties

- **id** (required) `string`: Stable node id referenced by edges
- **type** (required) `string`: Node kind. The 16 supported types break into four groups:
  messaging (send_message),
  control flow (trigger, condition, delay, wait_for_reply, a_b_split, end),
  data ops (set_variable, set_field, add_tag, remove_tag, enroll_sequence),
  integrations (webhook, ai, handoff, start_call).
 - one of: trigger, send_message, wait_for_reply, condition, set_variable, delay, webhook, ai, handoff, start_call, a_b_split, set_field, enroll_sequence, add_tag, remove_tag, end
- **config** `object`: Type-specific settings. All string fields support `{{variable}}` interpolation against the run's variable bag (resolved at execution time).

**trigger**: `{ triggerType: inbound_message|api_call|whatsapp_event, keywords:[string], matchType: any|contains|exact|regex, onlyFirstMessage:boolean, eventType: message_sent|message_delivered|message_read|message_failed|reaction }`. Default `triggerType` is `inbound_message` for legacy nodes. `eventType` is only honored when `triggerType` is `whatsapp_event` (WhatsApp-only).

**send_message**: `{ messageType: text|template|media|interactive, text, template:{name,language,variableMapping}, media:{mediaType:image|video|audio|document, url,caption}, interactive }`. `template` and `interactive` are WhatsApp-only. `interactive.type` is inferred from the payload shape when omitted; payloads with neither `type` nor an inferable shape are rejected.

**wait_for_reply**: `{ timeoutMinutes:int (max 43200), saveAs:string }`. Resume via the `'reply'` edge on inbound, or `'timeout'` edge after `timeoutMinutes` of silence.

**condition**: `{ rules:[{ id, variable, operator: equals|not_equals|contains|not_contains|starts_with|ends_with|exists|not_exists|matches, value }] }`. First matching rule takes its `id` as the sourceHandle; otherwise `'default'`.

**set_variable**: `{ assignments:[{ name, value }] }`. Run-scoped (lives only for this execution; use `set_field` for persistent values).

**delay**: `{ delayMinutes:int (max 43200) }`. Suspends the run, resumes via timer.

**webhook**: `{ url, method: GET|POST|PUT|PATCH|DELETE, headers, bodyTemplate, saveAs }`. SSRF-guarded (private/loopback/metadata IPs rejected). Response saved as `{ status, ok, body }` to `vars[saveAs]`. Edge: `'success'` on 2xx, `'error'` otherwise.

**ai**: `{ provider: anthropic|openai|google|mistral|groq|openrouter, model, preset: smart|tools|cheap, systemPrompt, userPromptTemplate, saveAs, temperature, maxTokens, outputType: text|json, tools:[{ name, description, parameters }] }`. Set `provider` + `model` for BYOK (uses your stored API key); omit `provider` for the legacy Telnyx path. Edges: `'success'`, `'tool:<name>'` (model picked a tool), `'error'`.

**handoff**: `{ note, assignTo }`. Terminates the run as `exited`, flags the conversation for a human operator.

**start_call**: `{ to, forwardTo, requirePermissionFirst, recordingEnabled, saveAs }`. WhatsApp-only. `forwardTo` can be `tel:+E164`, `sip:user@host`, or `wss://…` (AI voice agent). Edges: `'success'`, `'permission_required'`, `'failed'`.

**a_b_split**: `{ percentage: number 0-100 (default 50) }`. Random branch picker. Edges: `'a'` (with probability `percentage/100`), `'b'`.

**set_field**: `{ field, value }`. Persistent custom field on the Contact (vs `set_variable` which is run-scoped). Field name is sanitized to `[A-Za-z0-9_]`. No-op on `api_call` runs (no contact).

**enroll_sequence**: `{ sequenceId, saveAs }`. Enrolls the run's contact into a Sequence. Edges: `'success'`, `'error'`.

**add_tag** / **remove_tag**: `{ tag }`. Push or pull a tag on the Contact. No-op on `api_call` runs.

**end**: no config. Terminates the run as `completed`.

- **position** `object`: Canvas coordinates (ignored by the executor; used by the visual builder).
  - **x** `number`: 
  - **y** `number`: 
- **label** `string`: Optional display name shown on the builder canvas and inspector, falling back to the node type when absent. The nodes array is replaced wholesale on update, so it must be resent to be kept. (max: 80)

## WorkflowEdge

A directed edge between two nodes.

### Properties

- **id** (required) `string`: No description
- **source** (required) `string`: Source node id
- **target** (required) `string`: Target node id
- **sourceHandle** `string,null`: Selects a branch output of a multi-output node. Null (or omitted) = the node's single/default output. Known handles per node type:

  - **condition**: a rule's `id`, or `'default'` (no rule matched)
  - **wait_for_reply**: `'reply'` (contact replied) | `'timeout'` (no reply in window)
  - **webhook**: `'success'` (2xx) | `'error'` (non-2xx / fetch failed)
  - **ai**: `'success'` (text/JSON response) | `'tool:<toolName>'` (model invoked
    that tool) | `'error'` (upstream failure / non-JSON in JSON mode)
  - **start_call**: `'success'` | `'permission_required'` | `'failed'`
  - **a_b_split**: `'a'` | `'b'`
  - **enroll_sequence**: `'success'` | `'error'`


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
