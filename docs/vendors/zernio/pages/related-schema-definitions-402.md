# Related Schema Definitions

## UsageStats

Plan and usage stats. The response shape depends on `billingSystem`:
  * Stripe users (default): per-period counters like `usage.uploads` and
    `usage.profiles` are returned, scoped by the plan's `limits`.
  * Usage-based billing users: `limits` are unlimited (-1). The
    `usage` block carries connected-account and per-X-operation counts,
    and the `spend` block carries current-period costs plus the X cap.


### Properties

- **billingSystem** `string`: Which billing system the account is on. Shape of `usage`/`spend` differs. - one of: stripe, metronome
- **planName** `string`: No description
- **billingPeriod** `string`: No description - one of: monthly, yearly
- **signupDate** `string`: No description
- **billingAnchorDay** `integer`: Day of month (1-31) when the billing cycle resets
- **hasAccess** `boolean`: True if the account is in good standing. False for past-due/unpaid/paused subscriptions.
- **customerId** `string,null`: Stripe customer ID, when present.
- **isInvitedUser** `boolean`: True if this is a team member; limits/usage reflect the account owner.
- **autoUpgradeEnabled** `boolean`: Stripe-only. Always false for accounts on usage-based billing.
- **limits** `object`: Plan limits. For accounts on usage-based billing both fields are `-1` (unlimited).
  - **uploads** `integer`: 
  - **profiles** `integer`: 
- **usage** `object`: Per-period usage counts. Fields present depend on `billingSystem`:
Stripe returns `uploads` / `profiles` / `lastReset`;
usage-based billing returns `connectedAccounts` / `xApiCalls` / `xApiCallsByOperation`.

  - **uploads** `integer`: Stripe users only. Uploads consumed in the current period.
  - **profiles** `integer`: Stripe users only. Profiles currently owned.
  - **lastReset** `string`: Stripe users only.
  - **connectedAccounts** `integer`: Usage-based billing only. Accounts currently connected across the team.
  - **xApiCalls** `object`: **Deprecated.** Legacy 3-tier aggregate. Operations outside the
three historical prices ($0.005/$0.010/$0.015), notably the
$0.200 "Posts with URL" tier added April 2026, are silently
excluded from this shape. Use `xApiCallsByOperation` instead;
it captures every tier and is the source of truth for
per-operation call counts.

  - **xApiCallsByOperation** `object`: Usage-based billing only. Per-operation X API call counts keyed by
operation (e.g. `posts_read`, `content_create`,
`content_create_with_url`). Resolve each key to price and metadata
via `GET /v1/billing/x-pricing`. This is the canonical source: it
covers every price tier including the $0.200 URL tier that
`xApiCalls` excludes.

- **spend** `object`: Usage-based billing only. Current-period spend summary.
  - **currentPeriodCents** `integer`: Total current-period spend in cents (all products combined).
  - **creditsRemainingCents** `integer`: Free-tier credit remaining in cents. Applied before any charge.
  - **xSpendCents** `integer`: Current-period X API spend in cents, summed from
`xApiCallsByOperation` × per-operation prices. Tier-agnostic
(covers every price including the $0.200 URL tier). Rounded
up for conservative enforcement against `xSpendLimitCents`.

  - **xSpendLimitCents** `integer,null`: Monthly X spend cap set by the account owner, or null if no cap.
When current X spend hits this cap, analytics and inbox sync are
auto-paused for X accounts. Publishing is never blocked by this cap.


## UsageMetering

Billed spend by product family over a window, from the usage-based invoice
breakdown (the CHARGE view). Returned by `GET /v1/usage`.


### Properties

- **supported** `boolean`: False for legacy Stripe accounts (no usage-based invoice to split); `days` and `totals` are then empty/zero.
- **granularity** `string`: No description - one of: day, month, total
- **days** `array`: One row per bucket. Empty when `granularity=total`. `date` is a UTC date (month buckets use the 1st).
- **totals** `object`: Sum of each product over the whole window (USD), plus `total`. Unaffected by `granularity`.
  - **accounts** `number`: 
  - **numbers** `number`: 
  - **calls** `number`: 
  - **sms** `number`: 
  - **dlc** `number`: 
  - **xApi** `number`: 
  - **credits** `number`: 
  - **other** `number`: 
  - **total** `number`: 
- **lineItems** `array`: Per-invoice-line-item rows (largest spend first) for a detailed breakdown.
- **peaks** `object,null`: Peak counts over the window (usage-based COUNT metrics + live active-number count). Null when `profileId` / `accountId` is set.
- **callUsage** `object,null`: Billable call volumes over the window. Null when `profileId` / `accountId` is set.
- **period** `object`: 
  - **start** `string`: 
  - **end** `string`: 
  - **source** `string`: `cycle` = a real billing period resolved; `window` = trailing/custom window (or cycle fallback). - one of: cycle, window
- **tax** `object,null`: Estimated tax on the window's net `totals.total`, computed with
Stripe Tax against the billing address (the same engine the real
invoice uses; invoices apply exclusive tax, so the card is charged
total + tax). Null when the account has no billing address on
file, the total is zero or negative, or the estimate failed.

- **attribution** `object`: Present with `groupBy`. The window's spend split per profile or account; `sum(groups) + unattributed` equals `totals` per product.
  - **groupBy** `string`:  - one of: profile, account
  - **groups** `array`: 
  - **unattributed**: Spend no profile/account can claim: credits, 10DLC fees, Verify, and usage whose record no longer resolves to an account. Zero for a restricted principal.
  - **totals**: The window totals; for a restricted principal, the sum of the visible groups.
  - **restricted** `boolean`: True when the caller (profile-scoped API key or member) cannot see every profile: `groups` are filtered, `totals` sum them, `unattributed` is zero, and the top-level `days` / `totals` / `lineItems` are projected onto the visible groups with `peaks`, `callUsage` and `tax` null.
- **scope**: Present with `profileId` / `accountId`: echoes the group the payload was projected onto.

---
