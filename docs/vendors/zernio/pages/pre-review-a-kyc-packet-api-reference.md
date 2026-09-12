# Pre-review a KYC packet API Reference

Advisory dry-run of a regulated-KYC packet before submitting: reviews
the exact documents the regulator will see (referenced by the ids from
POST /v1/phone-numbers/kyc/upload-document) against the declared values
and address, and returns plain-language advisories for likely decline
reasons (wrong document type, mismatched address, one-sided ID scans).
Non-blocking: advisories are warnings, submitting anyway is always
allowed, and any review failure degrades to an empty list.


## POST /v1/phone-numbers/kyc/review-packet

**Pre-review a KYC packet**

Advisory dry-run of a regulated-KYC packet before submitting: reviews
the exact documents the regulator will see (referenced by the ids from
POST /v1/phone-numbers/kyc/upload-document) against the declared values
and address, and returns plain-language advisories for likely decline
reasons (wrong document type, mismatched address, one-sided ID scans).
Non-blocking: advisories are warnings, submitting anyway is always
allowed, and any review failure degrades to an empty list.


### Request Body

- **country** (required) `string`: No description
- **numberType** (required) `string`: No description
- **values** `object`: requirementId to declared textual value.
- **address** `object`: Declared address (street_address, locality, ...), so a mismatched proof-of-address can be flagged.
- **docs** (required) `array`: No description

### Responses

#### 200: Advisories (empty when the packet looks fine or the review was unavailable).

**Response Body:**

- **advisories** `array[object]`: 
  - **requirementId** `string`: No description
  - **concern** `string`: One short plain-language concern about that requirement's document.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
