# Get calling config for an account API Reference

Returns the local calling configuration snapshot for the connected
WhatsApp account: whether calling is enabled, the forward-to
destination URI, recording opt-in state, the phone number record id
(use as `{id}` on the read-write calling sub-resource at
/v1/phone-numbers/{id}/whatsapp/calling) and whether SIP digest
credentials are stored (the encrypted password itself is never
returned). Also carries account-level extras (billing eligibility,
current-period spend) that the number-keyed GET does not.


## GET /v1/whatsapp/calling

**Get calling config for an account**

Returns the local calling configuration snapshot for the connected
WhatsApp account: whether calling is enabled, the forward-to
destination URI, recording opt-in state, the phone number record id
(use as `{id}` on the read-write calling sub-resource at
/v1/phone-numbers/{id}/whatsapp/calling) and whether SIP digest
credentials are stored (the encrypted password itself is never
returned). Also carries account-level extras (billing eligibility,
current-period spend) that the number-keyed GET does not.


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Calling config

**Response Body:**

- **phoneNumberDocId** `string`: Phone number record ID (use on /v1/phone-numbers/{id}/whatsapp/calling)
- **phoneNumber** `string`: No description
- **callingEnabled** `boolean`: No description
- **callDeepLink** `string,null`: Public calling deep link (https://wa.me/call/<number>). Tapping it on a phone starts a WhatsApp voice call to this number. Embed it on websites, emails, or QR codes. Null while calling is disabled; not supported by WhatsApp desktop clients.
- **forwardTo** `string,null`: tel:+E164 / sip:... / wss://... destination
- **recordingEnabled** `boolean`: No description
- **sipAuthUsername** `string,null`: No description
- **sipAuthPasswordConfigured** `boolean`: True when a SIP digest password is stored. The plaintext is never returned.
- **callIconCountries** `array,null`: No description
- **callerIdMode** `string`: Caller ID the forward-leg callee sees on tel: forwards. business = this WhatsApp number; platform = a Zernio number (customer-brought number without verified caller ID; verify via /v1/phone-numbers/{id}/whatsapp/caller-id-verification). - one of: business, platform
- **callerIdVerified** `boolean`: True once the number completed caller-ID verification.
- **maxCallDurationSeconds** `integer,null`: Hard cap (seconds) on forwarded calls; null = no cap.
- **forwardCallerId** `string`: No description - one of: business, caller

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp phone number not found for this account

---

---
