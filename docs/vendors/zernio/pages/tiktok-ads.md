# TikTok Ads

Run Spark Ads, standalone video campaigns and website conversion campaigns on a tiktokads account, with Custom Audiences and Insights.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Boost a TikTok video as a Spark Ad with `POST /v1/ads/boost`, or create a campaign, ad group and video ad in one `POST /v1/ads/create` call, on a `tiktokads` account. No TikTok Business Center developer onboarding is needed: connect with OAuth and call the ads endpoints. Zernio's `adSetId` is the TikTok ad group id (on Meta the same field is an ad set, on LinkedIn a campaign).

## Quick reference

| Feature | Status |
|---------|--------|
| Standalone campaigns (Campaign > Ad Group > Ad) | Yes |
| Website conversion ads (TikTok Pixel optimization) | Yes |
| Spark Ads (boost organic videos) | Yes |
| Spark Ad custom destination URL and CTA | Yes |
| Spark Code (cross-creator boosts via `auth_code`) | Yes |
| Bid strategy (Cost Cap, ROAS floor) | Yes |
| Campaign duplication (manual graph copy) | Yes |
| Attach to an existing ad group on `/v1/ads/create` | Yes |
| Creative swap on `PUT /v1/ads/{adId}` | Yes |
| Targeting updates after creation | Yes |
| Agency Business Centers (multi-advertiser plus a BC list endpoint) | Yes |
| Custom Audiences (customer list) | Yes |
| Age, gender, location and interest targeting | Yes |
| Video creative from URL | Yes |
| Insights (spend, views, CTR, CPM) | Yes |
| Catalog and TikTok Shop ads | Roadmap |
| Chunked video upload and async transcode | Roadmap |

## Before you start

TikTok Ads requires a `tiktokads` account with its own token: the TikTok Business API is a separate OAuth from TikTok posting. It does not use per-request OAuth scopes; when the user authorizes the connection, permissions are granted at the app level (ad account management, reporting, creative management) rather than as individual scopes in the consent URL. You also need the advertiser id, from [List ad accounts](/ad-accounts/list-ad-accounts).

## Connect

Call `GET /v1/connect/tiktok/ads` with `profileId` and, optionally, the `accountId` of a TikTok posting account ([Connect ads](/connect/connect-ads)). With `accountId`, the new `tiktokads` account links to that posting account, so Spark Ads and standalone ads with the real `@username` identity become available. Without it, ads-only mode applies: standalone ads use a synthetic Brand Identity (`CUSTOMIZED_USER`, configured with `PATCH /v1/connect/tiktok-ads` or inline through `brandIdentity` on create) and Spark Ads are unavailable, because TikTok requires a posting account for them.

```bash
curl "https://zernio.com/api/v1/connect/tiktok/ads?profileId=66a1f0c2a4b9d3e8f1a2b3c4&accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "authUrl": "https://business-api.tiktok.com/portal/auth?app_id=...",
  "state": "..."
}
```

Send the user to `authUrl`, as in the [connecting accounts guide](/guides/connecting-accounts). Once the flow completes, the same call returns `alreadyConnected: true` with the `tiktokads` `accountId`, which every sample below uses.

## Create ads

### Spark Ads (boost)

Call `POST /v1/ads/boost` with `postId`, `accountId`, `adAccountId`, `goal` and `budget`. Spark Ads keep the creator's identity, organic engagement signals and follower handle.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: boosted } = await zernio.adcampaigns.boostPost({
  body: {
    postId: '65f1c0a9e2b5af0012ab34cd',
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '7123456789012345678',
    name: 'Boost viral video',
    goal: 'traffic',
    budget: { amount: 50, type: 'daily' },
    schedule: { startDate: '2027-01-04T09:00:00Z', endDate: '2027-01-11T23:59:00Z' },
    targeting: { countries: ['US'] },
    linkUrl: 'https://example.com/landing',
    callToAction: 'SHOP_NOW',
    bidStrategy: 'COST_CAP',
    bidAmount: 0.5
  }
});

console.log(boosted.ad._id);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

