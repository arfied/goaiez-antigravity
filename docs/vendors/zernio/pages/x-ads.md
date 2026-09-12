# X Ads

Create campaigns, promote posts you already published and upload Tailored Audiences on an xads account, with OAuth 1.0a signing handled server-side.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Create a campaign with `POST /v1/ads/create`, or promote a post you already published with `POST /v1/ads/boost`, on an `xads` account for X (platform value `twitter`). Zernio signs every X Ads call with OAuth 1.0a on its own servers, so your integration sends `$ZERNIO_API_KEY` the same way it does for every other platform.

## Quick reference

| Property | Value |
|----------|-------|
| Hierarchy | Campaign > Line Item > Promoted Tweet, created in one call |
| Goals (`goal`) | `engagement`, `traffic`, `awareness`, `video_views`, `app_promotion` |
| Ad text | `body`, 280 characters including the shortened link |
| Ignored creative fields | `headline`, `imageUrl`, `callToAction` |
| Media | None on a create (the ad is a text post); a boost carries the media of the post it promotes |
| Targeting | Country, postal code, language |
| Audiences | Tailored Audiences (create, member upload, read) |
| Analytics | Yes |
| Edits after creation | Status and budget only |

## Before you start

X Ads is a separate OAuth from X posting, so the profile needs both: a connected X posting account to author the ads, and the `xads` account created from it. There is no ads-only mode. You also need the X Ads account id, a base36 string such as `18ce54d4x5t`, from [List ad accounts](/ad-accounts/list-ad-accounts).

<Callout type="info">
`accountId` takes either shape. Pass the X posting account id or the `xads` account id and Zernio resolves the sibling internally. When the resolved profile has no X Ads connection, the call returns `422` with code `ads_connection_required`.
</Callout>

## Connect

Call `GET /v1/connect/twitter/ads` with `profileId` and the `accountId` of the X posting account the ads are authored by ([Connect ads](/connect/connect-ads)). `accountId` is required here: X ads carry a real author, so there is no ads-only path.

```bash
curl "https://zernio.com/api/v1/connect/twitter/ads?profileId=66a1f0c2a4b9d3e8f1a2b3c4&accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "authUrl": "https://api.x.com/oauth/authorize?oauth_token=...",
  "state": "..."
}
```

Send the user to `authUrl`, as in the [connecting accounts guide](/guides/connecting-accounts). X Ads keeps its own token, so this round trip always happens the first time; afterwards the same call returns `alreadyConnected: true` with the `xads` `accountId`. On failure the browser lands on your `redirect_url` with `platform=xads` and an `error` slug such as `x_ads_denied`, `x_ads_auth_failed` or `x_ads_token_failed`. Pass `adAccountId` on the connect call to scope ad sync to one X Ads account, or `adAccountIds` (repeated or comma-separated) for several. Sending neither leaves whatever scope is already stored; sending one removes ads already synced from the accounts it drops.

## Create a campaign

Call `POST /v1/ads/create`. On X, `body` carries the ad text, at most 280 characters, and X counts a `linkUrl` as about 24 of them because it shortens the link. `headline`, `imageUrl` and `callToAction` have no slot on X and are ignored: the ad X publishes is a text post built from `body` and `linkUrl`. To run an X ad with an image or a video, [promote a post](#promote-an-existing-post) that already carries it, within [X's media limits](/platforms/twitter/media).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '18ce54d4x5t',
    name: 'Q2 product awareness',
    goal: 'awareness',
    budgetAmount: 75,
    budgetType: 'daily',
    body: 'Ship faster with platform engineering done right.',
    linkUrl: 'https://example.com/platform',
    countries: ['US']
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
    ad_account_id="18ce54d4x5t",
    name="Q2 product awareness",
    goal="awareness",
    budget_amount=75,
    budget_type="daily",
    body="Ship faster with platform engineering done right.",
    link_url="https://example.com/platform",
    countries=["US"],
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
    "adAccountId": "18ce54d4x5t",
    "name": "Q2 product awareness",
    "goal": "awareness",
    "budgetAmount": 75,
    "budgetType": "daily",
    "body": "Ship faster with platform engineering done right.",
    "linkUrl": "https://example.com/platform",
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
    "name": "Q2 product awareness",
    "platform": "twitter",
    "status": "pending_review",
    "adType": "standalone",
    "goal": "awareness",
    "budget": { "amount": 75, "type": "daily" },
    "platformAdId": "5gzhb",
    "platformCampaignId": "f4x8j",
    "platformAdSetId": "9r2mv"
  },
  "message": "Ad created"
}
```

`budgetAmount` is in whole currency units of the ad account, so `75` is $75.00 on a USD account. `platformAdSetId` is the X line item. Every sample below reuses the `zernio` and `client` constructors from this one.

### Targeting fields

`countries`, `languages` and `zips` apply on X. `incomeTier` is rejected, and `cities`, `regions`, `metros`, `customLocations` and `behaviors` are Meta and TikTok only. `audienceInclude` and `audienceExclude` take the X custom audience id of a Tailored Audience.

## Promote an existing post

`POST /v1/ads/boost` promotes a post that is already published, keeping its engagement.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: boosted } = await zernio.adcampaigns.boostPost({
  body: {
    postId: '65f1c0a9e2b5af0012ab34cd',
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '18ce54d4x5t',
    name: 'Boost launch post',
    goal: 'engagement',
    budget: { amount: 50, type: 'daily' },
    schedule: { startDate: '2027-01-04', endDate: '2027-01-11' }
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
    ad_account_id="18ce54d4x5t",
    name="Boost launch post",
    goal="engagement",
    budget={"amount": 50, "type": "daily"},
    schedule={"startDate": "2027-01-04", "endDate": "2027-01-11"},
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
    "adAccountId": "18ce54d4x5t",
    "name": "Boost launch post",
    "goal": "engagement",
    "budget": { "amount": 50, "type": "daily" },
    "schedule": { "startDate": "2027-01-04", "endDate": "2027-01-11" }
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d1",
    "name": "Boost launch post",
    "platform": "twitter",
    "status": "pending_review",
    "adType": "boost",
    "goal": "engagement",
    "budget": { "amount": 50, "type": "daily" }
  },
  "message": "Ad created"
}
```

