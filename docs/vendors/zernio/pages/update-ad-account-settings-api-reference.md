# Update ad account settings API Reference

Sets the default DSA beneficiary and payor on a Meta ad account (EU DSA, Article 26).
Set them once and every EU-targeted call to `/v1/ads/create`, `/v1/ads/boost` and
`/v1/ads/ctwa` on that ad account can omit `dsaBeneficiary`/`dsaPayor`: Meta applies
the defaults automatically.

The values are written to the ad account on Meta, the same setting Ads Manager edits.
Nothing is stored in Zernio, and defaults already set in Ads Manager work identically.
Zernio never guesses these values for you. Beneficiary and payor are legal disclosures
shown to EU users, so you must provide the entity names explicitly. Use
`GET /v1/ads/dsa-recommendations` to offer suggestions in your UI.

If `defaultDsaPayor` is omitted, the beneficiary is also set as the payor, which
covers the common case where the same entity benefits from and pays for the ads.
Read the current values back with `GET /v1/ads/dsa-defaults`.

Currently supported for Meta accounts only; other platforms return 400.


## POST /v1/ads/accounts

**Create Meta ad account**

Creates a durable Meta ad account in the end user's own business portfolio using
their connected Meta Ads token. Requires an active metaads accountId, Ads access,
business_management permission and business admin access. Discover portfolios with
GET /v1/ads/businesses. System-user tokens may return an empty businesses list;
supply the known business ID in that case.

The self-serve account starts without a payment method. The user must add a payment
method in Ads Manager before ads can deliver. Zernio cannot add payment methods.
Meta may require business verification and limits how many accounts a business can
create. Closing an account does not guarantee more capacity. An ad account cannot
truly be deleted, even after closing it and removing it from a business.

timezoneId is Meta's numeric ID, not an IANA timezone name. Select it from
https://developers.facebook.com/docs/marketing-api/reference/ad-account/timezone-ids/.
For example, 1 is America/Los_Angeles. Meta validates supported currencies and IDs.
endAdvertiser, mediaAgency and partner default to NONE for the self-serve flow.

The new account is added atomically to an existing scoped ad-account allowlist.
Unrestricted connections stay unrestricted. Reconnecting the same Meta identity
preserves this scope unless a caller explicitly replaces it. Discovery is nudged
immediately. Use the returned adAccountId with the existing ads endpoints.

This operation is not idempotent and Zernio never automatically retries it.
Unknown body fields are rejected. No validateOnly or dry-run option is supported.
After a timeout or a 502 with details.creationStatus=unknown, check the business
in Ads Manager before attempting another creation. A 201 with connectionUpdated=false
means the account exists but needs reconnecting with adAccountIds containing the returned ID and the previous
scoped IDs via GET /v1/connect/facebook/ads. Do not repeat the create call.


### Request Body

- **accountId** (required) `string`: Zernio metaads SocialAccount ID.
- **businessId** (required) `string`: Business portfolio that will own the account.
- **name** (required) `string`: Ad account name. Whitespace is trimmed.
- **currency** (required) `string`: Uppercase ISO 4217 currency supported by Meta.
- **timezoneId** (required) `integer`: Numeric Meta timezone ID from the linked timezone list. For example 1 is America/Los_Angeles.
- **endAdvertiser** `string`: End advertiser business or page ID. NONE uses the owning business.
- **mediaAgency** `string`: Media agency business or page ID. NONE for self-serve customers.
- **partner** `string`: Partner business or page ID. NONE for self-serve customers.
- **invoice** `boolean`: Request Meta invoicing. Eligibility is determined by Meta.
- **invoiceGroupId** `string`: Existing Meta invoice group ID.
- **invoicingEmails** `array`: Addresses for Meta invoices.
- **io** `boolean`: Meta insertion-order invoicing option.
- **poNumber** `string`: Purchase order number.
- **fundingId** `string`: Existing Meta funding reference. Does not add a payment method.
- **adAccountCreatedFromBmFlag** `boolean`: Meta Business Manager creation flag.