boosted = client.ad_campaigns.boost_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="7123456789012345678",
    name="Boost viral video",
    goal="traffic",
    budget={"amount": 50, "type": "daily"},
    schedule={"startDate": "2027-01-04T09:00:00Z", "endDate": "2027-01-11T23:59:00Z"},
    targeting={"countries": ["US"]},
    link_url="https://example.com/landing",
    call_to_action="SHOP_NOW",
    bid_strategy="COST_CAP",
    bid_amount=0.5,
)

print(boosted["ad"]["_id"])
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
    "adAccountId": "7123456789012345678",
    "name": "Boost viral video",
    "goal": "traffic",
    "budget": { "amount": 50, "type": "daily" },
    "schedule": { "startDate": "2027-01-04T09:00:00Z", "endDate": "2027-01-11T23:59:00Z" },
    "targeting": { "countries": ["US"] },
    "linkUrl": "https://example.com/landing",
    "callToAction": "SHOP_NOW",
    "bidStrategy": "COST_CAP",
    "bidAmount": 0.5
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d0",
    "name": "Boost viral video",
    "platform": "tiktok",
    "status": "pending_review",
    "adType": "boost",
    "goal": "traffic",
    "budget": { "amount": 50, "type": "daily" },
    "bidStrategy": "COST_CAP",
    "bidAmount": 0.5,
    "platformAdId": "1802345678901234567",
    "platformCampaignId": "1802345678901234001",
    "platformAdSetId": "1802345678901234002"
  },
  "message": "Ad created"
}
```

`linkUrl` and `callToAction` are the Spark Ad creative overrides, mapped to `landing_page_url` and `call_to_action` on TikTok's `/v2/ad/create/`. Pass them for traffic and conversion goals: without `linkUrl` the Spark Ad has no clickable destination distinct from the organic video. `targeting.countries` is required on TikTok boosts (the ad group needs `location_ids`). `bidStrategy` and `bidAmount` are optional Cost Cap bidding ([Bid strategy](#bid-strategy)). Every sample on this page reuses the `zernio` and `client` constructors from this one.

### Standalone campaign

Call `POST /v1/ads/create`. TikTok's ads endpoint is video-only, so the video URL goes in `imageUrl` (the field keeps its name for cross-platform consistency), `body` is the video caption, and `headline` is ignored because TikTok creatives have no headline slot. Valid `goal` values are `engagement`, `traffic`, `awareness`, `video_views`, `lead_generation`, `conversions` and `app_promotion`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '7123456789012345678',
    name: 'Spring launch',
    goal: 'traffic',
    budgetAmount: 100,
    budgetType: 'daily',
    body: 'Spring drop is live',
    linkUrl: 'https://example.com/spring',
    imageUrl: 'https://cdn.example.com/launch.mp4',
    callToAction: 'SHOP_NOW',
    countries: ['US'],
    ageMin: 18,
    ageMax: 34
  }
});

console.log(created.ad.platformAdSetId);
```
</Tab>
<Tab value="Python">
```python
created = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="7123456789012345678",
    name="Spring launch",
    goal="traffic",
    budget_amount=100,
    budget_type="daily",
    body="Spring drop is live",
    link_url="https://example.com/spring",
    image_url="https://cdn.example.com/launch.mp4",
    call_to_action="SHOP_NOW",
    countries=["US"],
    age_min=18,
    age_max=34,
)

print(created["ad"]["platformAdSetId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/create" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "7123456789012345678",
    "name": "Spring launch",
    "goal": "traffic",
    "budgetAmount": 100,
    "budgetType": "daily",
    "body": "Spring drop is live",
    "linkUrl": "https://example.com/spring",
    "imageUrl": "https://cdn.example.com/launch.mp4",
    "callToAction": "SHOP_NOW",
    "countries": ["US"],
    "ageMin": 18,
    "ageMax": 34
  }'
```
</Tab>
</Tabs>

