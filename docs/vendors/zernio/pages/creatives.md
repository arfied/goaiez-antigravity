# Creatives

Build Meta ad creatives on POST /v1/ads/create: attach to a running ad set, video, one asset per placement, carousels, multi-language ads, Advantage+ enhancements, swap the creative on a live ad, and read the media it serves.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

A creative is the headline, body, media, call to action and link of an ad. On `POST /v1/ads/create` it comes from the top-level fields of the [create request](/platforms/meta-ads/campaigns#create-the-full-tree-in-one-call); this page covers the other creative shapes, how to replace the creative on an ad that already runs, and how to read the media a live ad serves. Every JSON block below changes only the listed fields of that request, and the response is the same `{ ad, message }` as the full create.

## Attach a creative to an existing ad set

Pass `adSetId` with a single creative to add one ad to an ad set that already runs. Budget, targeting, schedule, goal and bid strategy come from the ad set on Meta's side, so omit them, and no new campaign is created. `platformAdSetId` from an earlier create or from the tree is the value to pass.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "act_1234567890",
  "adSetId": "120250000000000001",
  "name": "Spring sale #4",
  "headline": "One more angle",
  "body": "Social proof variant.",
  "imageUrl": "https://cdn.example.com/d.jpg",
  "linkUrl": "https://example.com/d",
  "callToAction": "SHOP_NOW"
}
```

Passing `bidStrategy` in attach mode returns a `400`; change a running ad set's bid on `PUT /v1/ads/ad-sets/{adSetId}`. `tracking` (pixel and URL tags) lives on the ad and is not inherited, so pass it on every attach call that should carry it. When the target is a lead ad set, pass `leadGenFormId` on every attached ad; Meta rejects a formless ad there. This is how you build N full ads that share one ad set: create the first one normally, then attach the rest one call each.

## Video creatives

Replace `imageUrl` with a `video` object on the single, multi-creative and attach shapes:

```json
{
  "goal": "video_views",
  "video": {
    "url": "https://cdn.example.com/spring.mp4",
    "thumbnailUrl": "https://cdn.example.com/spring-poster.jpg"
  }
}
```

`video` and `imageUrl` are mutually exclusive per creative; on the multi-creative shape put `video: { url, thumbnailUrl }` on each `creatives[]` entry. `thumbnailUrl` is optional: when you omit it, Meta generates the poster from its own preferred thumbnail (the same candidates Ads Manager shows), and the request fails with a `502` (`video_thumbnail_unavailable`) only when Meta produces none. Pass `video: { id }` instead of `url` to reuse a video already on the ad account (from an earlier create's `creative.videoId`, `POST /v1/ads/videos` or `GET /v1/ads/videos`), so N ads that differ only in copy share one upload.

Uploads are synchronous. Zernio sends the file to Meta in chunks and waits until Meta reports `status.video_status: "ready"`, which takes minutes on a long video. The wait is capped at 10 minutes: past that the create returns a `platform_error` and nothing is created. Set your HTTP client timeout above 10 minutes. The standalone `POST /v1/ads/videos` upload waits the same 10 minutes for transcoding, inside a handler that runs up to 800 seconds.

## Placement asset customization

`placementAssets` is Ads Manager's "use a different creative per placement" on one ad: a 9:16 asset on Stories and Reels, a 1:1 or 4:5 on Feed, all served by a single ad. Each rule pins one asset to one or more placements, and a default asset covers every placement no rule matches. A rule can also pin its own `headline`, `body` and `description`, so Feed and Stories can carry different copy in the same ad. The top-level `headline`, `body`, `description`, `linkUrl` and `callToAction` are the defaults for placements no rule matches, and for any field a rule leaves out.

```json
{
  "placementAssets": {
    "defaultImageUrl": "https://cdn.example.com/1x1.jpg",
    "rules": [
      {
        "imageUrl": "https://cdn.example.com/9x16.jpg",
        "placements": {
          "publisherPlatforms": ["instagram", "facebook"],
          "instagramPositions": ["story", "reels"],
          "facebookPositions": ["story", "facebook_reels"]
        }
      },
      {
        "imageUrl": "https://cdn.example.com/4x5.jpg",
        "placements": {
          "instagramPositions": ["stream"],
          "facebookPositions": ["feed"]
        }
      }
    ]
  }
}
```

For video, swap `imageUrl` for `videoUrl` and `defaultImageUrl` for `defaultVideoUrl`; `thumbnailUrl` and `defaultThumbnailUrl` are optional posters.

| Field | Type | Meaning |
|-------|------|---------|
| `defaultImageUrl` or `defaultVideoUrl` | string | Required. The catch-all asset for placements no rule matches. Exactly one of the two. |
| `defaultThumbnailUrl` | string | Optional poster for `defaultVideoUrl`. |
| `rules[]` | object[] | 1 to 10 rules, each pinning one asset to one or more placements. |
| `rules[].imageUrl` or `rules[].videoUrl` | string | The asset for this rule, in the block's media mode. |
| `rules[].thumbnailUrl` | string | Optional poster for `videoUrl`. |
| `rules[].placements` | object | At least one field (`publisherPlatforms` or a `*Positions` field). Same values as the `placements` object in the [reference](/platforms/meta-ads/reference#placements). |

<Callout type="warn">
A `placementAssets` block is all-image or all-video, never mixed: `imageUrl` plus `defaultImageUrl` throughout, or `videoUrl` plus `defaultVideoUrl` throughout. Mixing the two returns a `400`.
</Callout>

Per-placement copy is **pinned, not rotated**. Each placement receives exactly the copy its rule specifies. Meta is not given a pool of headlines to optimize between, which is what `dynamicCreative` does. If a rule sets only `description`, the other placements receive a blank description rather than inheriting one, so set every field you want on a rule that overrides any of them.

```json
{
  "headline": "Default headline",
  "body": "Default primary text",
  "placementAssets": {
    "defaultImageUrl": "https://cdn.example.com/1x1.jpg",
    "rules": [
      {
        "imageUrl": "https://cdn.example.com/9x16.jpg",
        "headline": "Swipe up",
        "body": "Made for Stories",
        "placements": { "instagramPositions": ["story", "reels"] }
      },
      {
        "imageUrl": "https://cdn.example.com/4x5.jpg",
        "headline": "Shop the collection",
        "body": "Made for Feed",
        "placements": { "instagramPositions": ["stream"] }
      }
    ]
  }
}
```

`placementAssets` builds one placement-customized ad, so it is mutually exclusive with `creatives[]` and `dynamicCreative`; it works on the attach shape (`adSetId`) too. Meta enforces placement co-selection rules (`profile_feed` requires `feed`) and its error surfaces verbatim. It is distinct from [creative testing](/platforms/meta-ads/creative-testing) (N separate ads Meta A/B tests) and from `dynamicCreative` (an asset pool Meta auto-optimizes): `placementAssets` is deterministic. On `goal: "lead_generation"` with a `leadGenFormId` it works too; `dynamicCreative` with a lead form returns a `422`, because it needs a dedicated Dynamic Creative ad set. Meta suppresses primary text and headline on Stories and Reels in delivery, so copy that must be visible there has to be part of the asset itself.

## Carousel ads

`carouselCards` builds a hand-made carousel: 2 to 10 cards in the order you choose, unlike the auto-optimizing `dynamicCreative` pool.

```json
{
  "body": "Three drops, one collection.",
  "linkUrl": "https://example.com/collection",
  "callToAction": "SHOP_NOW",
  "carouselCards": [
    { "imageUrl": "https://cdn.example.com/1.jpg", "headline": "The classic", "linkUrl": "https://example.com/classic" },
    { "imageUrl": "https://cdn.example.com/2.jpg", "headline": "The bold", "description": "New colorway" },
    { "imageUrl": "https://cdn.example.com/3.jpg", "headline": "The limited", "callToAction": "LEARN_MORE" }
  ]
}
```

Per card, `imageUrl` is required; `headline`, `description`, `linkUrl` and `callToAction` are optional and fall back to the top-level values. The top-level `body` is the primary text above the carousel, `linkUrl` is the see-more link Meta requires, and `callToAction` is the default button. Per-card image hashes come back on the created ad's `creative.carouselCards`. `carouselCards` is mutually exclusive with `imageUrl`, `video`, `creatives[]`, `adSetId`, `dynamicCreative`, `placementAssets`, `existingCreativeId`, `leadGenFormId` and `goal: "catalog_sales"`; the `400` names the conflicting field. Read per-card performance with the [creative asset breakdowns](/platforms/meta-ads/insights#demographic-and-placement-breakdowns).

## Multi-language ads

`translations` carries per-locale copy on one ad (Meta's Dynamic Language Optimization), so likes, comments and shares stay on a single post instead of splitting across one ad per language. `defaultLocale` names the language the top-level copy is written in, and must not appear in `translations`.

```json
{
  "defaultLocale": "en",
  "headline": "Ship every post from one API",
  "body": "Publish to 15 networks with one call.",
  "description": "Schedule, publish, measure.",
  "translations": [
    { "locale": "es", "headline": "Publica desde una sola API", "body": "Llega a 15 redes con una llamada.", "description": "Programa, publica, mide." },
    { "locale": "pt_BR", "headline": "Publique a partir de uma API", "body": "Alcance 15 redes com uma chamada.", "description": "Agende, publique, meça." }
  ]
}
```

Text does not inherit: every entry needs its own `headline`, `body` and `description`, and all 3 must differ from the other locales and from the top-level copy, because Meta collapses identical strings into one asset and then fails the create. Zernio checks that before calling Meta and returns a `400` naming the locale and the field. Media and `linkUrl` do inherit. At most 10 entries, and `translations` is mutually exclusive with `dynamicCreative`, `placementAssets`, `carouselCards`, `existingCreativeId` and `creatives[]`.

## Advantage+ creative enhancements

`creativeFeatures` opts a creative in or out of Meta's individual Advantage+ enhancements (adjusted brightness, enhanced CTA, text optimizations). It is accepted on every `POST /v1/ads/create` shape and on [`POST /v1/ads/creatives`](/platforms/meta-ads/creative-library#create-a-creative), and forwarded as Meta's `degrees_of_freedom_spec.creative_features_spec`:

```json
{
  "creativeFeatures": {
    "enhance_cta": "OPT_IN",
    "image_brightness_and_contrast": "OPT_IN",
    "text_optimizations": "OPT_OUT"
  }
}
```

The map is partial: keys are Meta's snake_case feature names, and anything you leave out defaults to `OPT_OUT`. An unknown key returns Meta's `400` verbatim, which lists the accepted keys (a read-back returns about 30). Meta deprecated the `standard_enhancements` bundle (subcode `3858504`): old creatives still carrying it cannot be [duplicated](/platforms/meta-ads/lifecycle#duplicate-an-ad-set-or-an-ad), so rebuild them with `creativeFeatures`. `multiAdvertiser: "OPT_OUT"` is a separate top-level field, not a `creativeFeatures` key.

`creativeFeatures` is accepted on every creative shape, single, attach (`adSetId`) and each entry of `creatives[]`; with `creatives[]` a top-level map sets the default and a per-item map replaces it entirely, including with `{}` to clear inherited enrollment.

## Promotion offers

`promotion` attaches an explicit Meta offer to a creative, separate from the `auto_promotion_tag` enhancement above. It is accepted on `POST /v1/ads/create` (single, attach and `creatives[]` shapes) and on [`POST /v1/ads/creatives`](/platforms/meta-ads/creative-library#create-a-creative):

```json
{
  "promotion": {
    "type": "PERCENTAGE_OFF",
    "value": 20,
    "code": "SAVE20",
    "startDate": "2026-10-01T00:00:00Z",
    "endDate": "2026-10-31T23:59:59Z"
  }
}
```

`type` and a nonnegative `value` are required. `type` is one of `AMOUNT_OFF`, `FREE_RETURN`, `FREE_SHIPPING`, `PERCENTAGE_OFF` or `PROMO_CODE`; `PERCENTAGE_OFF` is the percentage discount and cannot exceed 100. `AMOUNT_OFF` units are not confirmed, so do not assume major or minor currency units. `code` is optional and must be nonempty when supplied. Optional `startDate` and `endDate` use ISO 8601 timestamps with a timezone offset or `Z`, and the end must be after the start when both are set.

On `creatives[]`, a top-level `promotion` sets the default; an item's `promotion` replaces the whole offer, and `promotion: null` disables the inherited offer for that item. On a new creative, `null` omits the offer; when rebuilding one, it removes the explicit offer. `existingCreativeId` reuses that creative's settings instead of applying new promotion or feature settings.

Creation success alone does not confirm that Meta applied or will display the offer. Only ads supplied a `promotion` get a live readback, exposed as `ad.creative.promotion` with `ad.creative.promotionStatus`, or the corresponding fields in `ads[]` for a multi-create. `applied` means Meta returned the offer, `not_returned` means the read succeeded without promotion metadata, and `unavailable` means the read itself failed. Missing metadata is not confirmation that Ads Manager displays the requested promotion.

Lists, exports and default ad-detail reads contain the stored requested `promotion` and `creativeFeatures`, which do not prove platform application. On [`GET /v1/ads/{adId}`](/ad-campaigns/get-ad), pass `refreshPromotion=true` to re-check promotion metadata live and inspect `promotionStatus`.

## Call-to-action values

`callToAction` takes one of the values in the [reference](/platforms/meta-ads/reference#call-to-action-values). `CALL_NOW` and the messaging values are not among them on purpose: [`POST /v1/ads/call` and `POST /v1/ads/messaging`](/platforms/meta-ads/messaging-ads) build the ad set those buttons require.

## Swap the creative on a live ad

Creatives are immutable on Meta beyond their name, so editing an ad's creative means building a new one and pointing the ad at it. `PUT /v1/ads/{adId}` with a `creative` object does both in one call.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: swapped } = await zernio.adcampaigns.updateAd({
  path: { adId: '66d4a1b2c3e4f5a6b7c8d9e2' },
  body: {
    creative: {
      headline: 'New angle',
      body: 'Same offer, sharper copy.',
      callToAction: 'SHOP_NOW',
      linkUrl: 'https://example.com/spring',
      imageUrl: 'https://cdn.example.com/v2.jpg'
    }
  }
});

console.log(swapped.ad.creative.creativeId);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

swapped = client.ad_campaigns.update_ad(
    ad_id="66d4a1b2c3e4f5a6b7c8d9e2",
    creative={
        "headline": "New angle",
        "body": "Same offer, sharper copy.",
        "callToAction": "SHOP_NOW",
        "linkUrl": "https://example.com/spring",
        "imageUrl": "https://cdn.example.com/v2.jpg",
    },
)

print(swapped["ad"]["creative"]["creativeId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT "https://zernio.com/api/v1/ads/66d4a1b2c3e4f5a6b7c8d9e2" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "creative": {
      "headline": "New angle",
      "body": "Same offer, sharper copy.",
      "callToAction": "SHOP_NOW",
      "linkUrl": "https://example.com/spring",
      "imageUrl": "https://cdn.example.com/v2.jpg"
    }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9e2",
    "status": "pending_review",
    "reviewStatus": "in_review",
    "creative": {
      "creativeId": "120250000000000006",
      "imageUrl": "https://cdn.example.com/v2.jpg",
      "linkUrl": "https://example.com/spring",
      "body": "Same offer, sharper copy."
    }
  },
  "message": "Ad updated"
}
```

