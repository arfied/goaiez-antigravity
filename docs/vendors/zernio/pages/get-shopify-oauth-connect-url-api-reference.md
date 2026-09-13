# Get Shopify OAuth connect URL API Reference

Initiate the Shopify OAuth flow for a store. Shopify is a connect-only
platform: the connected account does not publish social posts, it powers
the Blogs API (`/v1/accounts/{accountId}/blogs`). Returns an `authUrl`
to redirect the merchant to; after they approve the install, Shopify
redirects their browser to Zernio's callback, the account is created on
the profile (platform `shopify`), and the browser is redirected to
`redirect_url` (or the Zernio dashboard when omitted). Requested scopes
are `read_content` and `write_content` (content only; no customer or
order data). Connecting the same profile to a store again refreshes the
stored token in place.


## GET /v1/connect/shopify

**Get Shopify OAuth connect URL**

Initiate the Shopify OAuth flow for a store. Shopify is a connect-only
platform: the connected account does not publish social posts, it powers
the Blogs API (`/v1/accounts/{accountId}/blogs`). Returns an `authUrl`
to redirect the merchant to; after they approve the install, Shopify
redirects their browser to Zernio's callback, the account is created on
the profile (platform `shopify`), and the browser is redirected to
`redirect_url` (or the Zernio dashboard when omitted). Requested scopes
are `read_content` and `write_content` (content only; no customer or
order data). Connecting the same profile to a store again refreshes the
stored token in place.


### Parameters

- **profileId** (required) in query: Your Zernio profile ID (get from /v1/profiles).
- **shop** (required) in query: The myshopify.com store domain to connect, e.g. `your-store.myshopify.com` (the bare `your-store` prefix is accepted too).
- **redirect_url** (optional) in query: Your custom redirect URL after connection completes. MUST be an absolute http(s) URL or a custom app scheme for mobile deeplinks (e.g. myapp://callback); a relative path is rejected with 400 INVALID_REDIRECT_URL. On failure an `error` query param is appended.

### Responses

#### 200: OAuth authorization URL to redirect the merchant to

**Response Body:**

- **authUrl** `string` (uri): URL to redirect your user to for OAuth authorization
- **state** `string`: State parameter for security (handled automatically)

#### 400: Invalid `profileId` format, `shop` is not a myshopify.com store domain, or `redirect_url` is not an absolute http(s) URL or custom app scheme.

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

#### 403: API key does not have access to this profile.

#### 404: Profile not found or access denied.

#### 500: Shopify API not configured (missing credentials).

---

---