Response (`201`), same shape as the boost with `"adType": "standalone"`:

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d1",
    "name": "Spring launch",
    "platform": "tiktok",
    "status": "pending_review",
    "adType": "standalone",
    "goal": "traffic",
    "platformAdId": "1802345678901234568",
    "platformCampaignId": "1802345678901234003",
    "platformAdSetId": "1802345678901234004"
  },
  "message": "Ad created"
}
```

`platformAdSetId` is the new TikTok ad group.

### Conversion campaigns

A website-conversion campaign (`goal: "conversions"`, which Zernio maps to a `CONVERSIONS` objective with a `WEBSITE` / `CONVERT` ad group) needs a TikTok Pixel. Without one, TikTok rejects the ad group with `40002: Please select a pixel`. Pass the pixel through `promotedObject`, the same cross-platform field Meta uses:

| `promotedObject` field | Maps to TikTok | Required |
|---|---|---|
| `pixelId` | ad group `pixel_id` (numeric) | Yes |
| `customEventType` | ad group `optimization_event` (the pixel event to optimise for) | No (auto-bid CONVERT works without it) |

`customEventType` takes a TikTok `optimization_event` code (TikTok's own UPPER_SNAKE codes, not Meta's `PURCHASE` or `LEAD` vocabulary and not PascalCase), or the exact event name shown in TikTok Events Manager. The value must be one of the events your pixel is configured to track. Common website codes:

| Code | Pixel event |
|---|---|
| `ON_WEB_ORDER` | Complete Payment |
| `INITIATE_ORDER` | Place an Order |
| `ON_WEB_CART` | Add to Cart |
| `ON_WEB_REGISTER` | Complete Registration |
| `ON_WEB_DETAIL` | View Content |
| `FORM` | Submit Form |
| `ON_WEB_SEARCH` | Search |
| `ON_WEB_ADD_TO_WISHLIST` | Add to Wishlist |
| `LANDING_PAGE_VIEW` | Landing Page View |

<Callout type="warn">
`pixelId` must be the numeric TikTok Pixel id, not the alphanumeric Pixel Code shown in Events Manager (for example `D00IKHRC77UE0J0RTNHG`). TikTok's ad-group API rejects the code with `40002: pixel_id ... is not a valid integer string`. The numeric id is the `pixel_id` that TikTok's `GET /pixel/list/` returns; if Ads Manager only shows the alphanumeric code for your pixel, retrieve the numeric id through the TikTok Marketing API (`GET /pixel/list/`, filter by `code`).
</Callout>

Find the pixel and the events it tracks in TikTok Ads Manager under Assets, then Events. The request is the standalone create plus `promotedObject`:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "7123456789012345678",
  "name": "Spring launch, purchases",
  "goal": "conversions",
  "budgetAmount": 100,
  "budgetType": "daily",
  "body": "Spring drop is live",
  "linkUrl": "https://example.com/spring",
  "imageUrl": "https://cdn.example.com/launch.mp4",
  "callToAction": "SHOP_NOW",
  "countries": ["US"],
  "ageMin": 18,
  "ageMax": 34,
  "promotedObject": { "pixelId": "7987654321098765432", "customEventType": "ON_WEB_ORDER" }
}
```

