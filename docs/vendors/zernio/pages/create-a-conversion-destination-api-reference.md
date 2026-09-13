# Create a conversion destination API Reference

Create a new conversion destination on the platform. Supported for
LinkedIn (conversion rule) and Google Ads (conversion action). Meta
and OpenAI Ads pixels are created via their own tracking-tags flow
instead (`POST /v1/accounts/{accountId}/tracking-tags`); this endpoint
returns 405 for both.

**LinkedIn:** creation is NOT idempotent. A retry creates a second
destination. Deduplicate before retrying.

**Google Ads:** calling with a name that already exists reuses the
existing conversion action transparently (the response is identical to
a fresh create). Calling with the same name but a different category
returns a typed `IDEMPOTENCY_CONFLICT` (409) rather than silently
returning the mismatched action.

**LinkedIn:** the rule is created with `conversionMethod=CONVERSIONS_API`
and (by default) auto-associated with all of the ad account's campaigns
via `autoAssociationType=ALL_CAMPAIGNS`. Pass `autoAssociationType: NONE`
to opt out and manage associations explicitly via the associations
endpoints below.

365-day attribution windows are only valid for `SUBMIT_APPLICATION`,
`PURCHASE`, `ADD_TO_CART`, `QUALIFIED_LEAD`, and `LEAD` rule types;
the API rejects other combinations locally.

**Google Ads:** the conversion action is created with
`type=UPLOAD_CLICKS` (required for API-uploaded offline conversions,
immutable after creation). The `type` field carries the Google
`ConversionActionCategory` enum value, e.g. `PURCHASE`,
`SUBSCRIBE_PAID`, `SIGNUP`, `IMPORTED_LEAD`, `BOOK_APPOINTMENT`.
Unified standard event names (e.g. `Purchase`, `Subscribe`,
`CompleteRegistration`, `Lead`, `Schedule`) are resolved to their
Google category equivalents automatically. The action defaults to
secondary (non-primary) to avoid immediately steering Smart Bidding;
pass `primaryForGoal: true` to opt in.


## GET /v1/accounts/{accountId}/conversion-destinations

**List conversion destinations**

Returns the list of pixels (Meta), conversion actions (Google),
conversion rules (LinkedIn), or pixels (OpenAI Ads) accessible to the
connected ads account. Use the returned `id` as `destinationId` when
posting to `POST /v1/ads/conversions`.

For Google and LinkedIn, each destination's `type` reflects the
conversion type (PURCHASE, LEAD, SIGN_UP, etc.), and the event type is
locked to the destination. For Meta and OpenAI Ads, `type` is absent:
pixels accept any event name per request.

For LinkedIn, destinations are returned across every sponsored ad
account the connected token can access; the `adAccountId` field on
each destination identifies the parent ad account and is required for
subsequent CRUD calls (update, delete, associations, metrics).


### Parameters

- **accountId** (required) in path: SocialAccount ID (metaads, googleads, linkedinads, tiktokads, or openaiads).

### Responses

#### 200: Destinations listed

**Response Body:**

- **platform** `string`: No description - one of: metaads, googleads, linkedinads, tiktokads, openaiads
- **destinations** `array[object]`: 
  - **id** `string`: Destination identifier. Meta: pixel ID. Google:
conversion action resource name. LinkedIn:
numeric conversion rule ID. OpenAI Ads: pixel wire
id.

  - **name** `string`: No description
  - **type** `string`: Present when the platform locks event type to the
destination (Google conversion actions, LinkedIn
conversion rules).

  - **status** `string`: No description - one of: active, inactive
  - **adAccountId** `string`: Set by adapters whose destinations are scoped to a
specific ad account (LinkedIn). Pass back on
subsequent CRUD calls.


#### 400: Account's platform is not supported by the Conversions API.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans),
OR (for LinkedIn) the connected account lacks the `rw_conversions` scope and must be reconnected.


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

#### 429: LinkedIn rate limit hit. Retry with backoff.

---

## POST /v1/accounts/{accountId}/conversion-destinations

**Create a conversion destination**

Create a new conversion destination on the platform. Supported for
LinkedIn (conversion rule) and Google Ads (conversion action). Meta
and OpenAI Ads pixels are created via their own tracking-tags flow
instead (`POST /v1/accounts/{accountId}/tracking-tags`); this endpoint
returns 405 for both.

**LinkedIn:** creation is NOT idempotent. A retry creates a second
destination. Deduplicate before retrying.

**Google Ads:** calling with a name that already exists reuses the
existing conversion action transparently (the response is identical to
a fresh create). Calling with the same name but a different category
returns a typed `IDEMPOTENCY_CONFLICT` (409) rather than silently
returning the mismatched action.

**LinkedIn:** the rule is created with `conversionMethod=CONVERSIONS_API`
and (by default) auto-associated with all of the ad account's campaigns
via `autoAssociationType=ALL_CAMPAIGNS`. Pass `autoAssociationType: NONE`
to opt out and manage associations explicitly via the associations
endpoints below.

