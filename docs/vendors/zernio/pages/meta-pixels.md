# Meta Pixels

Create, read, rename and share a Meta Pixel through the tracking tags API, and read its firing stats.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

List the pixels an ads account already has, and create the one your conversion campaigns, website audiences and server-side events point at, with `GET` and `POST /v1/accounts/{accountId}/tracking-tags`. Zernio exposes pixels under the platform-neutral tracking tags API, where `kind` is `pixel` on Meta. It runs on the Meta ads account you already connected, so there is no extra consent screen. `accountId` is that ads account; the `act_<n>` ad account ids come from [`GET /v1/ads/accounts`](/platforms/meta-ads#find-your-ad-account-id).

## List the pixels you have

`GET /v1/accounts/{accountId}/tracking-tags` lists every pixel the connected ads account can see. Run it first: the pixel a campaign needs usually exists already, and the create below is not idempotent. Add `adAccountId` to scope the list to one ad account; omit it and each name carries the ad account it was discovered on. The list omits `code`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: existing } = await zernio.trackingtags.listTrackingTags({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  query: { adAccountId: 'act_1234567890' }
});
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

existing = client.tracking_tags.list_tracking_tags(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/tracking-tags?adAccountId=act_1234567890" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "metaads",
  "tags": [
    {
      "id": "1729525464415281",
      "name": "Website pixel",
      "platform": "metaads",
      "kind": "pixel",
      "status": "active",
      "lastFiredTime": 1804150800,
      "installed": true,
      "creationTime": 1804064400,
      "ownerBusinessId": "1049283746152738",
      "ownerAdAccountId": "act_1234567890"
    }
  ]
}
```

`GET /v1/accounts/{accountId}/tracking-tags/{tagId}` returns one pixel with the same fields plus its install `code`.

## Create a pixel

`adAccountId` and `name` are the only inputs. Meta owns the pixel through the Business Manager that owns the ad account.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.trackingtags.createTrackingTag({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { adAccountId: 'act_1234567890', name: 'Website pixel' }
});

const pixelId = created.tag.id;
```
</Tab>
<Tab value="Python">
```python
created = client.tracking_tags.create_tracking_tag(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Website pixel",
)

pixel_id = created["tag"]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/tracking-tags" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "adAccountId": "act_1234567890", "name": "Website pixel" }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "platform": "metaads",
  "tag": {
    "id": "1729525464415281",
    "name": "Website pixel",
    "platform": "metaads",
    "kind": "pixel",
    "status": "active",
    "code": "<script>!function(f,b,e,v,n,t,s){...}</script>",
    "lastFiredTime": null,
    "installed": false,
    "creationTime": 1804064400,
    "ownerBusinessId": "1049283746152738",
    "ownerAdAccountId": "act_1234567890"
  }
}
```