Boosts are not idempotent. Send an `Idempotency-Key` header to make a retry replay the original `201` instead of creating a second ad ([idempotency](/guides/idempotency)).

## Tailored Audiences

Create the audience with `POST /v1/ads/audiences` (`type: "customer_list"`), then upload members with `POST /v1/ads/audiences/{audienceId}/users`. X matches on email, ignores `phone`, and takes at most 10,000 users per request; Zernio hashes every value with SHA-256 before it leaves.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: audience } = await zernio.adaudiences.createAdAudience({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '18ce54d4x5t',
    type: 'customer_list',
    name: 'Trial signups'
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
    ad_account_id="18ce54d4x5t",
    type="customer_list",
    name="Trial signups",
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
  -d '{ "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "adAccountId": "18ce54d4x5t", "type": "customer_list", "name": "Trial signups" }'

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
  "audience": { "id": "66e5b2c3d4f5a6b7c8d9e0f1", "name": "Trial signups", "type": "customer_list", "platform": "twitter", "status": "pending" },
  "message": "Audience created"
}
```

Response (`200`):

```json
{ "message": "Users added", "numReceived": 2, "numInvalid": 0 }
```

An audience must match at least 100 recently active users before X allows targeting against it, so its size reads as 0 until then.

## Analytics

Call `GET /v1/ads/{adId}/analytics` with the ad's `_id` for spend, impressions, clicks, CTR, CPC and CPM over a date range ([Get ad analytics](/ad-insights/get-ad-analytics)).

```bash
curl "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0/analytics?fromDate=2027-01-04&toDate=2027-01-11" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), trimmed:

```json
{
  "ad": { "id": "66f0a1b2c3d4e5f6a7b8c9d0", "name": "Q2 product awareness", "platform": "twitter", "status": "active", "currency": "USD" },
  "analytics": {
    "summary": { "spend": 421.7, "impressions": 203800, "clicks": 3140, "ctr": 1.54, "cpc": 0.13, "cpm": 2.07, "engagement": 5820 },
    "daily": [ { "date": "2027-01-04", "spend": 59.8, "impressions": 29100, "clicks": 448 } ]
  }
}
```

X reports `reach` as 0, because Zernio does not sync it, and the demographic `breakdowns` parameter is Meta and TikTok only. `GET /v1/ads/tree` rolls the same metrics up per campaign and line item ([Get campaign tree](/ad-campaigns/get-ad-tree)).

## What you cannot do

X Ads through Zernio does not support:

- Targeting or creative edits after creation. `PUT /v1/ads/{adId}` takes status and budget; `targeting` or `creative` returns `501` with code `unsupported_platform_operation`.
- Campaign duplication. `POST /v1/ads/campaigns/{campaignId}/duplicate` returns `501` on X.
- An ads-only connection. Every X ad is authored by a connected posting account.
- Keyword targeting and follower look-alike targeting.
- Uploading creative with an ad. A create publishes text; media comes from the post a boost promotes.
- Income tier targeting, and city, region, metro or radius targeting.

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `422` with code `ads_connection_required` | The profile has no `xads` account | Run the [connect](#connect) flow with the X posting account id. |
| `400` with code `missing_required_field` | `body` or `linkUrl` is absent on a create | Send the ad text and its destination. |
| `400` with code `invalid_field_value` | A `goal` outside the 5 X values, or ad text over 280 characters | Use a supported goal and trim the text, counting about 24 characters for the link. |
| `403` with code `ads_allowance_exceeded` | The team has no payment method on file and has reached 500 live ads | Add a card to resume creating ads. |
| `502` with code `platform_api_error` | X rejected the request | Read `platformError` for X's own payload. |

A call against a profile with no X Ads connection returns:

```json
{
  "error": "X Ads is not connected for this profile",
  "type": "invalid_request_error",
  "code": "ads_connection_required",
  "param": "accountId"
}
```

[Error handling](/guides/error-handling) covers the envelope and the stable codes.

## Related

- [X](/platforms/twitter): the posting account that authors the ads.
- [Connecting accounts](/guides/connecting-accounts): the OAuth flow behind `authUrl`.
- [Create standalone ad](/ad-campaigns/create-standalone-ad) and [Boost post](/ad-campaigns/boost-post): every field of both requests.
- [Create ad audience](/ad-audiences/create-ad-audience) and [Add users to ad audience](/ad-audiences/add-users-to-ad-audience).
- [Get ad analytics](/ad-insights/get-ad-analytics): metrics, date ranges and rollups.

---
