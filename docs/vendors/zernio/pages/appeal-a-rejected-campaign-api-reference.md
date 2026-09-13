# Appeal a rejected campaign API Reference

Appeals a rejected 10DLC campaign with the carrier registry. Only a
registration that reached campaign creation can be appealed; a
brand-level rejection should be fixed and re-verified instead. On
success the registration returns to `pending`.

Content rejections (e.g. an opt-in flow without a verifiable form link,
or unrealistic samples) should be FIXED in the same call: pass the
corrected `messageFlow` / `sample1` / `sample2` and the campaign is
updated before the appeal is filed, so the reviewer sees the new
content. The current content is on `GET /v1/sms/registrations/{id}`
(`campaignContent`).


## POST /v1/sms/registrations/{id}/appeal

**Appeal a rejected campaign**

Appeals a rejected 10DLC campaign with the carrier registry. Only a
registration that reached campaign creation can be appealed; a
brand-level rejection should be fixed and re-verified instead. On
success the registration returns to `pending`.

Content rejections (e.g. an opt-in flow without a verifiable form link,
or unrealistic samples) should be FIXED in the same call: pass the
corrected `messageFlow` / `sample1` / `sample2` and the campaign is
updated before the appeal is filed, so the reviewer sees the new
content. The current content is on `GET /v1/sms/registrations/{id}`
(`campaignContent`).


### Parameters

- **id** (required) in path: No description

### Request Body

- **appealReason** (required) `string`: Goes verbatim to the carrier reviewer. Address the decline reason directly.
- **messageFlow** `string`: Corrected opt-in flow; include a link to the opt-in page/form.
- **sample1** `string`: No description
- **sample2** `string`: No description

### Responses

#### 200: Appeal submitted; the registration is pending again.

**Response Body:**

- **status** `string`: No description - one of: pending

#### 400: Malformed `id`, or the registration has no campaign to appeal (fix the brand and re-verify instead).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Registration not found

---

---
