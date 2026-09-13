# Check portability API Reference

Pre-flight portability check: whether each number can be ported in and
whether it qualifies for FastPort, BEFORE the user commits to a port
order (LOA, invoice, service address). Read-only; creates no order and
bills nothing.


## POST /v1/phone-numbers/port-in/check

**Check portability**

Pre-flight portability check: whether each number can be ported in and
whether it qualifies for FastPort, BEFORE the user commits to a port
order (LOA, invoice, service address). Read-only; creates no order and
bills nothing.


### Request Body

- **phoneNumbers** (required) `array`: E.164 numbers to check, e.g. +13035550000.

### Responses

#### 200: Per-number portability.

**Response Body:**

- **results** `array[object]`: 
  - **phoneNumber** `string`: No description
  - **portable** `boolean`: No description
  - **fastPortable** `boolean`: Qualifies for the carrier's accelerated FastPort lane.
  - **lineType** `string,null`: Line type when known (mobile, landline, voip…). A US/CA mobile number requires the transfer PIN at submit.
  - **countryCode** `string,null`: ISO country of the number. Pass it to GET /v1/phone-numbers/port-in/requirements for international numbers.
  - **phoneNumberType** `string,null`: Carrier number-type classification (local, mobile, national, toll_free…), the numberType for the requirements endpoint.
  - **notPortableReason** `string,null`: Carrier reason when not portable; null when portable.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
