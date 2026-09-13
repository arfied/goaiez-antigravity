# Duplicate & Lifecycle

Validate a Meta create before it spends, pause and resume delivery, duplicate a campaign, ad set or ad, and delete a campaign.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Change the state of a hierarchy that already exists: start and stop delivery, copy a level, delete, and validate a create before it costs anything. Every call here works on a campaign, ad set or ad you already have, so you need its platform id from an earlier create or from [`GET /v1/ads/tree`](/platforms/meta-ads/campaigns#reading-the-campaign-tree).

## Pause / resume

`PUT /v1/ads/campaigns/{campaignId}/status` switches one campaign and cascades to its ad sets and ads.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: paused } = await zernio.adcampaigns.updateAdCampaignStatus({
  path: { campaignId: '120250000000000000' },
  body: { status: 'paused', platform: 'facebook' }
});
```
</Tab>
<Tab value="Python">
```python
paused = client.ad_campaigns.update_ad_campaign_status(
    campaign_id="120250000000000000",
    status="paused",
    platform="facebook",
)
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT "https://zernio.com/api/v1/ads/campaigns/120250000000000000/status" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "status": "paused", "platform": "facebook" }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{ "status": "paused", "updated": 3, "skipped": 0, "skippedReasons": [] }
```

`updated` counts the ads whose own stored status changed with the campaign; `updated: 0` on a resume is normal when every ad is still waiting on Meta review. The switch lands on the campaign whatever the ads underneath report, and Meta cascades delivery from there, in one platform call rather than one per ad. On Meta it flips the campaign only: an ad set you paused in its own right stays paused, so a resumed campaign that delivers nothing needs `PUT /v1/ads/ad-sets/{adSetId}/status` on that ad set as well. `PUT /v1/ads/ad-sets/{adSetId}/status` and `PUT /v1/ads/{adId}/status` are the narrower scopes, with the same body and the same response.

`POST /v1/ads/campaigns/bulk-status` takes up to 50 campaigns and reports each row on its own, so one bad row does not fail the batch.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: bulk } = await zernio.adcampaigns.bulkUpdateAdCampaignStatus({
  body: {
    status: 'paused',
    campaigns: [
      { platformCampaignId: '120250000000000000', platform: 'facebook' },
      { platformCampaignId: '120250000000000002', platform: 'facebook' }
    ]
  }
});
```
</Tab>
<Tab value="Python">
```python
bulk = client.ad_campaigns.bulk_update_ad_campaign_status(
    status="paused",
    campaigns=[
        {"platformCampaignId": "120250000000000000", "platform": "facebook"},
        {"platformCampaignId": "120250000000000002", "platform": "facebook"},
    ],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/campaigns/bulk-status" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "status": "paused",
    "campaigns": [
      { "platformCampaignId": "120250000000000000", "platform": "facebook" },
      { "platformCampaignId": "120250000000000002", "platform": "facebook" }
    ]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "status": "paused",
  "totals": { "updated": 4, "skipped": 0, "failed": 1 },
  "results": [
    { "platformCampaignId": "120250000000000000", "platform": "facebook", "updated": 4, "skipped": 0 },
    { "platformCampaignId": "120250000000000002", "platform": "facebook", "error": "Campaign not found" }
  ]
}
```

## Duplicate a campaign

