# Related Schema Definitions

## XApiPricing

Canonical X API pricing table. Zernio passes X API costs through
at exact rates with zero markup, so every call you make has a known per-unit
price. Use this payload alongside `/v1/usage-stats` (which returns
per-operation call counts via `xApiCallsByOperation`) to compute exact
cost attribution by X action.


### Properties

- **currency** `string`: No description
- **markup** `string`: Always 0%, because Zernio does not mark up X API rates.
- **source** `string`: No description
- **lastVerified** `string`: Date the prices were last verified against X's published rates.
- **tiers** `array`: Rollup of operations grouped by their per-call price.
- **operations** `array`: Flat list of every X operation Zernio can perform, with its rate.

## XApiOperation

A single X API operation with its per-call price and the Zernio platform methods that trigger it.

### Properties

- **operation** `string`: Internal operation key. Matches keys in `xApiCallsByOperation`.
- **eventType** `string`: Metering `event_type` emitted when this operation runs.
- **displayName** `string`: Human-readable label shown on invoices.
- **pricePerCallUsd** `number`: No description
- **pricePerCallCents** `number`: Per-call price in cents. Fractional values are intentional.
- **tier** `string`: Tier key derived from `pricePerCallUsd` (e.g. `x_api_005` for
$0.005, `x_api_200` for $0.200). Useful for grouping operations
by price in dashboards.

- **triggeredBy** `array`: Zernio platform methods that emit this operation, with their metering rule.

---
