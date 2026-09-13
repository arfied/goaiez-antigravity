# Bid Pricing & Forecasts

Get LinkedIn's suggested bid, budget bounds and an impressions, clicks and spend forecast for a targeting spec before you create a campaign.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you know what to bid, which daily budget LinkedIn accepts, and how many impressions, clicks and spend a targeting spec should deliver, before creating anything. Both endpoints take the same `spec` targeting object as [Estimate audience reach](/ad-targeting/estimate-ad-reach). Non-LinkedIn accounts return `{"available": false}` at HTTP `200`, so a multi-platform UI can call them unconditionally.

## Suggested bid and budget bounds

Call `POST /v1/ads/targeting/bid-pricing` with `accountId`, `adAccountId`, `spec` and the campaign type. It answers "what should I bid, and what budget does LinkedIn accept" for that targeting.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: pricing } = await zernio.adtargeting.getLinkedInBidPricing({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '517258773',
    spec: { countries: ['US'] },
    campaignType: 'SPONSORED_UPDATES',
    bidType: 'CPM',
    currency: 'USD',
    objectiveType: 'WEBSITE_VISIT',
    dailyBudget: 50
  }
});

console.log(pricing.pricing.suggestedBid.default.amount);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

pricing = client.ad_targeting.get_linked_in_bid_pricing(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="517258773",
    spec={"countries": ["US"]},
    campaign_type="SPONSORED_UPDATES",
    bid_type="CPM",
    currency="USD",
    objective_type="WEBSITE_VISIT",
    daily_budget=50,
)

print(pricing["pricing"]["suggestedBid"]["default"]["amount"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/targeting/bid-pricing" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "517258773",
    "spec": { "countries": ["US"] },
    "campaignType": "SPONSORED_UPDATES",
    "bidType": "CPM",
    "currency": "USD",
    "objectiveType": "WEBSITE_VISIT",
    "dailyBudget": 50
  }'
```
</Tab>
</Tabs>

Response (`200`), every amount a decimal string with its currency:

```json
{
  "available": true,
  "pricing": {
    "bidLimits": {
      "min": { "amount": "8.52", "currencyCode": "USD" },
      "max": { "amount": "88.59", "currencyCode": "USD" }
    },
    "suggestedBid": {
      "min": { "amount": "22.10", "currencyCode": "USD" },
      "default": { "amount": "31.47", "currencyCode": "USD" },
      "max": { "amount": "41.20", "currencyCode": "USD" }
    },
    "dailyBudgetLimits": {
      "min": { "amount": "10.00", "currencyCode": "USD" },
      "default": { "amount": "50.00", "currencyCode": "USD" },
      "max": { "amount": "100000.00", "currencyCode": "USD" }
    }
  }
}
```

Pick a [`unitCost`](/platforms/linkedin-ads/create-ads#bidding) inside `bidLimits`, and warn the user when their daily budget sits below `dailyBudgetLimits.min`, the account-and-targeting-specific version of LinkedIn's flat $10/day minimum. `campaignType` defaults to `SPONSORED_UPDATES`, `bidType` to `CPM`, `matchType` to `EXACT` and `currency` to `USD`.

## Impressions, clicks and spend forecast

Call `POST /v1/ads/targeting/supply-forecast` with the same `spec`, a future window and a budget. It projects delivery over that window.

```bash
curl -X POST "https://zernio.com/api/v1/ads/targeting/supply-forecast" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "517258773",
    "spec": { "countries": ["US"] },
    "campaignType": "SPONSORED_UPDATES",
    "timeRangeStart": 1798848000000,
    "timeRangeEnd": 1801440000000,
    "objectiveType": "WEBSITE_VISIT",
    "dailyBudget": 50,
    "competingBid": { "bidType": "CPM", "amount": 10 }
  }'
```

- `timeRangeStart` and `timeRangeEnd` are Unix milliseconds, and the start must be in the future.
- Either `dailyBudget` or `totalBudget` is required.
- `competingBid` is required for manual-bid forecasts. Omit it and pass `optimizationTarget` instead for an auto-bid forecast.

<Callout type="warn">
`objectiveType` is required in practice. LinkedIn's docs mark it optional, but the live API returns a `422` ("The objective type is not supported") without it. Any valid objective works, for example `WEBSITE_VISIT`.
</Callout>

Response (`200`), one series per metric and granularity:

```json
{
  "available": true,
  "forecast": [
    {
      "metricType": "IMPRESSION",
      "granularity": "CUSTOM",
      "timeSeries": [
        { "timestamp": 1798848000000, "value": 184000, "adForecastRange": { "lowEnd": 120000, "highEnd": 250000 } }
      ]
    },
    {
      "metricType": "CLICK",
      "granularity": "CUSTOM",
      "timeSeries": [
        { "timestamp": 1798848000000, "value": 920, "adForecastRange": { "lowEnd": 600, "highEnd": 1300 } }
      ]
    }
  ]
}
```

LinkedIn returns roughly 20 series. `metricType` is LinkedIn's own value, forwarded verbatim: `IMPRESSION`, `CLICK`, `SPENDING`, `MAX_POTENTIAL_BUDGET`, `COST_PER_MILLION_IMPRESSIONS` and `COST_PER_MILLION_CLICKS` are the ones this endpoint returns for every forecast, so read the `metricType` on each series rather than assuming a fixed set. Granularities are `DAILY`, `SEVEN_DAY`, `THIRTY_DAY` and `CUSTOM` (the sum over the requested range, the number you would quote to a client). LinkedIn caps the daily spending forecast at 1.2x the daily budget and returns 0 once the total budget is exhausted.

## If it fails

A `400` means the targeting is invalid, a budget is missing, or LinkedIn rejected the forecast window; LinkedIn's own code is in the message:

```json
{
  "error": "END_DATE_MAX_HORIZON_FOR_FORECAST",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "timeRangeEnd"
}
```

Move `timeRangeEnd` inside LinkedIn's forecast horizon and repeat the call.

## Related

- [Create ads](/platforms/linkedin-ads/create-ads#bidding): where the bid goes.
- [Estimate audience reach](/ad-targeting/estimate-ad-reach): the same `spec`, for audience size.
- [Bid pricing](/ad-targeting/get-linkedin-bid-pricing) and [Supply forecast](/ad-targeting/get-linkedin-supply-forecast): every field.

---
