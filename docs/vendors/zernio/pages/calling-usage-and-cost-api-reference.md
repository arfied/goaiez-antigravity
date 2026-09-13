# Calling usage and cost API Reference

Aggregated calling usage across your numbers, both channels
(WhatsApp Business Calling + regular phone/PSTN): call counts,
answered counts, minutes, and cost. Use it for cost visibility or to
rebill your own customers per number.

Costs come from each call's billing snapshot, so this endpoint always
agrees with the invoice: `billableUSD` is what Zernio bills;
`metaUSD` is the WhatsApp per-minute charge Meta bills directly to
your WABA (display only, never billed by Zernio).

Optional `groupBy` returns a breakdown by UTC day, by your number, or
by channel. Defaults to the last 30 days.


## GET /v1/usage/calls

**Calling usage and cost**

Aggregated calling usage across your numbers, both channels
(WhatsApp Business Calling + regular phone/PSTN): call counts,
answered counts, minutes, and cost. Use it for cost visibility or to
rebill your own customers per number.

Costs come from each call's billing snapshot, so this endpoint always
agrees with the invoice: `billableUSD` is what Zernio bills;
`metaUSD` is the WhatsApp per-minute charge Meta bills directly to
your WABA (display only, never billed by Zernio).

Optional `groupBy` returns a breakdown by UTC day, by your number, or
by channel. Defaults to the last 30 days.


### Parameters

- **since** (optional) in query: Start of the window (inclusive). Default 30 days before `until`.
- **until** (optional) in query: End of the window (exclusive). Default now.
- **channel** (optional) in query: No description
- **number** (optional) in query: Scope to calls involving this number (typically one of YOUR numbers). E.164, leading + optional.
- **groupBy** (optional) in query: No description

### Responses

#### 200: Usage totals (+ breakdown when groupBy is set).

**Response Body:**

- **since** `string` (date-time): No description
- **until** `string` (date-time): No description
- **groupBy** `string,null`: No description - one of: day, number, channel, 
- **totals** `object`: 
  - **calls** `integer`: No description
  - **answered** `integer`: No description
  - **minutes** `number`: No description
  - **billableUSD** `number`: What Zernio bills for these calls.
  - **metaUSD** `number`: WhatsApp only: Meta's per-minute charge, billed by Meta directly to your WABA. Display only.
- **groups** `array[object]`: Present (possibly empty) when `groupBy` is set.
  - **key** `string`: The group key: a `YYYY-MM-DD` UTC day, one of your numbers, or a channel.
  - **calls** `integer`: No description
  - **answered** `integer`: No description
  - **minutes** `number`: No description
  - **billableUSD** `number`: No description
  - **metaUSD** `number`: No description

#### 400: since must be before until

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
