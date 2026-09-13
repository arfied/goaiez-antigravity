# Reach & Frequency

Quote, reserve and buy a fixed-price Meta reach and frequency campaign with POST /v1/ads/rf-predictions and buyingType RESERVED.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Buy reach at a price locked in advance instead of competing in the auction. Meta quotes a reach and a frequency cap before you spend anything, holds the inventory once you reserve, and the reservation then drives an otherwise ordinary create. It takes 3 calls, and `ads_management` already covers all of them.

<Mermaid
  chart={`flowchart LR
  Q["1. Quote<br/>POST /v1/ads/rf-predictions"] --> R["2. Reserve<br/>POST /{id}/reserve<br/>mints a new id"]
  R --> B["3. Buy<br/>POST /v1/ads/create<br/>buyingType RESERVED"]`}
/>

## Quote a reservation

`POST /v1/ads/rf-predictions` asks Meta what it can deliver. Nothing is bought, and a prediction expires on its own. Pass exactly one of `budgetAmount`, so Meta predicts the reach, or `reach`, so Meta predicts the budget.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: quote } = await zernio.reachandfrequency.createRfPrediction({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    budgetAmount: 500,
    startDate: '2027-03-01T09:00:00Z',
    endDate: '2027-03-08T09:00:00Z',
    frequencyCap: 2,
    targeting: { countries: ['US'], ageMin: 18, ageMax: 65 },
    placements: {
      publisherPlatforms: ['facebook'],
      facebookPositions: ['feed'],
      devicePlatforms: ['mobile', 'desktop']
    }
  }
});

const predictionId = quote.prediction.predictionId;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

quote = client.reach_and_frequency.create_rf_prediction(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    budget_amount=500,
    start_date="2027-03-01T09:00:00Z",
    end_date="2027-03-08T09:00:00Z",
    frequency_cap=2,
    targeting={"countries": ["US"], "ageMin": 18, "ageMax": 65},
    placements={
        "publisherPlatforms": ["facebook"],
        "facebookPositions": ["feed"],
        "devicePlatforms": ["mobile", "desktop"],
    },
)

