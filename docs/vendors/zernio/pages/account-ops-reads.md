# Account & Ops Reads

Read a Meta ad account's change log, A/B studies, finances, labels, budget schedules, Business portfolios, Instagram identities and app-promotion setup.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Read what surrounds the campaign tree: who changed what, what is being tested, how much money is left, and how the account is organized. Every endpoint here is Meta only. Response shapes vary by endpoint; the discovery reads below normalize Meta's identity and app data. All of them take `accountId`. All but the business list and the budget schedules also take `adAccountId`; the budget schedules are scoped by `campaignId` or `adSetId` instead.

| Endpoint | Returns |
|---|---|
| [`GET /v1/ads/activity`](#change--audit-log) | The ad account's change history |
| [`GET /v1/ads/studies`](#ab-tests--lift-studies) | A/B tests and lift studies |
| [`GET /v1/ads/accounts/finance`](#account-finances) | Balance, spend, spend cap and funding source |
| [`GET /v1/ads/labels`](#ad-labels) | Ad labels |
| [`GET /v1/ads/high-demand-periods`](#high-demand-periods) | Scheduled budget increases |
| [`GET /v1/ads/businesses`](#business-portfolios) | The Business Manager portfolios the connection belongs to |
| [`GET /v1/ads/instagram-accounts`](#instagram-ad-identities) | Instagram identities an ad can run as, and the resolved default |
| [`GET /v1/ads/advertisable-applications`](#app-promotion-discovery) | Apps this ad account can promote |
| [`GET /v1/ads/ios-fourteen-campaign-limits`](#app-promotion-discovery) | An app's iOS 14 SKAdNetwork campaign limits |

Two related reads live on their own pages: the [ad set details and learning phase](/platforms/meta-ads/ad-sets#read-an-ad-set-live) and [pixel firing stats](/platforms/meta-ads/pixels#firing-stats).

## Change / audit log

`GET /v1/ads/activity` returns who changed what and when, with Meta's `translated_event_type` and the structured before and after values in `extra_data`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: activity } = await zernio.adaccounts.getAdsActivityLog({
  query: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    since: '2027-02-01',
    until: '2027-02-20'
  }
});
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

activity = client.ad_accounts.get_ads_activity_log(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    since="2027-02-01",
    until="2027-02-20",
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/activity?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890&since=2027-02-01&until=2027-02-20" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), one row:

```json
{
  "adAccountId": "act_1234567890",
  "data": [
    {
      "event_type": "update_campaign_budget",
      "translated_event_type": "Edited campaign budget",
      "object_id": "120250000000000000",
      "extra_data": "{\"old_value\":\"7500\",\"new_value\":\"25000\"}"
    }
  ],
  "paging": { "after": "QVFIUm..." }
}
```

`objectId` filters the returned page rather than the query: Meta's activities edge has no server-side per-object filter. To walk one object's full history, raise `limit` (1 to 200, default 50) and pass the previous response's `paging.after` back as `after` until `after` comes back `null`.

```bash
curl "https://zernio.com/api/v1/ads/activity?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890&objectId=120250000000000000&limit=200&after=QVFIUm..." \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), the last page:

```json
{
  "adAccountId": "act_1234567890",
  "data": [],
  "paging": { "after": null }
}
```

## A/B tests & lift studies

`GET /v1/ads/studies` returns the account's `/ad_studies` rows verbatim. The default projection covers the id, name, type, timing and the cells with their split percentages; `fields` overrides it.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: studies } = await zernio.adaccounts.listAdStudies({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', adAccountId: 'act_1234567890' }
});
```
</Tab>
<Tab value="Python">
```python
studies = client.ad_accounts.list_ad_studies(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/studies?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), one row:

```json
{
  "adAccountId": "act_1234567890",
  "data": [
    { "id": "7412998877665544", "name": "Creative split March", "type": "SPLIT_TEST" }
  ],
  "paging": { "after": null }
}
```

## Account finances

`GET /v1/ads/accounts/finance` returns the money numbers in whole currency units.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: finance } = await zernio.adaccounts.getAdAccountFinance({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', adAccountId: 'act_1234567890' }
});
```
</Tab>
<Tab value="Python">
```python
finance = client.ad_accounts.get_ad_account_finance(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/accounts/finance?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "adAccountId": "act_1234567890",
  "currency": "USD",
  "balance": 214.50,
  "amountSpent": 7823.11,
  "spendCap": null,
  "fundingSource": { "displayString": "Mastercard *4985", "type": 1 }
}
```

`spendCap: null` means no cap. This is the account-level cap, a billing guardrail; the campaign-level one is a delivery setting on [`platformSpecificData.spendCap`](/platforms/meta-ads/campaigns#campaign-spend-cap).

## Ad labels

`GET /v1/ads/labels` returns the account's `/adlabels` rows with `limit` and `after` paging. Labels are how Ads Manager users group campaigns, ad sets, ads and creatives across the tree, so reading them lets you mirror those groupings in your own interface.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: labels } = await zernio.adaccounts.listAdLabels({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', adAccountId: 'act_1234567890' }
});
```
</Tab>
<Tab value="Python">
```python
labels = client.ad_accounts.list_ad_labels(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/labels?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), one row:

```json
{
  "adAccountId": "act_1234567890",
  "data": [
    {
      "id": "120250000000000021",
      "name": "Q1 evergreen",
      "created_time": "2027-01-04T09:00:00+0000",
      "updated_time": "2027-02-11T16:42:03+0000"
    }
  ],
  "paging": { "after": null }
}
```

## High demand periods

`GET /v1/ads/high-demand-periods` returns Meta's budget schedules, the scheduled increases behind a weekend or a sale, as raw rows. Pass exactly one of `campaignId` or `adSetId`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: schedules } = await zernio.adaccounts.listHighDemandPeriods({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', campaignId: '120250000000000000' }
});
```
</Tab>
<Tab value="Python">
```python
schedules = client.ad_accounts.list_high_demand_periods(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    campaign_id="120250000000000000",
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/high-demand-periods?accountId=66b2e19d8c3f5a7e9d0b1c2d&campaignId=120250000000000000" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "objectId": "120250000000000000",
  "data": [
    {
      "id": "6512348899001",
      "time_start": 1809216000,
      "time_end": 1809475200,
      "budget_value": 2,
      "budget_value_type": "MULTIPLIER",
      "recurrence_type": "ONE_TIME"
    }
  ],
  "paging": { "after": null }
}
```

The Graph edge exists on campaign and ad set nodes only, so an account-level read is a Meta `#100` error and Zernio does not offer that shape.

`POST /v1/ads/high-demand-periods` creates one, with the same target rule: exactly one of `campaignId` or `adSetId`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: schedule } = await zernio.adaccounts.createHighDemandPeriod({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    campaignId: '120250000000000000',
    budgetValue: 2,
    budgetValueType: 'MULTIPLIER',
    timeStart: 1809216000,
    timeEnd: 1809475200,
    recurrenceType: 'ONE_TIME'
  }
});
```
</Tab>
<Tab value="Python">
```python
schedule = client.ad_accounts.create_high_demand_period(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    campaign_id="120250000000000000",
    budget_value=2,
    budget_value_type="MULTIPLIER",
    time_start=1809216000,
    time_end=1809475200,
    recurrence_type="ONE_TIME",
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/high-demand-periods" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "campaignId": "120250000000000000",
    "budgetValue": 2,
    "budgetValueType": "MULTIPLIER",
    "timeStart": 1809216000,
    "timeEnd": 1809475200,
    "recurrenceType": "ONE_TIME"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "objectId": "120250000000000000",
  "id": "6512348899001"
}
```

`budgetValueType` is `ABSOLUTE` for a whole-unit amount in the account currency, or `MULTIPLIER` for a factor of the existing budget, where `2` doubles it. `timeStart` and `timeEnd` are Unix seconds on a 15-minute boundary, and `recurrenceType` is `ONE_TIME`, `WEEKLY` or `MONTHLY`. A campaign cannot mix `ABSOLUTE` and `MULTIPLIER` schedules; the second type is rejected with Meta's "Can't mix your budget scaling selection".

## Business portfolios

`GET /v1/ads/businesses` lists the Business Manager portfolios the connected Meta user belongs to, from Meta's `/me/businesses`, with `limit` and `after` paging.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: businesses } = await zernio.adaccounts.listMetaBusinesses({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});
```
</Tab>
<Tab value="Python">
```python
businesses = client.ad_accounts.list_meta_businesses(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/businesses?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "data": [
    {
      "id": "1049283746152738",
      "name": "Acme Holdings",
      "verification_status": "verified",
      "created_time": "2024-11-03T09:12:44+0000"
    }
  ],
  "paging": { "after": null }
}
```

This read is token-scoped, so it takes no `adAccountId`. System-user tokens can return an empty list; supply a known business ID when [creating an ad account](/platforms/meta-ads#create-an-ad-account) in that case. `GET /v1/ads/business-centers` is the TikTok equivalent despite the similar name.

## Instagram ad identities

`GET /v1/ads/instagram-accounts` discovers which Instagram identity an ad on this Page can run as, before you set `instagramAccountId` on [boost](/platforms/meta-ads/boost) or [messaging ads](/platforms/meta-ads/messaging-ads). It walks `connected_instagram_accounts`, Page linkage and Page-backed identities, with a best-effort Business fallback whose permission errors do not fail the read.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: identities } = await zernio.adaccounts.listAdsInstagramAccounts({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', adAccountId: 'act_1234567890' }
});

const igUserId = identities.resolved.igUserId;
```
</Tab>
<Tab value="Python">
```python
identities = client.ad_accounts.list_ads_instagram_accounts(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
)

ig_user_id = identities["resolved"]["igUserId"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/instagram-accounts?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), trimmed:

```json
{
  "accounts": [{ "igUserId": "17841400000000000", "username": "example", "isPageBacked": false, "source": "page_backed" }],
  "pages": [{ "pageId": "123456789", "name": "Example Page", "instagramBusinessAccount": { "igUserId": "17841400000000000", "username": "example" } }],
  "resolved": { "pageId": "123456789", "igUserId": "17841400000000000", "source": "page_backed" }
}
```

`accounts` lists every identity discovered, each tagged with its `source` (`ad_account`, `page_backed` or `business`). `resolved` is the identity the shared ad-creation resolver would pick, the same one an ad runs as if you omit `instagramAccountId`; a `null` `igUserId` means nothing resolved. Format-specific fallbacks Meta applies only at creative creation time are not predicted here.

## App promotion discovery

`GET /v1/ads/advertisable-applications` lists the Meta apps this ad account can promote, and `GET /v1/ads/ios-fourteen-campaign-limits` reads an app's iOS 14 SKAdNetwork campaign limits, for [`app_promotion` campaigns](/platforms/meta-ads/campaigns#ios-14-skadnetwork-attribution). Both take `accountId` and `adAccountId`; the limits read also takes `applicationId` from the apps list.

```bash
curl "https://zernio.com/api/v1/ads/advertisable-applications?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "applications": [
    {
      "id": "123456789",
      "name": "Example App",
      "supportedPlatforms": ["IOS", "ANDROID"],
      "storeUrls": { "iphone": "https://apps.apple.com/app/id123456789", "google_play": "https://play.google.com/store/apps/details?id=com.example.app" }
    }
  ]
}
```

A listed app still needs a configured mobile platform and store URL before it can run install promotion. Take `applications[].id` as `applicationId`:

```bash
curl "https://zernio.com/api/v1/ads/ios-fourteen-campaign-limits?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=act_1234567890&applicationId=123456789" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

`limits` is `null` when Meta has none configured for the app; a non-null read does not by itself confirm the app is set up for iOS promotion.

## Common errors

A `400` on the budget schedules read means neither scope was given:

```json
{
  "error": "Provide exactly one of campaignId or adSetId",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "campaignId"
}
```

Name a campaign or an ad set. A `501` means the account is not Meta: every read on this page is Meta only.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the tree these reads sit around.
- [Insights](/platforms/meta-ads/insights): performance rather than configuration.
- [Lifecycle](/platforms/meta-ads/lifecycle): the writes the change log records.
- [Activity log](/ad-accounts/get-ads-activity-log) and [Account finance](/ad-accounts/get-ad-account-finance): every field.

---
