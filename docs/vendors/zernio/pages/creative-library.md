# Creative Library

Manage Meta ad creatives as standalone objects with GET and POST /v1/ads/creatives, and upload, list and delete the ad account's image and video assets.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

A creative on Meta outlives the ad that uses it. `GET /v1/ads/creatives` lists the ad account's creatives, `POST /v1/ads/creatives` makes one without an ad, and the image and video endpoints fill the ad account's asset library. Any creative id from here is reusable as `existingCreativeId` on [`POST /v1/ads/create`](/platforms/meta-ads/campaigns) or [`POST /v1/ads/preview`](/platforms/meta-ads/previews#preview-before-you-create). You need `accountId` and `adAccountId`; every endpoint here is Meta only.

## List creatives

Call `GET /v1/ads/creatives` with `accountId` and `adAccountId`. Rows are Meta's `/adcreatives` rows verbatim: id, name, status, object type, thumbnail, and the story or asset feed spec.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: creatives } = await zernio.adcreatives.listAdCreatives({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', adAccountId: 'act_1234567890', limit: 50 }
});

const nextCursor = creatives.paging.after;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

creatives = client.ad_creatives.list_ad_creatives(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    limit=50,
)

next_cursor = creatives["paging"]["after"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/creatives?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890&limit=50" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "adAccountId": "act_1234567890",
  "data": [
    {
      "id": "120250000000000005",
      "name": "Spring sale - US feed",
      "status": "ACTIVE",
      "object_type": "SHARE",
      "thumbnail_url": "https://scontent.xx.fbcdn.net/...",
      "object_story_spec": { "page_id": "811889972008357", "link_data": { "link": "https://example.com/spring" } }
    }
  ],
  "paging": { "after": "QVFIUm..." }
}
```

`fields` overrides the default projection, `limit` and `after` page. `GET /v1/ads/creatives/{creativeId}?accountId=...` returns one creative the same way. The creative node rejects `created_time` even though the list edge returns it, so Zernio's default projection drops that field on the node read; a `fields` value you send is forwarded to Meta unchanged, and `fields=id,name,created_time` on the node still comes back as Meta's `#100`.

## Create a creative

Call `POST /v1/ads/creatives` with `headline`, `body`, `linkUrl` and exactly one of:

| Media field | Source |
|---|---|
| `imageUrl` | A public URL, uploaded to the ad account server-side |
| `imageHash` | A hash from [`POST /v1/ads/images`](#image-library) or the image library list |
| `carouselCards` | 2 to 10 cards, the same shape as [carousel ads](/platforms/meta-ads/creatives#carousel-ads), except every card needs `linkUrl` as well as `imageUrl` |

The Page, and the linked Instagram account when there is one, resolve from `accountId`, so there is no Page id in the body.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: creative } = await zernio.adcreatives.createAdCreative({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    headline: 'Spring drop is live',
    body: 'Three colourways, limited run.',
    linkUrl: 'https://example.com/spring',
    imageUrl: 'https://cdn.example.com/spring.jpg',
    creativeFeatures: { enhance_cta: 'OPT_IN' }
  }
});

const creativeId = creative.creativeId;
```
</Tab>
<Tab value="Python">
```python
creative = client.ad_creatives.create_ad_creative(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    headline="Spring drop is live",
    body="Three colourways, limited run.",
    link_url="https://example.com/spring",
    image_url="https://cdn.example.com/spring.jpg",
    creative_features={"enhance_cta": "OPT_IN"},
)

