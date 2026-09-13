# Boost a Post

Turn a published Facebook or Instagram post into a Meta ad with POST /v1/ads/boost, keeping the post's likes and comments.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Turn a published post into an ad with `POST /v1/ads/boost`. The post keeps its likes, comments and shares, and Zernio builds the campaign, ad set and ad around it. You need the Zernio `postId` (or Meta's `platformPostId`), `accountId` and `adAccountId`. Unlike `POST /v1/ads/create`, the body nests `budget`, `schedule` and `targeting`.

## The boost request

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: boosted } = await zernio.adcampaigns.boostPost({
  body: {
    postId: '65f1c0a9e2b5af0012ab34cd',
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Spring launch boost',
    goal: 'traffic',
    budget: { amount: 40, type: 'daily' },
    schedule: { startDate: '2027-03-01T09:00:00Z', endDate: '2027-03-08T09:00:00Z' },
    targeting: {
      ageMin: 25,
      ageMax: 45,
      countries: ['US', 'CA'],
      interests: [{ id: '6003139266461', name: 'DevOps' }]
    },
    linkUrl: 'https://example.com/spring',
    callToAction: 'LEARN_MORE'
  }
});

const adId = boosted.ad._id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

boosted = client.ad_campaigns.boost_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Spring launch boost",
    goal="traffic",
    budget={"amount": 40, "type": "daily"},
    schedule={"startDate": "2027-03-01T09:00:00Z", "endDate": "2027-03-08T09:00:00Z"},
    targeting={
        "ageMin": 25,
        "ageMax": 45,
        "countries": ["US", "CA"],
        "interests": [{"id": "6003139266461", "name": "DevOps"}],
    },
    link_url="https://example.com/spring",
    call_to_action="LEARN_MORE",
)

ad_id = boosted["ad"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/boost" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "postId": "65f1c0a9e2b5af0012ab34cd",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "name": "Spring launch boost",
    "goal": "traffic",
    "budget": { "amount": 40, "type": "daily" },
    "schedule": { "startDate": "2027-03-01T09:00:00Z", "endDate": "2027-03-08T09:00:00Z" },
    "targeting": {
      "ageMin": 25,
      "ageMax": 45,
      "countries": ["US", "CA"],
      "interests": [{ "id": "6003139266461", "name": "DevOps" }]
    },
    "linkUrl": "https://example.com/spring",
    "callToAction": "LEARN_MORE"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9e3",
    "name": "Spring launch boost",
    "platform": "facebook",
    "status": "pending_review",
    "reviewStatus": "in_review",
    "adType": "boost",
    "goal": "traffic",
    "budget": { "amount": 40, "type": "daily" },
    "schedule": { "startDate": "2027-03-01T09:00:00Z", "endDate": "2027-03-08T09:00:00Z" },
    "platformAdId": "120260000000000001",
    "platformCampaignId": "120250000000000002",
    "platformAdSetId": "120250000000000003",
    "creative": {
      "effectiveObjectStoryId": "811889972008357_1029384756",
      "pageId": "811889972008357"
    }
  },
  "message": "Ad created and submitted for review"
}
```

`goal` is one of `engagement`, `traffic`, `awareness`, `video_views`, `lead_generation`, `conversions` or `app_promotion`. `budget` is in whole currency units. `schedule.startDate` and `endDate` are date-times, and `endDate` is required for a lifetime budget. `destinationType` decides where the click lands (`WEBSITE`, `MESSENGER`, `WHATSAPP`, `INSTAGRAM_DIRECT`, `INSTAGRAM_PROFILE` or `ON_AD`), independently of plain link CTAs and their goal; a `lead_generation` boost forces `ON_AD` and ignores it. `instagramAccountId` picks the Instagram identity the ad runs as, overriding the one linked to the Page. Send `status: "PAUSED"` to create the campaign and ad paused instead of live, so you can review before it spends; the default, omitted or `"ACTIVE"`, publishes immediately.

## How it behaves

### The targeting object takes the full geo shape

`targeting` accepts `cities` (with `radius` and `distanceUnit`), `regions`, `zips`, `metros` and `customLocations` as well as `countries`, with keys from [the targeting search](/platforms/meta-ads/targeting#look-up-city-and-region-keys). A city radius and a lat/lng catchment keep the post's social proof, because the ad still references the original post.

### A call to action needs a link

Send `linkUrl` and `callToAction` together. On Meta they add a `call_to_action` to the post-reference creative, which is what gives a `traffic` boost a clickable destination without replacing the creative. One value is boost-only: `VIEW_INSTAGRAM_PROFILE`, with the Instagram profile URL as `linkUrl`. A call to action without a link is a `400`, and both are ignored when `leadGenFormId` supplies the destination ([lead forms](/platforms/meta-ads/lead-forms)). The 3 messaging CTAs below are the exception: omit `linkUrl` for them.

### Messaging boosts open a chat instead of a link

Use `goal: "engagement"` with `callToAction: "WHATSAPP_MESSAGE"`, `"MESSAGE_PAGE"` or `"INSTAGRAM_MESSAGE"` to boost into a chat starter rather than a link click. The CTA alone selects the destination (WhatsApp, Messenger or Instagram Direct); an explicit `destinationType` must then match it. Setting `destinationType` alone does not select a messaging CTA. Omit `linkUrl`. The campaign uses `OUTCOME_ENGAGEMENT` and the ad set uses the `CONVERSATIONS` optimization goal with the boosted post's Page. `whatsappPhoneNumber` (E.164) picks a number already paired with that Page for `WHATSAPP_MESSAGE`; omit it to use the Page's default pairing.

### Attach to an ad set that already runs

Send `adSetId` to put the boosted post under an existing ad set instead of a new campaign, so that ad set keeps its learning phase. The ad set then owns `budget`, `schedule` and `targeting`, and sending any of them with `adSetId` is a `400`.

### Retries

Boosts are not idempotent and can take minutes when Meta has to re-host an Instagram video, so do not retry on a client timeout. Send an `Idempotency-Key` header: the same key and body replays the original `201`, and distinct keys always create distinct ads. Without the header, an identical request in flight returns `409`, and within 10 minutes of a completed boost it returns the already-created ad. To duplicate a boost on purpose, send distinct keys or vary the body.

## If it fails

A `409` means an identical boost is still in progress:

```json
{
  "error": "An identical boost request is already in progress",
  "type": "invalid_request_error"
}
```

Wait for the first request to finish and read the ad from `GET /v1/ads` instead of sending the boost again. A `422` means the account has no ads connection or the Instagram account has no linked Facebook account ([before you start](/platforms/meta-ads#before-you-start)).

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the flat create body for ads with their own creative.
- [Targeting](/platforms/meta-ads/targeting): every field the `targeting` object takes.
- [Lead forms](/platforms/meta-ads/lead-forms): boost with `goal: "lead_generation"` and a form.
- [Boost post as ad](/ad-campaigns/boost-post): every field.

---
