# Account billing snapshot (plan, cycle, balance, caps, status) API Reference

The billing "wallet/statement" view: current plan, billing cycle,
accrued balance + remaining credits this period, spend caps, and
payment / access status. This is the billing half of the legacy
`/v1/usage-stats` snapshot. The per-product consumption half is metering
and lives on `GET /v1/usage`.

Accounts on usage-based billing get a populated `balance`; legacy Stripe
accounts get `balance: null` plus a deprecated `legacy.limits` block and,
when payment-blocked, `status.openInvoiceUrl` / `status.declineReason`.


## GET /v1/billing

**Account billing snapshot (plan, cycle, balance, caps, status)**

The billing "wallet/statement" view: current plan, billing cycle,
accrued balance + remaining credits this period, spend caps, and
payment / access status. This is the billing half of the legacy
`/v1/usage-stats` snapshot. The per-product consumption half is metering
and lives on `GET /v1/usage`.

Accounts on usage-based billing get a populated `balance`; legacy Stripe
accounts get `balance: null` plus a deprecated `legacy.limits` block and,
when payment-blocked, `status.openInvoiceUrl` / `status.declineReason`.


### Responses

#### 200: Billing snapshot

**Response Body:**

- **billingSystem** `string`: No description - one of: metronome, stripe, shopify
- **plan** `object`: 
  - **name** `string`: No description
  - **isUsageBased** `boolean`: No description
  - **isPaid** `boolean`: True when the key belongs to an account with an active paid billing relationship (Stripe subscription, usage-based billing, or Shopify-managed billing).
- **shopifyShopDomain** `string,null`: myshopify.com domain owning the subscription; present only when billingSystem is shopify.
- **period** `object`: Current billing cycle. `start`/`end` are resolved for usage-based accounts only.
  - **start** `string,null` (date-time): No description
  - **end** `string,null` (date-time): No description
  - **anchorDay** `integer`: Day-of-month the cycle resets.
- **balance** `object,null`: Accrued spend + remaining credits this cycle. `null` for fixed-subscription (Stripe) plans.
- **caps** `object`: 
  - **xSpendUsedCents** `integer`: No description
  - **xSpendLimitCents** `integer,null`: Monthly X-API spend cap; null = unlimited.
- **status** `object`: 
  - **hasAccess** `boolean`: No description
  - **suspended** `boolean`: No description
  - **suspendedAt** `string,null` (date-time): No description
  - **suspensionReason** `string,null`: No description
  - **openInvoiceUrl** `string,null`: Hosted invoice URL for dunning (Stripe).
  - **declineReason** `string,null`: No description
  - **autoUpgradeEnabled** `boolean`: No description
- **legacy** `object`: Deprecated plan entitlements (Stripe only); absent for usage-based accounts.
  - **limits** `object`: 
    - **uploads** `integer`: No description
    - **profiles** `integer`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
