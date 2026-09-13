# Select the Page whose Instagram account to connect API Reference

Saves the selected Page as an Instagram account connected via Facebook Login. The Page access token becomes the account's access token, so every Instagram call for it runs against the Facebook Graph host.

One Instagram account per profile: if the profile already has an Instagram account, this replaces it, and picking a different Instagram identity purges the previous account's conversations, external posts and stats.


## GET /v1/connect/instagram/select-account

**List Pages with a linked Instagram account**

Completes the `loginMethod=facebook_login` Instagram flow, i.e. "Instagram API with Facebook Login".

After the user authorizes on Facebook, extract `tempToken` from the redirect params (headless mode adds `step=select_account`) and pass it here to list the Facebook Pages they manage. Only Pages that have a linked Instagram professional account are returned, so an empty array means the user has no eligible Page. Use the X-Connect-Token header if connecting via API key.

Not used by the default `instagram_login` flow, which creates the account without a selection step.


### Parameters

- **profileId** (required) in query: Profile ID from your connection flow
- **tempToken** (required) in query: Long-lived Facebook user access token from the OAuth callback redirect

### Responses

#### 200: Facebook Pages that have a linked Instagram professional account

**Response Body:**

- **pages** `array[object]`: 
  - **id** `string`: Facebook Page ID
  - **name** `string`: Page name
  - **access_token** `string`: Page-specific access token
  - **instagram_business_account** `object`: The Instagram professional account linked to this Page
    - **id** `string`: Instagram Business Account ID
    - **username** `string`: No description
    - **profile_picture_url** `string`: No description

#### 400: Missing required parameters (profileId or tempToken)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: User does not have access to the specified profile

---

## POST /v1/connect/instagram/select-account

**Select the Page whose Instagram account to connect**

Saves the selected Page as an Instagram account connected via Facebook Login. The Page access token becomes the account's access token, so every Instagram call for it runs against the Facebook Graph host.

One Instagram account per profile: if the profile already has an Instagram account, this replaces it, and picking a different Instagram identity purges the previous account's conversations, external posts and stats.


### Request Body

- **profileId** (required) `string`: Profile ID from your connection flow
- **pageId** (required) `string`: The Facebook Page ID selected by the user, from GET /v1/connect/instagram/select-account
- **tempToken** (required) `string`: Long-lived Facebook user access token from the OAuth callback redirect
- **redirect_url** `string`: Optional custom redirect URL to return to after selection

### Responses

#### 200: Instagram account connected

**Response Body:**

- **message** `string`: No description
- **redirect_url** `string`: Redirect URL if a custom redirect_url was provided
- **account** `object`: 
  - **accountId** `string`: ID of the created SocialAccount
  - **platform** `string`: No description - one of: instagram
  - **username** `string`: No description
  - **displayName** `string`: Name of the Facebook Page backing this account
  - **profilePicture** `string`: No description
  - **isActive** `boolean`: No description
  - **loginMethod** `string`: No description - one of: facebook_login

#### 400: Missing required fields, or the selected Page has no linked Instagram professional account

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

#### 403: User does not have access to the specified profile

#### 404: Selected page not found among the pages this token can manage

---

---
