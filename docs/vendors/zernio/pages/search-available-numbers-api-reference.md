# Search available numbers API Reference

Search the provider's inventory for numbers available to purchase in a
country (default US). Optional filters narrow the results. The country
must be offerable (see GET /v1/phone-numbers/countries). Voice
capability is always required; pass `sms=true` to only see numbers that
can also text (SMS support is per-number, not per-country). Numbers a
purchase would refuse are left out, and any result's `phoneNumber` can
be bought exactly by passing it to POST /v1/phone-numbers/purchase.


## GET /v1/phone-numbers/available

**Search available numbers**

Search the provider's inventory for numbers available to purchase in a
country (default US). Optional filters narrow the results. The country
must be offerable (see GET /v1/phone-numbers/countries). Voice
capability is always required; pass `sms=true` to only see numbers that
can also text (SMS support is per-number, not per-country). Numbers a
purchase would refuse are left out, and any result's `phoneNumber` can
be bought exactly by passing it to POST /v1/phone-numbers/purchase.


### Parameters

- **country** (optional) in query: No description
- **type** (optional) in query: Number type; defaults to the country's WhatsApp-safe type
- **prefix** (optional) in query: Area code
- **locality** (optional) in query: City
- **contains** (optional) in query: Pattern to match within the number
- **sms** (optional) in query: true narrows the pool to SMS-capable numbers. Each result still carries its full `features` list for per-number capability badging.
- **limit** (optional) in query: No description

### Responses

#### 200: Available numbers.

**Response Body:**

- **country** `string`: No description
- **numberType** `string`: No description
- **requireSms** `boolean`: Echo of the `sms` filter applied to this search.
- **numbers** `array[object]`: 
  - **phoneNumber** `string`: E.164. Pass it as `phoneNumber` on POST /v1/phone-numbers/purchase to buy this exact number.
  - **features** `array[string]`: Provider capability list for this number (e.g. voice, sms, mms).
  - **locality** `string`: Town or rate center the number belongs to, as the carrier names it (e.g. WACO).
  - **bestEffort** `boolean`: true when the carrier added this number because too few matched your filters, so it may be outside the requested prefix or locality.

#### 400: Country not available

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
