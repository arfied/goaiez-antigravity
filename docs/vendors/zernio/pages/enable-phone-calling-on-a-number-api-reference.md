# Enable phone calling on a number API Reference

Turns on regular phone (PSTN) calling for one of your numbers and
configures how inbound calls are handled. Inbound calls route to
`forwardTo`: your own AI voice agent (Vapi/Retell), a phone, or a SIP
endpoint. Optional extras: voicemail, business-hours windows, an IVR
menu, a caller blocklist, recording, and transcription. A number can
also be voice-enabled with no forward (outbound-only).

Idempotent, and doubles as the settings update: only fields present in
the body are written. Omitting `forwardTo` preserves the current
destination; sending an empty string clears it.


## POST /v1/phone-numbers/{id}/voice

**Enable phone calling on a number**

Turns on regular phone (PSTN) calling for one of your numbers and
configures how inbound calls are handled. Inbound calls route to
`forwardTo`: your own AI voice agent (Vapi/Retell), a phone, or a SIP
endpoint. Optional extras: voicemail, business-hours windows, an IVR
menu, a caller blocklist, recording, and transcription. A number can
also be voice-enabled with no forward (outbound-only).

Idempotent, and doubles as the settings update: only fields present in
the body are written. Omitting `forwardTo` preserves the current
destination; sending an empty string clears it.


### Parameters

- **id** (required) in path: Phone number record ID (from GET /v1/phone-numbers).

### Request Body

- **forwardTo** `string`: tel:+E164, sip:..., or wss://... destination for inbound calls. Empty string clears the forward (outbound-only); omitted preserves the current one.
- **recordingEnabled** `boolean`: No description
- **transcriptionEnabled** `boolean`: No description
- **transcriptionLanguage** `string`: No description - one of: auto, en, es
- **voicemailEnabled** `boolean`: Voicemail is taken when there's no live destination. Default on.
- **voicemailGreeting** `string`: Custom spoken greeting; empty string restores the default.
- **businessHoursEnabled** `boolean`: Outside the windows, inbound skips the forward and goes to voicemail. Off = 24/7.
- **businessHoursTimezone** `string`: IANA timezone the windows are evaluated in.
- **businessHours** `array`: No description
- **blockedCallers** `array`: E.164 numbers rejected before answer. Replaces the whole list; bare 10-digit values are normalized as US numbers.
- **forwardCallerId** `string`: Caller ID on the forwarded leg: your number (`business`) or the original caller's (`caller`). - one of: business, caller
- **ivrEnabled** `boolean`: IVR menu (supersedes the plain forward within business hours).
- **ivrPrompt** `string`: No description
- **ivrOptions** `array`: No description

### Responses

#### 200: Voice enabled; the full effective voice config is echoed back.

**Response Body:**

- **enabled** `boolean`: No description
- **phoneNumber** `string`: No description
- **pstnForwardTo** `string,null`: No description
- **recordingEnabled** `boolean`: No description
- **transcriptionEnabled** `boolean`: No description
- **transcriptionLanguage** `string`: No description - one of: auto, en, es
- **voicemailEnabled** `boolean`: No description
- **voicemailGreeting** `string,null`: No description
- **businessHoursEnabled** `boolean`: No description
- **businessHoursTimezone** `string,null`: No description
- **businessHours** `array[object]`: 
  - **day** `integer`: No description
  - **open** `string`: No description
  - **close** `string`: No description
- **blockedCallers** `array[string]`: 
- **forwardCallerId** `string`: No description - one of: business, caller
- **ivrEnabled** `boolean`: No description
- **ivrPrompt** `string,null`: No description
- **ivrOptions** `array[object]`: 
  - **digit** `string`: No description
  - **forwardTo** `string`: No description
  - **label** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

#### 409: This number is attached to a SIP trunk; detach it first (code invalid_resource_state).

#### 422: This number is hosted by your own carrier (brought via WhatsApp embedded signup), so calls can't be enabled on it.

---

## DELETE /v1/phone-numbers/{id}/voice

**Disable phone calling on a number**

Turns off PSTN calling for the number. The stored forward destination
and settings are preserved, so re-enabling restores the prior config.


### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Voice disabled.

**Response Body:**

- **enabled** `boolean`: Always false after a successful disable.
- **phoneNumber** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

---

---
