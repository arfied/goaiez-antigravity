# Creative Testing

Test Meta creative variations by creating N ads inside one ad set with the creatives[] shape on POST /v1/ads/create.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Test creative variations by passing `creatives[]` on `POST /v1/ads/create`: one campaign, one ad set, N ads that share a budget, targeting and schedule, so the budget stays pooled instead of splitting across parallel ad sets and their [learning phases](/platforms/meta-ads/ad-sets#learning-phase). Meta's delivery algorithm allocates that budget across the ads inside the single ad set. You need the same `accountId` and `adAccountId` as any other [create](/platforms/meta-ads/campaigns#create-the-full-tree-in-one-call).

## Create N ads in one ad set

`creatives[]` replaces the top-level `headline`, `body`, `imageUrl`, `linkUrl` and `callToAction`. Everything else stays where it was.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: test } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Spring launch',
    goal: 'traffic',
    budgetAmount: 100,
    budgetType: 'daily',
    countries: ['US'],
    ageMin: 25,
    ageMax: 45,
    creatives: [
      { headline: 'Spring, 30% off', body: 'Limited time.', imageUrl: 'https://cdn.example.com/a.jpg', linkUrl: 'https://example.com/a', callToAction: 'SHOP_NOW' },
      { headline: 'Curated for you', body: 'Our picks.', imageUrl: 'https://cdn.example.com/b.jpg', linkUrl: 'https://example.com/b', callToAction: 'SHOP_NOW' },
      { headline: 'Free shipping', body: 'This week only.', imageUrl: 'https://cdn.example.com/c.jpg', linkUrl: 'https://example.com/c', callToAction: 'SHOP_NOW' }
    ]
  }
});

const adSetId = test.platformAdSetId;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

test = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Spring launch",
    goal="traffic",
    budget_amount=100,
    budget_type="daily",
    countries=["US"],
    age_min=25,
    age_max=45,
    creatives=[
        {"headline": "Spring, 30% off", "body": "Limited time.", "imageUrl": "https://cdn.example.com/a.jpg", "linkUrl": "https://example.com/a", "callToAction": "SHOP_NOW"},
        {"headline": "Curated for you", "body": "Our picks.", "imageUrl": "https://cdn.example.com/b.jpg", "linkUrl": "https://example.com/b", "callToAction": "SHOP_NOW"},
        {"headline": "Free shipping", "body": "This week only.", "imageUrl": "https://cdn.example.com/c.jpg", "linkUrl": "https://example.com/c", "callToAction": "SHOP_NOW"},
    ],
)

ad_set_id = test["platformAdSetId"]
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
    "name": "Spring launch",
    "goal": "traffic",
    "budgetAmount": 100,
    "budgetType": "daily",
    "countries": ["US"],
    "ageMin": 25,
    "ageMax": 45,
    "creatives": [
      { "headline": "Spring, 30% off", "body": "Limited time.", "imageUrl": "https://cdn.example.com/a.jpg", "linkUrl": "https://example.com/a", "callToAction": "SHOP_NOW" },
      { "headline": "Curated for you", "body": "Our picks.", "imageUrl": "https://cdn.example.com/b.jpg", "linkUrl": "https://example.com/b", "callToAction": "SHOP_NOW" },
      { "headline": "Free shipping", "body": "This week only.", "imageUrl": "https://cdn.example.com/c.jpg", "linkUrl": "https://example.com/c", "callToAction": "SHOP_NOW" }
    ]
  }'
```
</Tab>
</Tabs>

Response (`201`), trimmed to the first ad:

```json
{
  "platformCampaignId": "120250000000000004",
  "platformAdSetId": "120250000000000005",
  "ads": [
    {
      "_id": "66d4a1b2c3e4f5a6b7c8d9e4",
      "name": "Spring launch #1",
      "status": "pending_review",
      "reviewStatus": "in_review",
      "platformAdSetId": "120250000000000005"
    }
  ]
}
```

The multi-creative response carries `ads[]` instead of the single shape's `ad`, and every entry shares `platformCampaignId` and `platformAdSetId`. Each ad is named `"<name> #N"`, so the request above produces "Spring launch #1" through "Spring launch #3". Each entry takes `video: { url, thumbnailUrl }` in place of `imageUrl` for a video variation ([creatives](/platforms/meta-ads/creatives#video-creatives)).

## Add a variation later

To test a fourth creative without starting a new campaign, pass the ad set's id as `adSetId` on a fresh `POST /v1/ads/create` with a single creative. The new ad joins the ad set, inherits its budget, targeting and schedule, and keeps the learning Meta has already done: see [attach a creative to an existing ad set](/platforms/meta-ads/creatives#attach-a-creative-to-an-existing-ad-set).

## Read the results per creative

Rolled-up metrics land on each ad node of [`GET /v1/ads/tree`](/platforms/meta-ads/campaigns#reading-the-campaign-tree), which is enough to rank the variations. To split a single ad's results by the asset Meta served, use the creative-asset [breakdowns](/platforms/meta-ads/insights#demographic-and-placement-breakdowns) (`image_asset`, `video_asset`, `body_asset`, `title_asset`).

`creatives[]` is not the same as `dynamicCreative`, which hands Meta a pool of headlines, bodies and images to recombine inside one ad. Use `creatives[]` when you want N distinct ads you can read separately.

## If it fails

A `400` means `creatives[]` was combined with a shape that owns the creative:

```json
{
  "error": "\"creatives\" and \"adSetId\" are mutually exclusive. Use `creatives[]` to create a new campaign with multiple creatives, or `adSetId` to attach a single new creative to an existing ad set.",
  "type": "invalid_request_error",
  "code": "mutually_exclusive_fields",
  "param": "creatives"
}
```

The same `400` covers `placementAssets`, `carouselCards`, `existingCreativeId`, `translations`, `existingCampaignId` and `buyingType: "RESERVED"`. The top-level `headline`, `body`, `imageUrl`, `linkUrl` and `callToAction` are not in that set: this mode ignores them rather than rejecting them, so there is nothing to remove from the request. Non-Meta platforms return a `400` as well: this shape is Meta only.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the base create request.
- [Creatives](/platforms/meta-ads/creatives): video, carousel and per-placement creative shapes.
- [Insights](/platforms/meta-ads/insights): the breakdowns that attribute results to one asset.
- [Create standalone ad](/ad-campaigns/create-standalone-ad): every field of `creatives[]`.

---
