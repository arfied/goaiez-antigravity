# Upload opt-in form proof API Reference

Hosts a screenshot (or PDF) of your SMS opt-in form and returns its
public URL. Include that URL in the campaign's `messageFlow` (the
opt-in workflow text). The carrier registry has no attachment field,
so reviewers verify consent by opening links in that answer. Works
before a registration exists (use it when registering) and for
appeals. `/v1/sms/registrations/{id}/opt-in-proof` is an alias.


## POST /v1/sms/opt-in-proof

**Upload opt-in form proof**

Hosts a screenshot (or PDF) of your SMS opt-in form and returns its
public URL. Include that URL in the campaign's `messageFlow` (the
opt-in workflow text). The carrier registry has no attachment field,
so reviewers verify consent by opening links in that answer. Works
before a registration exists (use it when registering) and for
appeals. `/v1/sms/registrations/{id}/opt-in-proof` is an alias.


### Request Body


### Responses

#### 200: File hosted.

**Response Body:**

- **url** `string`: Public URL to reference in the opt-in flow text.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 422: Unsupported file type or file too large

---

---
