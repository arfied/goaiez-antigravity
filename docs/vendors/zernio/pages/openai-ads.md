# OpenAI Ads

Run ChatGPT ads on an openaiads account, from connecting an API key to creating chat card campaigns, pixels and server-side conversions.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Create a ChatGPT chat card campaign with `POST /v1/ads/create` on an `openaiads` account, then measure it with a pixel and the server-side conversions endpoint. OpenAI Ads has no OAuth: you connect by posting an API key from ChatGPT Ads Manager, and one key covers exactly one ad account.

## Quick reference

| Property | Value |
|----------|-------|
| Hierarchy | Campaign > Ad group > Chat card ad, created in one call |
| Goals (`goal`) | `traffic`, `awareness`, `conversions` |
| Title (`headline`) | Required, 3 to 50 characters |
| Body | Required, up to 100 characters |
| Creative | `imageUrl` and `linkUrl`, both required, static image only |
| Budget | `budgetType: "lifetime"` with `endDate`, minimum $1 |
| Targeting | Country only |
| Bidding | Max bid, and a target CPA under the conversions goal |
| Conversion tracking | Pixel plus server-side Conversions API |
| Performance sync | Daily impressions, clicks and spend ([analytics](#analytics)) |
| Edits after creation | Status and budget only |
| Boost an existing post | No, ChatGPT has no organic posts |
| Custom audiences | No, OpenAI exposes audiences in Ads Manager only |
| Product feed upload | No, feeds are managed in Ads Manager over SFTP |
| Video creatives | No, chat cards are images |

## Before you start

Create the key in ChatGPT Ads Manager under Settings. It is scoped to one ad account, so connect each ad account separately. OpenAI opens Ads Manager to US-based businesses, and the ads serve to ChatGPT Free and Go users in the United States, Canada, Australia and New Zealand.

<Callout type="warn">
The key you paste carries full campaign write access, because OpenAI has no read-only key scope. Zernio uses it to read your ads and performance, and to create and manage the campaigns you set up in Zernio. Campaigns built in ChatGPT Ads Manager stay editable there.
</Callout>

## Connect

Call `POST /v1/connect/openai-ads/credentials` with the key and a `profileId`. Zernio validates the key against OpenAI before storing anything, and reposting a rotated key for the same ad account updates the connection in place. The endpoint also accepts the `x-connect-token` header, so you can hand the flow to your own customers like every other connect endpoint.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: connected } = await zernio.connect.connectOpenAIAdsCredentials({
  body: {
    apiKey: 'sk-ads-xxxxxxxxxxxxxxxxxxxx',
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4'
  }
});

console.log(connected.accountId);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

connected = client.connect.connect_open_ai_ads_credentials(
    api_key="sk-ads-xxxxxxxxxxxxxxxxxxxx",
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
)

print(connected["accountId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/connect/openai-ads/credentials" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "apiKey": "sk-ads-xxxxxxxxxxxxxxxxxxxx",
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountName": "Acme US"
}
```

A key that cannot read an OpenAI ad account returns `401` with code `invalid_credentials`. A `402` with `code: "PAYMENT_REQUIRED"` is a billing gate closing before the key is even checked, usually `reason: "free_tier_exceeded"`: the team is past its free connected accounts with no card on file, and `dashboard_url` in the body is where the end user adds one ([connect failures](/guides/connecting-accounts#if-it-fails)). The `accountId` is the `openaiads` account every sample below uses. Connecting from the Zernio dashboard, under Connections, does the same thing.

## Create a campaign

A chat card carries a title, a body, an image and a destination URL, so `headline`, `body`, `imageUrl` and `linkUrl` are all required. OpenAI has no daily budget, so `budgetType` must be `lifetime` and `endDate` gives the lifetime cap its spend window.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'acct_abc123',
    name: 'Planner launch',
    goal: 'traffic',
    budgetAmount: 25,
    budgetType: 'lifetime',
    endDate: '2027-02-28T23:59:00Z',
    bidStrategy: 'LOWEST_COST_WITH_BID_CAP',
    bidAmount: 2,
    headline: 'Try the new planner',
    body: 'Coordinate tasks, docs and meetings in one place.',
    imageUrl: 'https://cdn.example.com/planner.png',
    linkUrl: 'https://example.com/planner',
    countries: ['US']
  }
});

console.log(created.ad._id);
```
</Tab>
<Tab value="Python">
```python
created = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="acct_abc123",
    name="Planner launch",
    goal="traffic",
    budget_amount=25,
    budget_type="lifetime",
    end_date="2027-02-28T23:59:00Z",
    bid_strategy="LOWEST_COST_WITH_BID_CAP",
    bid_amount=2,
    headline="Try the new planner",
    body="Coordinate tasks, docs and meetings in one place.",
    image_url="https://cdn.example.com/planner.png",
    link_url="https://example.com/planner",
    countries=["US"],
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
    "adAccountId": "acct_abc123",
    "name": "Planner launch",
    "goal": "traffic",
    "budgetAmount": 25,
    "budgetType": "lifetime",
    "endDate": "2027-02-28T23:59:00Z",
    "bidStrategy": "LOWEST_COST_WITH_BID_CAP",
    "bidAmount": 2,
    "headline": "Try the new planner",
    "body": "Coordinate tasks, docs and meetings in one place.",
    "imageUrl": "https://cdn.example.com/planner.png",
    "linkUrl": "https://example.com/planner",
    "countries": ["US"]
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d0",
    "name": "Planner launch",
    "platform": "openai",
    "status": "pending_review",
    "adType": "standalone",
    "goal": "traffic",
    "budget": { "amount": 25, "type": "lifetime" }
  },
  "message": "Ad created"
}
```

`budgetAmount` is in whole currency units, so `25` is $25.00, and the minimum is $1. Every sample below reuses the `zernio` and `client` constructors from this one.

### Goals and bidding

`traffic` bids on clicks, `awareness` on impressions and `conversions` on conversion events. The `conversions` goal needs an active conversion event setting on the ad account before the create call, from a [tracking tag](#conversion-tracking) with `defaultEventType` or from ChatGPT Ads Manager; without one the request returns `422`. Any other goal returns `400`.

OpenAI requires a bid cap on every ad group, so `bidStrategy` and `bidAmount` are required on the create: `LOWEST_COST_WITH_BID_CAP` and `COST_CAP` both map to the ad group's `bidding_config.max_bid_micros`, and `bidAmount` in whole currency units is converted to micros. There is no auto-bid option, so omitting `bidStrategy` or sending `LOWEST_COST_WITHOUT_CAP` returns `400` with code `missing_required_field`. `LOWEST_COST_WITH_MIN_ROAS` returns `422`, because OpenAI has no ROAS-based bidding.

### Targeting

Targeting is by country. Every other targeting field, including ages, genders, interests, `audienceInclude` and `audienceExclude`, returns `400` rather than being dropped silently.

## Manage ads

`PUT /v1/ads/{adId}` changes status and budget, and `PUT /v1/ads/{adId}/status` pauses or resumes a single ad, as on every other ads platform. A budget update stays lifetime-only. Sending `targeting` or `creative` returns `501` with code `unsupported_platform_operation`.

<Callout type="warn">
Deleting archives. OpenAI has no delete API, and archiving is terminal. Cancelling an ad in Zernio archives the ad, its ad group and its campaign on OpenAI, then marks the ad `cancelled` in Zernio.
</Callout>

## Conversion tracking

`POST /v1/accounts/{accountId}/tracking-tags` creates an OpenAI pixel and provisions a Conversions API key for it in one call. `adAccountId` is required by the endpoint and ignored here, because the API key already selects the ad account. Pass `defaultEventType` to provision the conversion event setting that `goal: "conversions"` needs; it takes one of `order_created`, `lead_created`, `items_added`, `contents_viewed`, `checkout_started`, `registration_completed`, `subscription_created`, `trial_started`, `appointment_scheduled`, `page_viewed`, `app_installed` or `app_opened`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: createdTag } = await zernio.trackingtags.createTrackingTag({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { adAccountId: 'acct_abc123', name: 'Acme website', defaultEventType: 'order_created' }
});

console.log(createdTag.tag.id);
```
</Tab>
<Tab value="Python">
```python
created_tag = client.tracking_tags.create_tracking_tag(
    "66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="acct_abc123",
    name="Acme website",
    default_event_type="order_created",
)

print(created_tag["tag"]["id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/tracking-tags" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "adAccountId": "acct_abc123", "name": "Acme website", "defaultEventType": "order_created" }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "platform": "openaiads",
  "tag": {
    "id": "134534000000",
    "name": "Acme website",
    "platform": "openaiads",
    "status": "active"
  }
}
```

<Callout type="warn">
Creating a tag is not idempotent and OpenAI pixels cannot be deleted, so never retry this call automatically: each call creates another pixel plus another Conversions API key. If the pixel is created but the key provisioning fails, the pixel stays live on OpenAI and the error names it. An ad account that is not enabled for pixel management returns `422` with code `FEATURE_NOT_AVAILABLE`; your OpenAI partner representative enables it.
</Callout>

### Send events

`POST /v1/ads/conversions` relays server-side events to OpenAI's Conversions API. `destinationId` is the pixel wire id, from `GET /v1/accounts/{accountId}/conversion-destinations` ([List conversion destinations](/conversions/list-conversion-destinations)). Zernio hashes email identifiers with SHA-256 and sends money in minor units, so send plaintext and whole currency values.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sent } = await zernio.conversions.sendConversions({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    destinationId: '134534000000',
    events: [{
      eventName: 'Purchase',
      eventTime: 1798804800,
      eventId: 'order_12345',
      sourceUrl: 'https://shop.example.com/checkout/confirmation',
      value: 25.99,
      currency: 'USD',
      user: { email: 'customer@example.com' }
    }]
  }
});

console.log(sent.eventsReceived);
```
</Tab>
<Tab value="Python">
```python
sent = client.conversions.send_conversions(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    destination_id="134534000000",
    events=[{
        "eventName": "Purchase",
        "eventTime": 1798804800,
        "eventId": "order_12345",
        "sourceUrl": "https://shop.example.com/checkout/confirmation",
        "value": 25.99,
        "currency": "USD",
        "user": {"email": "customer@example.com"},
    }],
)

print(sent["eventsReceived"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/conversions" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "destinationId": "134534000000",
    "events": [{
      "eventName": "Purchase",
      "eventTime": 1798804800,
      "eventId": "order_12345",
      "sourceUrl": "https://shop.example.com/checkout/confirmation",
      "value": 25.99,
      "currency": "USD",
      "user": { "email": "customer@example.com" }
    }]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "openaiads",
  "eventsReceived": 1,
  "eventsFailed": 0,
  "failures": []
}
```

`Purchase`, `Lead`, `AddToCart`, `ViewContent`, `InitiateCheckout`, `CompleteRegistration`, `Subscribe`, `StartTrial` and `Schedule` map 1 to 1 onto OpenAI's own event types, so `Purchase` arrives as `order_created`, `Lead` as `lead_created` and `AddToCart` as `items_added`. Any other name is sent as a custom event with the name preserved. Larger submissions are split into chunks of 1,000 events, and each chunk is all-or-nothing: one malformed event fails its chunk, and `eventsFailed` plus `failures[]` name the events that did not land.

## Creative and account limits

| Limit | Value |
|-------|-------|
| Title | 3 to 50 characters |
| Body | Up to 100 characters |
| Budget | Lifetime only, minimum $1 |
| Targeting | Country only |
| Creative | Static image chat cards |
| Events per conversions request | 1,000 |
| OpenAI rate limits | 600 requests per minute per endpoint, 1,200 per minute overall |

## Analytics

Call `GET /v1/ads/{adId}/analytics` with the ad's `_id` ([Get ad analytics](/ad-insights/get-ad-analytics)). Zernio syncs impressions, clicks and spend from OpenAI once a day and derives CTR, CPC and CPM from them. `fromDate` and `toDate` default to the last 90 days.

```bash
curl "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0/analytics?fromDate=2027-01-04&toDate=2027-01-11" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), trimmed:

```json
{
  "ad": { "id": "66f0a1b2c3d4e5f6a7b8c9d0", "name": "Planner launch", "platform": "openai", "status": "active", "currency": "USD" },
  "analytics": {
    "summary": { "spend": 142.8, "impressions": 61200, "clicks": 940, "ctr": 1.54, "cpc": 0.15, "cpm": 2.33 },
    "daily": [ { "date": "2027-01-04", "spend": 21.4, "impressions": 9100, "clicks": 138 } ]
  }
}
```

Those 3 metrics are the whole sync: every other field of the response stays 0 on OpenAI, `reach` included, and the demographic `breakdowns` parameter is Meta and TikTok only. `backfillPending: true` means the history is still loading, so read it again shortly. `GET /v1/ads/tree` rolls the same metrics up per campaign and ad group.

## What you cannot do

OpenAI Ads through Zernio does not support:

- Boosting an existing post, because ChatGPT has no organic posts.
- Custom audiences, which OpenAI exposes in Ads Manager only.
- Product feed upload, which stays in Ads Manager over SFTP.
- Video creatives.
- Targeting or creative edits after creation, and any targeting beyond country.
- Deleting a pixel or an ad. Cancelling archives instead.

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `401` with code `invalid_credentials` | The pasted key cannot read an OpenAI ad account | Recreate the key in ChatGPT Ads Manager under Settings and post it again. |
| `422` on create | `goal: "conversions"` with no active conversion event setting | Create a tracking tag with `defaultEventType`, or configure the event in Ads Manager. |
| `422` on `budgetType: "daily"` | OpenAI has no daily budgets | Send `budgetType: "lifetime"` with an `endDate`. |
| `422` with code `TRACKING_TAG_REQUIRED` | Conversions sent before any pixel exists | Create the tracking tag first. |
| `422` with code `FEATURE_NOT_AVAILABLE` | The ad account is not enabled for pixel management | Ask your OpenAI partner representative to enable it. |
| `400` on a targeting field | Anything beyond `countries` | Remove the field; country is the only dimension OpenAI honours. |
| `501` with code `unsupported_platform_operation` | `targeting` or `creative` sent to `PUT /v1/ads/{adId}` | Change status or budget, and recreate the ad for anything else. |

A create with a daily budget returns the flat envelope:

```json
{
  "error": "OpenAI Ads accepts lifetime budgets only",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "budgetType"
}
```

[Error handling](/guides/error-handling) covers the envelope and the stable codes.

## Related

- [Connect an OpenAI Ads account](/connect/connect-open-aiads-credentials): every field of the connect call.
- [Create standalone ad](/ad-campaigns/create-standalone-ad): every field of the create request.
- [Create tracking tag](/tracking-tags/create-tracking-tag): pixels and Conversions API keys.
- [Send conversions](/conversions/send-conversions) and [List conversion destinations](/conversions/list-conversion-destinations).
- [Connecting accounts](/guides/connecting-accounts): profiles, accounts and the connect token.

---