If a conversion ad group already exists in TikTok Ads Manager with the pixel and event configured, pass its id as `adSetId` on `POST /v1/ads/create` instead. The new creative inherits the pixel and `optimization_event` from the parent ad group, so `promotedObject` is not needed ([Attach to an existing ad group](#attach-to-an-existing-ad-group)).

### Spark Code for cross-creator boosts

To boost a creator's organic video from a different TikTok account than the one running the ads, the creator generates a Spark Code in their TikTok app's Promote settings and shares it with the advertiser. Pass it as `sparkAuthCode` on `POST /v1/ads/boost`, with the creator's video as `platformPostId`:

```json
{
  "platformPostId": "7310234567890123456",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "7123456789012345678",
  "name": "Cross-creator boost",
  "goal": "traffic",
  "budget": { "amount": 50, "type": "daily" },
  "targeting": { "countries": ["US"] },
  "linkUrl": "https://example.com",
  "callToAction": "SHOP_NOW",
  "sparkAuthCode": "BCAQAAAA..."
}
```

Without `sparkAuthCode`, boosts are limited to videos owned by the same TikTok account that runs the ads. The value maps to `auth_code` on TikTok's `AdcreateCreatives`.

### Bid strategy

Pass `bidStrategy` (the cross-platform Meta enum) on `POST /v1/ads/boost`, `POST /v1/ads/create` or `PUT /v1/ads/ad-sets/{adSetId}`. Zernio maps it to TikTok's `bid_type`, `bid_price` and `deep_bid_type`:

| `bidStrategy` | Maps to TikTok | Required field |
|---|---|---|
| `LOWEST_COST_WITHOUT_CAP` (default) | `bid_type: BID_TYPE_NO_BID` | none |
| `LOWEST_COST_WITH_BID_CAP` | `bid_type: BID_TYPE_CUSTOM` plus `bid_price` | `bidAmount` |
| `COST_CAP` | `bid_type: BID_TYPE_CUSTOM` plus `bid_price` | `bidAmount` |
| `LOWEST_COST_WITH_MIN_ROAS` | `bid_type: BID_TYPE_NO_BID` plus `deep_bid_type: MIN_ROAS` | `roasAverageFloor` (the account must be value-optimization-enabled) |

`bidAmount` is in whole currency units of the ad account (USD: `5` is $5.00). On reads (`GET /v1/ads/tree`, `GET /v1/ads/campaigns`), TikTok's native `bid_type` is normalized back to the same enum, so cross-platform consumers see one shape regardless of source platform.

### Media requirements

| Type | Format | Max size | Notes |
|------|--------|----------|-------|
| Video | MP4, MOV, MPEG | 500 MB | 9:16 vertical, 720p or better, 5 to 60 seconds |
| Image | JPEG, PNG | 30 MB | 1080 x 1920 for full-screen |

Videos are fetched from the URL you pass; the [media uploads guide](/guides/media-uploads) covers hosting them on Zernio.

## Target

### Custom Audiences

Create a customer-file Custom Audience with `POST /v1/ads/audiences` (`type: "customer_list"`), then upload members with `POST /v1/ads/audiences/{audienceId}/users`. `adAccountId` is the TikTok advertiser id. TikTok matches on email only (any `phone` is ignored); values are SHA-256 hashed server-side.

TikTok needs the member file at creation time, so the audience is created lazily on the first member upload: the create records it with status `pending` (no platform id yet), and the first `users` call provisions it on TikTok. A new audience takes up to 48 hours to finish processing before it is targetable.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: audience } = await zernio.adaudiences.createAdAudience({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '7123456789012345678',
    type: 'customer_list',
    name: 'Cart abandoners'
  }
});

const { data: upload } = await zernio.adaudiences.addUsersToAdAudience({
  path: { audienceId: audience.audience.id },
  body: { users: [{ email: 'jane@example.com' }, { email: 'sam@example.com' }] }
});

console.log(upload.numReceived);
```
</Tab>
<Tab value="Python">
```python
audience = client.ad_audiences.create_ad_audience(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="7123456789012345678",
    type="customer_list",
    name="Cart abandoners",
)

upload = client.ad_audiences.add_users_to_ad_audience(
    audience_id=audience["audience"]["id"],
    users=[{"email": "jane@example.com"}, {"email": "sam@example.com"}],
)

