# Pre-check a carrier registration API Reference

Dry-run of `POST /v1/sms/registrations` for 10DLC: validates and
composes the exact brand/campaign payloads a submission would store
(branding, disclosures, auto-replies), runs deterministic compliance
lints plus an AI reviewer over them, and returns the findings WITHOUT
creating anything. Use it to fix issues before submitting; `block`
severity findings indicate a near-certain carrier rejection.


## POST /v1/sms/registrations/preflight

**Pre-check a carrier registration**

Dry-run of `POST /v1/sms/registrations` for 10DLC: validates and
composes the exact brand/campaign payloads a submission would store
(branding, disclosures, auto-replies), runs deterministic compliance
lints plus an AI reviewer over them, and returns the findings WITHOUT
creating anything. Use it to fix issues before submitting; `block`
severity findings indicate a near-certain carrier rejection.


### Request Body

- **registrationType** (required) `string`: No description - one of: standard_10dlc, sole_prop_10dlc
- **phoneNumbers** `array`: No description
- **brand** (required) `object`: Same shape as the registration `brand`.
- **campaign** (required) `object`: Same shape as the registration `campaign`.
- **messagingBrandName** `string`: No description

### Responses

#### 200: Composed payloads + findings.

**Response Body:**

- **composed** `object`: The exact payloads a submission would store (post-branding, disclosures appended, auto-replies generated).
  - **brand** `object`: No description
  - **campaign** `object`: No description
- **advisories** `array[object]`: 
  - **field** `string,null`: The payload field the finding is about, when attributable.
  - **code** `string,null`: Stable rule id for deterministic findings; absent on AI findings.
  - **concern** `string`: No description
  - **severity** `string`: No description - one of: block, warn
- **verdict** `string`: No description - one of: pass, warn, fail, unreviewed
- **aiUnavailable** `boolean`: True when the AI portion of the check could not run; advisories then contain only deterministic findings.

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
