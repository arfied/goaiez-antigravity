# Catalog Ads

Run Advantage+ catalog ads from a Meta product catalog with goal catalog_sales, and find the catalog and product set to promote.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Promote items from a Meta product catalog with `goal: "catalog_sales"` on `POST /v1/ads/create`. One ad covers N items: Meta renders the visuals per catalog item and picks which product or vehicle each person sees. Two discovery endpoints find the catalog and the product set first. You need the same `accountId` and `adAccountId` as any other create, and the ad account needs a pixel.

Catalog contents live in Meta Commerce Manager or a feed provider. Zernio reads the catalogs and runs ads on them; it does not edit items.

## Find the catalog and product set

Catalogs hang off the ad account's business. Call `GET /v1/ads/catalogs` to list them.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: catalogs } = await zernio.adcreatives.listAdCatalogs({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', adAccountId: 'act_1234567890' }
});

const catalogId = catalogs.catalogs[0].id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

catalogs = client.ad_creatives.list_ad_catalogs(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
)

catalog_id = catalogs["catalogs"][0]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/catalogs?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "catalogs": [
    { "id": "1003405825408877", "name": "Vehicle inventory", "vertical": "vehicles", "productCount": 132 }
  ]
}
```

Then list that catalog's product sets. The product set, not the catalog, is what the ad promotes.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sets } = await zernio.adcreatives.listAdCatalogProductSets({
  path: { catalogId: '1003405825408877' },
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

const productSetId = sets.productSets[0].id;
```
</Tab>
<Tab value="Python">
```python
sets = client.ad_creatives.list_ad_catalog_product_sets(
    catalog_id="1003405825408877",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
)

product_set_id = sets["productSets"][0]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/catalogs/1003405825408877/product-sets?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "productSets": [
    { "id": "1003260032095191", "name": "All vehicles", "productCount": 132 }
  ]
}
```

## Create the catalog campaign

`goal: "catalog_sales"` builds the whole chain: a Sales-objective campaign, an ad set bound to the product set, and a catalog template creative. There is no `imageUrl` and no `video`, because Meta renders the visuals from the catalog items.

The copy fields become the template and accept Meta's catalog template tags (`{{product.name}}`, `{{product.price}}`, and on a vehicle catalog `{{vehicle.make}}`, `{{vehicle.model}}`, `{{vehicle.year}}`, `{{vehicle.price}}`), so each rendered item carries its own data.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Vehicle inventory, catalog',
    goal: 'catalog_sales',
    budgetAmount: 30,
    budgetType: 'daily',
    headline: '{{vehicle.year}} {{vehicle.make}} {{vehicle.model}}',
    body: 'Find your next car',
    description: '{{vehicle.price}}',
    callToAction: 'LEARN_MORE',
    linkUrl: 'https://example.com/inventory',
    countries: ['US'],
    optimizationGoal: 'OFFSITE_CONVERSIONS',
    promotedObject: {
      productSetId: '1003260032095191',
      pixelId: '1729525464415281',
      customEventType: 'PURCHASE'
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
    name="Vehicle inventory, catalog",
    goal="catalog_sales",
    budget_amount=30,
    budget_type="daily",
    headline="{{vehicle.year}} {{vehicle.make}} {{vehicle.model}}",
    body="Find your next car",
    description="{{vehicle.price}}",
    call_to_action="LEARN_MORE",
    link_url="https://example.com/inventory",
    countries=["US"],
    optimization_goal="OFFSITE_CONVERSIONS",
    promoted_object={
        "productSetId": "1003260032095191",
        "pixelId": "1729525464415281",
        "customEventType": "PURCHASE",
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
    "name": "Vehicle inventory, catalog",
    "goal": "catalog_sales",
    "budgetAmount": 30,
    "budgetType": "daily",
    "headline": "{{vehicle.year}} {{vehicle.make}} {{vehicle.model}}",
    "body": "Find your next car",
    "description": "{{vehicle.price}}",
    "callToAction": "LEARN_MORE",
    "linkUrl": "https://example.com/inventory",
    "countries": ["US"],
    "optimizationGoal": "OFFSITE_CONVERSIONS",
    "promotedObject": {
      "productSetId": "1003260032095191",
      "pixelId": "1729525464415281",
      "customEventType": "PURCHASE"
    }
  }'
```
</Tab>
</Tabs>

Response (`201`), trimmed:

```json
{
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9e6",
    "status": "pending_review",
    "goal": "catalog_sales",
    "platformObjective": "OUTCOME_SALES",
    "optimizationGoal": "OFFSITE_CONVERSIONS",
    "promotedObject": {
      "productSetId": "1003260032095191",
      "pixelId": "1729525464415281",
      "customEventType": "PURCHASE"
    }
  }
}
```

All 3 `promotedObject` fields are required. Meta's "Promoted Object is Required" error on a catalog ad set usually means the pixel is missing, not the product set.

## How it behaves

Targeting works like any other campaign: countries, age, gender, [placements](/platforms/meta-ads/reference#placements) and saved audiences all apply. Broad targeting is fine here, because Meta matches items to people; layer retargeting off pixel events with `optimizationGoal: "OFFSITE_CONVERSIONS"`.

The ad set optimization defaults to `LINK_CLICKS`, which would buy clicks for a campaign asking Meta for purchases, so the sample sends the top-level `optimizationGoal: "OFFSITE_CONVERSIONS"` to optimize toward the `customEventType` in `promotedObject` ([conversion campaigns](/platforms/meta-ads/conversion-campaigns#override-the-optimization-goal)).

`catalog_sales` is a single-creative shape. It cannot be combined with `creatives[]`, `adSetId`, `dynamicCreative` or `placementAssets`, because the catalog template is already the multi-item mechanism.

Catalog creatives need an explicit Instagram identity for Instagram placements. When the connected Page has no linked Instagram account, Zernio falls back to the Page-backed one. Meta has retired the legacy `PRODUCT_CATALOG_SALES` objective on modern ad accounts, so Zernio uses the current Sales-objective shape.

## Common errors

A `400` names the field that conflicts with the catalog shape:

```json
{
  "error": "creatives is not supported with goal catalog_sales",
  "type": "invalid_request_error",
  "param": "creatives"
}
```

Send one creative. Zernio checks `promotedObject.productSetId` before creating anything: passing the catalog id where the product set id belongs, or a set the token cannot read, returns a precise `400` naming `promotedObject.productSetId` rather than a raw Meta error. A `productCatalogId` that does not contain the set names `promotedObject.productCatalogId` instead. If the catalog or product set id belongs to another business, Meta's own `400` comes back inside `platformError`; re-read the ids from the two discovery calls above.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the base create request.
- [Conversion campaigns](/platforms/meta-ads/conversion-campaigns): every `promotedObject` key.
- [Pixels](/platforms/meta-ads/pixels): create the pixel a catalog ad set needs.
- [List catalogs](/ad-creatives/list-ad-catalogs) and [List product sets](/ad-creatives/list-ad-catalog-product-sets): every field.

---