`POST /v1/ads/campaigns/{campaignId}/duplicate` wraps Meta's native `/copies`. It defaults to `deepCopy: true` with `statusOption: "PAUSED"`, so the copy arrives with the full hierarchy and delivers nothing until you activate it. Zernio triggers an ads discovery sync afterwards so the new hierarchy shows up in the tree within seconds; pass `syncAfter: false` to skip that.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: copy } = await zernio.adcampaigns.duplicateAdCampaign({
  path: { campaignId: '120250000000000000' },
  body: {
    platform: 'facebook',
    deepCopy: true,
    statusOption: 'PAUSED',
    renameStrategy: 'DEEP_RENAME',
    renameSuffix: ' (copy)'
  }
});
```
</Tab>
<Tab value="Python">
```python
copy = client.ad_campaigns.duplicate_ad_campaign(
    campaign_id="120250000000000000",
    platform="facebook",
    deep_copy=True,
    status_option="PAUSED",
    rename_strategy="DEEP_RENAME",
    rename_suffix=" (copy)",
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/campaigns/120250000000000000/duplicate" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "facebook",
    "deepCopy": true,
    "statusOption": "PAUSED",
    "renameStrategy": "DEEP_RENAME",
    "renameSuffix": " (copy)"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{ "copiedCampaignId": "120250000000000013", "discovery": "triggered", "raw": {} }
```

`statusOption` also takes `ACTIVE`, which launches the copy at once, and `INHERITED_FROM_SOURCE`, which mirrors each entity's source status. `renameStrategy` is `DEEP_RENAME`, `ONLY_TOP_LEVEL_RENAME` or `NO_RENAME`, with `renamePrefix` and `renameSuffix`; `startTime` and `endTime` reschedule the copy.

## Duplicate an ad set or an ad

Same shape, one and two levels down. Both use Meta's native `/copies`, both create the copy paused by default, and both accept `syncAfter: false`.

`POST /v1/ads/ad-sets/{adSetId}/duplicate` takes an optional `campaignId` to retarget the copy into another campaign, plus `deepCopy` (default `true`, so ads and creatives come along), `statusOption`, `startTime`, `endTime` and the rename options. `POST /v1/ads/{adId}/duplicate` takes an optional `adSetId` to retarget the copy into another ad set, and accepts the Zernio ad id or the Meta ad id. The ad duplicate takes no `platform`, because the ad resolves it.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: adSetCopy } = await zernio.adcampaigns.duplicateAdSet({
  path: { adSetId: '120250000000000001' },
  body: { platform: 'facebook', campaignId: '120250000000000013', statusOption: 'PAUSED' }
});
```
</Tab>
<Tab value="Python">
```python
ad_set_copy = client.ad_campaigns.duplicate_ad_set(
    ad_set_id="120250000000000001",
    platform="facebook",
    campaign_id="120250000000000013",
    status_option="PAUSED",
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/ad-sets/120250000000000001/duplicate" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "platform": "facebook", "campaignId": "120250000000000013", "statusOption": "PAUSED" }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{ "copiedAdSetId": "120250000000000014", "discovery": "triggered", "raw": {} }
```

Re-running one creative under a different ad set is the ad-level copy:

```bash
curl -X POST "https://zernio.com/api/v1/ads/66d4a1b2c3e4f5a6b7c8d9e2/duplicate" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "adSetId": "120250000000000014" }'
```

Response (`200`):

```json
{ "copiedAdId": "120260000000000003", "discovery": "triggered", "raw": {} }
```

Creative settings Meta returns, including explicit [promotion metadata](/platforms/meta-ads/creatives#promotion-offers) and `creativeFeatures`, are preserved when the native copy requires a creative rebuild. Metadata Meta does not return on the copy cannot be recovered.

<Callout type="warn">
Meta caps a synchronous copy at fewer than 3 total objects, so even a one-ad ad set can trip it, and the count appears to include children deleted by earlier copies. Duplicate the campaign for anything bigger; Meta's own `400` surfaces verbatim. A creative still carrying Meta's deprecated `standard_enhancements` field blocks the campaign and ad set copies, so rebuild it with [`creativeFeatures`](/platforms/meta-ads/creatives#advantage-creative-enhancements) first; the ad copy drops the field and retries on its own.
</Callout>

## Delete a campaign

`DELETE /v1/ads/campaigns/{campaignId}` cascades on Meta's side, removing the ad sets and ads, and marks the local ad documents `cancelled` in the same pass. `platform` is required in the body.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: deleted } = await zernio.adcampaigns.deleteAdCampaign({
  path: { campaignId: '120250000000000013' },
  body: { platform: 'facebook' }
});
```
</Tab>
<Tab value="Python">
```python
deleted = client.ad_campaigns.delete_ad_campaign(
    campaign_id="120250000000000013",
    platform="facebook",
)
```
</Tab>
<Tab value="curl">
```bash
curl -X DELETE "https://zernio.com/api/v1/ads/campaigns/120250000000000013" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "platform": "facebook" }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{ "deleted": true, "adCount": 3 }
```

A campaign with zero ads has no local ad documents to resolve, so this route would `404` on it. Send `accountId` in the body to delete such a shell: Zernio then skips the local lookup and forwards the delete to Meta. It is ignored when the campaign does have ads.

`DELETE /v1/ads/{adId}` removes one ad and leaves its ad set and campaign in place. To retire an ad without losing its history, pause it instead: deleted objects stop reporting.

## Dry-run a create

`validateOnly: true` on `POST /v1/ads/create` runs every node through Meta's own `execution_options: validate_only`. Nothing is created on Meta and nothing is persisted in Zernio; you get `200` with per-node results instead of an ad.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: check } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Spring sale (dry run)',
    goal: 'conversions',
    budgetAmount: 50,
    budgetType: 'daily',
    headline: 'Spring sale',
    body: '20% off everything',
    callToAction: 'SHOP_NOW',
    linkUrl: 'https://example.com',
    imageUrl: 'https://cdn.example.com/banner.jpg',
    promotedObject: { pixelId: '1729525464415281', customEventType: 'PURCHASE' },
    validateOnly: true
  }
});

for (const result of check.results) console.log(result.node, result.status);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

check = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Spring sale (dry run)",
    goal="conversions",
    budget_amount=50,
    budget_type="daily",
    headline="Spring sale",
    body="20% off everything",
    call_to_action="SHOP_NOW",
    link_url="https://example.com",
    image_url="https://cdn.example.com/banner.jpg",
    promoted_object={"pixelId": "1729525464415281", "customEventType": "PURCHASE"},
    validate_only=True,
)

for result in check["results"]:
    print(result["node"], result["status"])
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
    "name": "Spring sale (dry run)",
    "goal": "conversions",
    "budgetAmount": 50,
    "budgetType": "daily",
    "headline": "Spring sale",
    "body": "20% off everything",
    "callToAction": "SHOP_NOW",
    "linkUrl": "https://example.com",
    "imageUrl": "https://cdn.example.com/banner.jpg",
    "promotedObject": { "pixelId": "1729525464415281", "customEventType": "PURCHASE" },
    "validateOnly": true
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "validateOnly": true,
  "results": [
    { "node": "campaign", "status": "validated" },
    { "node": "adSet", "status": "skipped" },
    { "node": "creative", "status": "validated" },
    { "node": "ad", "status": "skipped" }
  ]
}
```

