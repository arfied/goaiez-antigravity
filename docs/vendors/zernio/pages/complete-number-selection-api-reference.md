# Complete number selection API Reference

Bind a specific WhatsApp phone number to the Zernio profile after the user picks one from `listWhatsAppPhoneNumbers`. Exchanges the short-lived OAuth token for a long-lived token, subscribes the WABA to webhooks, and creates the SocialAccount.


## GET /v1/connect/whatsapp/select-phone-number

**List numbers for selection**

Fetch the WhatsApp phone numbers available across the user's WhatsApp Business Accounts (WABAs) after a headless OAuth flow.

WhatsApp OAuth grants access at the WABA level. When a connected WABA has 2 or more phone numbers, you must call this endpoint to list them and then `POST /v1/connect/whatsapp/select-phone-number` to bind one to the Zernio profile. Single-phone WABAs auto-complete during the OAuth callback and never reach this endpoint.

Use the `profileId` and `tempToken` returned in the headless redirect (`step=select_phone_number`).

Alternative: if you already know `wabaId` and `phoneNumberId` (e.g. from Meta Business Suite), use `connectWhatsAppCredentials` instead, which skips this two-step flow.


### Parameters

- **profileId** (required) in query: The Zernio profile ID from the headless redirect
- **tempToken** (required) in query: The temporary access token from the headless redirect
- **X-Connect-Token** (optional) in header: Alternative auth for API users' end customers (used when the bearer token is scoped to a different user)

### Responses

#### 200: Phone numbers fetched successfully

**Response Body:**

- **phoneNumbers** `array[object]`: 
  - **id** `string`: Phone Number ID (Meta)
  - **display_phone_number** `string`: E.164-formatted display number
  - **verified_name** `string`: Meta-verified business name
  - **quality_rating** `string`: GREEN, YELLOW, RED, or UNKNOWN
  - **name_status** `string`: APPROVED, PENDING_REVIEW, DECLINED, or NONE
  - **messaging_limit_tier** `string`: TIER_250, TIER_1K, TIER_10K, TIER_100K, or TIER_UNLIMITED
  - **wabaId** `string`: WhatsApp Business Account ID (Zernio enrichment)
  - **wabaName** `string`: WABA display name (Zernio enrichment)

#### 400: Missing profileId or tempToken

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

#### 500: Failed to fetch phone numbers (Meta API error, expired token, or insufficient permissions)

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

---

## POST /v1/connect/whatsapp/select-phone-number

**Complete number selection**

Bind a specific WhatsApp phone number to the Zernio profile after the user picks one from `listWhatsAppPhoneNumbers`. Exchanges the short-lived OAuth token for a long-lived token, subscribes the WABA to webhooks, and creates the SocialAccount.


### Parameters

- **X-Connect-Token** (optional) in header: Alternative auth for API users' end customers

### Request Body

- **profileId** (required) `string`: The Zernio profile ID
- **phoneNumberId** (required) `string`: The selected phone number ID (from listWhatsAppPhoneNumbers)
- **wabaId** (required) `string`: The WABA ID containing the selected phone
- **tempToken** (required) `string`: The temporary access token from the headless redirect
- **userProfile** `object`: Optional user profile data (passthrough)
- **redirect_url** `string`: Optional URL to receive the post-connection redirect target

### Responses

#### 200: Phone number connected successfully

**Response Body:**

- **message** `string`: No description
- **redirect_url** `string`: Present only if redirect_url was provided in the request
- **account** `object`: 
  - **accountId** `string`: No description
  - **platform** `string`: No description - one of: whatsapp
  - **username** `string`: Display phone number
  - **displayName** `string`: Meta-verified business name
  - **isActive** `boolean`: No description
  - **selectedPhoneNumber** `string`: No description

#### 400: Missing required fields (profileId, phoneNumberId, wabaId, or tempToken)

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

#### 403: Profile limit exceeded for the user's plan (PROFILE_LIMIT_EXCEEDED)

#### 404: Selected phone number not found in the specified WABA

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

#### 409: Conflict with an existing connection. One of: the target profile already has a WhatsApp number connected (code ONE_WHATSAPP_PER_PROFILE, each profile holds exactly one WhatsApp number, so connect this number to a different or new profile); the phone number is a Zernio-provisioned number pinned to a different profile (code WHATSAPP_NUMBER_PINNED_TO_PROFILE, connect it from that profile or move it first with PATCH /v1/whatsapp/phone-numbers/{id}/profile); or the number is already actively connected on another profile or team (code WHATSAPP_NUMBER_ALREADY_CONNECTED, disconnect it there first). A number can only be live on one profile.

#### 500: Failed to bind phone number

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

---
