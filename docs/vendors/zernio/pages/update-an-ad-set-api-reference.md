# Update an ad set API Reference

Ad-set-level writes. Use this for ABO budget updates, ad-set-scoped
pause/resume, bid-strategy edits, Meta value-rule-set attach/detach, and
Meta-only post-launch delivery settings via `platformSpecificData`. At
least one updatable field is required.

Value rule sets (Meta only, see `/v1/ads/value-rule-sets`):
- ATTACH or REPLACE: send `valueRuleSetId`. Attachment is driven by the id's
  presence, so `valueRulesApplied: true` is optional. Sending a different id
  replaces the previous association; there is no separate replace call.
- DETACH: send `valueRulesApplied: false` and OMIT `valueRuleSetId`.
- Sending `valueRulesApplied: false` TOGETHER with `valueRuleSetId` returns 400
  `mutually_exclusive_fields`. This is deliberate: Meta attaches the rule set
  whenever `value_rule_set_id` is present, even with `value_rules_applied` false,
  so echoing stored state while asking to detach would silently keep the bid
  adjustments live.
- Eligibility: only ad sets on `LOWEST_COST_WITHOUT_CAP` or `COST_CAP`. Meta
  rejects the rest server-side.
- Read back with `GET /v1/ads/ad-sets/{adSetId}?fields=value_rule_set_id`. Meta
  does not document `value_rules_applied` as a readable ad-set field, so the
  boolean cannot be read back.

