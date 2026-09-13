# Mint a browser softphone session API Reference

Step 1 of the two-step browser softphone handshake. Mints a WebRTC
session (token + credential) the browser registers with the
`@telnyx/webrtc` SDK. Once registered, call
`POST /v1/voice/calls/web/dial` with the returned `credentialId` to
place the call. The split avoids bridging to a browser that has not
finished registering. The token lives ~1 hour (it must outlive the
whole call, not only the handshake).


## POST /v1/voice/calls/web

**Mint a browser softphone session**

Step 1 of the two-step browser softphone handshake. Mints a WebRTC
session (token + credential) the browser registers with the
`@telnyx/webrtc` SDK. Once registered, call
`POST /v1/voice/calls/web/dial` with the returned `credentialId` to
place the call. The split avoids bridging to a browser that has not
finished registering. The token lives ~1 hour (it must outlive the
whole call, not only the handshake).


### Responses

#### 200: WebRTC session minted.

**Response Body:**

- **success** `boolean`: No description
- **token** `string`: Login token for the browser WebRTC SDK.
- **credentialId** `string`: Pass to POST /v1/voice/calls/web/dial once the browser is registered.
- **expiresAt** `string` (date-time): No description
- **sdk** `string`: No description (example: "@telnyx/webrtc")

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 502: Failed to mint the WebRTC session

---

---
