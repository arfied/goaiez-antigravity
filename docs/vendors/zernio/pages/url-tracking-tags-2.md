# URL Tracking Tags

Read and set a LinkedIn campaign's Dynamic UTM parameters through GET and PATCH /v1/ads/{adId}/tracking-tags.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a LinkedIn campaign appends UTM parameters to every creative's landing-page URL. LinkedIn's Dynamic UTM Tracking lives at the campaign level, and you operate on it through the ad endpoints by passing any ad in the campaign. `dynamicValueParameters` map a key to a LinkedIn token resolved at serve time (`CAMPAIGN_ID`, `CAMPAIGN_NAME`, `CAMPAIGN_GROUP_ID`, `CAMPAIGN_GROUP_NAME`, `CREATIVE_ID`, `ACCOUNT_ID`, `ACCOUNT_NAME`); `customValueParameters` map a key to a static string.

## Step 1: read the current parameters

Call `GET /v1/ads/{adId}/tracking-tags` with the Zernio `_id` of an ad in the campaign.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: tags } = await zernio.trackingtags.getAdTrackingTags({
  path: { adId: '66f0a1b2c3d4e5f6a7b8c9d0' }
});

console.log(tags.dynamicValueParameters);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

tags = client.tracking_tags.get_ad_tracking_tags(ad_id="66f0a1b2c3d4e5f6a7b8c9d0")

print(tags["dynamicValueParameters"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0/tracking-tags" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "linkedinads",
  "level": "campaign",
  "dynamicValueParameters": { "utm_campaign": "CAMPAIGN_NAME" },
  "customValueParameters": { "utm_source": "linkedin" }
}
```

## Step 2: set or update them

Call `PATCH /v1/ads/{adId}/tracking-tags` with `dynamicValueParameters`, `customValueParameters` or both.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: updated } = await zernio.trackingtags.updateAdTrackingTags({
  path: { adId: '66f0a1b2c3d4e5f6a7b8c9d0' },
  body: {
    dynamicValueParameters: { utm_campaign: 'CAMPAIGN_NAME', utm_content: 'CREATIVE_ID' },
    customValueParameters: { utm_source: 'linkedin', utm_medium: 'paid_social' }
  }
});

console.log(updated.customValueParameters);
```
</Tab>
<Tab value="Python">
```python
updated = client.tracking_tags.update_ad_tracking_tags(
    ad_id="66f0a1b2c3d4e5f6a7b8c9d0",
    dynamic_value_parameters={"utm_campaign": "CAMPAIGN_NAME", "utm_content": "CREATIVE_ID"},
    custom_value_parameters={"utm_source": "linkedin", "utm_medium": "paid_social"},
)

print(updated["customValueParameters"])
```
</Tab>
<Tab value="curl">
```bash
curl -X PATCH "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0/tracking-tags" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "dynamicValueParameters": { "utm_campaign": "CAMPAIGN_NAME", "utm_content": "CREATIVE_ID" },
    "customValueParameters": { "utm_source": "linkedin", "utm_medium": "paid_social" }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "linkedinads",
  "level": "campaign",
  "dynamicValueParameters": { "utm_campaign": "CAMPAIGN_NAME", "utm_content": "CREATIVE_ID" },
  "customValueParameters": { "utm_source": "linkedin", "utm_medium": "paid_social" }
}
```

Parameters set on a campaign apply to all its creatives, existing and new, without re-review. Conversation, Message and Lead Gen Form ads do not support dynamic UTM parameters, and static UTMs baked into a creative's own landing-page URL should not reuse the same keys, or they collide.

## If it fails

A `404` means `adId` matched no ad of yours:

```json
{
  "error": "Ad not found",
  "type": "not_found",
  "code": "ad_not_found",
  "param": "adId"
}
```

Take the `_id` from the create response or from `GET /v1/ads`. A `403` with code `ads_allowance_exceeded` on the `PATCH` means the team has no payment method on file and has reached its 500 free live ads: add a card to resume. A `405` means the ad's platform has no click-URL tracking surface at all (TikTok, X, Pinterest), which a LinkedIn ad never returns.

## Related

- [Create ads](/platforms/linkedin-ads/create-ads): where the ad `_id` comes from.
- [Meta Ads tracking tags](/platforms/meta-ads/tracking-tags) and [Google Ads tracking tags](/platforms/google-ads/tracking-tags): the same endpoint on the other networks.
- [Get ad tracking tags](/tracking-tags/get-ad-tracking-tags) and [Set ad tracking tags](/tracking-tags/update-ad-tracking-tags): every field.

---
