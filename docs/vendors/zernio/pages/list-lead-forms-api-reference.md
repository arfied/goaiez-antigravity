# List lead forms API Reference

Lists the Lead Gen forms owned by the account. Meta: forms on the connected Facebook Page. Pass either the `metaads` ads connection (the Page is taken from the Facebook account linked to it) or the Facebook account itself. LinkedIn: forms owned by the ad account's Company Page. Pass `adAccountId` (LinkedIn forms are org-owned). Requires the Ads add-on.


## GET /v1/ads/lead-forms

**List lead forms**

Lists the Lead Gen forms owned by the account. Meta: forms on the connected Facebook Page. Pass either the `metaads` ads connection (the Page is taken from the Facebook account linked to it) or the Facebook account itself. LinkedIn: forms owned by the ad account's Company Page. Pass `adAccountId` (LinkedIn forms are org-owned). Requires the Ads add-on.


### Parameters

- **accountId** (required) in query: Connected Meta ads, Facebook or LinkedIn ads account ID. A Meta ads connection resolves its Page through the Facebook account linked to the same profile.
- **adAccountId** (optional) in query: LinkedIn only: the LinkedIn ad account id (used to resolve the owning organization). Required for LinkedIn.
- **limit** (optional) in query: No description
- **cursor** (optional) in query: No description

### Responses

#### 200: Forms list.

**Response Body:**

- **status** `string`: No description (example: "success")
- **forms** `array[object]`: 
  Type: `object`
- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **cursor** `string,null`: No description

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

#### 403: Ads add-on required.

---

## POST /v1/ads/lead-forms

**Create a lead form**

Creates a Lead Gen form. The form content goes inside `platformSpecificData` for both platforms (the shape is selected by the accountId's platform). Meta: created on the connected Facebook Page (POST /{page-id}/leadgen_forms), where `accountId` may be the `metaads` ads connection (its Page comes from the Facebook account linked to the same profile) or the Facebook account itself; the old top-level Meta fields (questions, thankYou*, contextCard, …) are DEPRECATED but still accepted while platformSpecificData is absent; mixing both shapes is a 400. LinkedIn: created on the ad account's Company Page. NOT idempotent: a retry creates a second form. Meta prefilled question types (EMAIL, PHONE, FULL_NAME, …) must omit label/key; CUSTOM questions require both. LinkedIn exposes only free-text and multiple-choice questions via API (prefilled-from-profile fields are Campaign Manager UI-only). Requires the Ads add-on.


### Request Body

- **accountId** (required) `string`: No description
- **name** (required) `string`: No description
- **questions** `array`: Deprecated (Meta legacy shape): use platformSpecificData.questions.
- **privacyPolicyUrl** (required) `string`: No description
- **privacyPolicyLinkText** `string`: Deprecated: use platformSpecificData.privacyPolicyLinkText.
- **followUpActionUrl** `string`: Deprecated: use platformSpecificData.followUpActionUrl.
- **locale** `string`: Deprecated: use platformSpecificData.locale.
- **thankYouTitle** `string`: Deprecated: use platformSpecificData.thankYouTitle.
- **thankYouBody** `string`: Deprecated: use platformSpecificData.thankYouBody.
- **thankYouButtonText** `string`: Deprecated: use platformSpecificData.thankYouButtonText.
- **thankYouButtonType** `string`: Deprecated: use platformSpecificData.thankYouButtonType.
- **thankYouWebsiteUrl** `string`: Deprecated: use platformSpecificData.thankYouWebsiteUrl.
- **isOptimizedForQuality** `boolean`: Deprecated: use platformSpecificData.isOptimizedForQuality.
- **platformSpecificData**: Platform-specific settings (see schema definitions below)

### Responses

#### 200: Created form.

**Response Body:**

- **status** `string`: No description (example: "success")
- **form** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description

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

#### 403: Ads add-on required.

#### 422: Meta rejected the lead form. Code 3 is Meta's generic app-capability error and does not name a field; when the request set isPhoneSmsVerifyEnabled, the response names that field as the one to drop first.

---
