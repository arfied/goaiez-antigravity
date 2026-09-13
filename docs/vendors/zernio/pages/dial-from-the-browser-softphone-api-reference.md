# Dial from the browser softphone API Reference

Step 2 of the browser softphone handshake: places an outbound call
whose answered leg is bridged to the browser registered with the
credential from `POST /v1/voice/calls/web`. The call runs through the
normal outbound lane, so it is logged as outbound (from = your number,
to = target) and recorded per the number's settings.


## POST /v1/voice/calls/web/dial

**Dial from the browser softphone**

Step 2 of the browser softphone handshake: places an outbound call
whose answered leg is bridged to the browser registered with the
credential from `POST /v1/voice/calls/web`. The call runs through the
normal outbound lane, so it is logged as outbound (from = your number,
to = target) and recorded per the number's settings.


### Request Body

- **to** (required) `string`: The number to call, E.164 with leading +.
- **credentialId** (required) `string`: The WebRTC credential id returned by POST /v1/voice/calls/web (the registered browser).
- **fromNumber** `string`: Which of your voice-enabled numbers to call from (optional when you have one).
- **recordOverride** `boolean`: No description

### Responses

#### 200: Call originated; answer/bridge continue asynchronously.

**Response Body:**

- **success** `boolean`: No description
- **callId** `string`: No description
- **telnyxCallControlId** `string`: No description
- **status** `string`: No description - one of: dialing
- **direction** `string`: No description - one of: outbound
- **from** `string`: No description
- **to** `string`: No description
- **recordingEnabled** `boolean`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 422: Invalid or unknown WebRTC credential, or no voice-enabled number matches `fromNumber`

#### 429: Outbound call limit reached (per rolling hour).

#### 502: Carrier-side originate failed

---

---
