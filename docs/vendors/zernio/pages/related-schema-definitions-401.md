# Related Schema Definitions

## BillingSnapshot

Account billing state: plan, cycle, balance, spend caps, and payment /
access status. Returned by `GET /v1/billing`.


### Properties

- **billingSystem** `string`: No description - one of: metronome, stripe, shopify
- **plan** `object`: 
  - **name** `string`: 
  - **isUsageBased** `boolean`: 
  - **isPaid** `boolean`: True when the key belongs to an account with an active paid billing relationship (Stripe subscription, usage-based billing, or Shopify-managed billing).
- **shopifyShopDomain** `string,null`: myshopify.com domain owning the subscription; present only when billingSystem is shopify.
- **period** `object`: Current billing cycle. `start`/`end` are resolved for usage-based accounts only.
  - **start** `string,null`: 
  - **end** `string,null`: 
  - **anchorDay** `integer`: Day-of-month the cycle resets.
- **balance** `object,null`: Accrued spend + remaining credits this cycle. `null` for fixed-subscription (Stripe) plans.
- **caps** `object`: 
  - **xSpendUsedCents** `integer`: 
  - **xSpendLimitCents** `integer,null`: Monthly X-API spend cap; null = unlimited.
- **status** `object`: 
  - **hasAccess** `boolean`: 
  - **suspended** `boolean`: 
  - **suspendedAt** `string,null`: 
  - **suspensionReason** `string,null`: 
  - **openInvoiceUrl** `string,null`: Hosted invoice URL for dunning (Stripe).
  - **declineReason** `string,null`: 
  - **autoUpgradeEnabled** `boolean`: 
- **legacy** `object`: Deprecated plan entitlements (Stripe only); absent for usage-based accounts.
  - **limits** `object`:

---
