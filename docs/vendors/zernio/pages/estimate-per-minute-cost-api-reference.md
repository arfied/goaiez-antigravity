# Estimate per-minute cost API Reference

Returns a zero-markup estimated cost for an outbound call to the
given destination, broken down by Meta + Telnyx + recording line
items. Costs are pass-through, no margin applied.


## GET /v1/whatsapp/calls/estimate

**Estimate per-minute cost**

Returns a zero-markup estimated cost for an outbound call to the
given destination, broken down by Meta + Telnyx + recording line
items. Costs are pass-through, no margin applied.


### Parameters

- **accountId** (required) in query: No description
- **to** (required) in query: No description
- **minutes** (optional) in query: No description
- **recording** (optional) in query: No description

### Responses

#### 200: Estimate

**Response Body:**

- **destinationCountry** `string,null`: No description
- **perMinuteUsd** `number`: No description
- **breakdown** `object`: 
  - **metaMinutes** `integer`: No description
  - **metaCostUSD** `number`: Estimated Meta per-minute charge, billed by Meta directly to your WABA. Display only; not billed by Zernio.
  - **telnyxCostUSD** `number`: No description
  - **recordingCostUSD** `number`: No description
  - **billableCostUSD** `number`: Estimated amount Zernio bills you = Telnyx leg + recording (excludes Meta).
  - **totalCostUSD** `number`: Estimated full cost incl. the Meta portion you pay directly. Display only.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