365-day attribution windows are only valid for `SUBMIT_APPLICATION`,
`PURCHASE`, `ADD_TO_CART`, `QUALIFIED_LEAD`, and `LEAD` rule types;
the API rejects other combinations locally.

**Google Ads:** the conversion action is created with
`type=UPLOAD_CLICKS` (required for API-uploaded offline conversions,
immutable after creation). The `type` field carries the Google
`ConversionActionCategory` enum value, e.g. `PURCHASE`,
`SUBSCRIBE_PAID`, `SIGNUP`, `IMPORTED_LEAD`, `BOOK_APPOINTMENT`.
Unified standard event names (e.g. `Purchase`, `Subscribe`,
`CompleteRegistration`, `Lead`, `Schedule`) are resolved to their
Google category equivalents automatically. The action defaults to
secondary (non-primary) to avoid immediately steering Smart Bidding;
pass `primaryForGoal: true` to opt in.


### Parameters

- **accountId** (required) in path: SocialAccount ID (linkedinads or googleads).

### Request Body

- **adAccountId** (required) `string`: Ad account ID. For LinkedIn: numeric (e.g. "5123456") or
full `urn:li:sponsoredAccount:{id}` URN. For Google: numeric
customer ID (e.g. "1234567890") or `customers/{id}` form.

- **name** (required) `string`: No description
- **type** (required) `string`: Conversion type. For LinkedIn: a unified standard event name
(e.g. "Purchase", "Lead", "AddToCart") or a LinkedIn rule
type enum (e.g. "PURCHASE", "QUALIFIED_LEAD"). For Google:
a unified standard event name (Purchase, Subscribe,
CompleteRegistration, Lead, Schedule) or a Google
ConversionActionCategory enum value directly (e.g.
"PURCHASE", "SUBSCRIBE_PAID", "SIGNUP", "IMPORTED_LEAD",
"BOOK_APPOINTMENT"). Unknown values pass through to the
platform.

- **attributionType** `string`: LinkedIn only. - one of: LAST_TOUCH_BY_CAMPAIGN, LAST_TOUCH_BY_CONVERSION
- **postClickAttributionWindowSize** `integer`: LinkedIn only. Default 30. 365 only allowed for LEAD,
PURCHASE, ADD_TO_CART, QUALIFIED_LEAD, SUBMIT_APPLICATION
rule types; the API rejects other combinations locally.
 - one of: 1, 7, 30, 90, 365
- **viewThroughAttributionWindowSize** `integer`: LinkedIn only. Default 7. Same 365-day-window type
restriction applies as `postClickAttributionWindowSize`.
 - one of: 1, 7, 30, 90, 365
- **valueType** `string`: LinkedIn only. DYNAMIC (default) uses the per-event `value`
from `sendConversions`. FIXED uses the rule's `value` field.
NO_VALUE drops monetary value entirely.
 - one of: DYNAMIC, FIXED, NO_VALUE
- **value** `object`: LinkedIn only. Static conversion value. Used when
`valueType=FIXED`. The currency should match the ad
account's currency.

- **autoAssociationType** `string`: LinkedIn only. Controls campaign association at rule-creation
time:
- ALL_CAMPAIGNS: associate the rule with every active,
  paused, and draft campaign in the ad account
- OBJECTIVE_BASED: associate only campaigns whose
  objective matches the rule's type
- NONE: don't auto-associate. Manage associations via
  the `/associations` endpoints below.
Note: auto-association runs once at create time; new
campaigns added after the rule still need explicit
association.
 - one of: ALL_CAMPAIGNS, OBJECTIVE_BASED, NONE
- **countingType** `string`: Google Ads only. Whether to count multiple conversions from
the same click (MANY_PER_CLICK) or at most one
(ONE_PER_CLICK). Defaults to MANY_PER_CLICK if omitted.
 - one of: MANY_PER_CLICK, ONE_PER_CLICK
- **primaryForGoal** `boolean`: Google Ads only. When true, the conversion action is marked
as primary and immediately influences Smart Bidding. Defaults
to false (secondary, record-only) to avoid unintentionally
steering the customer's campaigns on creation.


### Responses

#### 201: Destination created

**Response Body:**

- **platform** `string`: No description - one of: linkedinads, googleads
- **destination**: `ConversionDestination` - See schema definition

#### 400: Invalid body or platform validation failure.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required (Ads add-on on legacy plans, included on usage-based plans),
or the connected LinkedIn account lacks the `rw_conversions` scope (reconnect required).


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

#### 405: Platform does not support destination creation.

#### 409: The account may also be inactive or need reconnection (code ads_connection_required). Reconnect it and read GET /v1/accounts for its current ID before retrying.
Google Ads only. A conversion action with the given name already
exists but has a different category. Use a different name or use
the existing destination. Error code: `IDEMPOTENCY_CONFLICT`.


#### 429: Rate limit hit. Retry with backoff.

---
