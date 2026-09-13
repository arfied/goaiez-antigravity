# SMS usage (volumes) API Reference

Aggregated SMS/MMS volumes across your numbers: sent, received, and
total message counts, with an optional breakdown by UTC day or by
number. Defaults to the last 30 days.

Volumes only, deliberately: SMS cost is carrier-rated asynchronously
and billed to your invoice, so per-message cost is not available here.
Calling usage (GET /v1/usage/calls) does include billable cost.


## GET /v1/usage/sms

**SMS usage (volumes)**

Aggregated SMS/MMS volumes across your numbers: sent, received, and
total message counts, with an optional breakdown by UTC day or by
number. Defaults to the last 30 days.

Volumes only, deliberately: SMS cost is carrier-rated asynchronously
and billed to your invoice, so per-message cost is not available here.
Calling usage (GET /v1/usage/calls) does include billable cost.


### Parameters

- **since** (optional) in query: Start of the window (inclusive). Default 30 days before `until`.
- **until** (optional) in query: End of the window (exclusive). Default now.
- **number** (optional) in query: Scope to one of YOUR SMS-enabled numbers (E.164, leading + optional).
- **groupBy** (optional) in query: No description

### Responses

#### 200: Volume totals (+ breakdown when groupBy is set).

**Response Body:**

- **since** `string` (date-time): No description
- **until** `string` (date-time): No description
- **groupBy** `string,null`: No description - one of: day, number, 
- **totals** `object`: 
  - **sent** `integer`: No description
  - **received** `integer`: No description
  - **total** `integer`: No description
- **groups** `array[object]`: Present (possibly empty) when `groupBy` is set.
  - **key** `string`: A `YYYY-MM-DD` UTC day or one of your numbers.
  - **sent** `integer`: No description
  - **received** `integer`: No description
  - **total** `integer`: No description

#### 400: since must be before until

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: `number` doesn't match any of your SMS-enabled numbers

---

---
