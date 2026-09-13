# Hand a conversation to or from Meta Business Agent API Reference

WhatsApp only, on numbers with Meta Business Agent enabled. Wraps Meta's thread control:
- `release`: hand the conversation back to the agent so it resumes answering. You must currently hold control (sending any message takes it implicitly).
- `take`: take control before sending anything, so the agent stops replying while an operator reads the thread. Meta accepts this only from the business configured as the number's escalation partner; other apps take control by sending a message.
- `pass`: transfer control to the number's configured escalation partner, or to the agent with `target: ai_agent`. Meta's Cloud API currently rejects it ("Pass action is not supported", verified 2026-09-08); use `release` to hand a thread back to the agent.

The conversation's `threadControl` follows the result; a `conversation.control_changed` webhook fires when Meta later reports the change.


## POST /v1/inbox/conversations/{conversationId}/thread-control

**Hand a conversation to or from Meta Business Agent**

WhatsApp only, on numbers with Meta Business Agent enabled. Wraps Meta's thread control:
- `release`: hand the conversation back to the agent so it resumes answering. You must currently hold control (sending any message takes it implicitly).
- `take`: take control before sending anything, so the agent stops replying while an operator reads the thread. Meta accepts this only from the business configured as the number's escalation partner; other apps take control by sending a message.
- `pass`: transfer control to the number's configured escalation partner, or to the agent with `target: ai_agent`. Meta's Cloud API currently rejects it ("Pass action is not supported", verified 2026-09-08); use `release` to hand a thread back to the agent.

The conversation's `threadControl` follows the result; a `conversation.control_changed` webhook fires when Meta later reports the change.


### Parameters

- **conversationId** (required) in path: The conversation ID

### Request Body

- **accountId** (required) `string`: Social account ID
- **action** (required) `string`: No description - one of: release, take, pass
- **target** `string`: With action pass: send control to Meta Business Agent instead of the escalation partner. - one of: ai_agent
- **metadata** `string`: Free-form note forwarded verbatim to the app receiving control (its messaging_handovers webhook).

### Responses

#### 200: Control transferred

**Response Body:**

- **success** `boolean`: No description
- **control** `object`: 
  - **owner** `string`: No description - one of: app, ai_agent, other

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

#### 403: Inbox addon required

#### 404: Account or conversation not found

---