`tag.id` is the pixel id for `promotedObject.pixelId` on [conversion campaigns](/platforms/meta-ads/conversion-campaigns), for `pixelId` on a [website audience](/platforms/meta-ads/audiences#website-audiences) and for `destinationId` on the [Conversions API](/platforms/meta-ads/capi). Creating a pixel does not install it: put `code` on the site, or skip the snippet and send events server-side, which a new pixel accepts immediately. `installed` is derived from `lastFiredTime`, so a `null` there means the pixel has never fired.

<Callout type="warn">
The create is not idempotent: every call makes another pixel. Never auto-retry one. On a `502`, confirm with `GET /v1/accounts/{accountId}/tracking-tags` before sending it again. A `400` means an invalid `adAccountId`, an ad account outside a Business Manager, or the per-business pixel cap.
</Callout>

## Rename and update

`PATCH /v1/accounts/{accountId}/tracking-tags/{tagId}` updates a whitelist of fields, at least one of them: `name`, `enableAutomaticMatching` (Meta's Advanced Matching), `automaticMatchingFields`, `firstPartyCookieStatus` and `dataUseSetting`. `automaticMatchingFields` takes Meta's terse codes: `em` email, `ph` phone, `fn` first name, `ln` last name, `ge` gender, `db` date of birth, `ct` city, `st` state, `zp` ZIP, `country` and `external_id`.

```bash
curl -X PATCH "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/tracking-tags/1729525464415281" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "name": "Website pixel (renamed)", "enableAutomaticMatching": true }'
```

Response (`200`), the re-fetched tag:

```json
{
  "platform": "metaads",
  "tag": {
    "id": "1729525464415281",
    "name": "Website pixel (renamed)",
    "kind": "pixel",
    "status": "active",
    "lastFiredTime": 1804150800,
    "installed": true
  }
}
```

There is no delete. Meta has no API for it, so stop using a pixel by unsharing it or disabling it in Events Manager.

## Share a pixel with another ad account

A pixel works only in the ad account it was created on until you share it, which is what lets another account's campaigns and audiences use it.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
await zernio.trackingtags.addTrackingTagSharedAccount({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', tagId: '1729525464415281' },
  body: { adAccountId: 'act_9876543210' }
});
```
</Tab>
<Tab value="Python">
```python
client.tracking_tags.add_tracking_tag_shared_account(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    tag_id="1729525464415281",
    ad_account_id="act_9876543210",
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/tracking-tags/1729525464415281/shared-accounts" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "adAccountId": "act_9876543210" }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{ "platform": "metaads", "ok": true }
```

`GET` on the same path returns `sharedAccounts[]` with each account's `id`, `name` and `businessId`, and `DELETE` removes one. A pixel created on an ad account that no Business Manager owns comes back with `ownerBusinessId: null` and cannot be shared at all, because Meta rejects the share; claim the ad account into a Business Manager first. These endpoints share pixels with ad accounts; they do not expose partner-business sharing or system-user assignment.

## Firing stats

`GET /v1/accounts/{accountId}/tracking-tags/{tagId}/stats` returns aggregated event counts between `startTime` and `endTime` (Unix seconds).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: stats } = await zernio.trackingtags.getTrackingTagStats({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', tagId: '1729525464415281' },
  query: { aggregation: 'event', startTime: 1809216000, endTime: 1809475200 }
});
```
</Tab>
<Tab value="Python">
```python
stats = client.tracking_tags.get_tracking_tag_stats(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    tag_id="1729525464415281",
    aggregation="event",
    start_time=1809216000,
    end_time=1809475200,
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/tracking-tags/1729525464415281/stats?aggregation=event&startTime=1809216000&endTime=1809475200" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "metaads",
  "stats": {
    "aggregation": "event",
    "startTime": 1809216000,
    "endTime": 1809475200,
    "rows": [
      { "aggregation": "event", "data": [{ "value": 3 }] }
    ]
  }
}
```

Zernio echoes the query back and passes each row through untouched, so what a row holds is Meta's own `AdsPixelStatsResult` for the aggregation you asked for: read the keys you get rather than coding against a fixed shape. `aggregation` defaults to `event` and also accepts `host`, `url`, `url_by_rule`, `pixel_fire`, `device_type`, `device_os`, `browser_type`, `had_pii`, `custom_data_field`, `match_keys`, `event_source`, `event_detection_method`, `event_processing_results`, `event_total_counts` and `event_value_count`. Both time bounds are optional; omit them for Meta's own default window.

## Common errors

A `403` means the connection has no ads access:

```json
{
  "error": "Ads add-on required"
}
```

Ads are included with usage-based billing, so this is either a legacy plan without ads or a Meta token missing the ads permissions; reconnect the account ([scopes](/guides/connecting-accounts#scopes)). A `405` on the `PATCH` means the account is not Meta: pixel updates are Meta only.

## Related

- [Conversion campaigns](/platforms/meta-ads/conversion-campaigns): the `promotedObject.pixelId` a pixel feeds.
- [Conversions](/platforms/meta-ads/capi): send server-side events to the same pixel.
- [Audiences](/platforms/meta-ads/audiences#website-audiences): build a retargeting audience off it.
- [URL tracking tags](/platforms/meta-ads/tracking-tags): the click-URL parameters, which are a different thing.
- [Create tracking tag](/tracking-tags/create-tracking-tag) and [Tag stats](/tracking-tags/get-tracking-tag-stats): every field.

---
