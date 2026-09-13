# Previews

Render a Meta ad, or a creative that does not exist yet, as Meta renders it, with GET /v1/ads/{adId}/preview and POST /v1/ads/preview.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Render an ad the way Meta renders it with `GET /v1/ads/{adId}/preview`, or a creative that does not exist on an ad yet with `POST /v1/ads/preview`. Both return Meta's `<iframe>` HTML, one snippet per requested format, which you drop into your UI so the user sees the real ad in the real placement without opening Ads Manager. Meta only.

## Preview an existing ad

Call `GET /v1/ads/{adId}/preview` with the Zernio ad id and up to 10 comma-separated `formats`. Omitting `formats` returns `DESKTOP_FEED_STANDARD`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: rendered } = await zernio.adcreatives.getAdPreviews({
  path: { adId: '66d4a1b2c3e4f5a6b7c8d9e2' },
  query: { formats: 'DESKTOP_FEED_STANDARD,INSTAGRAM_STORY' }
});

for (const preview of rendered.previews) console.log(preview.format, preview.html);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

rendered = client.ad_creatives.get_ad_previews(
    ad_id="66d4a1b2c3e4f5a6b7c8d9e2",
    formats="DESKTOP_FEED_STANDARD,INSTAGRAM_STORY",
)

for preview in rendered["previews"]:
    print(preview["format"], preview["html"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/66d4a1b2c3e4f5a6b7c8d9e2/preview?formats=DESKTOP_FEED_STANDARD,INSTAGRAM_STORY" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "adId": "66d4a1b2c3e4f5a6b7c8d9e2",
  "previews": [
    { "format": "DESKTOP_FEED_STANDARD", "html": "<iframe src=\"https://www.facebook.com/ads/api/preview_iframe.php?d=...\" width=\"540\" height=\"690\" scrolling=\"yes\" style=\"border: none;\"></iframe>" },
    { "format": "INSTAGRAM_STORY", "html": "<iframe src=\"https://www.facebook.com/ads/api/preview_iframe.php?d=...\" width=\"320\" height=\"580\" scrolling=\"yes\" style=\"border: none;\"></iframe>" }
  ]
}
```

`html` is `null` for a format Meta returned no preview for.

## Preview before you create

Call `POST /v1/ads/preview` with `accountId`, `adAccountId` and exactly one of `existingCreativeId` (a creative from an earlier create or the [library](/platforms/meta-ads/creative-library)) or `creativeSpec` (a raw Meta creative spec, forwarded verbatim). `formats` is optional here too, defaulting to `[DESKTOP_FEED_STANDARD]`, and takes up to 10 values.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: draft } = await zernio.adcreatives.generateAdPreviews({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    existingCreativeId: '120250000000000005',
    formats: ['DESKTOP_FEED_STANDARD', 'INSTAGRAM_STORY']
  }
});
```
</Tab>
<Tab value="Python">
```python
draft = client.ad_creatives.generate_ad_previews(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    existing_creative_id="120250000000000005",
    formats=["DESKTOP_FEED_STANDARD", "INSTAGRAM_STORY"],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/preview" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "existingCreativeId": "120250000000000005",
    "formats": ["DESKTOP_FEED_STANDARD", "INSTAGRAM_STORY"]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "previews": [
    { "format": "DESKTOP_FEED_STANDARD", "html": "<iframe src=\"https://www.facebook.com/ads/api/preview_iframe.php?d=...\" width=\"540\" height=\"690\" scrolling=\"yes\" style=\"border: none;\"></iframe>" },
    { "format": "INSTAGRAM_STORY", "html": "<iframe src=\"https://www.facebook.com/ads/api/preview_iframe.php?d=...\" width=\"320\" height=\"580\" scrolling=\"yes\" style=\"border: none;\"></iframe>" }
  ]
}
```

To preview a design that is not in the ad account yet, replace `existingCreativeId` with `creativeSpec`. `link_data.picture` takes a plain public image URL, so no upload is needed, which makes this the cheap way to put a live preview inside a creative editor:

```json
{
  "creativeSpec": {
    "object_story_spec": {
      "page_id": "811889972008357",
      "link_data": {
        "link": "https://example.com",
        "message": "Primary text",
        "name": "Headline",
        "picture": "https://cdn.example.com/i.jpg",
        "call_to_action": { "type": "LEARN_MORE", "value": { "link": "https://example.com" } }
      }
    }
  }
}
```

## Formats

`formats` passes through to Meta's `ad_format` enum, which has about 60 values that change over time. The ones you reach for most:

| Format | Placement |
|---|---|
| `DESKTOP_FEED_STANDARD` | Facebook desktop feed |
| `MOBILE_FEED_STANDARD` | Facebook mobile feed |
| `INSTAGRAM_STANDARD` | Instagram feed |
| `INSTAGRAM_STORY` | Instagram Stories |
| `INSTAGRAM_REELS` | Instagram Reels |
| `FACEBOOK_STORY_MOBILE` | Facebook Stories |
| `MOBILE_BANNER` | Audience Network banner |

## Rendering the snippet

Each entry in `previews[]` is an `<iframe>` pointing at a signed Meta URL. Render it as-is: rewriting the markup or proxying the frame breaks the signature. The URL is short-lived, so fetch previews when the user opens the panel instead of caching them. The frame has fixed dimensions per format; scale it with CSS `transform` rather than changing the width and height attributes.

## If it fails

A `400` on an unknown format carries Meta's own message, which lists every valid `ad_format` value and is always current:

```json
{
  "error": "Invalid parameter: ad_format must be one of DESKTOP_FEED_STANDARD, MOBILE_FEED_STANDARD, ...",
  "type": "platform_error",
  "platform": "meta"
}
```

Pick a value from that list. A `429` means Meta's rate limit; wait and retry.

## Related

- [Creative library](/platforms/meta-ads/creative-library): the creative ids `existingCreativeId` accepts.
- [Creatives](/platforms/meta-ads/creatives): the shapes a preview can render.
- [Render pre-create ad previews](/ad-creatives/generate-ad-previews) and [Render previews of an existing ad](/ad-creatives/get-ad-previews): every field.

---
