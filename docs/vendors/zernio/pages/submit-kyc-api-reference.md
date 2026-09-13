# Submit KYC API Reference

Submit the end customer's KYC (textual values, uploaded documents,
address) for a Tier 3/4 country. Documents are streamed straight to the
number provider and are not stored by Zernio. Builds + submits a
regulatory requirement group and claims a pending_regulatory slot; the
number is ordered + activated once the provider approves (asynchronous).
A customer may hold several same-country numbers in review at once; a
double-submit of the SAME attempt is deduped via `submissionId`.

For an ID-card document requirement, carriers commonly require BOTH sides:
combine the front and back into a single file before uploading (the
dashboard does this automatically). A one-sided ID is a common decline
reason; fix it via POST /v1/phone-numbers/{id}/remediate.

Before submitting, call GET /v1/phone-numbers/availability to
check the country has deliverable inventory and, for geographic-match
countries, which area the address must be in. Otherwise the submission
can pass review yet never be assignable a number.


## GET /v1/phone-numbers/kyc

**Get KYC form spec**

For a Tier 3/4 country, the fields the end customer must provide (Telnyx
regulatory requirements) before a number can be ordered: text, date,
address, or file (document) per requirement.


### Parameters

- **country** (required) in query: No description
- **numberType** (optional) in query: Requirements and reuse eligibility are per (country, type). Omitted = the country's default type. Pass the same value on the POST.

### Responses

#### 200: The KYC form spec.

**Response Body:**

- **country** `string`: No description
- **numberType** `string`: No description
- **fields** `array[object]`: 
  - **requirementId** `string`: No description
  - **label** `string`: No description
  - **kind** `string`: "action" = an out-of-band verification (e.g. Onfido); not filled here, fulfilled after the order via a link. - one of: text, date, address, file, action
  - **description** `string,null`: Plain-English explanation of what to provide.
  - **example** `string,null`: Concrete example value.
  - **localTo** `string,null`: ISO country the value must be local to
- **reusable** `object,null`: Present when this account already has a reusable verification for the country (skip the form). `fromPhoneNumber`/`details` mirror the first option; `options` lists ALL reusable verifications (agencies hold one per end client), approved-first. Pass the chosen option's `id` as `reuseOptionId` on POST. Each option's `instant` says whether it activates in minutes (group-approved) or still queues for carrier review (1-3 days).
- **pendingReview** `boolean`: true when this account already has a number for this country in regulatory review (status pending_regulatory). Scope is the whole account across all profiles, and the country only (any number type), so it is not a per-end-client signal on a multi-tenant setup. Informational only: it never blocks a submission, and several same-country numbers may sit in review at once. For a per-end-client view, call GET /v1/phone-numbers with `profileId` and `status=pending_regulatory`; that view also lists numbers declined in the last 30 days.

#### 400: Country not available

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## POST /v1/phone-numbers/kyc

**Submit KYC**

Submit the end customer's KYC (textual values, uploaded documents,
address) for a Tier 3/4 country. Documents are streamed straight to the
number provider and are not stored by Zernio. Builds + submits a
regulatory requirement group and claims a pending_regulatory slot; the
number is ordered + activated once the provider approves (asynchronous).
A customer may hold several same-country numbers in review at once; a
double-submit of the SAME attempt is deduped via `submissionId`.

For an ID-card document requirement, carriers commonly require BOTH sides:
combine the front and back into a single file before uploading (the
dashboard does this automatically). A one-sided ID is a common decline
reason; fix it via POST /v1/phone-numbers/{id}/remediate.

Before submitting, call GET /v1/phone-numbers/availability to
check the country has deliverable inventory and, for geographic-match
countries, which area the address must be in. Otherwise the submission
can pass review yet never be assignable a number.


### Request Body

- **profileId** (required) `string`: No description
- **country** (required) `string`: No description
- **submissionId** `string`: Idempotency token for this submission attempt. Once the number has been ordered, a retry with the same token returns that same number instead of ordering another. A submission that fails before the number is ordered releases the token, so you can correct your details and re-submit with it. Omit it and every call provisions a new number.
- **quantity** `integer`: Provision several same-country numbers from one submission (1-5). The single verification covers all of them; each number is billed only when it activates. Numbers that fail to order are skipped (best-effort). With `areaCode`, a quantity above that area's live stock is rejected with a 400.
- **reuse** `boolean`: Reuse a prior approved verification for this country (skips document/field collection; places the order immediately).
- **reuseOptionId** `string`: Which reusable verification to use (GET reusable.options[].id). The unambiguous selection key. Omitted = the approved default. No match = 409.
- **reuseFrom** `string`: Legacy fallback for `reuseOptionId`: the source phone number (GET reusable.options[].fromPhoneNumber). Ambiguous when a number labels two verifications, so prefer `reuseOptionId`. Omitted = the approved default. No match = 409.
- **areaCode** `string`: Area code (NDC) the number must be in. Hard constraint: an empty area pool fails with 409 code AREA_CODE_UNAVAILABLE instead of ordering from another area. Omit for any area. Options come from GET /v1/phone-numbers/availability (areaOptions); the purchase 202 kycUrl echoes the areaCode picked at purchase time so it can be passed here.
- **endUserFirstName** `string`: End user's legal first name. Required when the country has an action/ID-verification (Onfido) requirement.
- **endUserLastName** `string`: End user's legal last name. Same condition as endUserFirstName.
- **values** `object`: requirementId → textual value
- **documents** `array`: One per document requirement. Each is EITHER inline base64 OR a `documentId` returned by POST /v1/phone-numbers/kyc/upload-document (use the upload endpoint for large files to stay under the request-size limit).
- **address** `object`: No description

### Responses

#### 200: KYC submitted (or already submitted); number pending review.

**Response Body:**

- **status** `string`: No description - one of: kyc_submitted, kyc_reused, kyc_already_submitted
- **preOrder** `boolean`: True when nothing was in stock and this submission placed a pre-order. The number stays `pending_regulatory` until we get it, from regular stock the moment it returns or sourced by the carrier (usually 2 to 4 weeks), and is not billed until active. Releasing it (DELETE /v1/phone-numbers/{id}) cancels the pre-order. A pre-order is one number: `quantity` above 1 is rejected with 400.
- **phoneNumber** `object`: The first/primary number, kept at the top level for backward compatibility. See `numbers` for the full set when `quantity` > 1.
  - **id** `string`: No description
  - **status** `string`: No description
  - **country** `string`: No description
- **numbers** `array[object]`: Every number provisioned from this submission. Length equals the requested `quantity` on full success (fewer if some orders failed; best-effort). The first element mirrors `phoneNumber`.
  - **id** `string`: No description
  - **status** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description

#### 400: Validation error (e.g. address not in-country, file too large)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 409: Either reuse was requested but no prior approved verification exists for this country, or the requested areaCode has no deliverable inventory right now (code: area_code_unavailable; pick another area and resubmit).

---

---
