# Upload opt-in form proof for an appeal API Reference

Hosts a screenshot (or PDF) of your SMS opt-in form and returns its
public URL. Carrier reviewers reject campaigns whose consent can't be
verified and ask for a "link/screenshot of the opt-in form". The
registry has no attachment field, so include the returned URL inside
the `messageFlow` you submit with the appeal
(`POST /v1/sms/registrations/{id}/appeal`).


## POST /v1/sms/registrations/{id}/opt-in-proof

**Upload opt-in form proof for an appeal**

Hosts a screenshot (or PDF) of your SMS opt-in form and returns its
public URL. Carrier reviewers reject campaigns whose consent can't be
verified and ask for a "link/screenshot of the opt-in form". The
registry has no attachment field, so include the returned URL inside
the `messageFlow` you submit with the appeal
(`POST /v1/sms/registrations/{id}/appeal`).


### Parameters

- **id** (required) in path: No description

### Request Body


### Responses

#### 200: File hosted.

**Response Body:**

- **url** `string`: Public URL to reference in the opt-in flow text.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Registration not found

#### 422: Unsupported file type or file too large

---

---
