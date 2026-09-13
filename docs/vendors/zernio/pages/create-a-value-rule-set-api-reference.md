# Create a value rule set API Reference

Creates a value rule set on the ad account (Meta's `POST /act_X/value_rule_set`).
Attach the returned id to an ad set with `valueRuleSetId` on `POST /v1/ads/create` or
`PUT /v1/ads/ad-sets/{adSetId}`.

**Rule order is semantic**: rules are evaluated in array order and only the first
matching rule adjusts the bid for an overlapping audience.

`adjustValue` is an unsigned magnitude in percent; the direction lives in `adjustSign`.
`INCREASE` accepts 1-1000, `DECREASE` accepts 1-90. There is no signed field and 0 is
out of range.

`criteriaValueTypes` is positionally paired with `criteriaValues` (same length, same
order). Every type is the literal `"NONE"` except on `LOCATION`, which uses
`LOCATION_COUNTRY` / `LOCATION_REGION` / `LOCATION_CITY` / `LOCATION_COMSCORE_MARKET`
and may mix them within one criterion. Location values are Targeting-Search keys: a
two-letter country code for `LOCATION_COUNTRY`, a numeric key for the rest.

`LOCATION_DMA` was replaced by `LOCATION_COMSCORE_MARKET` on 2026-06-22 and rules using
DMAs are no longer active, so this API rejects it.

`AUDIENCE_LABEL` values (e.g. `HIGH_VALUE`) are applied to a Custom Audience in Ads
Manager. There is no API to provision them, so label strings are passed through
unvalidated and a typo produces a rule that never fires.

Ads Manager turns a rule set read-only (this API stays editable) when a rule uses more
than 2 criteria, a custom age range, or the placements `FB_MARKETPLACE`, `FB_SEARCH`,
`FB_VIDEO` or `IG_EXPLORE`.

Limits: 6 rule sets per ad account, 10 rules per set, 4 criteria per rule. The
per-account cap is enforced by Meta, not here.

## GET /v1/ads/value-rule-sets

**List value rule sets**

Lists the ad account's value rule sets (Meta's `/act_X/value_rule_set`). A value rule
set adjusts the auction bid up or down for audience segments you value differently;
attach one to an ad set with `valueRuleSetId` on `POST /v1/ads/create` or
`PUT /v1/ads/ad-sets/{adSetId}`.

Rows are returned in the same camelCase shape the `PUT` body takes, ids included, so a
set round-trips 1:1: **the update is a full replace, not a patch**, so you GET, mutate
and send the whole thing back.

Limits: 6 rule sets per ad account, 10 rules per set, 4 criteria per rule.

**Rule order is semantic.** Rules are evaluated in array order and only the FIRST
matching rule adjusts the bid for an overlapping audience. The order you send is the
order that is stored and returned.

Eligibility: value rule sets apply only to ad sets on the `LOWEST_COST_WITHOUT_CAP`
(auto-bid) or `COST_CAP` bid strategies. Meta rejects the rest server-side.

### Parameters

- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.
- **adAccountId** (required) in query: Meta ad account id (act_<n>).
- **limit** (optional) in query: Rows per page
- **after** (optional) in query: Cursor from paging.after of the previous page. Meta does not document paging on this edge; `after` comes back null when it omits cursors.

### Responses

#### 200: Value rule sets

**Response Body:**

- **adAccountId** `string`: No description
- **data** `array[ValueRuleSet]`: 
- **paging** `object`: 
  - **after** `string,null`: Cursor for the next page; null when exhausted or when Meta omits paging.

#### 400: Invalid input, or Meta rejected the query. Meta answers a bad rule-set id with GraphMethodException code 100 / subcode 33, which is indistinguishable between not-found, no-permission, and account-not-enabled.

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

## POST /v1/ads/value-rule-sets

**Create a value rule set**

Creates a value rule set on the ad account (Meta's `POST /act_X/value_rule_set`).
Attach the returned id to an ad set with `valueRuleSetId` on `POST /v1/ads/create` or
`PUT /v1/ads/ad-sets/{adSetId}`.

**Rule order is semantic**: rules are evaluated in array order and only the first
matching rule adjusts the bid for an overlapping audience.

`adjustValue` is an unsigned magnitude in percent; the direction lives in `adjustSign`.
`INCREASE` accepts 1-1000, `DECREASE` accepts 1-90. There is no signed field and 0 is
out of range.

`criteriaValueTypes` is positionally paired with `criteriaValues` (same length, same
order). Every type is the literal `"NONE"` except on `LOCATION`, which uses
`LOCATION_COUNTRY` / `LOCATION_REGION` / `LOCATION_CITY` / `LOCATION_COMSCORE_MARKET`
and may mix them within one criterion. Location values are Targeting-Search keys: a
two-letter country code for `LOCATION_COUNTRY`, a numeric key for the rest.

`LOCATION_DMA` was replaced by `LOCATION_COMSCORE_MARKET` on 2026-06-22 and rules using
DMAs are no longer active, so this API rejects it.

`AUDIENCE_LABEL` values (e.g. `HIGH_VALUE`) are applied to a Custom Audience in Ads
Manager. There is no API to provision them, so label strings are passed through
unvalidated and a typo produces a rule that never fires.

Ads Manager turns a rule set read-only (this API stays editable) when a rule uses more
than 2 criteria, a custom age range, or the placements `FB_MARKETPLACE`, `FB_SEARCH`,
`FB_VIDEO` or `IG_EXPLORE`.

Limits: 6 rule sets per ad account, 10 rules per set, 4 criteria per rule. The
per-account cap is enforced by Meta, not here.

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id (posting or ads variant); its platform decides where the campaign is created.
- **adAccountId** (required) `string`: Platform ad account id (Meta act_<n>, Google customer id, LinkedIn account id, ...).
- **name** (required) `string`: No description
- **rules** (required) `array`: Evaluated in order; the first matching rule wins.

### Responses

#### 201: Value rule set created

**Response Body:**

- **adAccountId** `string`: No description
- **valueRuleSetId** `string,null`: The new rule set id. Meta does not document the create response body, so this is null on the (unobserved) case where it omits the id.

#### 400: Invalid input, or Meta rejected the create (per-account rule-set cap, ineligible criteria, or an account that is not enabled for value rules)

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
