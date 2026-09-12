# Related Schema Definitions

## BusinessAgentStatus

Where the merchant is in the Meta Business Agent setup for this number.

### Properties

- **eligible** (required) `boolean,null`: Whether the number can run the agent; null when the terms are not accepted yet (Meta refuses the check).
- **termsAccepted** (required) `boolean`: False when Meta rejects calls because the merchant has not accepted the terms in WhatsApp Manager.
- **onboarded** (required) `boolean`: An agent exists on the number (onboard was called).
- **enabled** (required) `boolean`: The agent answers live conversations.
- **agentId** (required) `string,null`: No description
- **settings** (required): No description
- **manualSteps** (required) `array`: Steps Meta keeps outside the API that Zernio can verify are still pending.
- **unverifiedSteps** (required) `array`: Steps Meta keeps outside the API and exposes no state for, listed once an agent exists. Informational: Zernio cannot tell whether the merchant already did them.

## BusinessAgentSettings

Meta Business Agent settings for one WhatsApp number, as Meta returns them.

### Properties

- **agent_id** (required) `string`: No description
- **channel** (required) `string`: No description
- **rollout** (required) `object`: 
  - **enabled** `boolean`: Whether the agent answers live conversations.
- **handoff** `object,null`: No description
- **followup** `object,null`: No description
- **ai_audience** `string,null`: EVERYONE answers all consumers; ALLOWLISTED_ONLY answers only the allowlist and needs no payment method. - one of: EVERYONE, ALLOWLISTED_ONLY, 
- **never_say_phrases** `array`: Exact phrases the agent must never say.

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
