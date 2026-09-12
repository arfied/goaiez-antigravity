# Billing

When your card is charged, and how daily proration and the graduated price ladder compute your invoice.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can compute your own invoice from your connected accounts: what a day of connection costs, when the card is charged, and where the $12 monthly credit lands. The rates are on the [pricing page](/pricing); this page is the arithmetic behind them. `GET /v1/usage` ([Get usage](/usage/get-usage)) returns the billed spend behind an invoice, and `GET /v1/billing` ([Get billing](/usage/get-billing)) the balance, credits and payment status.

## When your card is charged

Zernio charges your card as you accrue usage. The first time your accrued usage reaches a small fraud-protection threshold (starting at $10), Zernio charges that amount and the threshold doubles for next time. Established customers see fewer, larger charges; a brand-new paying customer sees one quick small charge that validates the card.

[Metronome](https://metronome.com) produces the invoices on top of Stripe. Each invoice is itemized: you see which accounts, phone numbers, messages, calls and X API operations you paid for.

## Daily proration

Zernio meters accounts per day. Every day it records which accounts are connected and reports one event per active account per day to the billing engine, so you pay for the days each account was connected.

The whole invoice comes from one formula, which divides by 30 rather than by the length of the month:

```
billable_units = (sum of all account-days in the month) ÷ 30
```

An account connected for 15 days contributes 15 account-days, so half a unit. Zernio meters UTC days 1 to 30 only, so the divisor and the meter agree: an account connected every day of a 31-day month counts 30 account-days, not 31, and a whole February counts 28 or 29 account-days and bills slightly under a full month.

## The graduated ladder applies to the total

Pricing is graduated ($6 / $3 / $1), so no account has a rate of its own: the rate follows your total billable units for the month. The first 10 units cost $6, the next 90 cost $3, everything above costs $1, with no upper cap. Units that fall into the second or third band are never charged the first band's price; partial usage in a higher band gets the lower rate.

At the end of the month Zernio adds up every account-day (one per account per day connected), divides by 30, and runs the result through the ladder. So 100 accounts × 3 days costs the same as 10 accounts × 30 days. The graduated rates apply to the total, not to individual accounts.

The [calculator on the pricing page](/pricing#connected-accounts) uses this math: account-days, divided by 30, through the graduated ladder, minus the $12 free credit.

## Worked example

A customer has 10 accounts connected for the full month. In week 2 they connect an 11th account, then disconnect it in week 3. In week 4 they connect a 12th, different account, for the rest of the month.

### Step 1: Count account-days

| Account group | Days active | Account-days |
|---|---|---|
| 10 baseline accounts | 30 days each | 300 |
| 1 account (week 2 to 3) | 7 days | 7 |
| 1 account (week 4 to end) | 7 days | 7 |
| **Total** | | **314 account-days** |

### Step 2: Convert to billable units

```
314 ÷ 30 = 10.467 billable units
```

### Step 3: Apply the graduated ladder

| Band | Units in this band | Rate | Cost |
|---|---|---|---|
| Band 1 (units 1 to 10) | 10 | $6.00 | $60.00 |
| Band 2 (units 11 to 100) | 0.467 | $3.00 | $1.40 |
| **Total gross** | | | **$61.40** |

### Step 4: Apply the free credit

| Item | Amount |
|---|---|
| Total gross | $61.40 |
| Free credit (flat per month) | −$12.00 |
| **Net invoice** | **$49.40** |

The bill is 314 account-days converted into 10.467 billable units and run through the graduated rates, not "11 accounts" or "12 accounts". The 11th and 12th accounts, which existed for partial weeks, push the total past the 10-unit boundary, so their fractional contribution is priced at the second band's $3 rate, not the first band's $6.

## Reproduce an invoice

Call `GET /v1/usage` with a `range` to read the billed spend behind an invoice. It is the same charge view the invoice is built from, so the totals reconcile with it line for line. `range=prev-cycle` is the period you were last billed for; `cycle` is the one running now. Without `range` (or `granularity`, `from`, `to`, `groupBy`) the same path returns the plan and quota snapshot instead.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: usage } = await zernio.usage.getUsage({
  query: { range: 'prev-cycle', granularity: 'total' },
});

console.log(usage.totals.total);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

usage = client.usage.get_usage(range="prev-cycle", granularity="total")

print(usage["totals"]["total"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/usage?range=prev-cycle&granularity=total" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), for the month worked through above:

```json
{
  "supported": true,
  "granularity": "total",
  "days": [],
  "totals": {
    "accounts": 61.4,
    "numbers": 0,
    "calls": 0,
    "sms": 0,
    "dlc": 0,
    "xApi": 0,
    "credits": -12,
    "other": 0,
    "total": 49.4
  },
  "lineItems": [
    { "name": "Connected Social Accounts", "product": "accounts", "totalUsd": 61.4, "quantity": 10.467 }
  ],
  "peaks": { "accounts": 12, "numbers": 0 },
  "period": { "start": "2026-08-01T00:00:00Z", "end": "2026-09-01T00:00:00Z", "source": "cycle" },
  "tax": { "taxUsd": 10.37, "ratePercent": 21, "jurisdictionLabel": "ES VAT" }
}
```

Every number in the worked example is in there. `lineItems[].quantity` is the 10.467 billable units, `totals.accounts` the $61.40 gross, `totals.credits` the $12 free credit as a negative amount, and `totals.total` the $49.40 net. `peaks.accounts` is the highest account count seen in the window (12 here), which is what a dashboard shows and not what you pay for. `tax` is estimated on the net total and charged on top of it, so the card sees $59.77.

Spend in USD per product family sits in `totals`: `accounts`, `numbers`, `calls`, `sms`, `dlc`, `xApi`, `credits` and `other`. `granularity=day` (the default) fills `days[]` with one dated row per UTC day in the same shape, for a chart. `groupBy=profile` adds an `attribution` breakdown per profile, where `sum(groups) + unattributed` equals `totals` exactly.

## If it fails

A `400` from `GET /v1/usage` means a query parameter is invalid. `range=custom` needs both `from` and `to`:

```json
{
  "error": "range=custom requires from and to",
  "type": "invalid_request_error",
  "code": "missing_required_field",
  "param": "from"
}
```

Send both as UTC dates, at most 366 days apart.

## Common questions

- **What window does Zernio measure activity in?** The calendar month. Account-days accumulate over UTC days 1 to 30, then a new period starts on the 1st.
- **If I disconnect and reconnect the same account, does it count twice?** Each day it is connected counts. Connected for 10 days, disconnected for 5, then reconnected for 10 more in the same calendar month is 20 account-days for that account.
- **What if I connect a brand-new account mid-month?** It is charged from the day it is connected, at one thirtieth of the band rate per day it stays connected.
- **Which accounts pay the $6 rate and which the $3 rate?** None in particular. The graduated rate applies to the **total billable units**: the first 10 units at $6, units 11 to 100 at $3, and so on. A monthly total of 12.5 billable units bills the first 10 at $6 and the remaining 2.5 at $3.
- **What about the free credit?** Each calendar month grants $12 of credit, which covers the first 2 accounts at the $6 rate. The credit applies to the gross monthly total as a flat per-month grant, not per account.

## Related

- [Pricing](/pricing): the rates, the free credit and the calculator.
- [Get usage](/usage/get-usage): every metering parameter and response field.
- [Get billing](/usage/get-billing): balance, credits, caps and payment status.
- [Build a platform](/multi-tenant): what each of your customers costs you.

---
