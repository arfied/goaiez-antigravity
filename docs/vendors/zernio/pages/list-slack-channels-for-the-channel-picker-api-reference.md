# List Slack channels for the channel picker API Reference

Serves the channel picker of the Slack connect flow. Slack's OAuth installs the bot into a
workspace, not a channel, so after the redirect the caller lists the workspace's channels
here and finalizes one with `POST /v1/connect/slack`. Served by a dedicated route that
shadows `GET /v1/connect/{platform}` for `slack`.

Send exactly one of `pendingDataToken` (first connect: the nonce from the OAuth redirect,
bound to the same `profileId`) or `accountId` (add another channel to a workspace already
connected: the existing Slack account's workspace token is reused, no re-OAuth). With
neither, the endpoint behaves like `GET /v1/connect/{platform}` and returns `authUrl` and
`state` to start the OAuth flow.

Channels are read live from Slack (`conversations.list`, public and private, archived
excluded, up to 2,000). `isMember` says whether the Zernio bot is already in the channel:
a public channel is joined automatically on finalize, a private one must be invited
(`/invite @Zernio`) first.


## GET /v1/connect/slack

**List Slack channels for the channel picker**

Serves the channel picker of the Slack connect flow. Slack's OAuth installs the bot into a
workspace, not a channel, so after the redirect the caller lists the workspace's channels
here and finalizes one with `POST /v1/connect/slack`. Served by a dedicated route that
shadows `GET /v1/connect/{platform}` for `slack`.

Send exactly one of `pendingDataToken` (first connect: the nonce from the OAuth redirect,
bound to the same `profileId`) or `accountId` (add another channel to a workspace already
connected: the existing Slack account's workspace token is reused, no re-OAuth). With
neither, the endpoint behaves like `GET /v1/connect/{platform}` and returns `authUrl` and
`state` to start the OAuth flow.

Channels are read live from Slack (`conversations.list`, public and private, archived
excluded, up to 2,000). `isMember` says whether the Zernio bot is already in the channel:
a public channel is joined automatically on finalize, a private one must be invited
(`/invite @Zernio`) first.


### Parameters

- **profileId** (required) in query: Zernio profile the channel account will belong to. Must match the profile the OAuth flow was started on when `pendingDataToken` is used.
- **pendingDataToken** (optional) in query: Nonce from the OAuth redirect (first connect).
- **accountId** (optional) in query: Existing active Slack account (yours or a team member's) whose workspace token is reused.
- **redirect_url** (optional) in query: Start-OAuth mode only: where to send the user after the connect completes. `redirectUrl` is accepted as an alias.

### Responses

#### 200: Channel list (picker mode), or the OAuth URL when neither `pendingDataToken` nor `accountId` is sent

**Response Body:**

*One of the following:*
  - **team** (required) `object`: 
    - **id** `string`: Slack workspace (team) id
    - **name** `string,null`: No description
    - **icon** `string,null` (uri): Workspace icon URL
  - **channels** (required) `array[object]`: 
    - **id** (required) `string`: Channel id (C... or G...), the value to send as channelId on POST
    - **name** (required) `string`: No description
    - **isPrivate** (required) `boolean`: No description
    - **isMember** (required) `boolean`: Whether the Zernio bot is already a member of the channel
  - **authUrl** (required) `string` (uri): No description
  - **state** (required) `string`: No description

#### 400: Invalid profileId or accountId format, or pendingDataToken invalid, expired or issued for another profile (code: invalid_field_value)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: No access to the profile, or Slack connections are temporarily unavailable (code: feature_not_available)

#### 404: Profile not found (start-OAuth mode), or no active Slack account with that accountId for this user or their team (code: account_not_found)

---

## POST /v1/connect/slack

**Connect a Slack channel**

Finalize a Slack connect by creating the per-channel account. Served by a dedicated route, so it is not reachable through POST /v1/connect/{platform}. Send pendingDataToken for a first connect (the nonce from the OAuth redirect) or accountId to add another channel to a workspace already connected.

### Request Body

- **profileId** (required) `string`: No description
- **channelId** (required) `string`: Slack channel id, C... or G...
- **pendingDataToken** `string`: Nonce from the OAuth redirect. Required unless accountId is sent.
- **accountId** `string`: Existing Slack account whose workspace token is reused. Required unless pendingDataToken is sent.

### Responses

#### 200: Channel connected

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

#### 403: Slack connections are temporarily unavailable

#### 404: Profile not found

---
