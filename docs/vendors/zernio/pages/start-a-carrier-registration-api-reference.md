# Start a carrier registration API Reference

Starts the US carrier registration that a number needs before SMS
delivers: 10DLC (standard company or sole-proprietor) or toll-free
verification. 10DLC needs `brand` + `campaign`; toll-free needs
`tollFree`. Approval is asynchronous; poll
`GET /v1/sms/registrations/{id}` (sole-prop registrations first need
the OTP step: a code is texted to the brand's mobile number, submit it
via `/verify-otp`).

Already have an approved registration? Add another number to it with
`POST /v1/phone-numbers/{id}/sms/reuse-registration` instead
of registering (and paying the carrier brand fee) again.

Rather have your client fill in the legal business details? Create a
share link with `POST /v1/sms/registrations/share`.


## POST /v1/sms/registrations

**Start a carrier registration**

Starts the US carrier registration that a number needs before SMS
delivers: 10DLC (standard company or sole-proprietor) or toll-free
verification. 10DLC needs `brand` + `campaign`; toll-free needs
`tollFree`. Approval is asynchronous; poll
`GET /v1/sms/registrations/{id}` (sole-prop registrations first need
the OTP step: a code is texted to the brand's mobile number, submit it
via `/verify-otp`).

Already have an approved registration? Add another number to it with
`POST /v1/phone-numbers/{id}/sms/reuse-registration` instead
of registering (and paying the carrier brand fee) again.

Rather have your client fill in the legal business details? Create a
share link with `POST /v1/sms/registrations/share`.


### Request Body

- **registrationType** (required) `string`: No description - one of: standard_10dlc, sole_prop_10dlc, toll_free
- **phoneNumbers** `array`: Your numbers this registration covers. When omitted or empty on a 10DLC registration, defaults to your active SMS-enabled US local numbers not already covered by another registration.
- **brand** `object`: Required for 10DLC. The legal entity behind the traffic (TCR brand).
- **campaign** `object`: Required for 10DLC. What you'll send and how recipients opt in/out.
The opt-in/opt-out/help auto-responses (`optinMessage`,
`optoutMessage`, `helpMessage`) are optional: when omitted, a
compliant, brand-named template with the carrier-required
disclosures is generated for you. If you do send them, they must
name the registered brand and carry the disclosures. Submissions
that don't are rewritten to the compliant template before the
campaign is filed.

- **messagingBrandName** `string`: DBA / trade name used to brand message content (samples and auto-replies) when it differs from the legal name, e.g. a sole proprietor texting under a business name. The legal `brand.displayName` is still what the carrier vets.
- **wizardValues** `object`: Raw dashboard-wizard answers, stored only to prefill edit-and-resubmit. API integrators can omit.
- **resubmitRequestId** `string`: Resubmit a registration that was returned for changes. Updates it in place instead of creating a new one.
- **tollFree** `object`: Required for toll_free.

### Responses

#### 200: Registration submitted.

**Response Body:**

- **registrationId** `string`: No description
- **status** `string`: No description - one of: pending
- **awaitingOtp** `boolean`: True for sole-prop 10DLC: an OTP was texted to the brand's mobile; submit it via /verify-otp.

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

#### 422: Carrier registry rejected a field; `param` names it when known.

---

## GET /v1/sms/registrations

**List carrier registrations**

### Parameters

- **includeDeactivated** (optional) in query: Deactivated (terminated) registrations are hidden by default. Pass true to include them.

### Responses

#### 200: Registrations, newest first

**Response Body:**

- **registrations** `array[object]`: 
  - **id** `string`: No description
  - **registrationType** `string`: No description - one of: standard_10dlc, sole_prop_10dlc, toll_free
  - **displayName** `string,null`: No description
  - **status** `string`: requested/changes_requested = pre-submission review states; customers see them as pending / needs changes. - one of: pending, approved, rejected, requested, changes_requested, deactivated
  - **brandStatus** `string`: Carrier-registry brand status (e.g. VERIFIED).
  - **campaignStatus** `string`: No description
  - **brandId** `string,null`: TCR brand id, useful when referencing the brand in carrier support threads.
  - **campaignId** `string,null`: TCR campaign id.
  - **declineReason** `string,null`: No description
  - **tfActionRequiredAt** `string,null` (date-time): Toll-free only: when the carrier requested changes ("Waiting For Customer"). The request must be resubmitted within 7 days of this timestamp or it expires.
  - **phoneNumbers** `array[string]`: 
  - **awaitingOtp** `boolean`: Sole-prop 10DLC only; the OTP step is still pending.
  - **trustScore** `number,null`: Carrier-assigned brand trust score; drives throughput.
  - **throughput** `object`: Carrier throughput tier derived from the trust score.
    - **label** `string`: No description
    - **smsPerMinute** `number`: No description
    - **smsPerDay** `number`: No description

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
