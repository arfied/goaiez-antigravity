# Conversion Campaigns

Optimize a Meta campaign toward a pixel event, an instant form or an app install by adding a promotedObject to the create request.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Optimize toward a conversion by sending a `goal` from the table below plus a `promotedObject` on `POST /v1/ads/create`. The promoted object names the thing being optimized against: a pixel and its event, a Page, an app, or a product set. Without it Meta rejects the ad set with subcode `1815430`, so Zernio checks first and returns a `400` with `param: "promotedObject"`.

## The promoted object

| `goal` | Meta objective and optimization | Required `promotedObject` fields |
|--------|-------------------|----------------------------------|
| `conversions` | Sales, `OFFSITE_CONVERSIONS` | `pixelId` and `customEventType`, with a commerce event such as `PURCHASE`, `ADD_TO_CART` or `START_TRIAL` |
| `lead_conversion` | Leads, `OFFSITE_CONVERSIONS` | `pixelId` and `customEventType`, with a leads event such as `LEAD`, `SUBMIT_APPLICATION`, `SCHEDULE` or `CONTACT` |
| `lead_generation` | Leads, `LEAD_GENERATION` on an instant form | `pageId`, auto-filled from the connected Page when omitted ([lead forms](/platforms/meta-ads/lead-forms)) |
| `app_promotion` | App promotion, `APP_INSTALLS` | `applicationId` and `objectStoreUrl` |
| `catalog_sales` | Sales, catalog template creative | `productSetId`, `pixelId` and `customEventType` ([catalog ads](/platforms/meta-ads/catalog-ads)) |

Meta gates which events are valid per objective. `conversions` runs the Sales objective, so a leads event such as `LEAD` is rejected there; website pixel lead optimization is `lead_conversion`. `lead_generation` is the Leads objective too, but it optimizes for an instant form rather than a pixel event.

`customEventType` takes Meta's standard event names: `PURCHASE`, `LEAD`, `COMPLETE_REGISTRATION`, `ADD_TO_CART`, `INITIATE_CHECKOUT`, `ADD_PAYMENT_INFO`, `SUBSCRIBE`, `START_TRIAL`, `VIEW_CONTENT`, `SEARCH`, `CONTACT`, `SUBMIT_APPLICATION`, `SCHEDULE`. For a pixel event you named yourself, or for a Custom Conversion, see [the samples below](#custom-events-and-custom-conversions).

