# Create profile API Reference

Creates a new profile with a name, optional description, and color. Names are unique per team: a duplicate returns a 409 whose details.existingProfileId carries the id of the existing profile. Send an Idempotency-Key header to make retries safe: a retried create with the same key and body replays the original 201 (same _id) instead of conflicting.

## GET /v1/profiles

**List profiles**

Returns profiles sorted default-first, then by creation date. Filter with name (exact match) and paginate with limit/skip; without those params the full list is returned unchanged. Use includeOverLimit=true to include profiles that exceed the plan limit.

### Parameters

- **includeOverLimit** (optional) in query: When true, includes over-limit profiles (marked with isOverLimit: true).
- **name** (optional) in query: Exact-match filter on the profile name. Useful to recover a profile id after an ambiguous create (timeout followed by a 409 on retry).
- **limit** (optional) in query: Page size. When limit or skip is present, the response includes total and skip (and echoes limit).
- **skip** (optional) in query: Number of profiles to skip, applied after sorting and filtering.

### Responses

#### 200: Profiles

**Response Body:**

- **profiles** `array[Profile]`: 
- **total** `integer`: Total matching profiles across all pages. Present only when limit or skip was passed.
- **skip** `integer`: Offset applied. Present only when limit or skip was passed.
- **limit** `integer`: Echo of the limit query param. Present only when it was passed.

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

---

## POST /v1/profiles

**Create profile**

Creates a new profile with a name, optional description, and color. Names are unique per team: a duplicate returns a 409 whose details.existingProfileId carries the id of the existing profile. Send an Idempotency-Key header to make retries safe: a retried create with the same key and body replays the original 201 (same _id) instead of conflicting.

### Parameters

- **undefined** (optional): No description

### Request Body

- **name** (required) `string`: No description
- **description** `string`: No description
- **color** `string`: No description

### Responses

#### 201: Created

**Response Body:**

- **message** `string`: No description
- **profile**: `Profile` - See schema definition

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 402: Payment method or enterprise contract required. The authenticated
account hit a billing gate before the connection could proceed.
Three reasons:

  - `free_tier_exceeded`: the team has connected more accounts
    than the free tier allows. Add a payment method on the
    dashboard to continue (the user will be billed per
    additional connected account).

  - `twitter_passthrough`: connecting an X account
    requires a card on file from day one because X API calls
    incur real per-call pass-through costs. Applies to the 1st
    X account, not only the 3rd+.

  - `enterprise_required`: the team is on an enterprise
    contract with a negotiated connected-account cap and has
    reached it. Self-service teams have NO connected-account cap (the
    $1/account rate continues at any scale), so this reason can
    only fire for teams whose contract sets an explicit limit.
    `dashboard_url` deep-links to the enterprise contact page
    rather than the billing tab. The end-user already has a
    card on file; this gate is about contract terms, not card
    collection.

SDK consumers should switch on `reason` to render the right
prompt. For `free_tier_exceeded` and `twitter_passthrough`,
redirect the end-user to `dashboard_url` to add a payment method
via Zernio's hosted Stripe Setup Checkout. For
`enterprise_required`, redirect to `dashboard_url` (the
enterprise contact form) to adjust the contract's limit.


**Response Body:**

- **error** (required) `string`: Human-readable error message suitable for end-user display. (example: "X (Twitter) requires a payment method due to API pass-through costs. Add a payment method to connect an X account.")
- **code** (required) `string`: Machine-readable error code. Stable across versions. - one of: PAYMENT_REQUIRED
- **reason** (required) `string`: Discriminator for which gate fired. - one of: free_tier_exceeded, twitter_passthrough, enterprise_required
- **documentation_url** `string` (uri): Link to the relevant documentation page. (example: "https://docs.zernio.com/billing/payment-method-required")
- **dashboard_url** `string` (uri): Deep-link to send the end-user to. For
`free_tier_exceeded` and `twitter_passthrough` this is
the Zernio billing tab. For `enterprise_required` this
is the Zernio enterprise contact page.
 (example: "https://zernio.com/dashboard?tab=billing")
- **details** `object`: Structured context for SDK clients that want to render their own UX. Keys vary by `reason`.
  - **free_tier_account_limit** `integer`: How many accounts the free tier allows. Only set when reason=free_tier_exceeded. (example: 2)
  - **current_account_count** `integer`: How many accounts the team currently has connected. Set when reason=free_tier_exceeded or reason=enterprise_required. (example: 5)
  - **has_payment_method** `boolean`: Whether the team currently has a card on file in Stripe. Set when reason=free_tier_exceeded or reason=twitter_passthrough.
  - **effective_account_limit** `integer`: The negotiated connected-account cap from the
team's enterprise contract. Self-service teams
have no cap and never receive this reason. Only
set when reason=enterprise_required.
 (example: 2000)

#### 403: Profile limit exceeded

#### 409: A profile with this name already exists (code: profile_name_conflict); details.existingProfileId carries the id of the existing profile. Also returned while a request with the same Idempotency-Key is still processing.

#### 422: Idempotency-Key reused with a different request

---
