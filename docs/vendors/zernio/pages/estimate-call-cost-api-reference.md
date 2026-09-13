# Estimate call cost API Reference

Pre-call cost estimate for a PSTN call: the carrier leg plus optional
recording and transcription add-ons. Same billing formula as the
post-call invoice, so the quote and the final charge can't disagree.
The per-minute figure is deliberately conservative (the real cost
comes from the settled carrier record after the call), so estimates
trend slightly over the actual invoice. Parity endpoint of
`GET /v1/whatsapp/calls/estimate`, minus the Meta line (PSTN calls
have no separate Meta bill, so `totalCostUSD` equals
`billableCostUSD`).


## GET /v1/voice/calls/estimate

**Estimate call cost**

Pre-call cost estimate for a PSTN call: the carrier leg plus optional
recording and transcription add-ons. Same billing formula as the
post-call invoice, so the quote and the final charge can't disagree.
The per-minute figure is deliberately conservative (the real cost
comes from the settled carrier record after the call), so estimates
trend slightly over the actual invoice. Parity endpoint of
`GET /v1/whatsapp/calls/estimate`, minus the Meta line (PSTN calls
have no separate Meta bill, so `totalCostUSD` equals
`billableCostUSD`).


### Parameters

- **to** (required) in query: Destination number, E.164 (leading + optional).
- **minutes** (optional) in query: No description
- **recording** (optional) in query: No description
- **transcription** (optional) in query: No description

### Responses

#### 200: Estimate

**Response Body:**

- **destinationCountry** `string,null`: No description
- **minutes** `integer`: No description
- **perMinuteUsd** `number`: Billable cost per minute for the requested options.
- **breakdown** `object`: 
  - **telnyxCostUSD** `number`: No description
  - **recordingCostUSD** `number`: No description
  - **transcriptionCostUSD** `number`: No description
  - **billableCostUSD** `number`: What Zernio bills for the call.
  - **totalCostUSD** `number`: Equals billableCostUSD (no separate Meta bill on PSTN); kept for shape parity with the WhatsApp estimate.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
