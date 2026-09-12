# Ad URL Tracking Tags

Read and set the click-URL parameters on a Meta ad with GET and PATCH /v1/ads/{adId}/tracking-tags, without rebuilding the creative yourself.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Audit or fix the attribution parameters on an ad's click destination with `GET` and `PATCH /v1/ads/{adId}/tracking-tags`. These are Meta's `url_tags`, the UTM-style query parameters appended to the landing page URL. They are unrelated to [Meta Pixels](/platforms/meta-ads/pixels), which share the path segment but measure events.

## Read the tags on an ad

The read works on ads Zernio created and on ads discovered from Ads Manager.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: tags } = await zernio.trackingtags.getAdTrackingTags({
  path: { adId: '66d4a1b2c3e4f5a6b7c8d9e2' }
});

console.log(tags.urlTags);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

tags = client.tracking_tags.get_ad_tracking_tags(ad_id="66d4a1b2c3e4f5a6b7c8d9e2")

print(tags["urlTags"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/66d4a1b2c3e4f5a6b7c8d9e2/tracking-tags" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "facebook",
  "level": "creative",
  "urlTags": "utm_source=meta&utm_medium=cpc",
  "templateUrlSpec": null
}
```

`urlTags` is the `&`-joined parameter string and `templateUrlSpec` is Meta's third-party click-tracking template, used by dynamic ads. `level` says which object holds them; on Meta that is the creative.

## Set the tags

`PATCH` takes `urlTags` as an array of `{ key, value }` pairs. Meta creatives are immutable, so the update rebuilds the creative and repoints the ad. Zernio preserves the existing creative verbatim, re-posting its current headline, body, call to action and image, which it reuses by hash rather than re-uploading, so `urlTags` is all you send.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
await zernio.trackingtags.updateAdTrackingTags({
  path: { adId: '66d4a1b2c3e4f5a6b7c8d9e2' },
  body: {
    urlTags: [
      { key: 'utm_source', value: 'meta' },
      { key: 'utm_medium', value: 'cpc' },
      { key: 'utm_content', value: '{{ad.id}}' }
    ]
  }
});
```
</Tab>
<Tab value="Python">
```python
client.tracking_tags.update_ad_tracking_tags(
    ad_id="66d4a1b2c3e4f5a6b7c8d9e2",
    url_tags=[
        {"key": "utm_source", "value": "meta"},
        {"key": "utm_medium", "value": "cpc"},
        {"key": "utm_content", "value": "{{ad.id}}"},
    ],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X PATCH "https://zernio.com/api/v1/ads/66d4a1b2c3e4f5a6b7c8d9e2/tracking-tags" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "urlTags": [
      { "key": "utm_source", "value": "meta" },
      { "key": "utm_medium", "value": "cpc" },
      { "key": "utm_content", "value": "{{ad.id}}" }
    ]
  }'
```
</Tab>
</Tabs>

The `PATCH` answers with the rebuilt creative's tags, so no read-back is needed.

Response (`200`):

```json
{
  "platform": "facebook",
  "level": "creative",
  "urlTags": "utm_source=meta&utm_medium=cpc&utm_content={{ad.id}}",
  "templateUrlSpec": null
}
```

Meta's dynamic macros (`{{ad.id}}`, `{{campaign.id}}`, `{{placement}}` and the rest) go through unescaped so Meta expands them; every other character is percent-encoded. The rebuilt creative goes through Meta review again.

`creative` is optional and takes `headline`, `body`, `callToAction`, `linkUrl` and `imageUrl` together. Send it only to rebuild the creative explicitly, or for the creatives Zernio cannot preserve.

## Set tags at create time

`tracking.urlTags` (and `tracking.pixelId` for pixel measurement) is also accepted on every Meta create flow: the `POST /v1/ads/create` single, multi-creative and attach shapes, `POST /v1/ads/boost`, and `POST /v1/ads/messaging` / `POST /v1/ads/ctwa`. It lands on the new creative's `url_tags`, the same place this page's `GET` reads from, so a value set at create now reads back correctly instead of null. The ad also keeps its own copy for compatibility. Because tracking lives on the ad object rather than the ad set, resend `tracking` on every attach call (`adSetId`) that should carry the pixel; it is not inherited.

```json
{
  "tracking": {
    "pixelId": "1729525464415281",
    "urlTags": [
      { "key": "utm_source", "value": "meta" },
      { "key": "utm_medium", "value": "cpc" }
    ]
  }
}
```

## Common errors

A `422` means Meta stripped the creative's `object_story_spec`, so there is nothing to preserve:

```json
{
  "error": "Meta creative cannot be rebuilt; supply creative",
  "type": "invalid_request_error",
  "param": "creative"
}
```

Send the `creative` object with all 5 fields. This is what SHARE, page-post boost, dark post and Advantage+ asset-feed creatives return. A `405` means the ad's platform has no click-URL tracking surface, which covers TikTok, X and Pinterest. A `502` means Meta accepted the rebuild and then failed to produce the media; read `platformError.reason`.

## Related

- [Pixels](/platforms/meta-ads/pixels): the measurement tags on the same path segment.
- [Creatives](/platforms/meta-ads/creatives#swap-the-creative-on-a-live-ad): the other way to replace a live creative.
- [Campaigns](/platforms/meta-ads/campaigns): `tracking.urlTags` sets the same parameters at create time.
- [Read tracking tags](/tracking-tags/get-ad-tracking-tags) and [Update tracking tags](/tracking-tags/update-ad-tracking-tags): every field.

---