print(upload["numReceived"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/audiences" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "adAccountId": "7123456789012345678", "type": "customer_list", "name": "Cart abandoners" }'

curl -X POST "https://zernio.com/api/v1/ads/audiences/66e5b2c3d4f5a6b7c8d9e0f1/users" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "users": [{ "email": "jane@example.com" }, { "email": "sam@example.com" }] }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "audience": { "id": "66e5b2c3d4f5a6b7c8d9e0f1", "name": "Cart abandoners", "type": "customer_list", "platform": "tiktok", "status": "pending" },
  "message": "Audience created"
}
```

Response (`200`):

```json
{ "message": "Users added", "numReceived": 2, "numInvalid": 0 }
```

### Targeting fields

`countries`, `ageMin`, `ageMax`, `gender`, `interests` (from `GET /v1/ads/interests`), `behaviors`, `regions`, `cities`, `zips`, `metros` and `incomeTier` apply on create; `audienceInclude` and `audienceExclude` take the `platformAudienceId` of a processed Custom Audience. To change targeting after creation, see [Targeting update after creation](#targeting-update-after-creation).

## Measure

Call `GET /v1/ads/{adId}/analytics` with the ad's `_id` for spend, impressions, clicks, CTR, CPM and video metrics, and `breakdowns` for a split by `gender`, `age`, `country_code`, `platform`, `ac` or `language`.

```bash
curl "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0/analytics?fromDate=2027-01-04&toDate=2027-01-11&breakdowns=age" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), trimmed:

```json
{
  "ad": { "id": "66f0a1b2c3d4e5f6a7b8c9d0", "name": "Boost viral video", "platform": "tiktok", "status": "active", "currency": "USD" },
  "analytics": {
    "summary": { "spend": 312.4, "impressions": 128400, "clicks": 2110, "ctr": 1.64, "cpc": 0.15, "cpm": 2.43, "videoPlayActions": 96200, "videoP100WatchedActions": 18400 },
    "daily": [ { "date": "2027-01-04", "spend": 44.1, "impressions": 18200, "clicks": 301 } ],
    "breakdowns": { "age": [ { "value": "AGE_18_24", "spend": 140.2, "impressions": 61000, "clicks": 1080 } ] }
  }
}
```

`GET /v1/ads/tree` rolls the same metrics up per campaign and ad group ([Get campaign tree](/ad-campaigns/get-ad-tree)). For offline conversions (in-store, CRM, call-center), `POST /v1/ads/conversions` on a `tiktokads` account sends them to TikTok's Offline Events API: `destinationId` is the Offline Event Set id, each event must carry an email or phone, and the connection must have granted the Offline Events permission (older grants must reconnect). Web-pixel events are not sent this way ([Send conversions](/conversions/send-conversions)).

## Operate

### Attach to an existing ad group

`POST /v1/ads/create` with `adSetId` creates a new ad inside an existing TikTok ad group, skipping the campaign and ad-group create. Bid strategy, budget, targeting and goal are inherited from the ad group. Use it when one ad group was configured by hand in TikTok Ads Manager and you want to add Zernio-managed creatives to it.

### Ad comments

