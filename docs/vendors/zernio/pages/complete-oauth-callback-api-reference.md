# Complete OAuth callback API Reference

Exchange the OAuth authorization code for tokens and connect the account to the specified profile.

Facebook, Google Business Profile, Snapchat and WhatsApp are not accepted here: their account identity is a destination chosen after OAuth, which this single-shot exchange cannot do. Connect them through the redirect flow from `GET /v1/connect/{platform}`, or, for WhatsApp Embedded Signup, through `POST /v1/connect/whatsapp/embedded-signup`.


## GET /v1/connect/{platform}

**Get OAuth connect URL**

Initiate an OAuth connection flow. Returns an authUrl to redirect the user to.
Standard flow: Zernio hosts the selection UI, then redirects to your redirect_url. Headless mode (headless=true): user is redirected to your redirect_url with OAuth data for custom UI. Use the platform-specific selection endpoints to complete.

TikTok: every connection now goes through the TikTok for Business app. One TikTok account per
profile, so connecting on a profile that already holds one replaces it. Reconnecting the SAME
account keeps it and all of its history; authorizing a DIFFERENT TikTok account takes the slot
over and permanently deletes the previous account's analytics, inbox and DM history. The two
are told apart by the `@handle` stored at the last connect, so an account whose handle has
been renamed on TikTok since then reads as a different account. An authorization that leaves
out a permission the connected account needs changes nothing at all and comes back as
`missing_tiktok_permissions`; connect again and accept every permission on TikTok's screen.


### Parameters

- **platform** (required) in path: Social media platform to connect. `snapchat` is a closed beta with no public release date: it returns 403 `PLATFORM_BETA_RESTRICTED` until the account is approved.
- **profileId** (required) in query: Your Zernio profile ID (get from /v1/profiles). For WhatsApp, a Zernio-provisioned number can only be connected on the profile it was provisioned to; connecting from any other profile is rejected with a 409.
- **redirect_url** (optional) in query: Your custom redirect URL after connection completes. MUST be an absolute http(s) URL or a custom app scheme for mobile deeplinks (e.g. myapp://callback); a relative path is rejected with 400 INVALID_REDIRECT_URL. Result params are appended with the URL API, so an existing query string is preserved. Standard mode appends connected={platform}&profileId=X&accountId=Y&username=Z. Headless mode appends OAuth data params for platforms requiring selection (e.g. LinkedIn orgs, Facebook pages). If no selection is needed, the account is created directly and the redirect includes accountId.

On failure, the browser is sent to the same redirect_url with `error` and `platform` appended.
`error` and `platform` are always present. `error_message`, `is_user_fixable`, `reason` and
`dashboard_url` are conditional and must be treated as optional.

This list is NOT exhaustive and new values may be added at any time. Treat an unrecognized
value as a generic failure rather than matching it exhaustively. Existing values are not
renamed or removed without notice.

OAuth and callback:
  oauth_denied, invalid_callback, invalid_state, unsupported_platform, connection_failed,
  internal_error, token_exchange_failed, byok_config_error, personal_account_not_supported,
  missing_google_permissions, missing_tiktok_permissions, platform_requires_destination,
  reconnect_account_mismatch, invalid_request

Access and limits:
  profile_not_found, invalid_profile_id, access_denied, account_limit_exceeded,
  profile_limit_exceeded, payment_required

Destination selection:
  no_facebook_pages, facebook_pages_error, no_google_locations, google_locations_error,
  google_permission_denied, no_snapchat_public_profiles, snapchat_profiles_error,
  discord_no_guild, slack_no_team

WhatsApp:
  whatsapp_error, one_whatsapp_per_profile, whatsapp_number_already_connected,
  whatsapp_number_pinned_to_profile, connection_cancelled

Google Ads (platform=googleads):
  google_ads_auth_failed, google_ads_invalid_state, google_ads_config_error,
  google_ads_token_failed, google_ads_quota_exhausted, google_ads_callback_error

TikTok Ads (platform=tiktokads):
  tiktok_ads_auth_failed, tiktok_ads_invalid_state, tiktok_ads_access_denied,
  tiktok_ads_config_error, tiktok_ads_token_failed, tiktok_ads_account_not_found,
  tiktok_ads_callback_error

X Ads (platform=xads):
  x_ads_denied, x_ads_auth_failed, x_ads_config_error, x_ads_account_not_found,
  x_ads_state_error, x_ads_token_failed, x_ads_token_missing, x_ads_callback_error

Shopify (platform=shopify):
  shopify_auth_failed, shopify_config_error, shopify_invalid_state, shopify_invalid_hmac,
  shopify_invalid_shop, shopify_missing_scopes, shopify_callback_error

1. On this endpoint every upstream OAuth error is collapsed into `oauth_denied`. The
provider's own value (for example Meta's `access_denied`) is not forwarded. The dedicated
ads flows below are different: they use their own denial slugs and `google_ads_auth_failed`
and `tiktok_ads_auth_failed` may carry the provider's raw error string in `error_message`.

