# Disable SMS on a number API Reference

Turns off SMS for the number (deactivates its SMS account). The carrier
registration is untouched, so re-enabling later reactivates it,
with no re-registration.


## POST /v1/phone-numbers/{id}/sms

**Enable SMS on a number**

Turns on SMS for one of your numbers. The number's real carrier
capability is checked first: some number types can't do SMS at all
(`smsCapable: false`), and a number still provisioning at the carrier
returns `notReady: true` (try again once provisioning finishes).

US numbers additionally need a carrier registration before messages
deliver; the response tells you which path applies:
- `alreadyRegistered: true`: a prior registration still covers this
  number; SMS was reactivated.
- `reusable` set: you have an approved registration this number can
  join in one click via
  `POST /v1/phone-numbers/{id}/sms/reuse-registration`
  (no new brand/campaign, no extra carrier fee).
- `needsRegistration: true` and no `reusable`: start one via
  `POST /v1/sms/registrations`.

Idempotent: re-running re-attempts any carrier-side setup that failed.


### Parameters

- **id** (required) in path: Phone number record ID (from GET /v1/phone-numbers).

### Responses

#### 200: Result. Check `enabled`: a 200 with `enabled: false` means the number can't do SMS (`smsCapable: false`) or isn't ready yet (`notReady: true`).

**Response Body:**

- **enabled** `boolean`: No description
- **id** `string`: The SMS account ID (present when enabled).
- **phoneNumber** `string`: No description
- **isActive** `boolean`: False for US numbers until their registration is approved.
- **country** `string`: No description
- **smsCapable** `boolean,null`: Null when capability can't be read yet (still provisioning).
- **mmsCapable** `boolean`: No description
- **domesticOnly** `boolean`: No description
- **notReady** `boolean`: Number is still provisioning at the carrier; retry shortly.
- **needsRegistration** `boolean`: US only; a carrier registration is required before delivery.
- **alreadyRegistered** `boolean`: A prior non-rejected registration already covers this number; no re-submit needed.
- **registrationStatus** `string,null`: No description - one of: pending, approved, rejected, 
- **reusable** `object,null`: Present when an existing approved registration can cover this number via /sms/reuse-registration.
- **message** `string`: Human-readable explanation when `enabled` is false.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

#### 422: This number is hosted by your own carrier (brought via WhatsApp embedded signup), so SMS can't be enabled on it.

---

## DELETE /v1/phone-numbers/{id}/sms

**Disable SMS on a number**

Turns off SMS for the number (deactivates its SMS account). The carrier
registration is untouched, so re-enabling later reactivates it,
with no re-registration.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: SMS disabled.

**Response Body:**

- **enabled** `boolean`: Always false after a successful disable.
- **phoneNumber** `string`: No description
- **disabled** `boolean`: False when SMS was already off. Legacy field; prefer `enabled`.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

---

---
