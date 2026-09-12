# URL Tracking Tags

Read and update a Google campaign's tracking template and final URL suffix through GET and PATCH /v1/ads/{adId}/tracking-tags.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a Google campaign's click URLs carry your tracking template and final URL suffix. `trackingUrlTemplate` is the redirect or tracking template and must contain `{lpurl}`; `finalUrlSuffix` is the parse-only `key=value` params appended to the landing page, the part that survives parallel tracking. Both live at the campaign level, and you operate on them through the ad endpoints by passing any ad in the campaign.

## Step 1: read the current values

Call `GET /v1/ads/{adId}/tracking-tags` with the Zernio `_id` of an ad in the campaign.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: tags } = await zernio.trackingtags.getAdTrackingTags({
  path: { adId: '66f0a1b2c3d4e5f6a7b8c9d0' }
});

console.log(tags.trackingUrlTemplate);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

tags = client.tracking_tags.get_ad_tracking_tags(ad_id="66f0a1b2c3d4e5f6a7b8c9d0")

print(tags["trackingUrlTemplate"])
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
  "platform": "google",
  "level": "campaign",
  "trackingUrlTemplate": "{lpurl}?utm_source=google",
  "finalUrlSuffix": "utm_medium=cpc"
}
```

## Step 2: update either field

Call `PATCH /v1/ads/{adId}/tracking-tags` with only the fields you want to change. Omit a field to leave it untouched; pass an empty string to clear it.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: updated } = await zernio.trackingtags.updateAdTrackingTags({
  path: { adId: '66f0a1b2c3d4e5f6a7b8c9d0' },
  body: {
    trackingUrlTemplate: '{lpurl}?utm_source=google&utm_campaign={campaignid}',
    finalUrlSuffix: 'utm_medium=cpc'
  }
});

console.log(updated.trackingUrlTemplate);
```
</Tab>
<Tab value="Python">
```python
updated = client.tracking_tags.update_ad_tracking_tags(
    ad_id="66f0a1b2c3d4e5f6a7b8c9d0",
    tracking_url_template="{lpurl}?utm_source=google&utm_campaign={campaignid}",
    final_url_suffix="utm_medium=cpc",
)

print(updated["trackingUrlTemplate"])
```
</Tab>
<Tab value="curl">
```bash
curl -X PATCH "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0/tracking-tags" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "trackingUrlTemplate": "{lpurl}?utm_source=google&utm_campaign={campaignid}",
    "finalUrlSuffix": "utm_medium=cpc"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "google",
  "level": "campaign",
  "trackingUrlTemplate": "{lpurl}?utm_source=google&utm_campaign={campaignid}",
  "finalUrlSuffix": "utm_medium=cpc"
}
```

Both calls go straight to Google, against the developer-token quota Zernio shares across all customers. They are not metered by the per-user ops budget that gates GAQL and Keyword Planner ([quotas](/platforms/google-ads/reference#quotas-and-the-ops-budget)). A one-off audit is fine; before auditing tags across a large number of campaigns, contact support so the capacity is provisioned.

## If it fails

The `{lpurl}` requirement is Google's, checked by Google. A template without it comes back as a forwarded platform error carrying Google's own code and message, and the campaign keeps the template it had:

```json
{
  "error": "Google Ads API error (400): The tracking url template must contain at least one tag (for example, {lpurl}).",
  "type": "platform_error",
  "code": "platform_api_error",
  "platform": "google",
  "platformError": {
    "code": 3,
    "message": "Request contains an invalid argument.",
    "details": [
      {
        "errors": [
          {
            "errorCode": { "urlFieldError": "MISSING_TRACKING_URL_TEMPLATE_TAG" },
            "message": "The tracking url template must contain at least one tag (for example, {lpurl})."
          }
        ]
      }
    ]
  }
}
```

Read `platformError.details[0].errors[0].errorCode`: the same shape carries `MALFORMED_TRACKING_URL_TEMPLATE` for a template that is not a URL and `INVALID_TAG_IN_TRACKING_URL_TEMPLATE` for a macro Google does not know. Put `{lpurl}` back and send the `PATCH` again. A `404` means `adId` is not an ad on one of your connected accounts.

## Related

- [Conversions](/platforms/google-ads/conversions): server-side conversion events for the same campaigns.
- [LinkedIn tracking tags](/platforms/linkedin-ads/tracking-tags) and [Meta Ads tracking tags](/platforms/meta-ads/tracking-tags): the same endpoint on the other networks.
- [Get ad tracking tags](/tracking-tags/get-ad-tracking-tags) and [Set ad tracking tags](/tracking-tags/update-ad-tracking-tags): every field.

---
