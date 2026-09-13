# Pinterest Ads

Create Promoted Pin campaigns, promote Pins you already published, upload customer list audiences and read ad metrics on a pinterestads account.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Create a Promoted Pin campaign with `POST /v1/ads/create`, or promote a Pin you already published with `POST /v1/ads/boost`, on a `pinterestads` account. The ads account reuses the token of the Pinterest posting account in the same profile, so there is no second OAuth flow and no Pinterest developer application to file.

## Quick reference

| Property | Value |
|----------|-------|
| Hierarchy | Campaign > Ad Group > Promoted Pin, created in one call |
| Goals (`goal`) | `engagement`, `traffic`, `awareness`, `video_views` |
| Headline | Required, 100 characters |
| Body | Required, 500 characters |
| Creative | `imageUrl` and `linkUrl`, both required |
| Minimum budget | $5 (`budgetAmount`, or `budget.amount` on a boost) |
| Board | `boardId`, optional |
| Targeting | Country, region, metro, postal code, age, gender, interests |
| Audiences | Customer list |
| Analytics | Yes |
| Edits after creation | Status and budget only |
| Conversions API | Roadmap |
| Catalog and shopping ads | Roadmap |

## Before you start

You need a connected Pinterest account in the profile ([Pinterest](/platforms/pinterest)) and the Pinterest ad account id, from [List ad accounts](/ad-accounts/list-ad-accounts). Ads run on 2 scopes ([scopes](/guides/connecting-accounts#scopes)):

| Scope | What it enables |
|-------|-----------------|
| `ads:read` | Read ad accounts and reporting |
| `ads:write` | Create and manage campaigns, ad groups and Promoted Pins |

New Pinterest connections request both as part of the standard flow. An account connected before Pinterest Ads existed re-consents to these 2 scopes.

## Connect

Call `GET /v1/connect/pinterest/ads` with `profileId` ([Connect ads](/connect/connect-ads)). Pinterest shares one token between posting and ads, so when the profile already holds an active Pinterest account the `pinterestads` account inherits that token and no OAuth happens.

```bash
curl "https://zernio.com/api/v1/connect/pinterest/ads?profileId=66a1f0c2a4b9d3e8f1a2b3c4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "alreadyConnected": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platform": "pinterest",
  "username": "@acmehome",
  "displayName": "Acme Home"
}
```

Without an active Pinterest account the response carries `authUrl` and `state` instead; send the user to `authUrl`, as in the [connecting accounts guide](/guides/connecting-accounts). A Pinterest account whose stored token cannot reach ad accounts fails with `400 RECONNECT_REQUIRED`, which reconnecting the posting account clears. The `accountId` in the response is the `pinterestads` account every sample below uses.

<Callout type="info">
Pinterest has no ads discovery, so the `adAccountId` and `adAccountIds` scoping parameters are accepted and ignored on this call. Pass the ad account per request instead.
</Callout>

## Create a Promoted Pin

Pinterest creates the Pin and promotes it in the same call, so `headline`, `body`, `imageUrl` and `linkUrl` are all required. `boardId` chooses the board; without it the Pin lands on a `Zernio Ads` board that Zernio creates once. `startDate` and `endDate` set the run window at the top level of the body, where the boost below takes a `schedule` object instead; omit them and the Promoted Pin runs until you pause it. An ad account that requires Campaign Budget Optimization needs `endDate` with a `lifetime` budget and returns a `400` naming `endDate` without one.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '549123456789',
    name: 'Spring home decor',
    goal: 'traffic',
    budgetAmount: 30,
    budgetType: 'daily',
    headline: 'Freshen up your space',
    body: 'Spring drops, limited time.',
    imageUrl: 'https://cdn.example.com/spring-decor.jpg',
    linkUrl: 'https://example.com/spring-decor',
    countries: ['US', 'CA'],
    ageMin: 25,
    ageMax: 44
  }
});