creative_id = creative["creativeId"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/creatives" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "headline": "Spring drop is live",
    "body": "Three colourways, limited run.",
    "linkUrl": "https://example.com/spring",
    "imageUrl": "https://cdn.example.com/spring.jpg",
    "creativeFeatures": { "enhance_cta": "OPT_IN" }
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "adAccountId": "act_1234567890",
  "creativeId": "120250000000000007"
}
```

`imageUrl` is the shortest path: Meta fetches the file and uploads it into the ad account for you. Send `imageHash` instead when the image is already in the library ([image library](#image-library)). `callToAction` defaults to `LEARN_MORE`, `description` and `urlTags` are optional, `creativeFeatures` follows the [Advantage+ rules](/platforms/meta-ads/creatives#advantage-creative-enhancements), and `promotion` attaches an explicit offer ([Promotion offers](/platforms/meta-ads/creatives#promotion-offers)) with a live readback only when supplied. The create call takes no `name`; set one afterwards with [rename](#rename-a-creative). Creative names are unique per ad account, deleted creatives included, so a name you deleted last month still collides; suffix generated names with a timestamp or a counter.

### Promotion readback

The optional `promotion` input uses the same [offer fields and validation](/platforms/meta-ads/creatives#promotion-offers): required `type` and nonnegative `value`, optional `code`, `startDate` and `endDate`, or `null` to omit the offer on a new creative. It is separate from `creativeFeatures.auto_promotion_tag`.

Only when you supply `promotion` does `POST /v1/ads/creatives` read the creative back from Meta. The response fields are top-level `promotion` and `promotionStatus`, alongside `creativeId`:

```json
{
  "adAccountId": "act_1234567890",
  "creativeId": "120250000000000007",
  "promotion": null,
  "promotionStatus": "not_returned"
}
```

`applied` confirms that Meta returned promotion metadata. `not_returned` means the read succeeded without that metadata, and `unavailable` means the read failed. A successful create alone does not prove the offer was applied or will display. Attaching this creative with `existingCreativeId` preserves its settings; new promotion settings do not modify the reused creative.

## Rename a creative

Call `PUT /v1/ads/creatives/{creativeId}` with `accountId` and `name`. The name is the only mutable field: to change copy, media or the button, create a new creative and swap it onto the ad with [`PUT /v1/ads/{adId}`](/platforms/meta-ads/creatives#swap-the-creative-on-a-live-ad). The old creative keeps its own reporting history.

```json
{ "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "name": "Spring drop v2" }
```

Response (`200`):

```json
{ "creativeId": "120250000000000007", "name": "Spring drop v2", "message": "Creative renamed" }
```

A `GET` issued right after the rename can return the old name for a few seconds; re-read after a moment rather than treating it as a failure.

## Delete a creative

`DELETE /v1/ads/creatives/{creativeId}?accountId=...` succeeds only when no ad references the creative; otherwise Meta's `400` surfaces verbatim. Delete or repoint the ads first.

## Asset library

### Image library

`GET /v1/ads/images` lists the ad account's `/adimages` rows: `hash`, a Meta-hosted `url`, name, dimensions and status. Any `hash` is reusable wherever Meta accepts an image hash, `imageHash` above included.

`POST /v1/ads/images` uploads an image from base64, for creatives whose media has no public URL. The limit is 30 MB decoded.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: upload } = await zernio.adcreatives.uploadAdImage({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    imageBase64: 'data:image/jpeg;base64,/9j/4AAQSkZJRg...',
    filename: 'promo.jpg'
  }
});

const imageHash = upload.image.hash;
```
</Tab>
<Tab value="Python">
```python
upload = client.ad_creatives.upload_ad_image(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    image_base64="data:image/jpeg;base64,/9j/4AAQSkZJRg...",
    filename="promo.jpg",
)

image_hash = upload["image"]["hash"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/images" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "imageBase64": "data:image/jpeg;base64,/9j/4AAQSkZJRg...",
    "filename": "promo.jpg"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "adAccountId": "act_1234567890",
  "image": {
    "hash": "2f1c8e0a9b7d6c5e4f3a2b1c0d9e8f7a",
    "url": "https://scontent.xx.fbcdn.net/v/t45.1600-4/..."
  }
}
```

Both halves are useful: `hash` goes wherever Meta wants an image hash, and `url` is Meta-hosted and public, so it works directly as `imageUrl` on the create endpoints, the shortest path from bytes in memory to a running ad. With a hash in hand, the create above becomes:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "act_1234567890",
  "headline": "Spring drop is live",
  "body": "Three colourways, limited run.",
  "linkUrl": "https://example.com/spring",
  "imageHash": "2f1c8e0a9b7d6c5e4f3a2b1c0d9e8f7a"
}
```
 Meta dedupes uploads by content, so uploading the same bytes twice returns the same hash.

### Video library

Video has the same pair of calls plus a delete. `POST /v1/ads/videos` takes a public `videoUrl` (downloaded server-side) or `videoBase64` (capped around 4.5 MB by the request body limit) and blocks until Meta reports the video ready, which chunked upload and transcoding can stretch to minutes. It returns `video.id`, reusable as `video: { id }` on create and inside a [preview](/platforms/meta-ads/previews) creative spec, plus Meta's poster URL when it has produced one.

`GET /v1/ads/videos` lists the account's videos, including ones uploaded in Ads Manager or another tool, which is the only way to reach those. A row is usable once its `status.video_status` reads `ready`, and each row carries `source`, a signed playable MP4 URL that expires: play it to pick a video, never store it.

`DELETE /v1/ads/videos/{videoId}?accountId=...&adAccountId=...` removes one from the library and returns `{ "success": true }` from Meta verbatim. Both query parameters are required, and the ad account must be the one that owns the video.

## If it fails

A `422` on create means no Facebook Page could act as the creative's story actor:

```json
{
  "error": "No Facebook Page found to act as the story actor",
  "type": "invalid_request_error"
}
```

Pass an `accountId` that is a connected Facebook account, or an Instagram account connected with Facebook Login, on the same profile as the ad account ([before you start](/platforms/meta-ads#before-you-start)).

## Related

- [Creatives](/platforms/meta-ads/creatives): the creative shapes on `POST /v1/ads/create` and swapping a creative on a live ad.
- [Previews](/platforms/meta-ads/previews): render a library creative by id.
- [Account reads](/platforms/meta-ads/operational-reads#ad-labels): the ad labels Ads Manager groups creatives with.
- [Creative library](/ad-creatives/list-ad-creatives) and [Upload an ad image](/ad-creatives/upload-ad-image): every field.
- [Upload an ad video](/ad-creatives/upload-ad-video), [Ad video library](/ad-creatives/list-ad-videos) and [Delete an ad video](/ad-creatives/delete-ad-video): the video half.

---