2. On the tiktok and twitter ads flows `platform` carries the ads platform id
(`tiktokads`, `xads`), not the value used in the request path. The googleads and shopify
flows report `googleads` and `shopify`.

3. `missing_tiktok_permissions` means the TikTok authorization left out a permission the
already-connected account needs, so nothing was changed and it keeps working as before.
It is user-fixable: connect again and accept every permission on TikTok's screen.

- **headless** (optional) in query: When true, the user is redirected to your redirect_url with raw OAuth data (code, state) instead of Zernio's default account selection UI. Use this to build a custom connect experience.
- **loginMethod** (optional) in query: Instagram only. Which of the two Instagram connection methods to use. Ignored for every other platform.

`instagram_login` (the default, and what you get if you omit this): the Instagram Login dialog. The user authorizes their Instagram professional account directly, no Facebook Page required.

`facebook_login`: the Facebook Login dialog, i.e. "Instagram API with Facebook Login". The user authorizes a Facebook Page that has a linked Instagram professional account, and every API call for that account then runs through the Page. Use this when the customer manages Instagram through a Page and expects the Facebook consent screen. Because the user has to pick which Page to connect, the callback continues at the account-selection step, `/v1/connect/instagram/select-account`.

`facebook_login` supports `headless=true` like the other selection platforms: the callback redirects to your `redirect_url` with `profileId`, `tempToken`, `platform=instagram`, `step=select_account` and `connect_token`, which you pass into the select-account endpoints to finish. The default `instagram_login` has no selection step, so it connects the account directly.

- **onboarding** (optional) in query: WhatsApp only. Ignored for every other platform. Controls which screen Meta's Embedded Signup popup shows.

If omitted, the connection defaults to coexistence (same as `business_app` below), preserving existing behavior for numbers already on the WhatsApp Business app.

`api`: standard Embedded Signup, showing Meta's WABA/number picker. Use this to connect a phone number already on Cloud API elsewhere.

`business_app`: coexistence, i.e. 'Connect existing WhatsApp Business app' (a number shared between Cloud API and the consumer WhatsApp Business app).

- **signup** (optional) in query: WhatsApp only. Rejected with 400 `INVALID_FIELD_VALUE` on any other platform.

