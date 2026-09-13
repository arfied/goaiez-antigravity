# Pre-validate KYC address API Reference

Optional early check for the address step of a Tier 4 (end-user identity)
registration: validates a postal address for deliverability BEFORE the full
KYC submit, so it can be corrected before any documents are uploaded. The
full submit (POST /v1/phone-numbers/kyc) re-validates the address,
so this call is purely a fast feedback path and skipping it is safe. Only
the postal address is sent (no documents, no gov-ID fields). A region
(`administrative_area`) is required by the validator; when it is omitted the
pre-check is skipped and `{ ok: true, skipped: true }` is returned (the
final submit still validates).


## POST /v1/phone-numbers/kyc/validate-address

**Pre-validate KYC address**

Optional early check for the address step of a Tier 4 (end-user identity)
registration: validates a postal address for deliverability BEFORE the full
KYC submit, so it can be corrected before any documents are uploaded. The
full submit (POST /v1/phone-numbers/kyc) re-validates the address,
so this call is purely a fast feedback path and skipping it is safe. Only
the postal address is sent (no documents, no gov-ID fields). A region
(`administrative_area`) is required by the validator; when it is omitted the
pre-check is skipped and `{ ok: true, skipped: true }` is returned (the
final submit still validates).


### Request Body

- **country** (required) `string`: ISO 3166-1 alpha-2 country code.
- **street_address** (required) `string`: No description
- **extended_address** `string`: Address complement: apartment, suite, unit, or the quadra/lote used in some countries. Optional. Does not substitute for a building number on street_address.
- **locality** (required) `string`: City / town.
- **administrative_area** `string`: State / province / region. When omitted, the pre-check is skipped (the final submit still validates).
- **postal_code** (required) `string`: No description

### Responses

#### 200: Address is deliverable, or the pre-check was skipped (no region supplied).

**Response Body:**

- **ok** `boolean`: No description (example: true)
- **skipped** `boolean`: true when no `administrative_area` was supplied, so no pre-check ran.

#### 400: The country isn't offered, or the address could not be verified. When the
provider returned usable corrections, `details.addressSuggestions` carries
them per field for a one-click "apply suggestion" card. (Flat error
envelope: `error` is the human message; `code`/`param`/`details` are
top-level siblings.)


**Response Body:**

- **error** `string`: Human-readable message.
- **type** `string`: No description
- **code** `string`: No description (example: "INVALID_FIELD_VALUE")
- **param** `string`: No description (example: "address")
- **details** `object`: 
  - **addressSuggestions** `array[object]`: 
    - **field** `string`: No description (example: "administrative_area")
    - **label** `string`: No description (example: "State / Province")
    - **value** `string`: No description (example: "Dublin")

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
