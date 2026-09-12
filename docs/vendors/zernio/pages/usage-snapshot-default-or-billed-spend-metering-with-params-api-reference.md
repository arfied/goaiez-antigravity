# Usage snapshot (default) or billed-spend metering (with params) API Reference

Dual-mode endpoint, selected by query params, and fully backward
compatible:

**Without metering params (the default):** the plan / quota / usage
snapshot: plan name, billing period, limits, usage counts, access
state. Identical to `GET /v1/usage-stats`. Existing integrations keep
working unchanged.

**With `range`, `granularity`, `from`, or `to`:** usage METERING:
billed spend (USD) by product family (`accounts`, `numbers`, `calls`,
`sms`, `dlc`, `xApi`, `credits`, `other`) over the window, at
`day` / `month` / `total` granularity, from the usage-based invoice
breakdown (the CHARGE view, which always reconciles with what gets billed).
Also served at `GET /v1/usage/daily`. Usage-based accounts only:
legacy Stripe accounts get `{ "supported": false, "days": [] }`.

**Attribution (metering mode):** `groupBy=profile|account` adds an
`attribution` breakdown of the window's spend per profile or account,
assembled from your own records and pro-rated against the invoice so
`sum(groups) + unattributed` equals `totals` exactly. `profileId` /
`accountId` instead project the whole payload (`days`, `totals`,
`lineItems`) onto that one group; `peaks`, `callUsage` and `tax` are
then `null` (team-level facts). Projected `days` spread the
group's period share over each day (usage is attributed per period,
not per day). Profile-scoped API keys and members only see their
profiles' groups (`attribution.restricted: true`, with `totals`
summing the visible groups). Credits, 10DLC fees and Verify are always
unattributed. `profileId` / `accountId` on their own do not select
metering mode: pair them with `range`.

For per-domain consumption *volumes* use `GET /v1/usage/calls` and
`GET /v1/usage/sms`. For the billing statement (balance, credits,
caps, payment status) use `GET /v1/billing`.


## GET /v1/usage

**Usage snapshot (default) or billed-spend metering (with params)**

Dual-mode endpoint, selected by query params, and fully backward
compatible:

**Without metering params (the default):** the plan / quota / usage
snapshot: plan name, billing period, limits, usage counts, access
state. Identical to `GET /v1/usage-stats`. Existing integrations keep
working unchanged.

**With `range`, `granularity`, `from`, or `to`:** usage METERING:
billed spend (USD) by product family (`accounts`, `numbers`, `calls`,
`sms`, `dlc`, `xApi`, `credits`, `other`) over the window, at
`day` / `month` / `total` granularity, from the usage-based invoice
breakdown (the CHARGE view, which always reconciles with what gets billed).
Also served at `GET /v1/usage/daily`. Usage-based accounts only:
legacy Stripe accounts get `{ "supported": false, "days": [] }`.

**Attribution (metering mode):** `groupBy=profile|account` adds an
`attribution` breakdown of the window's spend per profile or account,
assembled from your own records and pro-rated against the invoice so
`sum(groups) + unattributed` equals `totals` exactly. `profileId` /
`accountId` instead project the whole payload (`days`, `totals`,
`lineItems`) onto that one group; `peaks`, `callUsage` and `tax` are
then `null` (team-level facts). Projected `days` spread the
group's period share over each day (usage is attributed per period,
not per day). Profile-scoped API keys and members only see their
profiles' groups (`attribution.restricted: true`, with `totals`
summing the visible groups). Credits, 10DLC fees and Verify are always
unattributed. `profileId` / `accountId` on their own do not select
metering mode: pair them with `range`.

For per-domain consumption *volumes* use `GET /v1/usage/calls` and
`GET /v1/usage/sms`. For the billing statement (balance, credits,
caps, payment status) use `GET /v1/billing`.


### Parameters

- **reconcile** (optional) in query: Snapshot mode only. For Stripe subscription users, `true` forces a
subscription reconciliation pass even when cached plan data looks
complete.

- **range** (optional) in query: Window to report. `cycle` / `prev-cycle` resolve to the customer's
real billing-period bounds (falling back to a trailing 30 days when
no invoice exists yet); `7d`…`12mo` are trailing windows; `custom`
uses `from` / `to`.

- **from** (optional) in query: Inclusive start (UTC date). Required when `range=custom`.
- **to** (optional) in query: Inclusive end (UTC date). Required when `range=custom`. Max span 366 days.
- **granularity** (optional) in query: Bucketing of the `days` series: `day` (one row per UTC day),
`month` (one row per calendar month, dated to the 1st), or `total`
(no series, read `totals`). Does not affect `totals`.

- **groupBy** (optional) in query: Metering mode. Adds `attribution`: the window's spend split per profile or per account (keys are ids; resolve names via `GET /v1/profiles` / `GET /v1/accounts`).
- **profileId** (optional) in query: Metering mode (pair with `range`). Project the payload onto this profile's attributed share. Mutually exclusive with `accountId`, and `groupBy` (if given) must be `profile`; 404 when the profile is not in your team (or outside a scoped key's profiles).
- **accountId** (optional) in query: Metering mode (pair with `range`). Project the payload onto this account's attributed share. Mutually exclusive with `profileId`, and `groupBy` (if given) must be `account`; 404 when the account is not visible to the caller.

### Responses

#### 200: Snapshot (no metering params) or billed spend by product over the
window (with metering params).


**Response Body:**

*One of the following:*
- `UsageStats`
- `UsageMetering`

#### 400: Invalid query parameter

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
