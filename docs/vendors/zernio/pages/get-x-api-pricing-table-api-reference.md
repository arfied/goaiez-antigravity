# Get X API pricing table API Reference

Returns Zernio's canonical X API pricing table. Each X action has its
own billing product and its own rate, and Zernio passes X API costs through
at exact rates with zero markup.

The response is identical for every authenticated user (pricing is universal),
so it is safe to cache on the client for the duration of a billing period.

To compute your own per-operation spend, pair this endpoint with
`GET /v1/usage-stats`, which returns `usage.xApiCallsByOperation`
keyed by the same `operation` field you get here.


## GET /v1/billing/x-pricing

**Get X API pricing table**

Returns Zernio's canonical X API pricing table. Each X action has its
own billing product and its own rate, and Zernio passes X API costs through
at exact rates with zero markup.

The response is identical for every authenticated user (pricing is universal),
so it is safe to cache on the client for the duration of a billing period.

To compute your own per-operation spend, pair this endpoint with
`GET /v1/usage-stats`, which returns `usage.xApiCallsByOperation`
keyed by the same `operation` field you get here.


### Responses

#### 200: X pricing table

**Response Body:**

- **currency** `string`: No description (example: "USD")
- **markup** `string`: Always 0%, because Zernio does not mark up X API rates. (example: "0%")
- **source** `string` (uri): No description (example: "https://developer.x.com/#pricing")
- **lastVerified** `string` (date): Date the prices were last verified against X's published rates.
- **tiers** `array[object]`: Rollup of operations grouped by their per-call price.
  - **tier** `string`: Tier key derived from price (e.g. `x_api_005` for $0.005,
`x_api_200` for $0.200). The first three keys map to the
legacy `xApiCalls` aggregate; new tiers (e.g. `x_api_200`
for the URL tier added April 2026) are surfaced here but
not in the legacy shape.
 (example: "x_api_005")
  - **pricePerCallUsd** `number`: No description (example: 0.005)
  - **operationCount** `integer`: No description (example: 13)
- **operations** `array[XApiOperation]`: Flat list of every X operation Zernio can perform, with its rate.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---
