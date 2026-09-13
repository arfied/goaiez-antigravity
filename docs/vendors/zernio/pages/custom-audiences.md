# Custom Audiences

Create Meta customer list, website, lookalike, engagement and saved targeting audiences with POST /v1/ads/audiences, and fill a customer list with hashed uploads.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Create an audience with `POST /v1/ads/audiences` and a `type`, then target it from a create call. Customer lists are filled with `POST /v1/ads/audiences/{audienceId}/users`; every other type fills itself from the pixel, source or seed you point it at. You need `accountId` and `adAccountId`, and creates are not idempotent, so never auto-retry one.

| `type` | Built from | Fields |
|---|---|---|
| [`customer_list`](#create-a-customer-list) | Emails and phones you upload, SHA-256 hashed server-side | rows through [`/users`](#add-users-to-a-customer-list), optional `customerFileSource` |
| `website` | Pixel visitors | `pixelId`, `retentionDays` (at most 180), optional `urlContains` or a raw Meta `rule` |
| [`lookalike`](#lookalike-audiences) | An existing audience as seed | `sourceAudienceId`, `country`, `ratio` (0.01 to 0.2) |
| `meta_engagement` | People who engaged with a Page, an Instagram account or a video | see [engagement audiences](#engagement-audiences) |
| `saved_targeting` | A stored targeting spec, no members | see [saved targeting](#saved-targeting) |

## Create a customer list

The list every integrator builds first: an audience filled from your own records. Create it empty, then upload the rows.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adaudiences.createAdAudience({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    type: 'customer_list',
    name: 'US customers'
  }
});

const audienceId = created.audience.id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.ad_audiences.create_ad_audience(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    type="customer_list",
    name="US customers",
)

audience_id = created["audience"]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/audiences" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "type": "customer_list",
    "name": "US customers"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "audience": {
    "id": "66e5b2c3d4f5a6b7c8d9e0f1",
    "platformAudienceId": "6123456789",
    "name": "US customers",
    "type": "customer_list"
  },
  "message": "Audience created"
}
```

`audience.id` is the `audienceId` for the other endpoints here; `platformAudienceId` is what `audienceInclude` and `audienceExclude` take in a targeting spec, and what a lookalike takes as its seed. `GET /v1/ads/audiences?accountId=...&adAccountId=...` lists them, with `size` and `status`, and `GET /v1/ads/audiences/{audienceId}` adds fresh data from Meta. `customerFileSource` declares where the rows came from and defaults to `USER_PROVIDED_ONLY`.

## Add users to a customer list

Call `POST /v1/ads/audiences/{audienceId}/users` with plaintext `email` or `phone` per row, up to 10,000 per request. Zernio normalizes and SHA-256 hashes every value before it reaches Meta.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: uploaded } = await zernio.adaudiences.addUsersToAdAudience({
  path: { audienceId: '66e5b2c3d4f5a6b7c8d9e0f1' },
  body: {
    users: [
      { email: 'user@example.com' },
      { phone: '+14155551234' }
    ]
  }
});
```
</Tab>
<Tab value="Python">
```python
uploaded = client.ad_audiences.add_users_to_ad_audience(
    audience_id="66e5b2c3d4f5a6b7c8d9e0f1",
    users=[
        {"email": "user@example.com"},
        {"phone": "+14155551234"},
    ],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/audiences/66e5b2c3d4f5a6b7c8d9e0f1/users" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "users": [
      { "email": "user@example.com" },
      { "phone": "+14155551234" }
    ]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{ "message": "Users added", "numReceived": 2, "numInvalid": 0 }
```

`numInvalid` counts the rows Meta could not read. Each call adds to the audience rather than replacing it.

## Lookalike audiences

`type: "lookalike"` needs a seed, so create it after the list above and pass that list's `platformAudienceId` as `sourceAudienceId`. `ratio` is the share of the country's population to match, 0.01 (the closest 1%) to 0.2:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "act_1234567890",
  "type": "lookalike",
  "name": "LAL 1% of US customers",
  "sourceAudienceId": "6123456789",
  "country": "US",
  "ratio": 0.01
}
```

The response has the same shape as the create above, with `type: "lookalike"`. Meta builds the audience from the seed's matched members, so upload the rows before creating the lookalike and check the seed's `size` on `GET /v1/ads/audiences/{audienceId}`.

## Website audiences

`type: "website"` takes a `pixelId` and `retentionDays`. Omit `urlContains` for every visitor of the pixel, or set it to narrow the audience to visitors of URLs containing that substring:

```json
{
  "type": "website",
  "name": "Checkout visitors 30d",
  "pixelId": "1729525464415281",
  "retentionDays": 30,
  "urlContains": "/checkout"
}
```

A raw Meta `rule` replaces the one Zernio builds. It must be Meta's flexible rule shape, with `event_sources`, `retention_seconds` and `filter` on every entry of `inclusions.rules`; a rule missing one is a `400` here instead of Meta's `Invalid rule JSON format` (subcode `1713098`), and the pre-2018 flat shapes are not accepted by Meta at all (subcode `1870029`).

## Engagement audiences

`type: "meta_engagement"` builds an audience of people who engaged with a Page, an Instagram professional account or a video:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: engagers } = await zernio.adaudiences.createAdAudience({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    type: 'meta_engagement',
    name: 'Page engagers 90d',
    engagementSource: 'page',
    sourceId: '811889972008357',
    retentionDays: 90
  }
});
```
</Tab>
<Tab value="Python">
```python
engagers = client.ad_audiences.create_ad_audience(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    type="meta_engagement",
    name="Page engagers 90d",
    engagement_source="page",
    source_id="811889972008357",
    retention_days=90,
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/audiences" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "type": "meta_engagement",
    "name": "Page engagers 90d",
    "engagementSource": "page",
    "sourceId": "811889972008357",
    "retentionDays": 90
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "audience": {
    "id": "66e5b2c3d4f5a6b7c8d9e0f3",
    "platformAudienceId": "6123456791",
    "name": "Page engagers 90d",
    "type": "meta_engagement"
  },
  "message": "Audience created"
}
```

| Field | Values |
|---|---|
| `engagementSource` | `page`, `instagram` or `video` |
| `sourceId` | The Page id, Instagram account id or video id |
| `retentionDays` | 1 to 365 |
| `event` (optional) | Defaults per source: `page_engaged`, `ig_business_profile_all`, `video_watched` |
| `rule` (optional) | A raw Meta engagement rule, forwarded verbatim, for sources beyond the 3 built-in ones (events, canvas, lead forms) |

<Callout type="warn">
The source must be eligible for engagement audiences: an active Page or Instagram account with tracked engagement, or a video with views. An ineligible source returns Meta's subcode `1713151` ("Invalid Event Name") verbatim. That is Meta's eligibility check, not a wrong `event` value.
</Callout>

Meta uses 2 wire formats here (a modern rule shape for Page and Instagram sources, a legacy flat one for video, which rejects the modern shape with subcode `1870049`); Zernio picks the right one, so the request above is all there is. LinkedIn's `engagement` type is a different audience with different fields, on [LinkedIn Ads](/platforms/linkedin-ads).

## Saved targeting

`type: "saved_targeting"` stores a reusable targeting spec on Zernio, with no members and no `adAccountId`. Reference it later as `savedTargetingId` on `POST /v1/ads/create`; inline targeting fields merge on top of it.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "type": "saved_targeting",
  "name": "US, 25 to 55, DevOps",
  "spec": {
    "countries": ["US"],
    "ageMin": 25,
    "ageMax": 55,
    "interests": [{ "id": "6003139266461", "name": "DevOps" }]
  }
}
```

Ads created from a saved targeting snapshot the spec at creation and are unaffected by later edits.

## Rename an audience

`PUT /v1/ads/audiences/{audienceId}` updates `name` and `description` on platform audiences (uploaded, website, lookalike, engagement), written to Meta first and mirrored locally. Their rules are immutable, so `spec` returns a `400` on them; `spec` is editable on `saved_targeting` audiences only. `DELETE /v1/ads/audiences/{audienceId}` removes the audience on Meta and locally.

## If it fails

A `422` on `/users` means the audience is not a customer list:

```json
{
  "error": "Audience is not a customer_list type or has no platform ID yet",
  "type": "invalid_request_error"
}
```

Only `customer_list` audiences take uploads; the other types fill themselves. A `400` with `type: "platform_error"` carries Meta's subcode in `platformError`; the [reference](/platforms/meta-ads/reference#common-errors) explains `1713151` and `1870049`.

## Related

- [Targeting](/platforms/meta-ads/targeting): the fields a saved targeting spec holds and `audienceInclude` on a create.
- [Pixels](/platforms/meta-ads/pixels): the `pixelId` a website audience reads from.
- [Create custom audience](/ad-audiences/create-ad-audience) and [Add users to audience](/ad-audiences/add-users-to-ad-audience): every field.

---