`hosted`: `authUrl` points at a Zernio-hosted page on zernio.com instead of Meta's OAuth dialog, and
the response carries `authUrl` only (no `state`). That page opens Meta's Embedded Signup popup itself,
so it learns which WhatsApp Business Account and number the user picked inside the popup and connects
exactly that one. Use it when your users' Facebook logins manage several WhatsApp accounts: on the
default redirect flow Meta only returns an authorization code, so when that login can see more than
one number the user lands on Zernio's number picker and has to choose again. Nothing to embed on your
side and no domain setup: send the user to `authUrl`, and they come back to `redirect_url` with the
same params as the redirect flow. Success: `connected=whatsapp`, `profileId`, `accountId`, `username`
(plus `connect_token` for API-key callers). Failure: `error` and `platform=whatsapp`, with the same
values and extras as the redirect flow (`one_whatsapp_per_profile`, `whatsapp_number_already_connected`
and `whatsapp_number_pinned_to_profile` with `is_user_fixable=true`; `payment_required` with `reason`
and `dashboard_url`; `whatsapp_error` with `error_message` when Meta reported one), plus two of its own:
`connection_cancelled` when the popup was closed before finishing (`error_message` carries Meta's last
reported step or error when there is one) and `session_expired` when the user took longer than the
60 minute window the hosted page is valid for; restart the flow with a new call in that case.
`onboarding` is carried through and a pre-verified Zernio-provisioned number is attached like on the
redirect flow. `headless` has no effect here because there is no selection step left to hand you.
When a previously disconnected account for this profile can simply be re-enabled, this endpoint
re-enables it and returns the account directly instead of a URL, exactly like the redirect flow.
The page shows the same guidance as the Zernio dashboard: a pre-verified Zernio-provisioned number is
called out by name ("choose it in Meta's list, no code will be asked"), coexistence and standard signups
get their explainer video, and a step-by-step follow-along checklist stays visible while Meta's popup is
open. Skin it with `brandName`, `primaryColor` and `language` below.

- **brandName** (optional) in query: Hosted signup page only (`signup=hosted`, WhatsApp): name shown in the page title ("Connect your WhatsApp number to <brandName>") instead of Zernio. The Zernio logo stays: the page is co-branded, not white-label. Trimmed; 1 to 60 characters. Rejected with 400 `INVALID_FIELD_VALUE` without `signup=hosted`. Stored on the signup session at issue time, so the page URL cannot change it.
- **primaryColor** (optional) in query: Hosted signup page only (`signup=hosted`, WhatsApp): hex colour (`#RRGGBB`) for the primary button and step accents. Validated server-side; anything else is a 400 `INVALID_FIELD_VALUE`. Rejected without `signup=hosted`.
- **language** (optional) in query: Hosted signup page only (`signup=hosted`, WhatsApp): language of the page and its follow-along guide. Explainer videos stay in English. Default `en`. Rejected without `signup=hosted`.

### Responses

#### 200: OAuth authorization URL to redirect user to

**Response Body:**

- **authUrl** `string` (uri): URL to redirect your user to for OAuth authorization
- **state** `string`: State parameter for security (handled automatically)

#### 400: Missing/invalid parameters (e.g., invalid profileId format, or a non-absolute redirect_url)

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

#### 403: No access to profile, BYOK required for AppSumo X, or Snapchat closed beta (code PLATFORM_BETA_RESTRICTED)

#### 404: Profile not found

---

## POST /v1/connect/{platform}

**Complete OAuth callback**

Exchange the OAuth authorization code for tokens and connect the account to the specified profile.

Facebook, Google Business Profile, Snapchat and WhatsApp are not accepted here: their account identity is a destination chosen after OAuth, which this single-shot exchange cannot do. Connect them through the redirect flow from `GET /v1/connect/{platform}`, or, for WhatsApp Embedded Signup, through `POST /v1/connect/whatsapp/embedded-signup`.


### Parameters

- **platform** (required) in path: Social platform to complete the connect for. Discord, Slack and Telegram are absent because they are
served by their own dedicated routes, documented separately.


### Request Body

- **code** (required) `string`: No description
- **state** (required) `string`: No description
- **profileId** (required) `string`: No description

### Responses

#### 200: Account connected

#### 400: Invalid params, or the platform requires choosing a destination (code: platform_requires_destination)

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

#### 403: No access to the profile, or BYOK required for AppSumo X

#### 404: Profile not found

#### 500: Internal error while connecting the account

#### 502: The platform rejected the token exchange (type: platform_error; an upstream 4xx status is forwarded instead of 502)

#### 503: Connections for this platform are temporarily disabled (code: platform_disabled)

---

---
