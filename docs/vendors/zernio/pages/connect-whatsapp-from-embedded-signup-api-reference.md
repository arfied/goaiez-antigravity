# Connect WhatsApp from Embedded Signup API Reference

Exchange the authorization code Meta's Embedded Signup popup returned. This is the call the Zernio-hosted
signup page makes after the popup closes (`GET /v1/connect/whatsapp?signup=hosted`), sending the `wabaId`
and `phoneNumberId` Meta reported so exactly the chosen number is connected; when both are omitted the
first number the token can see is used. The code never passes through a `redirect_uri`, so
`POST /v1/connect/{platform}` cannot accept it. Authenticates with an API key, or with the connect token
the hosted flow issues (`X-Connect-Token` header).


## POST /v1/connect/whatsapp/embedded-signup

**Connect WhatsApp from Embedded Signup**

Exchange the authorization code Meta's Embedded Signup popup returned. This is the call the Zernio-hosted
signup page makes after the popup closes (`GET /v1/connect/whatsapp?signup=hosted`), sending the `wabaId`
and `phoneNumberId` Meta reported so exactly the chosen number is connected; when both are omitted the
first number the token can see is used. The code never passes through a `redirect_uri`, so
`POST /v1/connect/{platform}` cannot accept it. Authenticates with an API key, or with the connect token
the hosted flow issues (`X-Connect-Token` header).


### Parameters

- **X-Connect-Token** (optional) in header: Connect token issued by the hosted signup flow, accepted instead of an API key.

### Request Body

- **code** (required) `string`: Authorization code from the WA_EMBEDDED_SIGNUP postMessage
- **profileId** (required) `string`: No description
- **wabaId** `string`: WhatsApp Business Account id, when the SDK reported one
- **phoneNumberId** `string`: No description
- **isCoexistence** `boolean`: Number is also live in the WhatsApp Business app
- **expectedPhoneNumber** `string`: Rejects the connect when Meta returns a different number
- **redirectUrl** `string`: Hosted signup page only. When present, the response also carries `redirectUrl`, the URL the user should land on, with the outcome mapped exactly like the redirect flow (success params, or `error` and `platform` with the same values). Must be an absolute http(s) URL or a custom app scheme.
- **echoConnectToken** `boolean`: Hosted signup page only. Append the connect token to the success redirect, as the redirect flow does for API-key callers.

### Responses

#### 200: Number connected

**Response Body:**

- **message** `string`: No description
- **account** `object`: 
  - **accountId** `string`: No description
  - **platform** `string`: No description - one of: whatsapp
  - **username** `string`: No description
  - **displayName** `string`: No description
  - **isActive** `boolean`: No description
  - **selectedPhoneNumber** `string`: No description
- **redirectUrl** `string`: Present only when `redirectUrl` was sent; also present on error responses.

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

#### 409: The number is already connected on another profile or team

---
