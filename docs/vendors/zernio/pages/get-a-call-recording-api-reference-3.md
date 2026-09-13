# Get a call recording API Reference

Resolves a fresh, playable MP3 URL for the call's recording.
Provider-signed recording URLs expire ~10 minutes after signing, so the
`recordingUrl` stored on the call is usually stale by the time it is
played; this endpoint re-signs on demand. Default responds `302 Found`
redirecting to the fresh URL (point an `<audio>` element or a link
straight at this endpoint); pass `as=json` to receive `{ url }` instead.


## GET /v1/whatsapp/calls/{id}/recording

**Get a call recording**

Resolves a fresh, playable MP3 URL for the call's recording.
Provider-signed recording URLs expire ~10 minutes after signing, so the
`recordingUrl` stored on the call is usually stale by the time it is
played; this endpoint re-signs on demand. Default responds `302 Found`
redirecting to the fresh URL (point an `<audio>` element or a link
straight at this endpoint); pass `as=json` to receive `{ url }` instead.


### Parameters

- **id** (required) in path: No description
- **accountId** (required) in query: No description
- **as** (optional) in query: `json` returns `{ url }` instead of a 302 redirect.

### Responses

#### 200: Recording URL (`as=json` only).

**Response Body:**

- **url** `string`: No description

#### 302: Redirect to a freshly-signed recording URL.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Call not found, or no recording is available for this call

#### 502: Recording provider lookup failed

---

---