Bid strategy compatibility (per Meta's spec):
- `LOWEST_COST_WITHOUT_CAP`: no `bidAmount`, no `roasAverageFloor`.
- `LOWEST_COST_WITH_BID_CAP` / `COST_CAP`: `bidAmount` REQUIRED (whole currency units).
- `LOWEST_COST_WITH_MIN_ROAS`: `roasAverageFloor` REQUIRED (decimal multiplier, e.g. 2.0 = 2.0x ROAS).
- Meta only: send `bidAmount` WITHOUT `bidStrategy` to change the cap amount on an ad set
  under a COST_CAP / LOWEST_COST_WITH_BID_CAP parent campaign, leaving the strategy itself
  (inherited from the campaign) untouched. `roasAverageFloor` without `bidStrategy` is
  rejected (it has no meaning outside LOWEST_COST_WITH_MIN_ROAS).

Delivery settings are validated by Meta against the campaign objective;
incompatible combinations (e.g. a billingEvent the optimization goal
doesn't allow) surface as 400s from Meta.

When updating `budget` on an ABO campaign: if the parent campaign is
CBO, the response is 409 with code BUDGET_LEVEL_MISMATCH. Route to
PUT /v1/ads/campaigns/{campaignId} instead.


## GET /v1/ads/ad-sets/{adSetId}

**Get live ad-set details**

Reads the ad set live from Meta, returned verbatim. The default projection includes
`learning_stage_info` (learning-phase status: LEARNING / SUCCESS / FAIL / WAIVING; Meta
omits its `status` key on paused ad sets), delivery settings, budgets, schedule and
targeting. `fields` is a raw-passthrough override; unknown fields return Meta's 400
verbatim.

### Parameters

- **adSetId** (required) in path: Meta ad set id (platformAdSetId).
- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.
- **fields** (optional) in query: Comma-separated Graph field override. Supports nested {} projections and Graph field modifiers, so a nested edge can be paged explicitly: without a .limit() modifier the expansion runs at the Meta default page size and the tail is dropped silently.

### Responses

#### 200: The ad set as returned by Meta

**Response Body:**

- **adSet** `object`: Raw Meta ad set; keys are the requested Graph fields.

#### 400: Invalid input, or Meta rejected the query; the message carries Meta's error

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

## PUT /v1/ads/ad-sets/{adSetId}

**Update an ad set**

Ad-set-level writes. Use this for ABO budget updates, ad-set-scoped
pause/resume, bid-strategy edits, Meta value-rule-set attach/detach, and
Meta-only post-launch delivery settings via `platformSpecificData`. At
least one updatable field is required.

Value rule sets (Meta only, see `/v1/ads/value-rule-sets`):
- ATTACH or REPLACE: send `valueRuleSetId`. Attachment is driven by the id's
  presence, so `valueRulesApplied: true` is optional. Sending a different id
  replaces the previous association; there is no separate replace call.
- DETACH: send `valueRulesApplied: false` and OMIT `valueRuleSetId`.
- Sending `valueRulesApplied: false` TOGETHER with `valueRuleSetId` returns 400
  `mutually_exclusive_fields`. This is deliberate: Meta attaches the rule set
  whenever `value_rule_set_id` is present, even with `value_rules_applied` false,
  so echoing stored state while asking to detach would silently keep the bid
  adjustments live.
- Eligibility: only ad sets on `LOWEST_COST_WITHOUT_CAP` or `COST_CAP`. Meta
  rejects the rest server-side.
- Read back with `GET /v1/ads/ad-sets/{adSetId}?fields=value_rule_set_id`. Meta
  does not document `value_rules_applied` as a readable ad-set field, so the
  boolean cannot be read back.

Bid strategy compatibility (per Meta's spec):
- `LOWEST_COST_WITHOUT_CAP`: no `bidAmount`, no `roasAverageFloor`.
- `LOWEST_COST_WITH_BID_CAP` / `COST_CAP`: `bidAmount` REQUIRED (whole currency units).
- `LOWEST_COST_WITH_MIN_ROAS`: `roasAverageFloor` REQUIRED (decimal multiplier, e.g. 2.0 = 2.0x ROAS).
- Meta only: send `bidAmount` WITHOUT `bidStrategy` to change the cap amount on an ad set
  under a COST_CAP / LOWEST_COST_WITH_BID_CAP parent campaign, leaving the strategy itself
  (inherited from the campaign) untouched. `roasAverageFloor` without `bidStrategy` is
  rejected (it has no meaning outside LOWEST_COST_WITH_MIN_ROAS).

Delivery settings are validated by Meta against the campaign objective;
incompatible combinations (e.g. a billingEvent the optimization goal
doesn't allow) surface as 400s from Meta.

When updating `budget` on an ABO campaign: if the parent campaign is
CBO, the response is 409 with code BUDGET_LEVEL_MISMATCH. Route to
PUT /v1/ads/campaigns/{campaignId} instead.


### Parameters

- **adSetId** (required) in path: Platform ad set ID

### Request Body

- **platform** (required) `string`: No description - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai
- **budget** `object`: Omit if not updating budget
- **status** `string`: Writes the ad set's own on/off switch (Meta: `configured_status`) on Meta and LinkedIn, whatever delivery status its ads report. Omit if not toggling delivery state. - one of: active, paused
- **name** `string`: Rename the ad set (Meta only; other platforms return 501). At least one of budget/status/bidStrategy/name is required.
- **bidStrategy**: Ad-set-level bid strategy. Overrides the campaign-level default.
Supported on Meta (facebook, instagram), TikTok, and OpenAI. On TikTok the
Meta-style enum is mapped to bid_type / bid_price / deep_bid_type
automatically. On OpenAI, LOWEST_COST_WITH_BID_CAP and COST_CAP both map to
the ad group's `bidding_config.max_bid_micros` (one knob covers both);
LOWEST_COST_WITH_MIN_ROAS is rejected with 422 (OpenAI has no ROAS-based
bidding). Other platforms (linkedin, pinterest, google, twitter) return 501
Not Implemented when bidStrategy is set.

- **bidAmount** `number`: Bid cap in WHOLE currency units (USD: 5 = $5.00; JPY: 100 = ¥100). Required when
bidStrategy is LOWEST_COST_WITH_BID_CAP or COST_CAP. Internally converted to Meta's
smallest-denomination integer, or (on OpenAI) to micros (× 1,000,000). Meta only:
may be sent alone, WITHOUT bidStrategy, to update the cap amount on an ad set whose
parent campaign is COST_CAP or LOWEST_COST_WITH_BID_CAP (the strategy is inherited
from the campaign and is left untouched).

- **roasAverageFloor** `number`: Minimum ROAS as a decimal multiplier (2.0 = 2.0x). Required when bidStrategy is
LOWEST_COST_WITH_MIN_ROAS. Sent to Meta as `bid_constraints.roas_average_floor` × 10000.
Not supported on OpenAI (422).

- **valueRuleSetId** `string`: Meta only (other platforms return 501). Value rule set to attach to this ad
set, from `/v1/ads/value-rule-sets`. Sending a different id replaces the
current association. To DETACH, send `valueRulesApplied: false` and omit
this field.

- **valueRulesApplied** `boolean`: Meta only (other platforms return 501). `false` DETACHES the ad set's value
rule set and must be sent WITHOUT `valueRuleSetId`; the combination returns
400. `true` is optional when attaching, since attachment is driven by
`valueRuleSetId`, and requires it to be present.

- **platformSpecificData** `object`: Platform-specific post-launch delivery settings. The platform is implied by the
`platform` body param. Meta only; other platforms return 400. Unknown keys are rejected.


### Responses

#### 200: Ad set updated

**Response Body:**

- **budget**: `AdBudget` - See schema definition
- **budgetLevel** `string`: No description - one of: adset
- **status** `string`: The status written to the ad set. Absent when nothing was written (see statusMessage). - one of: active, paused
- **statusUpdated** `integer`: Number of ads whose own stored status changed alongside the ad set switch
- **statusSkipped** `integer`: Number of ads whose own status was left as it was
- **statusSkippedReasons** `array[string]`: Why each group of ads was skipped
- **statusMessage** `string`: Present only where the platform has no ad-set switch and no child ad was actionable; `status` is then absent because nothing was written
- **bidStrategy**: `BidStrategy` - See schema definition
- **bidAmount** `number,null`: No description
- **roasAverageFloor** `number,null`: No description
- **platformSpecificData** `object`: No description

#### 400: Invalid input

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Ad set not found

#### 409: Campaign is CBO. Route to /v1/ads/campaigns/{campaignId} instead

#### 422: bidStrategy is LOWEST_COST_WITH_MIN_ROAS on OpenAI (unsupported: no ROAS-based bidding)

#### 501: bidStrategy not supported on the platform (Meta, TikTok, and OpenAI only)

---

## DELETE /v1/ads/ad-sets/{adSetId}

**Delete an ad set**

Deletes the ad set on the platform, cascading to its ads only (never the
campaign). Locally, every Ad document under the ad set is marked
`status: cancelled`.

Delete is soft on platforms that have no hard delete: LinkedIn moves the
campaign to `PENDING_DELETION`, Pinterest archives the ad group, and X
soft-flags the line item. Google removes the ad group. All remain readable
for reporting.


### Parameters

- **adSetId** (required) in path: Platform ad set ID

### Responses

#### 200: Ad set deleted

**Response Body:**

- **deleted** `boolean`: No description
- **adCount** `integer`: Local Ad documents marked cancelled

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Ad set not found

#### 501: Operation not supported on this platform

---
