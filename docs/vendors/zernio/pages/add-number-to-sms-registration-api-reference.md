# Add number to SMS registration API Reference

Attaches this number to your existing approved 10DLC campaign instead
of running a fresh registration: the number inherits the campaign's
approval (no new brand or campaign, no extra carrier fee). Enable SMS
on the number first (`POST /v1/phone-numbers/{id}/sms`; its response
tells you whether a reusable registration exists).


## POST /v1/phone-numbers/{id}/sms/reuse-registration

**Add number to SMS registration**

Attaches this number to your existing approved 10DLC campaign instead
of running a fresh registration: the number inherits the campaign's
approval (no new brand or campaign, no extra carrier fee). Enable SMS
on the number first (`POST /v1/phone-numbers/{id}/sms`; its response
tells you whether a reusable registration exists).


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Number added to the existing registration.

**Response Body:**

- **registrationId** `string`: No description
- **status** `string`: requested/changes_requested = pre-submission review states; customers see them as pending / needs changes. - one of: pending, approved, rejected, requested, changes_requested, deactivated

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

#### 409: No existing SMS registration to reuse for this number

---

---
