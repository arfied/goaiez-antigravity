# Create Ads

Create a Google Search, Display or Performance Max campaign in one POST /v1/ads/create call, edit RSA headlines and descriptions with pinning, and manage sitelink, callout and structured-snippet assets.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a Google Search or Display campaign is live, with its ad group and ad, from one `POST /v1/ads/create` call. You need a `googleads` [account](/platforms/google-ads#connect) and the customer id from [List ad accounts](/ad-accounts/list-ad-accounts). `campaignType` (lowercase `search` or `display`) selects the campaign type.

## Create a Search campaign

Call `POST /v1/ads/create` with `campaignType: "search"`, the ad copy and `keywords`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '1234567890',
    name: 'US DevOps Search',
    campaignType: 'search',
    goal: 'traffic',
    budgetAmount: 50,
    budgetType: 'daily',
    headline: 'Internal Developer Platforms',
    body: 'Spec-driven platform engineering.',
    linkUrl: 'https://example.com/platform',
    keywords: ['platform engineering tools', 'internal developer platform'],
    additionalHeadlines: ['Build your Dev Platform', 'Ship DevEx Faster', 'Internal Platforms, Done Right'],
    additionalDescriptions: ['Spec-driven platform. Free tier available.', 'Enterprise features. Startup price.'],
    countries: ['US', 'CA', 'GB']
  }
});

console.log(created.ad._id);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="1234567890",
    name="US DevOps Search",
    campaign_type="search",
    goal="traffic",
    budget_amount=50,
    budget_type="daily",
    headline="Internal Developer Platforms",
    body="Spec-driven platform engineering.",
    link_url="https://example.com/platform",
    keywords=["platform engineering tools", "internal developer platform"],
    additional_headlines=["Build your Dev Platform", "Ship DevEx Faster", "Internal Platforms, Done Right"],
    additional_descriptions=["Spec-driven platform. Free tier available.", "Enterprise features. Startup price."],
    countries=["US", "CA", "GB"],
)

print(created["ad"]["_id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/create" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "1234567890",
    "name": "US DevOps Search",
    "campaignType": "search",
    "goal": "traffic",
    "budgetAmount": 50,
    "budgetType": "daily",
    "headline": "Internal Developer Platforms",
    "body": "Spec-driven platform engineering.",
    "linkUrl": "https://example.com/platform",
    "keywords": ["platform engineering tools", "internal developer platform"],
    "additionalHeadlines": ["Build your Dev Platform", "Ship DevEx Faster", "Internal Platforms, Done Right"],
    "additionalDescriptions": ["Spec-driven platform. Free tier available.", "Enterprise features. Startup price."],
    "countries": ["US", "CA", "GB"]
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d0",
    "name": "US DevOps Search",
    "platform": "google",
    "status": "pending_review",
    "adType": "standalone",
    "goal": "traffic",
    "budget": { "amount": 50, "type": "daily" },
    "platformAdId": "735198246013",
    "platformCampaignId": "21874563210",
    "platformAdSetId": "165489732105"
  },
  "message": "Ad created"
}
```

`platformAdSetId` is the Google ad group id (Zernio's `adSetId`), and `_id` is what the [Insights](/platforms/google-ads/insights) and [tracking tag](/platforms/google-ads/tracking-tags) endpoints take as `adId`.

- `budgetAmount` is whole units of the ad account's currency, not micros and not cents: `50` with `budgetType: "daily"` is $50.00 a day on a USD account. Google's own API takes micros here, so a value copied from a GAQL report is a million times too small.
- `goal` is `engagement`, `traffic` or `awareness`. Google Ads rejects `video_views` at create with a `422` and code `feature_not_available`: publish a YouTube video post and boost it with `POST /v1/ads/boost` instead. Conversion-goal campaigns are not supported on `/v1/ads/create` either; pass `traffic` for click-optimised delivery.
- `keywords` is a flat array of strings, created as broad-match keywords on the new ad group (up to 1,000 per call, 80 characters each). An entry can also be `{ text, matchType }` with `exact`, `phrase` or `broad`; [Keywords](/platforms/google-ads/keywords) covers editing them later.
- `additionalHeadlines` and `additionalDescriptions` apply to Search Responsive Search Ads only. Each entry accepts either a string or an object with `text` and optional `pinnedField`: use `HEADLINE_1`, `HEADLINE_2` or `HEADLINE_3` for headlines, and `DESCRIPTION_1` or `DESCRIPTION_2` for descriptions. For example, `{ "text": "Build your Dev Platform", "pinnedField": "HEADLINE_2" }` pins an extra headline. After including the primary text and deduplicating, the effective lists must contain 3 to 15 headlines and 2 to 4 descriptions; excess entries return `400`.

`status` is `pending_review` on every Google create. Later syncs re-derive it from Google: `active` once the ad is approved, `rejected` when Google disapproves it, `paused`, `completed` when the campaign has ended, `cancelled` when the ad is removed, and `error` when Google reports a state Zernio does not recognise. Read `GET /v1/ads/{adId}` to see where it landed; the [`ad.status_changed` webhook](/webhooks/ads#adstatus_changed) fires for Meta only.

## Create a Display campaign

Call `POST /v1/ads/create` with `campaignType: "display"`, `businessName` and both `images`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: display } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '1234567890',
    name: 'Retargeting Display',
    campaignType: 'display',
    goal: 'traffic',
    budgetAmount: 40,
    budgetType: 'daily',
    headline: 'Finish your signup',
    body: 'Come back and upgrade today.',
    linkUrl: 'https://example.com/upgrade',
    businessName: 'Acme',
    images: {
      landscape: 'https://cdn.example.com/retarget-1200x628.jpg',
      square: 'https://cdn.example.com/retarget-1080x1080.jpg'
    },
    countries: ['US']
  }
});

console.log(display.ad.platformCampaignId);
```
</Tab>
<Tab value="Python">
```python
display = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="1234567890",
    name="Retargeting Display",
    campaign_type="display",
    goal="traffic",
    budget_amount=40,
    budget_type="daily",
    headline="Finish your signup",
    body="Come back and upgrade today.",
    link_url="https://example.com/upgrade",
    business_name="Acme",
    images={
        "landscape": "https://cdn.example.com/retarget-1200x628.jpg",
        "square": "https://cdn.example.com/retarget-1080x1080.jpg",
    },
    countries=["US"],
)

print(display["ad"]["platformCampaignId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/create" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "1234567890",
    "name": "Retargeting Display",
    "campaignType": "display",
    "goal": "traffic",
    "budgetAmount": 40,
    "budgetType": "daily",
    "headline": "Finish your signup",
    "body": "Come back and upgrade today.",
    "linkUrl": "https://example.com/upgrade",
    "businessName": "Acme",
    "images": {
      "landscape": "https://cdn.example.com/retarget-1200x628.jpg",
      "square": "https://cdn.example.com/retarget-1080x1080.jpg"
    },
    "countries": ["US"]
  }'
```
</Tab>
</Tabs>