console.log(created.ad.platformAdSetId);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="549123456789",
    name="Spring home decor",
    goal="traffic",
    budget_amount=30,
    budget_type="daily",
    headline="Freshen up your space",
    body="Spring drops, limited time.",
    image_url="https://cdn.example.com/spring-decor.jpg",
    link_url="https://example.com/spring-decor",
    countries=["US", "CA"],
    age_min=25,
    age_max=44,
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
    "adAccountId": "549123456789",
    "name": "Spring home decor",
    "goal": "traffic",
    "budgetAmount": 30,
    "budgetType": "daily",
    "headline": "Freshen up your space",
    "body": "Spring drops, limited time.",
    "imageUrl": "https://cdn.example.com/spring-decor.jpg",
    "linkUrl": "https://example.com/spring-decor",
    "countries": ["US", "CA"],
    "ageMin": 25,
    "ageMax": 44
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d0",
    "name": "Spring home decor",
    "platform": "pinterest",
    "status": "pending_review",
    "adType": "standalone",
    "goal": "traffic",
    "budget": { "amount": 30, "type": "daily" },
    "platformAdId": "687195120340",
    "platformCampaignId": "626746010001",
    "platformAdSetId": "2680069880002"
  },
  "message": "Ad created"
}
```

`budgetAmount` is in whole currency units of the ad account, so `30` is $30.00 on a USD account. Every sample below reuses the `zernio` and `client` constructors from this one.

### Targeting fields

`countries`, `regions`, `metros`, `zips`, `ageMin`, `ageMax`, `gender` and `interests` (from `GET /v1/ads/interests`) apply on Pinterest, and `audienceInclude` and `audienceExclude` take the Pinterest customer list id of an audience. Pinterest's geo spec takes region codes or postal codes but never both, so sending `regions` and `zips` together returns a `400` with code `mutually_exclusive_fields`. `incomeTier` is rejected on Pinterest, and `cities`, `customLocations` and `behaviors` are Meta and TikTok only.

## Promote an existing Pin

`POST /v1/ads/boost` promotes a Pin that is already published, keeping its engagement. Pass the Zernio `postId` (or the Pinterest `platformPostId`), the account, the ad account, a goal and a budget.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: boosted } = await zernio.adcampaigns.boostPost({
  body: {
    postId: '65f1c0a9e2b5af0012ab34cd',
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '549123456789',
    name: 'Boost decor Pin',
    goal: 'traffic',
    budget: { amount: 30, type: 'daily' },
    schedule: { startDate: '2027-01-04T09:00:00Z', endDate: '2027-02-04T23:59:00Z' }
  }
});

console.log(boosted.ad._id);
```
</Tab>
<Tab value="Python">
```python
boosted = client.ad_campaigns.boost_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="549123456789",
    name="Boost decor Pin",
    goal="traffic",
    budget={"amount": 30, "type": "daily"},
    schedule={"startDate": "2027-01-04T09:00:00Z", "endDate": "2027-02-04T23:59:00Z"},
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
    "adAccountId": "549123456789",
    "name": "Boost decor Pin",
    "goal": "traffic",
    "budget": { "amount": 30, "type": "daily" },
    "schedule": { "startDate": "2027-01-04T09:00:00Z", "endDate": "2027-02-04T23:59:00Z" }
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d1",
    "name": "Boost decor Pin",
    "platform": "pinterest",
    "status": "pending_review",
    "adType": "boost",
    "goal": "traffic",
    "budget": { "amount": 30, "type": "daily" }
  },
  "message": "Ad created"
}
```

Boosts are not idempotent. Send an `Idempotency-Key` header to make a retry replay the original `201` instead of creating a second ad ([idempotency](/guides/idempotency)).

## Customer list audiences

Create the audience with `POST /v1/ads/audiences` (`type: "customer_list"`), then upload members with `POST /v1/ads/audiences/{audienceId}/users`. Pinterest matches on email, ignores `phone`, and takes at most 10,000 users per request; Zernio hashes every value with SHA-256 before it leaves.