`GET /v1/ads/{adId}/comments` returns the comments on a TikTok ad in the same normalized shape it returns for Meta, and TikTok also supports moderation: reply, hide or restore, and delete. Comments on organic posts are read and moderated through the [inbox](/platforms/tiktok#comments) instead; this endpoint is the one that reaches comments on ad creatives, dark posts included.

TikTok reads comments over a date window rather than all of history, so the window is at most 30 days and defaults to the last 30 days. See [Ad comments](/platforms/meta-ads/ad-comments#tiktok-ads) for the endpoints, the window rules and the `canReply` / `canDelete` / `canHide` flags.

Listing uses the stored ad group without resolving identity or looking up the video item ID. `meta.tiktokItemId: null` does not block it, and there is no ad-level identity or item-ID check to perform first. `canReply` and `canDelete` are per comment and false when the identity or item needed to moderate is unknown. Hiding does not require an identity. A direct reply or delete can resolve missing fields lazily; use the returned capabilities to guide the UI without blocking comment reads.

### Creative swap

`PUT /v1/ads/{adId}` accepts a `creative` object that replaces the live creative on an existing TikTok ad, patch-style: only the fields you supply are touched. `headline` is ignored (no slot on TikTok), `body` becomes `ad_text`, `linkUrl` becomes `landing_page_url`, and `videoUrl` triggers a fresh upload.

```bash
curl -X PUT "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "creative": { "body": "Spring drop, last week", "videoUrl": "https://cdn.example.com/launch-v2.mp4" } }'
```

Response (`200`):

```json
{
  "ad": { "_id": "66f0a1b2c3d4e5f6a7b8c9d0", "platform": "tiktok", "status": "pending_review", "creative": { "body": "Spring drop, last week" } },
  "message": "Ad updated"
}
```

### Targeting update after creation

`PUT /v1/ads/{adId}` accepts `targeting` for TikTok ads with the same field set as create; Zernio forwards it to `/v2/adgroup/update/`. The other networks are narrower: Pinterest and X return `501`, Google accepts keyword and device bid adjustments only, and LinkedIn accepts `countries` only.

### Campaign duplication

`POST /v1/ads/campaigns/{campaignId}/duplicate` with `platform: "tiktok"` works through a manual graph walk: Zernio reads the source campaign, ad groups and ads, then recreates each entity with bid configuration, targeting, schedule and creative fields preserved. Spark Ad linkage (`tiktok_item_id`) carries over. Everything is created paused so you can review before launching; the response carries `copiedCampaignId`.

### Agency Business Centers

Connecting a TikTok account that owns one or more Business Centers enumerates every advertiser under those BCs. There is no per-call cap: `GET /v1/ads/accounts` walks `/v2/bc/asset/get/` (paginated server-side), chunks the `/v2/advertiser/info/` lookup underneath, and returns the full roster. Solo advertisers without a BC fall back to the OAuth-time advertiser list (a single token can typically reach a handful of advertisers without a BC). The advertiser list is cached on the connection for 1 hour and refreshed on the next call after expiry. `GET /v1/ads/business-centers?accountId=` lists the BCs themselves with an `advertiserCount` each, for agency-style pickers.

## What you cannot do

TikTok Ads through Zernio does not support:

- Catalog and TikTok Shop ads. `goal` has no catalog option on TikTok, and a catalog campaign built in Ads Manager syncs back as `conversions`.
- TikTok Instant Forms. `goal: "lead_generation"` builds a Smart+ lead campaign that sends the click to your own form on `linkUrl`.
- Spark Ads on an ads-only connection, because TikTok requires a posting account for them. Connect the TikTok account itself, not the ad account alone.
- Chunked video upload and async transcode. A video creative transfers in one request, under the 500 MB ceiling in [media requirements](#media-requirements).
- Creating an ad group on its own. `POST /v1/ads/ad-sets` is Google Ads only and answers `501` everywhere else.
- The Meta-only reads and tools. Ad previews, the creative library, reach and frequency, the change log, ad labels, A/B studies, account finance, the image and video libraries and asynchronous insights reports all answer `501` on TikTok.

## Common errors

| Error | Cause | Fix |
|---|---|---|
| `422` with code `ads_connection_required` | No `tiktokads` account for the profile, or the TikTok user is not authorized as an Identity on the advertiser | [Connect](#connect) TikTok Ads, or authorize the identity in TikTok Ads Manager. |
| `40002: Please select a pixel` | `goal: "conversions"` without `promotedObject.pixelId` | Pass the numeric pixel id, or attach to an existing conversion ad group with `adSetId`. |
| `40002: pixel_id ... is not a valid integer string` | The alphanumeric Pixel Code was sent instead of the numeric id | Use the `pixel_id` from TikTok's `GET /pixel/list/`. |
| `400` on a boost | `countries` missing, or `callToAction` sent without `linkUrl` | Send `targeting.countries`, and always pair the CTA with a destination. |
| Budget rejected | Below TikTok's minimum | TikTok's minimum is $20 per ad set. |

A `422` on any ads call means the profile has no TikTok Ads connection:

```json
{
  "error": "TikTok Ads is not connected for this profile",
  "type": "invalid_request_error",
  "code": "ads_connection_required",
  "param": "accountId"
}
```

[Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow behind `authUrl`.
- [TikTok](/platforms/tiktok): the posting account Spark Ads boost from.
- [Boost post](/ad-campaigns/boost-post) and [Create standalone ad](/ad-campaigns/create-standalone-ad): every field.
- [Meta Ads](/platforms/meta-ads): the same endpoints on Meta, where `adSetId` is an ad set.

---