Response (`201`), same shape as the Search create:

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d1",
    "name": "Retargeting Display",
    "platform": "google",
    "status": "pending_review",
    "adType": "standalone",
    "goal": "traffic",
    "budget": { "amount": 40, "type": "daily" },
    "platformAdId": "735198246014",
    "platformCampaignId": "21874563211",
    "platformAdSetId": "165489732106"
  },
  "message": "Ad created"
}
```

Display campaigns require `businessName` (max 25 characters) on top of the Search fields, and `longHeadline` defaults to `headline` when omitted (max 90 characters).

Discovered campaigns of every type, including Shopping and Video, sync into [`GET /v1/ads/tree`](/platforms/google-ads/insights) with metrics; `/v1/ads/create` builds Search, Display and Performance Max.

## Create a Performance Max campaign

Call `POST /v1/ads/create` with `campaignType: "pmax"` and an `assetGroup`. The campaign, its budget and the asset group with all its assets are created in one atomic Google mutate, so you either get the whole thing or nothing.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: pmax } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '1234567890',
    name: 'Spring Performance Max',
    campaignType: 'pmax',
    budgetAmount: 40,
    budgetType: 'daily',
    assetGroup: {
      businessName: 'Acme',
      finalUrl: 'https://example.com',
      longHeadline: 'Everything your team needs in one place',
      headlines: ['Acme for teams', 'Start in minutes', 'Built for scale'],
      descriptions: [
        'Set up in minutes and invite your whole team.',
        'Trusted by thousands of growing companies.'
      ],
      images: {
        landscape: ['https://cdn.example.com/pmax-1200x628.jpg'],
        square: ['https://cdn.example.com/pmax-1080x1080.jpg'],
        logo: ['https://cdn.example.com/logo-1200x1200.jpg']
      }
    },
    countries: ['US']
  }
});
```
</Tab>
<Tab value="Python">
```python
pmax = zernio.adcampaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="1234567890",
    name="Spring Performance Max",
    campaign_type="pmax",
    budget_amount=40,
    budget_type="daily",
    asset_group={
        "businessName": "Acme",
        "finalUrl": "https://example.com",
        "longHeadline": "Everything your team needs in one place",
        "headlines": ["Acme for teams", "Start in minutes", "Built for scale"],
        "descriptions": [
            "Set up in minutes and invite your whole team.",
            "Trusted by thousands of growing companies.",
        ],
        "images": {
            "landscape": ["https://cdn.example.com/pmax-1200x628.jpg"],
            "square": ["https://cdn.example.com/pmax-1080x1080.jpg"],
            "logo": ["https://cdn.example.com/logo-1200x1200.jpg"],
        },
    },
    countries=["US"],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/ads/create \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "1234567890",
    "name": "Spring Performance Max",
    "campaignType": "pmax",
    "budgetAmount": 40,
    "budgetType": "daily",
    "assetGroup": {
      "businessName": "Acme",
      "finalUrl": "https://example.com",
      "longHeadline": "Everything your team needs in one place",
      "headlines": ["Acme for teams", "Start in minutes", "Built for scale"],
      "descriptions": [
        "Set up in minutes and invite your whole team.",
        "Trusted by thousands of growing companies."
      ],
      "images": {
        "landscape": ["https://cdn.example.com/pmax-1200x628.jpg"],
        "square": ["https://cdn.example.com/pmax-1080x1080.jpg"],
        "logo": ["https://cdn.example.com/logo-1200x1200.jpg"]
      }
    },
    "countries": ["US"]
  }'
```
</Tab>
</Tabs>

