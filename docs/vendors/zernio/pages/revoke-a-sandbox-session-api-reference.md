# Revoke a sandbox session API Reference

Hard-deletes the session. The user loses the ability to send to that
phone via the sandbox until they re-activate it. Existing conversations
and messages already exchanged with that phone are untouched.
Revocation only blocks FUTURE sends.

Sessions belonging to other users cannot be revoked; the response is
the same 400 as "session not found" so existence isn't leaked.


## DELETE /v1/whatsapp/sandbox/sessions/{sessionId}

**Revoke a sandbox session**

Hard-deletes the session. The user loses the ability to send to that
phone via the sandbox until they re-activate it. Existing conversations
and messages already exchanged with that phone are untouched.
Revocation only blocks FUTURE sends.

Sessions belonging to other users cannot be revoked; the response is
the same 400 as "session not found" so existence isn't leaked.


### Parameters

- **sessionId** (required) in path: The session id returned by POST /v1/whatsapp/sandbox/sessions.

### Responses

#### 200: Session revoked

**Response Body:**

- **success** `boolean`: No description

#### 400: Invalid or unknown session id

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

---
