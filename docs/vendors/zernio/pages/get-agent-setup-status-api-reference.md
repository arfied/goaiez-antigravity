# Get agent setup status API Reference

One read that says where the merchant is: whether the number is eligible, whether the
Meta Business Agent terms are accepted, whether an agent exists, whether it is on, and its
settings. `manualSteps` lists what Zernio can verify is still pending (accepting the terms
in WhatsApp Manager); `unverifiedSteps` lists what Meta exposes no state for (the payment
method in Billing Hub). Never fails for those pre-setup states; it reports them as flags.


## GET /v1/accounts/{accountId}/business-agent

**Get agent setup status**

One read that says where the merchant is: whether the number is eligible, whether the
Meta Business Agent terms are accepted, whether an agent exists, whether it is on, and its
settings. `manualSteps` lists what Zernio can verify is still pending (accepting the terms
in WhatsApp Manager); `unverifiedSteps` lists what Meta exposes no state for (the payment
method in Billing Hub). Never fails for those pre-setup states; it reports them as flags.


### Parameters

- **undefined** (optional): No description

### Responses

#### 200: Setup status

**Response Body:**

- **eligible** (required) `boolean,null`: Whether the number can run the agent; null when the terms are not accepted yet (Meta refuses the check).
- **termsAccepted** (required) `boolean`: False when Meta rejects calls because the merchant has not accepted the terms in WhatsApp Manager.
- **onboarded** (required) `boolean`: An agent exists on the number (onboard was called).
- **enabled** (required) `boolean`: The agent answers live conversations.
- **agentId** (required) `string,null`: No description
- **settings** (required): One of multiple types
  - `BusinessAgentSettings`
- **manualSteps** (required) `array[object]`: Steps Meta keeps outside the API that Zernio can verify are still pending.
  - **step** (required) `string`: No description - one of: accept_terms
  - **url** (required) `string` (uri): No description
  - **description** (required) `string`: No description
- **unverifiedSteps** (required) `array[object]`: Steps Meta keeps outside the API and exposes no state for, listed once an agent exists. Informational: Zernio cannot tell whether the merchant already did them.
  - **step** (required) `string`: No description - one of: attach_payment_method
  - **url** (required) `string` (uri): No description
  - **description** (required) `string`: No description

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