### Responses

#### 201: Ad account created. Check connectionUpdated and payment instructions.

**Response Body:**

- **adAccountId** (required) `string`: New Meta ad account ID for subsequent ads calls.
- **businessId** (required) `string`: Owning business portfolio ID.
- **connectionUpdated** (required) `boolean`: Whether the connection scope and discovery schedule were updated.
- **paymentMethodRequired** (required) `boolean`: Always true as a delivery prerequisite. This is not a live funding-source check. Confirm payment or invoicing in Ads Manager.
- **adsManagerUrl** (required) `string` (uri): Open the created account in Ads Manager.
- **nextSteps** (required) `string`: Payment setup instructions for the user.
- **warnings** (required) `array[string]`: Recovery instructions if the account could not be attached to the connection.

#### 400: Invalid input or Meta rejection. details.reason identifies creation_limit, business_verification_required, unsupported_currency, unsupported_timezone or business_unavailable when recognized.

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

#### 403: Ads access denied or Meta permission missing. details.reason may be business_management_required, business_admin_required or business_access_required.

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

#### 502: Creation outcome unknown. Check Ads Manager before repeating this non-idempotent request.

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

## GET /v1/ads/accounts

**List ad accounts**

Returns the platform ad accounts available for the given account (e.g. Meta ad
accounts, TikTok advertiser IDs, Google Ads customer IDs).
Meta business-login accounts use their own system-user token. Fresh Meta discovery
includes businessId and businessName from the owning Business Manager when available;
cached entries gain these fields after the next discovery refresh.

For TikTok agencies: enumerates every advertiser under every Business Center the token
can read (paginated server-side), then chunks the lookup against TikTok's
`/advertiser/info/` endpoint (which has a per-call cap of ≤100 IDs). Solo advertisers
without a BC fall back to the OAuth-time `advertiser_ids` list. Cached for 1h on the
SocialAccount; lazy-refreshed on first call after expiry.

For Google Ads: responds `429` when Google's API quota is temporarily exhausted
(instead of an empty list). Retry after a delay.


### Parameters

- **accountId** (required) in query: Account ID
- **adAccountId** (optional) in query: Filter response to a single platform ad account ID (e.g. `act_123` for Meta, advertiser_id for TikTok). Returns at most one item.
- **limit** (optional) in query: Clamp the returned `accounts[]` length. Useful for typeahead pickers on agency tokens with hundreds of advertisers.

### Responses

#### 200: Ad accounts

**Response Body:**

- **accounts** `array[object]`: 
  - **id** `string`: Platform ad account ID (e.g. act_123)
  - **name** `string`: No description
  - **currency** `string`: No description
  - **businessId** `string`: Meta only. Owning Business Manager ID when available on the grant.
  - **businessName** `string`: Owning business name when supplied by the platform.
  - **status** `string`: LinkedIn only. LinkedIn's own ad account status. In practice always `ACTIVE`, because the LinkedIn query filters to active accounts. Meta, Google, TikTok and Pinterest report `accountStatus` instead; X reports `approvalStatus`.
  - **accountStatus**: The platform's own account status, forwarded unchanged. No JSON type is
declared because the type differs per platform: Meta sends an integer,
Google, TikTok and Pinterest send a string. Absent on LinkedIn (reports
`status`) and on X (reports `approvalStatus`).

If all you need is whether the account can run ads right now, read
`selectable` and skip this field. Read this one when you need to tell
the states apart, because they call for different responses:

- `1` ACTIVE. Running normally.
- `2` DISABLED. Disabled by Meta. Read `disableReason` to tell a policy
  action apart from a billing one; they need very different follow-ups.
- `3` UNSETTLED. There is an unpaid balance, but the account still runs
  ads. Not a ban.
- `7` PENDING_RISK_REVIEW. Meta is reviewing the account. Wait for the
  outcome.
- `8` PENDING_SETTLEMENT. Meta blocks new ads until an outstanding
  balance clears. Settle it and the account runs again.