Pinterest needs the member file at creation time, so the audience is provisioned lazily: the create records it with status `pending` and no platform id, and the first upload creates it on Pinterest.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: audience } = await zernio.adaudiences.createAdAudience({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '549123456789',
    type: 'customer_list',
    name: 'Repeat buyers'
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
    ad_account_id="549123456789",
    type="customer_list",
    name="Repeat buyers",
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
  -d '{ "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "adAccountId": "549123456789", "type": "customer_list", "name": "Repeat buyers" }'

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
  "audience": { "id": "66e5b2c3d4f5a6b7c8d9e0f1", "name": "Repeat buyers", "type": "customer_list", "platform": "pinterest", "status": "pending" },
  "message": "Audience created"
}
```

Response (`200`):

```json
{ "message": "Users added", "numReceived": 2, "numInvalid": 0 }
```

## Media requirements

| Type | Formats | Max size | Notes |
|------|---------|----------|-------|
| Standard Pin | JPEG, PNG | 20 MB | 1000 x 1500 px (2:3) recommended |
| Video Pin | MP4, MOV, M4V | 2 GB | 4 seconds to 15 minutes, 9:16 or 1:1 |

Creatives are fetched from the URL you pass, which must be public and return the media bytes ([media uploads](/guides/media-uploads)).

## Analytics

Call `GET /v1/ads/{adId}/analytics` with the ad's `_id` for spend, impressions, clicks, CTR, CPC and CPM over a date range ([Get ad analytics](/ad-insights/get-ad-analytics)). Pinterest reports saves and closeups alongside those standard metrics.

```bash
curl "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0/analytics?fromDate=2027-01-04&toDate=2027-01-11" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), trimmed:

```json
{
  "ad": { "id": "66f0a1b2c3d4e5f6a7b8c9d0", "name": "Spring home decor", "platform": "pinterest", "status": "active", "currency": "USD" },
  "analytics": {
    "summary": { "spend": 186.5, "impressions": 74300, "clicks": 1240, "ctr": 1.67, "cpc": 0.15, "cpm": 2.51 },
    "daily": [ { "date": "2027-01-04", "spend": 27.9, "impressions": 10600, "clicks": 181 } ]
  }
}
```

Pinterest reports `reach` as 0, because Zernio does not sync it, and the demographic `breakdowns` parameter is Meta and TikTok only. `GET /v1/ads/tree` rolls the same metrics up per campaign and ad group ([Get campaign tree](/ad-campaigns/get-ad-tree)).

## What you cannot do

Pinterest Ads through Zernio does not support:

- Targeting or creative edits after creation. `PUT /v1/ads/{adId}` takes status and budget; `targeting` or `creative` returns `501` with code `unsupported_platform_operation`.
- Campaign duplication. `POST /v1/ads/campaigns/{campaignId}/duplicate` returns `501` on Pinterest.
- Ad-account scoping on the connect call, because Pinterest has no ads discovery.
- Income tier, city and radius targeting. Regions, metros and postal codes are supported.
- The Pinterest Conversions API, and catalog or shopping ads.

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `400` with code `missing_required_field` | One of `headline`, `body`, `imageUrl` or `linkUrl` is absent | Send all 4; Pinterest builds the Pin from them. |
| `400` with code `invalid_field_value` | A `goal` outside the 4 Pinterest values, or a headline over 100 or a body over 500 characters | Use a supported goal and trim the copy. |
| `403` with code `ads_allowance_exceeded` | The team has no payment method on file and has reached 500 live ads | Add a card to resume creating ads. |
| `501` with code `unsupported_platform_operation` | `targeting` or `creative` sent to `PUT /v1/ads/{adId}` | Change status or budget, and recreate the ad for anything else. |
| `502` with code `platform_api_error` | Pinterest rejected the request | Read `platformError` for Pinterest's own payload. |

A rejected create returns the flat envelope:

```json
{
  "error": "headline is required",
  "type": "invalid_request_error",
  "code": "missing_required_field",
  "param": "headline"
}
```

[Error handling](/guides/error-handling) covers the envelope and the stable codes.

## Related

- [Pinterest](/platforms/pinterest): the posting account this ads account inherits its token from.
- [Connecting accounts](/guides/connecting-accounts): the OAuth flow behind `authUrl`.
- [Create standalone ad](/ad-campaigns/create-standalone-ad) and [Boost post](/ad-campaigns/boost-post): every field of both requests.
- [Create ad audience](/ad-audiences/create-ad-audience) and [Add users to ad audience](/ad-audiences/add-users-to-ad-audience).
- [Get ad analytics](/ad-insights/get-ad-analytics): metrics, date ranges and rollups.

---
