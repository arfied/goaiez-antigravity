# Disable calling on a number API Reference

Disable calling. Sends calling.status=DISABLED to Meta (best-effort)
and flips the local `callingEnabled` flag off. forwardTo and SIP
creds are preserved so a re-enable does not lose the destination.


## GET /v1/phone-numbers/{id}/whatsapp/calling

**Get calling config for a number**

The WhatsApp Business Calling configuration of this number, keyed the
same way as the POST/PATCH/DELETE below (full read-write on one
sub-resource). Encrypted secrets are never returned; only a boolean
saying whether a SIP password is stored. The account-scoped read
(`GET /v1/whatsapp/calling?accountId=`) remains for callers that only
know the account id, and additionally carries account-level
extras (billing eligibility, current-period spend).


### Parameters

- **id** (required) in path: Phone number record ID (from GET /v1/phone-numbers).

### Responses

#### 200: Calling config

**Response Body:**

- **phoneNumber** `string`: No description
- **callingEnabled** `boolean`: No description
- **callDeepLink** `string,null`: Public calling deep link (https://wa.me/call/<number>). Null while calling is disabled.
- **forwardTo** `string,null`: tel:+E164 / sip:... / wss://... destination
- **recordingEnabled** `boolean`: No description
- **sipAuthUsername** `string,null`: No description
- **sipAuthPasswordConfigured** `boolean`: True when a SIP digest password is stored. The plaintext is never returned.
- **callIconCountries** `array,null`: No description
- **outboundDisabled** `boolean`: True when the number's country blocks business-initiated (outbound) WhatsApp calling; inbound still works.
- **callerIdMode** `string`: Caller ID the forward-leg callee sees on tel: forwards. business = this WhatsApp number; platform = a Zernio number (used when the number was brought by the customer and its caller ID is not verified for PSTN origination). - one of: business, platform
- **callerIdVerified** `boolean`: True once the number completed caller-ID verification, making tel: forwards display the business number itself.
- **maxCallDurationSeconds** `integer,null`: Hard cap (seconds) on forwarded calls; null = no cap.
- **forwardCallerId** `string`: No description - one of: business, caller

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Number not found

---

## POST /v1/phone-numbers/{id}/whatsapp/calling

**Enable calling on a number**

Enable WhatsApp Business Calling on a connected number. Configures
Meta calling.status=ENABLED with our Telnyx SIP endpoint, fetches and
stores the Meta-issued SIP password (encrypted), and snapshots the
customer's forward-to destination.


### Parameters

- **id** (required) in path: Phone number record ID (from GET /v1/phone-numbers).

### Request Body

- **accountId** (required) `string`: No description
- **forwardTo** (required) `string`: tel:+E164 / sip:... / wss://... destination
- **sipAuthUsername** `string`: No description
- **sipAuthPassword** `string`: Stored encrypted, never returned by any endpoint.
- **recordingEnabled** `boolean`: No description
- **callIconCountries** `array`: No description
- **maxCallDurationSeconds** `integer`: Hard cap (seconds) on a forwarded call; the carrier hangs up both legs when it fires. Safety valve against dead-air billing when a destination hangs up but the signal is lost.
- **forwardCallerId** `string`: Caller ID presented to the forward destination. caller = the WhatsApp user's number (sip: destinations only; ignored on tel: forwards). Fixes AI-agent trunks that reject seeing the business number call itself. - one of: business, caller

### Responses

#### 200: Calling enabled

**Response Body:**

- **success** `boolean`: No description
- **callingEnabled** `boolean`: No description
- **sipHostname** `string`: No description
- **forwardTo** `string`: No description
- **callerIdMode** `string`: Caller ID the forward-leg callee sees on tel: forwards. business = this WhatsApp number; platform = a Zernio number (customer-brought number without verified caller ID). - one of: business, platform

#### 400: Invalid request (including forwardTo set to the number itself)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Phone number not found

#### 409: This number is attached to a SIP trunk; detach it first (code invalid_resource_state).

#### 422: Not eligible to enable calling: not on usage-based billing, or the number's messaging limit is below Meta's ~2,000-daily-recipient threshold (TIER_250). Warm the number up to raise the limit.

---

## PATCH /v1/phone-numbers/{id}/whatsapp/calling

**Update calling config**

Update fields on an already-enabled number. Only fields present in
the body are written; `undefined` leaves the stored value alone,
explicit `null` clears a nullable field. No Meta side effect, this
only changes local routing state consumed by the Telnyx webhook
handler.


### Parameters

- **id** (required) in path: No description

### Request Body

- **accountId** (required) `string`: No description
- **forwardTo** `string`: No description
- **sipAuthUsername** `string,null`: No description
- **sipAuthPassword** `string,null`: No description
- **recordingEnabled** `boolean`: No description
- **callIconCountries** `array,null`: No description
- **maxCallDurationSeconds** `integer,null`: Hard cap (seconds) on forwarded calls; null clears the cap.
- **forwardCallerId** `string`: caller = present the WhatsApp user's number to the forward destination (sip: only). - one of: business, caller

### Responses

#### 200: Updated

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Phone number not found

#### 422: Calling must be enabled before settings can be updated

---

## DELETE /v1/phone-numbers/{id}/whatsapp/calling

**Disable calling on a number**

Disable calling. Sends calling.status=DISABLED to Meta (best-effort)
and flips the local `callingEnabled` flag off. forwardTo and SIP
creds are preserved so a re-enable does not lose the destination.


### Parameters

- **id** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Disabled

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Phone number not found

---

---