- `9` IN_GRACE_PERIOD. Still running, on a deadline.
- `100` PENDING_CLOSURE. Scheduled to close.
- `101` CLOSED. Terminal.

  - **approvalStatus** `string`: X only. X's own ad account approval status. Observed values are `ACCEPTED`, `PENDING` and `REJECTED`, but X does not publish the full vocabulary, so treat an unrecognised value as not usable. Other platforms report `accountStatus` or `status` instead.
  - **disableReason** `integer`: Meta only. Meta's `disable_reason` code, forwarded unchanged. Present when `accountStatus` is `2` (DISABLED) and Meta gives a reason, which is what separates a policy action from a payment problem. Meta does not publish a stable list of values for this field, so none are enumerated here: resolve the code against Meta's own ad account reference. Absent when Meta reports no reason, or when the connected token cannot read the field.
  - **timezoneName** `string`: IANA timezone of the ad account (Meta only). Drives daily-budget reset and Insights day boundaries.
  - **timezoneOffsetHoursUtc** `number`: Signed UTC offset in hours, reflecting current DST (Meta only).
  - **minimumDailyBudget** `number`: Meta only. Minimum daily budget for the account, in the account currency's major units. This is the impressions-billed minimum; other billing events have higher minimums. Absent when the connected token cannot read it.
  - **selectable** `boolean`: Meta and X only. Whether the account can create/run ads now. Absent (treat as true) on other platforms.
  - **unusableReason** `string,null`: Meta and X only. Human-readable reason when selectable is false; null when selectable.
- **cachedAt** `string,null` (date-time): Google only. When this list was fetched from Google. Null when it was never served from cache, or on other platforms.
- **stale** `boolean`: Google only. True when Google's daily API quota was exhausted and this is the last successful fetch, not a live read. Absent on other platforms.

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

#### 422: Platform ads connection required (TikTok Ads, X Ads) or Instagram missing linked Facebook account

#### 429: The connected account's upstream platform quota is exhausted.

Reddit rate-limits per connected Reddit user (1000 requests per
10-minute window), and that budget is shared by every operation using
that account. Retry after the window resets rather than retrying
immediately; repeated calls while exhausted do not succeed and keep the
budget spent.


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

## PATCH /v1/ads/accounts

**Update ad account settings**

Sets the default DSA beneficiary and payor on a Meta ad account (EU DSA, Article 26).
Set them once and every EU-targeted call to `/v1/ads/create`, `/v1/ads/boost` and
`/v1/ads/ctwa` on that ad account can omit `dsaBeneficiary`/`dsaPayor`: Meta applies
the defaults automatically.

The values are written to the ad account on Meta, the same setting Ads Manager edits.
Nothing is stored in Zernio, and defaults already set in Ads Manager work identically.
Zernio never guesses these values for you. Beneficiary and payor are legal disclosures
shown to EU users, so you must provide the entity names explicitly. Use
`GET /v1/ads/dsa-recommendations` to offer suggestions in your UI.

If `defaultDsaPayor` is omitted, the beneficiary is also set as the payor, which
covers the common case where the same entity benefits from and pays for the ads.
Read the current values back with `GET /v1/ads/dsa-defaults`.

Currently supported for Meta accounts only; other platforms return 400.


### Request Body

- **accountId** (required) `string`: Account ID (metaads, or a facebook/instagram posting account)
- **adAccountId** (required) `string`: Meta ad account ID (act_...)
- **defaultDsaBeneficiary** (required) `string`: Legal entity benefiting from ads on this ad account
- **defaultDsaPayor** `string`: Legal entity paying for ads on this ad account. Defaults to defaultDsaBeneficiary when omitted.

### Responses

#### 200: DSA defaults updated (re-read from Meta after the write)

**Response Body:**

- **adAccountId** `string`: No description
- **dsaDefaults** `object`: 
  - **beneficiary** `string`: No description
  - **payor** `string`: No description

#### 400: Unsupported platform (non-Meta account) or invalid adAccountId

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

---
