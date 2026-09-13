# Resubmit a declined number API Reference

Submit corrected values/documents for the declined requirement(s). We
PATCH them onto the SAME requirement group and re-submit it for approval;
the number goes `regulatory_declined` → `pending_regulatory`. No new
number and no new billing. Body shape matches the KYC submit (values /
documents / address). Send only the corrected fields.


## GET /v1/phone-numbers/{id}/remediate

**Get declined requirements**

For a number in `regulatory_declined`, returns ONLY the requirements the
reviewer flagged declined, as a form spec (same shape as the KYC form GET).
The customer fixes only those, because Telnyx supports correcting a declined
requirement group and re-submitting it (no new number/group). Falls back
to the full spec if the provider exposes no per-requirement flags.


### Parameters

- **id** (required) in path: Phone number record ID.

### Responses

#### 200: The declined requirements to fix.

**Response Body:**

- **country** `string`: No description
- **numberType** `string`: No description
- **declineReason** `string,null`: No description
- **fields** `array[object]`: Same field shape as GET /v1/phone-numbers/kyc.
  Type: `object`

#### 400: Number is not awaiting remediation

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

---

## POST /v1/phone-numbers/{id}/remediate

**Resubmit a declined number**

Submit corrected values/documents for the declined requirement(s). We
PATCH them onto the SAME requirement group and re-submit it for approval;
the number goes `regulatory_declined` → `pending_regulatory`. No new
number and no new billing. Body shape matches the KYC submit (values /
documents / address). Send only the corrected fields.


### Parameters

- **id** (required) in path: No description

### Request Body

- **values** `object`: No description
- **documents** `array`: No description
- **address** `object`: Same shape as the KYC submit address.

### Responses

#### 200: Re-submitted for approval.

**Response Body:**

- **status** `string`: No description (example: "resubmitted")
- **phoneNumber** `object`: 
  - **id** `string`: No description
  - **status** `string`: No description

#### 400: Number is not awaiting remediation / nothing to remediate

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

---

---