prediction_id = quote["prediction"]["predictionId"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/rf-predictions" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "budgetAmount": 500,
    "startDate": "2027-03-01T09:00:00Z",
    "endDate": "2027-03-08T09:00:00Z",
    "frequencyCap": 2,
    "targeting": { "countries": ["US"], "ageMin": 18, "ageMax": 65 },
    "placements": {
      "publisherPlatforms": ["facebook"],
      "facebookPositions": ["feed"],
      "devicePlatforms": ["mobile", "desktop"]
    }
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "adAccountId": "act_1234567890",
  "currency": "USD",
  "prediction": {
    "predictionId": "6234567890123",
    "status": "ready",
    "budget": 500,
    "reach": 412000,
    "impressions": 824000,
    "minBudget": 120,
    "maxBudget": 18000,
    "minReach": 98000,
    "maxReach": 2100000,
    "frequencyCap": 2,
    "startTime": 1804064400,
    "stopTime": 1804669200,
    "expiresAt": null
  }
}
```

Show `minBudget`, `maxBudget`, `minReach` and `maxReach` in your interface: a reservation outside those bounds is rejected. `status` is `ready`, `pending` while Meta is still computing, or `failed:<meta code>`. Read it again at any time with `GET /v1/ads/rf-predictions/{predictionId}`, which requires both `accountId` and `adAccountId`.

<Callout type="warn">
A reservation campaign rejects automatic placements (subcode `1885696`), so a prediction needs explicit `placements` ([values](/platforms/meta-ads/reference#placements)). Omit them and Zernio defaults to the Facebook feed, plus the Instagram stream when a linked Instagram professional account resolves.
</Callout>

`frequencyCap` caps impressions per person across the whole window, from 1 to 90. It is optional on the request, and the prediction echoes back the value Meta actually priced against, which is `null` when Meta applied no cap: read it from the response rather than assuming your input carried.

## Reserve it

`POST /v1/ads/rf-predictions/{predictionId}/reserve` locks the price and the inventory until `expiresAt`, roughly 14 days out. It takes `accountId` and `adAccountId` in the body.

```bash
curl -X POST "https://zernio.com/api/v1/ads/rf-predictions/6234567890123/reserve" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "adAccountId": "act_1234567890" }'
```

Response (`201`):

```json
{
  "adAccountId": "act_1234567890",
  "prediction": {
    "predictionId": "6234567890456",
    "status": "ready",
    "budget": 500,
    "reach": 412000,
    "expiresAt": "2027-02-25T09:00:00Z"
  }
}
```

Reserving mints a new `predictionId`. The reservation is a different object from the quote, so pass the id returned here to the create call, not the one you quoted with. `DELETE /v1/ads/rf-predictions/{reservedId}` releases an unused reservation and needs `accountId` and `adAccountId` as query parameters.

## Buy the reservation

`POST /v1/ads/create` with `buyingType: "RESERVED"` and `rfPredictionId` set to the reserved id.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'March brand push',
    goal: 'awareness',
    buyingType: 'RESERVED',
    rfPredictionId: '6234567890456',
    headline: 'The new collection',
    body: 'Out now.',
    imageUrl: 'https://cdn.example.com/brand.jpg',
    callToAction: 'LEARN_MORE',
    linkUrl: 'https://example.com',
    countries: ['US'],
    ageMin: 18,
    ageMax: 65,
    placements: {
      publisherPlatforms: ['facebook'],
      facebookPositions: ['feed'],
      devicePlatforms: ['mobile', 'desktop']
    }
  }
});
```
</Tab>
<Tab value="Python">
```python
created = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="March brand push",
    goal="awareness",
    buying_type="RESERVED",
    rf_prediction_id="6234567890456",
    headline="The new collection",
    body="Out now.",
    image_url="https://cdn.example.com/brand.jpg",
    call_to_action="LEARN_MORE",
    link_url="https://example.com",
    countries=["US"],
    age_min=18,
    age_max=65,
    placements={
        "publisherPlatforms": ["facebook"],
        "facebookPositions": ["feed"],
        "devicePlatforms": ["mobile", "desktop"],
    },
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/create" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "name": "March brand push",
    "goal": "awareness",
    "buyingType": "RESERVED",
    "rfPredictionId": "6234567890456",
    "headline": "The new collection",
    "body": "Out now.",
    "imageUrl": "https://cdn.example.com/brand.jpg",
    "callToAction": "LEARN_MORE",
    "linkUrl": "https://example.com",
    "countries": ["US"],
    "ageMin": 18,
    "ageMax": 65,
    "placements": {
      "publisherPlatforms": ["facebook"],
      "facebookPositions": ["feed"],
      "devicePlatforms": ["mobile", "desktop"]
    }
  }'
```
</Tab>
</Tabs>

Response (`201`), trimmed:

```json
{
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9ea",
    "status": "pending_review",
    "goal": "awareness",
    "platformCampaignId": "120250000000000011",
    "platformAdSetId": "120250000000000012"
  }
}
```

3 rules apply to a reserved create. It is the plain single-ad shape only: no `creatives[]`, no `adSetId` attach, and no [`validateOnly`](/platforms/meta-ads/lifecycle#dry-run-a-create). It carries no budget or bid fields, because budget, pricing and the run window come from the reservation; Zernio persists them as a campaign-level lifetime budget. And the targeting and placements must match the ones the reservation was priced against.

Zernio handles Meta's own restrictions here: it strips the auction-only fields Meta rejects on a reserved campaign (budget sharing, subcode `4834012`; ad set bid fields, subcode `1815830`), pins the ad set to the reserved window, and creates the first ad `ACTIVE` because Meta refuses to create a paused reach and frequency ad at all (subcode `1487716`). Delivery is still gated by the campaign and ad set status plus the future start date, so nothing spends early.

## Common errors

A `400` on the quote is Instagram placements asked for on an account with no linked Instagram professional account:

```json
{
  "error": "Instagram placements on an R&F prediction require a linked Instagram professional account (Meta subcode 1885237).",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "placements"
}
```

Link an Instagram professional account to the Page, or quote with Facebook placements alone. A `422` means no Facebook Page resolved for the account, so connect one on the same profile ([before you start](/platforms/meta-ads#before-you-start)).

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the create request the reserved id goes on.
- [Targeting](/platforms/meta-ads/targeting): the fields the `targeting` object takes.
- [Reference](/platforms/meta-ads/reference#placements): every placement value.
- [Create prediction](/reach-and-frequency/create-rf-prediction) and [Reserve prediction](/reach-and-frequency/reserve-rf-prediction): every field.

---
