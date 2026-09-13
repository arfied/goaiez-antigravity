# Look up carrier + line type API Reference

Carrier name and line type (mobile / landline / voip / toll-free) for a
number, plus `smsReachable` (landlines can't receive SMS). Use it to
validate recipients before sending. Each lookup is billed by the
carrier-data provider, so call it explicitly (e.g. pre-validating an
opt-in list), not on every send.


## GET /v1/sms/lookup

**Look up carrier + line type**

Carrier name and line type (mobile / landline / voip / toll-free) for a
number, plus `smsReachable` (landlines can't receive SMS). Use it to
validate recipients before sending. Each lookup is billed by the
carrier-data provider, so call it explicitly (e.g. pre-validating an
opt-in list), not on every send.


### Parameters

- **number** (required) in query: Number to look up (E.164; formatting is normalized).

### Responses

#### 200: Lookup result. An unknown/invalid number returns lineType `unknown` with `smsReachable` false rather than an error.

**Response Body:**

- **phoneNumber** `string`: No description
- **carrierName** `string,null`: No description
- **lineType** `string`: No description - one of: mobile, landline, voip, toll-free, unknown
- **smsReachable** `boolean`: True when the line type can receive SMS (not a landline).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 502: Lookup provider failed

---

---