A Meta validation failure comes back as its own `400`, verbatim. That is the point of the call: Meta's validator catches configuration errors a schema cannot, such as a budget sharing conflict, a CBO campaign whose ad sets share an optimization goal, or a landing page Meta refuses.

Each node reports `validated` or `skipped`, and a skipped one carries a `reason`. Meta can validate a child only against a real parent, so on a fresh tree the campaign and the creative are validated and the ad set is skipped. Pass `existingCampaignId` to validate the ad set against that campaign too. The ad is never validatable before create.

<Callout type="warn">
`validateOnly` works on the single standalone shape only: combining it with `creatives[]`, an `adSetId` attach or `buyingType: "RESERVED"` returns a `400`, and a non-Meta platform returns a `501` with `code: "feature_not_available"`. Media referenced by `imageUrl` is uploaded to the ad account's image library during the dry run, because Meta needs a real image hash to validate the creative. Uploads are content-deduped, so repeated dry runs add nothing.
</Callout>

## Common errors

A `400` on a dry run is Zernio's own shape check, before the request reaches Meta:

```json
{
  "error": "validateOnly supports the single standalone shape only (not creatives[], adSetId attach, or RESERVED buying).",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "validateOnly"
}
```

Send the single standalone shape for a dry run. A `404` on the delete means the campaign has no local ads, which `accountId` in the body solves.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the create these calls operate on.
- [Ad sets](/platforms/meta-ads/ad-sets): status and budget edits that are not lifecycle changes.
- [Reference](/platforms/meta-ads/reference#what-you-cannot-do): the copy limits Meta imposes.
- [Duplicate campaign](/ad-campaigns/duplicate-ad-campaign) and [Bulk status](/ad-campaigns/bulk-update-ad-campaign-status): every field.

---