For iOS app promotion, follow the [SKAdNetwork campaign fields](/platforms/meta-ads/campaigns#ios-14-skadnetwork-attribution). [App promotion discovery](/platforms/meta-ads/operational-reads#app-promotion-discovery) lists promotable apps and reads an app's iOS 14 campaign limits before creation.

## Create a purchase-optimized ad

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Spring sale, purchases',
    goal: 'conversions',
    budgetAmount: 75,
    budgetType: 'daily',
    headline: 'Spring sale, 30% off',
    body: 'Limited time. Upgrade today.',
    imageUrl: 'https://cdn.example.com/spring.jpg',
    callToAction: 'SHOP_NOW',
    linkUrl: 'https://example.com/spring',
    countries: ['US'],
    promotedObject: { pixelId: '1729525464415281', customEventType: 'PURCHASE' }
  }
});
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Spring sale, purchases",
    goal="conversions",
    budget_amount=75,
    budget_type="daily",
    headline="Spring sale, 30% off",
    body="Limited time. Upgrade today.",
    image_url="https://cdn.example.com/spring.jpg",
    call_to_action="SHOP_NOW",
    link_url="https://example.com/spring",
    countries=["US"],
    promoted_object={"pixelId": "1729525464415281", "customEventType": "PURCHASE"},
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
    "name": "Spring sale, purchases",
    "goal": "conversions",
    "budgetAmount": 75,
    "budgetType": "daily",
    "headline": "Spring sale, 30% off",
    "body": "Limited time. Upgrade today.",
    "imageUrl": "https://cdn.example.com/spring.jpg",
    "callToAction": "SHOP_NOW",
    "linkUrl": "https://example.com/spring",
    "countries": ["US"],
    "promotedObject": {
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
    "_id": "66d4a1b2c3e4f5a6b7c8d9e5",
    "status": "pending_review",
    "goal": "conversions",
    "platformObjective": "OUTCOME_SALES",
    "optimizationGoal": "OFFSITE_CONVERSIONS",
    "promotedObject": { "pixelId": "1729525464415281", "customEventType": "PURCHASE" }
  }
}
```

To find a `pixelId`, list the pixels the connected ad account can see with [`GET /v1/accounts/{accountId}/tracking-tags`](/platforms/meta-ads/pixels), or create one there. [`GET /v1/accounts/{accountId}/conversion-destinations`](/platforms/meta-ads/capi#find-the-pixel-to-send-to) returns the same set in the shape the Conversions API uses.

### Custom events and custom conversions

For a pixel event you named yourself, send `OTHER` with the name exactly as Events Manager shows it. The two fields travel together: `OTHER` without `customEventStr` is a `400`, and so is `customEventStr` with any other event type.

```json
{
  "promotedObject": {
    "pixelId": "1729525464415281",
    "customEventType": "OTHER",
    "customEventStr": "trial_activated"
  }
}
```

To optimize against a Custom Conversion instead, send its id alone. It replaces the pixel and event pair rather than joining it:

```json
{
  "promotedObject": { "customConversionId": "1234567890123456" }
}
```

The id comes from [`GET /v1/accounts/{accountId}/custom-conversions`](/ad-accounts/list-custom-conversions) with `adAccountId`, which lists the ad account's Custom Conversions, archived ones included. [`POST` on the same path](/ad-accounts/create-custom-conversion) provisions one from a `name`, a `pixelId`, a `customEventType` and Meta's own `rule` grammar (for example `{"url": {"i_contains": "thank-you"}}`), so a flow does not have to send the user to Ads Manager first. It returns `customConversionId` ready for `promotedObject`, and reuses a non-archived conversion with the same name on the same pixel rather than minting a duplicate: that reply is a `200` with `reused: true` instead of a `201`.

Everything else in the create request stays as it is above.

## Every promoted object key

`promotedObject` accepts exactly these keys, on create and on the [post-launch edit](/platforms/meta-ads/ad-sets#post-launch-delivery-edits):

| Field | When to send it |
|-------|-------------------|
| `pixelId` | The pixel to optimize against. Meta rejects a promoted object with a pixel and no `customEventType` (subcode `1885014`). |
| `customEventType` | The standard event, or `OTHER` with `customEventStr`. |
| `customEventStr` | The custom pixel event name, case-sensitive. Requires `customEventType: "OTHER"`, and `OTHER` requires it. |
| `pageId` | The Page for `goal: "lead_generation"`. |
| `applicationId`, `objectStoreUrl` | The app and its store listing, for `goal: "app_promotion"`. |
| `customConversionId` | A Custom Conversion instead of a standard event. Accepted on its own. |
| `productCatalogId`, `productSetId` | Catalog and Advantage+ shopping campaigns. |
| `offlineConversionDataSetId` | An offline event set. Post-merger these are datasets, so the id is the dataset id (the pixel id for pixel-backed datasets). |
| `whatsappPhoneNumber` | The WhatsApp number on a messaging-destination ad set. |

<Callout type="warn">
`promotedObject` is strict. An unknown or misspelled key returns a `400` naming it instead of being dropped, because a dropped key used to change what the ad set optimized toward with no warning.
</Callout>

## Override the optimization goal

The ad set's `optimization_goal` is derived from `goal` (`traffic` gives `LINK_CLICKS`). Send the top-level `optimizationGoal` to set it yourself, for example `LANDING_PAGE_VIEWS`, `REACH`, `IMPRESSIONS`, `OFFSITE_CONVERSIONS` or `THRUPLAY`. It is forwarded verbatim and Meta rejects a combination its objective does not allow. `billingEvent` works the same way and defaults to `IMPRESSIONS`.

## If it fails

A `400` before the request reaches Meta means the promoted object is missing:

```json
{
  "error": "promotedObject is required for goal conversions",
  "type": "invalid_request_error",
  "param": "promotedObject"
}
```

Add `pixelId` and `customEventType`, or `customConversionId`. If the request does reach Meta, subcode `1815430` is the same problem and `1885014` is a pixel sent without an event; the [reference](/platforms/meta-ads/reference#common-errors) lists both.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the base create request and the bid strategy.
- [Pixels](/platforms/meta-ads/pixels): create and share the pixel these goals point at.
- [Conversions](/platforms/meta-ads/capi): send the events server-side.
- [Ad sets](/platforms/meta-ads/ad-sets#post-launch-delivery-edits): change the promoted object after launch.
- [Create standalone ad](/ad-campaigns/create-standalone-ad): every field.

---
