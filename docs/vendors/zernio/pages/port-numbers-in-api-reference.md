# Port numbers in API Reference

Submit a port-in for one or more existing numbers from another carrier.
Creates the carrier order(s), attaches the end-user (current account)
info plus the LOA and invoice documents, and submits to the losing
carrier. The transfer PIN is forwarded to the carrier and never stored.
Ported numbers arrive voice-ready (and SMS-ready where the order
supports messaging).

Run the portability check (POST /v1/phone-numbers/port-in/check) and
upload the two documents (POST /v1/phone-numbers/port-in/documents)
first. Uploaded documents must be attached to an order within 30
minutes or the carrier deletes them, so upload right before this call.
The carrier may split the numbers into several orders (by country,
number type, losing carrier); `orders` carries per-order results, and a
partial failure still returns 201 with the failed orders' `error` set
(they stay as cancellable drafts).

Non-US/CA numbers additionally need the country-specific values from
GET /v1/phone-numbers/port-in/requirements, passed via `requirements`,
and must be submitted one country per request. When required
information is still missing after submission, the order is kept as a
resumable draft whose `error` / `declineReason` names the gaps.


## POST /v1/phone-numbers/port-in

**Port numbers in**

Submit a port-in for one or more existing numbers from another carrier.
Creates the carrier order(s), attaches the end-user (current account)
info plus the LOA and invoice documents, and submits to the losing
carrier. The transfer PIN is forwarded to the carrier and never stored.
Ported numbers arrive voice-ready (and SMS-ready where the order
supports messaging).

Run the portability check (POST /v1/phone-numbers/port-in/check) and
upload the two documents (POST /v1/phone-numbers/port-in/documents)
first. Uploaded documents must be attached to an order within 30
minutes or the carrier deletes them, so upload right before this call.
The carrier may split the numbers into several orders (by country,
number type, losing carrier); `orders` carries per-order results, and a
partial failure still returns 201 with the failed orders' `error` set
(they stay as cancellable drafts).

Non-US/CA numbers additionally need the country-specific values from
GET /v1/phone-numbers/port-in/requirements, passed via `requirements`,
and must be submitted one country per request. When required
information is still missing after submission, the order is kept as a
resumable draft whose `error` / `declineReason` names the gaps.


### Request Body

- **phoneNumbers** (required) `array`: E.164 numbers to port in.
- **endUser** (required) `object`: End-user / current-carrier account info that authorizes the port. The
losing carrier matches every field against its records and rejects the
whole port on a mismatch, so enter values exactly as they appear on the
carrier bill.

- **loaDocumentId** (required) `string`: Document id from POST /v1/phone-numbers/port-in/documents (kind=loa).
- **invoiceDocumentId** (required) `string`: Document id from POST /v1/phone-numbers/port-in/documents (kind=invoice).
- **focDatetimeRequested** `string`: Requested port date; the carrier confirms the actual FOC later. US/CA default is one week out (shifted off weekends); international orders are scheduled into the carrier's next allowed porting window at or after this date.
- **customerReference** `string`: No description
- **portType** `string`: Whether the losing account ports all its numbers (full) or keeps some (partial). - one of: full, partial
- **requirements** `array`: Country-specific requirement values for international ports (from GET /v1/phone-numbers/port-in/requirements). Not needed for US/CA. The LOA and invoice requirements are satisfied automatically by loaDocumentId/invoiceDocumentId, and address-type requirements by the endUser service address.

### Responses

#### 201: Port submitted. Top-level fields mirror the first successfully submitted order; per-order truth (including failures) is in `orders`.

**Response Body:**

- **id** `string`: Porting order ID.
- **telnyxPortingOrderId** `string`: No description
- **status** `string`: No description - one of: draft, pending, foc_confirmed, ported, exception, cancelled
- **phoneNumbers** `array[string]`: 
- **orders** `array[object]`: 
  - **id** `string`: No description
  - **telnyxPortingOrderId** `string`: No description
  - **status** `string`: No description
  - **phoneNumbers** `array[string]`: 
  - **error** `string`: Present when this split order failed to submit (it stays as a cancellable draft).

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

#### 409: A number is already provisioned, or already in an in-flight port

#### 422: A number is not portable (reason included), numbers span multiple non-US/CA countries, or every split order failed to submit

---

## GET /v1/phone-numbers/port-in

**List port-in orders**

Your porting orders, newest first (max 50). Poll this for port progress:
pending, confirmed FOC date, exception reason, or ported.


### Responses

#### 200: Porting orders

**Response Body:**

- **orders** `array[object]`: 
  - **id** `string`: No description
  - **status** `string`: No description - one of: draft, pending, foc_confirmed, ported, exception, cancelled
  - **telnyxStatusValue** `string,null`: Raw carrier status string.
  - **phoneNumbers** `array[string]`: 
  - **fastPortEligible** `boolean,null`: No description
  - **focDatetimeRequested** `string,null` (date-time): No description
  - **focDatetimeActual** `string,null` (date-time): No description
  - **declineReason** `string,null`: No description
  - **submittedAt** `string,null` (date-time): No description
  - **portedAt** `string,null` (date-time): No description
  - **createdAt** `string` (date-time): No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---
