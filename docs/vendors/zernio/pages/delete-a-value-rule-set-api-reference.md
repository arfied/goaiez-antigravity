# Delete a value rule set API Reference

Deletes the rule set (Meta's `POST /{value-rule-set-id}/delete_rule_set`, a custom
action edge rather than an HTTP DELETE on its side). Ad sets pointing at it are not
modified here; detach them first with `valueRulesApplied: false` on
`PUT /v1/ads/ad-sets/{adSetId}`.

## GET /v1/ads/value-rule-sets/{valueRuleSetId}

**Read a value rule set**

Reads one value rule set including every nested rule id and criterion id. This is step
one of any edit: `PUT` is a full replace, so you need the ids before you can keep the
objects you are not changing.

Meta's own read returns `GENDER` values lowercase (`"male"`) while writes require
`"MALE"`. Values are passed through untouched, so never case-compare a stored rule
against a fetched one.

### Parameters

- **valueRuleSetId** (required) in path: Platform value rule set id.
- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.

### Responses

#### 200: Value rule set

**Response Body:**

- **valueRuleSet**: `ValueRuleSet` - See schema definition

#### 400: Invalid input, or Meta rejected the read. A bad id comes back as GraphMethodException code 100 / subcode 33, which cannot be told apart from a permission problem.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 501: Only supported on Meta (facebook/instagram)

---

## PUT /v1/ads/value-rule-sets/{valueRuleSetId}

**Replace a value rule set**

**THIS IS A FULL REPLACE, NOT A PATCH.** Meta's update is declarative: the body you
send becomes the rule set.

- `GET /v1/ads/value-rule-sets/{valueRuleSetId}` FIRST.
- Keep a rule or criterion by echoing its `id`.
- Create one by including the object WITHOUT an `id`.
- Delete one by OMITTING it from the array. There is no warning and no undo.

`name` and `rules` are both required for exactly this reason: a partial body would
silently destroy every rule left out.

**Rule order is semantic**: the array order you send is the evaluation order, and only
the first matching rule adjusts the bid for an overlapping audience.

Existing rule sets created elsewhere may contain `LOCATION_DMA` criteria. Those went
inert on 2026-06-22 and are rejected here; migrate them to `LOCATION_COMSCORE_MARKET`.

### Parameters

- **valueRuleSetId** (required) in path: Platform value rule set id.

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id (posting or ads variant); its platform decides where the campaign is created.
- **name** (required) `string`: Required: the update replaces the whole set.
- **rules** (required) `array`: The COMPLETE rule list. Omitting a rule deletes it on Meta.

### Responses

#### 200: Value rule set replaced

**Response Body:**

- **valueRuleSetId** `string`: No description
- **name** `string`: No description
- **rules** `array[ValueRule]`: 
- **message** `string`: No description

#### 400: Invalid input, or Meta rejected the update

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 501: Only supported on Meta (facebook/instagram)

---

## DELETE /v1/ads/value-rule-sets/{valueRuleSetId}

**Delete a value rule set**

Deletes the rule set (Meta's `POST /{value-rule-set-id}/delete_rule_set`, a custom
action edge rather than an HTTP DELETE on its side). Ad sets pointing at it are not
modified here; detach them first with `valueRulesApplied: false` on
`PUT /v1/ads/ad-sets/{adSetId}`.

### Parameters

- **valueRuleSetId** (required) in path: Platform value rule set id.
- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.

### Responses

#### 200: Value rule set deleted

**Response Body:**

- **valueRuleSetId** `string`: No description
- **message** `string`: No description

#### 400: Invalid input, or Meta rejected the delete. A bad id comes back as GraphMethodException code 100 / subcode 33, which reads like a permission error rather than a 404.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 501: Only supported on Meta (facebook/instagram)

---