The asset group needs `businessName`, `finalUrl`, `longHeadline`, at least three `headlines`, at least two `descriptions`, and images under all three of `landscape`, `square` and `logo`. A YouTube video is optional via `assetGroup.youtubeVideoId`. Read the result back with [`GET /v1/ads/campaigns/{campaignId}/asset-groups`](/ad-campaigns/list-google-asset-groups).

Performance Max differs from Search and Display in ways that will reject the request rather than degrade quietly:

| Rule | Detail |
| --- | --- |
| Always created paused | Google rejects creating a Performance Max campaign in an active state. Activate it after review. |
| Daily budget only | `budgetType` must be `daily`. |
| Bidding | Maximize Conversions or Maximize Conversion Value. Portfolio bidding and bid caps are rejected. |
| Targeting | Geo and language only. An omitted geo targets all locations. |
| No legacy creative fields | `headline`, `body`, `linkUrl` and attach shapes belong to the other campaign types. |

Send `validateOnly: true` to validate the whole request against Google without creating or persisting anything. That is the safe way to check a payload before committing budget.

## Edit responsive search ad text

`PUT /v1/ads/{adId}` replaces a Search ad's RSA text with top-level `headlines`, `descriptions` and `finalUrls`. Each array is a full replacement of that list, not a merge: omit a field entirely to leave it unchanged, or include it to replace every entry, including ones you want to keep.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: updated } = await zernio.adcampaigns.updateAd({
  path: { adId: '66f0a1b2c3d4e5f6a7b8c9d0' },
  body: {
    headlines: [
      { text: 'Social Media API', pinnedField: 'HEADLINE_1' },
      { text: 'Schedule Your Posts' },
      { text: 'Build With Zernio' }
    ],
    descriptions: [
      { text: 'Connect social accounts and schedule posts with the Zernio API.', pinnedField: 'DESCRIPTION_1' },
      { text: 'Build social publishing into your application.' }
    ],
    finalUrls: ['https://zernio.com']
  }
});
```
</Tab>
<Tab value="Python">
```python
updated = client.ad_campaigns.update_ad(
    ad_id="66f0a1b2c3d4e5f6a7b8c9d0",
    headlines=[
        {"text": "Social Media API", "pinnedField": "HEADLINE_1"},
        {"text": "Schedule Your Posts"},
        {"text": "Build With Zernio"},
    ],
    descriptions=[
        {"text": "Connect social accounts and schedule posts with the Zernio API.", "pinnedField": "DESCRIPTION_1"},
        {"text": "Build social publishing into your application."},
    ],
    final_urls=["https://zernio.com"],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "headlines": [
      { "text": "Social Media API", "pinnedField": "HEADLINE_1" },
      { "text": "Schedule Your Posts" },
      { "text": "Build With Zernio" }
    ],
    "descriptions": [
      { "text": "Connect social accounts and schedule posts with the Zernio API.", "pinnedField": "DESCRIPTION_1" },
      { "text": "Build social publishing into your application." }
    ],
    "finalUrls": ["https://zernio.com"]
  }'
