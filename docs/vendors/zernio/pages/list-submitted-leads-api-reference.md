# List submitted leads API Reference

Returns submitted Lead Gen leads for your team, newest-first, with keyset pagination on `cursor`. For Meta (default) leads are served from the persisted cache, ingested in real time from the `leadgen` webhook. When `accountId` is a LinkedIn ads account, leads are fetched live from LinkedIn's `leadFormResponses` (LinkedIn has no webhook and enforces 90-day retention, so nothing is persisted) and `adAccountId` is required. Reading LinkedIn responses needs the `r_marketing_leadgen_automation` permission; accounts connected before it was added must reconnect. Requires the Ads add-on.


## GET /v1/ads/leads

**List submitted leads**

Returns submitted Lead Gen leads for your team, newest-first, with keyset pagination on `cursor`. For Meta (default) leads are served from the persisted cache, ingested in real time from the `leadgen` webhook. When `accountId` is a LinkedIn ads account, leads are fetched live from LinkedIn's `leadFormResponses` (LinkedIn has no webhook and enforces 90-day retention, so nothing is persisted) and `adAccountId` is required. Reading LinkedIn responses needs the `r_marketing_leadgen_automation` permission; accounts connected before it was added must reconnect. Requires the Ads add-on.


### Parameters

- **formId** (optional) in query: Filter to a single lead form.
- **accountId** (optional) in query: Filter to a single connected account. LinkedIn ads accounts switch to the live fetch.
- **adAccountId** (optional) in query: LinkedIn only: the LinkedIn ad account id whose responses to read (owner-scoped finder).
- **limit** (optional) in query: No description
- **since** (optional) in query: Unix seconds; only leads created at/after this timestamp. Millisecond timestamps return 400 with instructions to divide by 1000.
- **cursor** (optional) in query: Keyset cursor from a previous response's pagination.cursor (Meta: AdLead id; LinkedIn: numeric start offset).

### Responses

#### 200: Lead list.

**Response Body:**

- **status** `string`: No description (example: "success")
- **leads** `array[object]`: 
  - **id** `string`: Zernio lead id.
  - **leadgenId** `string`: Meta lead id. On LinkedIn, the leadFormResponse id.
  - **formId** `string`: No description
  - **formName** `string,null`: No description
  - **accountId** `string`: No description
  - **adId** `string,null`: No description
  - **adsetId** `string,null`: No description
  - **campaignId** `string,null`: On LinkedIn, this is the LinkedIn Campaign id, which corresponds to platformAdSetId on GET /v1/ads (LinkedIn's Campaign Group is Zernio's campaign).
  - **isOrganic** `boolean`: No description
  - **createdTime** `string,null`: ISO 8601.
  - **fields** `object`: Question key → answer. On LinkedIn, the key is the lowercased predefinedField, else the question name, else the numeric questionId; multiple-choice values are option labels (unlike Meta, which returns the option key).
  - **fieldData** `array[object]`: Raw Meta field_data.
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

#### 502: The platform returned a server error.

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

#### 503: An upstream service or database is temporarily unavailable. Retry after the indicated delay. A timed-out write may have completed upstream; check its outcome before resubmitting.

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