On Meta the patch is partial: fields you omit are kept from the live creative, media included. Use `videoUrl` or `videoId` instead of `imageUrl` for a video swap, or `existingCreativeId` to point the ad at a [library creative](/platforms/meta-ads/creative-library). The same endpoint accepts `targeting`, which lands on the ad's ad set, and `name`. The swapped creative goes through Meta review again.

## Read an ad's media

`GET /v1/ads/{adId}/media` returns the direct file URLs of every image and video the ad's live creative uses, normalised across shapes: single image or video, carousel, Reels and Stories, and dynamic creative. `adId` is the Zernio id or the platform ad id.

```bash
curl "https://zernio.com/api/v1/ads/66d4a1b2c3e4f5a6b7c8d9e2/media" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "adId": "66d4a1b2c3e4f5a6b7c8d9e2",
  "platform": "facebook",
  "media": [
    { "type": "video", "url": "https://video.xx.fbcdn.net/v/t42.1790-2/...", "thumbnailUrl": "https://scontent.xx.fbcdn.net/v/t15.5256-10/...", "videoId": "1234567890123456", "length": 21.4, "index": 0 }
  ]
}
```

The read goes to Meta live rather than to the stored creative because the URLs are signed and expire: about 24 hours for an image, about 12 days for a video source. Fetch this endpoint again when you need the file, and never store the URL as if it were permanent. `videoId` is reusable as `video.id` on the create endpoints.

## If it fails

A `400` naming two fields means you combined creative shapes that exclude each other:

```json
{
  "error": "carouselCards is mutually exclusive with imageUrl",
  "type": "invalid_request_error",
  "param": "carouselCards"
}
```

Send exactly one media source per creative: `imageUrl`, `video`, `carouselCards`, `dynamicCreative`, `placementAssets` or `existingCreativeId`. A `502` with `type: "platform_error"` means Meta accepted the request and then failed to produce the media (upload session, transcoding timeout, no image hash); the raw Meta payload is in `platformError`.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the base create request every block above modifies.
- [Creative library](/platforms/meta-ads/creative-library): standalone creatives, `existingCreativeId` and the image library.
- [Previews](/platforms/meta-ads/previews): render a creative before or after it exists.
- [Ad media](/ad-creatives/get-ad-media): every field of the media read.
- [Reference](/platforms/meta-ads/reference): call-to-action values, placements and media limits.
- [Create standalone ad](/ad-campaigns/create-standalone-ad) and [Update ad](/ad-campaigns/update-ad): every field.

---