```
</Tab>
</Tabs>

Use 3 to 15 headlines (1 to 30 characters each) and 2 to 4 descriptions (1 to 90 characters each); Zernio does not pad or truncate on update the way it does on create. `pinnedField` fixes an asset to one position (`HEADLINE_1`, `HEADLINE_2`, `HEADLINE_3`, `DESCRIPTION_1` or `DESCRIPTION_2`). Omit an asset from a supplied list to remove it; include it without `pinnedField` to unpin it. Omitting the entire list preserves its current text and pins. These Google updates use the top-level fields above; the legacy `creative` fields are unsupported for Google.

Read the current text and pins back with [`GET /v1/ads/{adId}`](/ad-campaigns/get-ad) in `ad.creative.headlines`, `ad.creative.descriptions` and `ad.creative.finalUrls`. Google Search enrichment preserves `pinnedField` and reports top-level `cachedAt` and `stale`; Google mutations invalidate this read. If enrichment fails, the endpoint still returns `200` with stored details and no cache metadata, so that response is not confirmation of a fresh Google read.

## Account, campaign and ad-group assets

Sitelinks, structured snippets and callouts are Google's account extensions. They exist at 3 levels, each with its own attach lifecycle:

| Level | Attach with | Manage with |
|---|---|---|
| Account (reusable across campaigns) | [Add account sitelinks](/ad-accounts/add-account-sitelinks), [snippets](/ad-accounts/add-account-structured-snippets), [callouts](/ad-accounts/add-account-callouts) | [List](/ad-accounts/list-account-sitelinks), [update](/ad-accounts/update-account-sitelinks), [remove](/ad-accounts/remove-account-sitelink) |
| Campaign | [Attach campaign assets](/ad-campaigns/attach-campaign-assets) | [List](/ad-campaigns/list-campaign-assets), [update](/ad-campaigns/update-campaign-assets), [remove](/ad-campaigns/remove-campaign-assets) |
| Ad group (ad set) | [Attach ad-group assets](/ad-campaigns/attach-ad-group-assets) | [List](/ad-campaigns/list-ad-group-assets), [update](/ad-campaigns/update-ad-group-assets), [remove](/ad-campaigns/remove-ad-group-assets) |

`POST /v1/ads/campaigns/{campaignId}/assets` and `POST /v1/ads/ad-sets/{adSetId}/assets` create and attach `sitelinks`, `callouts` and `structuredSnippets` in one mutation at the chosen level.

At all three levels, updates edit the underlying shared Google asset in place. Send `updates` with `assetResourceName` and the fields to change; omitted fields stay unchanged. A change is visible on every attachment using that asset, even when you make it through a campaign or ad-group endpoint. Updates consume the Google operations budget and invalidate affected cached lists.

Removing at any level only detaches the specified attachment. Google assets cannot be deleted, so the underlying asset and its other attachments remain.

## Media requirements

| Type | Format | Max | Notes |
|------|--------|-----|-------|
| Responsive Search Ad | Text | 15 headlines and 4 descriptions | Headlines are truncated to 30 characters and descriptions to 90; Zernio pads up to Google's minimum of 3 headlines and 2 descriptions |
| Responsive Display (landscape) | JPEG, PNG | 5120 KB | 1.91:1, 1200 x 628 recommended. Required. |
| Responsive Display (square) | JPEG, PNG | 5120 KB | 1:1, 1080 x 1080 recommended. Required. |
| Keyword | Text | 80 characters | `broad`, `phrase` or `exact` match |

## If it fails

A `400` with `NOT_ENOUGH_SQUARE_MARKETING_IMAGE_ASSET` means one of the 2 Display images is missing. Google's own message is forwarded in `platformError`:

```json
{
  "error": "Google rejected the ad: NOT_ENOUGH_SQUARE_MARKETING_IMAGE_ASSET",
  "type": "platform_error",
  "code": "platform_api_error",
  "platform": "google",
  "platformError": {
    "code": 3,
    "message": "Request contains an invalid argument.",
    "details": [ { "errors": [ { "errorCode": { "assetError": "NOT_ENOUGH_SQUARE_MARKETING_IMAGE_ASSET" }, "message": "Too few." } ] } ]
  }
}
```

Pass both `images.landscape` and `images.square` and repeat the call. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Keywords](/platforms/google-ads/keywords): read, add and research keywords.
- [Limits and errors](/platforms/google-ads/reference): the ops budget, what the API cannot do, and the errors Google returns most often.
- [Create standalone ad](/ad-campaigns/create-standalone-ad): every field, including `sitelinks`, `callouts` and `structuredSnippets`.

---
