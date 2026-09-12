# Insights

Read Meta ad performance: rolled-up metrics, demographic and placement breakdowns, live Graph queries and async report runs.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Read Meta performance data at increasing depth, from the metrics already synced onto the campaign tree to a raw Graph query you compose yourself. Pick the shallowest one that answers the question: the synced metrics cost no Meta call, the flexible query costs one per request, and a report run is a job Meta schedules.

## Which endpoint to use

| You want | Call | What it costs |
|---|---|---|
| A dashboard: spend, CPC, CPM, conversions, ROAS per campaign, ad set or ad | [`GET /v1/ads/tree`](/platforms/meta-ads/campaigns#reading-the-campaign-tree), `GET /v1/ads`, `GET /v1/ads/{adId}` | Nothing. Served from Zernio's synced metrics, pre-aggregated and rolled up. |
| A daily series for one account: spend or conversions per calendar day | [`GET /v1/ads/timeline`](/ad-campaigns/get-ads-timeline) | Nothing. The same synced rows, one per day, instead of `GET /v1/ads/tree` once per day. |
| One dimension split out (age, placement, creative asset) | [`GET /v1/ads/{adId}/analytics`](#demographic-and-placement-breakdowns), `GET /v1/ads/campaigns/{campaignId}/analytics` | One Meta call per requested dimension. |
| Arbitrary Meta fields, breakdowns and filters | [`GET /v1/ads/insights`](#flexible-queries) | One live Graph call. |
| The same over a long range, or at agency scale | [`POST /v1/ads/insights/reports`](#async-reports) | A Meta job you poll. |

`GET /v1/ads/timeline` takes `accountId` and an optional `fromDate`, `toDate` (last 90 days by default, 730 at most) and returns one row per calendar day with spend, impressions, reach, clicks, conversions and the derived `ctr`, `cpc`, `cpm` and `roas`. Reach is de-duplicated within a day only, so never sum it across days. A range reaching further back than the ingested history answers `202` with `backfillPending: true` and the covered part; repeat the call until it returns `200`.

Every `metrics` object on the tree, the campaign list and `GET /v1/ads/{adId}` carries the standard counters plus Meta's monetary fields, and each ad node carries 3 separate status fields. Both sets are defined below.

## ROAS and revenue per event

Every `metrics` object on `GET /v1/ads/tree`, `GET /v1/ads/campaigns` and `GET /v1/ads/{adId}` carries 3 monetary fields next to the `actions` and `conversions` counts:

| Field | Type | Meaning |
|-------|------|---------|
| `actionValues` | `{[action_type]: number}` | Monetary mirror of `actions`, from Meta's `action_values[]`, in the ad account's currency (the campaign node's `currency`). Populated for the action types Meta reports values on (purchases, AddToCart with value). |
| `purchaseValue` | number | Sum of purchase-type action values, picked from `actionValues` with the same priority as `conversions` (`offsite_conversion.fb_pixel_purchase`, then `omni_purchase`, then `purchase`). Same unit as `spend`. |
| `roas` | number | `purchaseValue / spend`. Recomputed from the summed numerator and denominator at ad set and campaign level, never averaged across children. Equals Meta's `purchase_roas` under default attribution. |

A campaign rollup for a purchase campaign:

```json
"metrics": {
  "spend": 493.39,
  "purchaseValue": 2456.78,
  "roas": 4.98,
  "conversions": 42,
  "costPerConversion": 11.75,
  "actions": { "offsite_conversion.fb_pixel_purchase": 42, "add_to_cart": 138, "link_click": 1205 },
  "actionValues": { "offsite_conversion.fb_pixel_purchase": 2456.78, "add_to_cart": 4230.50 }
}
```

For cost per AddToCart or cost per lead, read `costPerAction[key]`, or divide `actions[key]` by `spend`. Prefer the `offsite_conversion.fb_pixel_*` keys: Meta reports the same conversion under a pixel key, an `omni_*` key and a canonical key at once.

## Status axes on ads

Each ad node carries 3 status fields. Conflating them is the usual dashboard bug:

| Field | Answers | Values |
|---|---|---|
| `status` | Is it delivering? | Derived from Meta's `effective_status`, so it inherits ancestor pauses: an `ACTIVE` ad under a `PAUSED` campaign reads `paused`. An ad whose campaign or ad set schedule has ended reads `completed`. |
| `configuredStatus` | What did you set? | The ad's own on/off toggle, unaffected by ancestors. |
| `reviewStatus` | What does Meta's review say? | `in_review`, `approved`, `rejected`, `with_issues`. A rejected ad and an ad you paused are different problems. |

## Demographic and placement breakdowns

`GET /v1/ads/{adId}/analytics` with `breakdowns` splits one ad's metrics by one or more dimensions. Meta serves breakdowns off any insights node, so `GET /v1/ads/campaigns/{campaignId}/analytics` takes the same parameter with no per-ad fan-out.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: split } = await zernio.adinsights.getAdAnalytics({
  path: { adId: '66d4a1b2c3e4f5a6b7c8d9e2' },
  query: { breakdowns: 'age,gender' }
});

console.log(split.analytics.breakdowns.age);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

split = client.ad_insights.get_ad_analytics(
    ad_id="66d4a1b2c3e4f5a6b7c8d9e2",
    breakdowns="age,gender",
)

print(split["analytics"]["breakdowns"]["age"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/66d4a1b2c3e4f5a6b7c8d9e2/analytics?breakdowns=age,gender" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), trimmed to one row per dimension:

```json
{
  "ad": { "id": "66d4a1b2c3e4f5a6b7c8d9e2", "name": "Spring sale - US feed", "platform": "facebook", "currency": "USD" },
  "analytics": {
    "summary": { "spend": 493.39, "impressions": 88210, "clicks": 1205, "ctr": 1.37, "cpc": 0.41, "cpm": 5.59 },
    "breakdowns": {
      "age": [{ "age": "25-34", "spend": 210.40, "impressions": 39100, "clicks": 611 }],
      "gender": [{ "gender": "female", "spend": 268.90, "impressions": 47320, "clicks": 702 }]
    }
  }
}
```

`analytics.breakdowns` is keyed by the dimension you asked for. `analytics.summary` is the same ad totalled, and `fromDate` and `toDate` set the range (90 days back by default, 730 days at most).

| Group | Values |
|---|---|
| Demographics | `age`, `gender`, `country`, `region` |
| Placement | `publisher_platform`, `platform_position`, `device_platform`, `impression_device` |
| Creative asset | `video_asset`, `image_asset`, `body_asset`, `title_asset` |

<Callout type="warn">
`placement` is not a Meta dimension. Zernio returns a `400` listing the supported values rather than passing it on: use `publisher_platform` (facebook, instagram, audience_network) or `platform_position` (feed, story, reels). Any other unknown dimension is a `400` too, never a silent drop.
</Callout>

The creative-asset breakdowns are what make [creative testing](/platforms/meta-ads/creative-testing) and [carousel](/platforms/meta-ads/creatives#carousel-ads) results readable: they attribute spend and results to the individual image, video, body text or headline Meta served. The same endpoint serves LinkedIn's firmographic pivots (`job_title`, `seniority`, `industry` and the rest) on a LinkedIn ad.

## Flexible queries

`GET /v1/ads/insights` is a live Graph query where you choose the fields, breakdowns, filters, node and row granularity.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: rows } = await zernio.adinsights.queryAdInsights({
  query: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    objectId: 'act_1234567890',
    level: 'ad',
    fields: 'ad_id,ad_name,spend,frequency,website_purchase_roas',
    filtering: JSON.stringify([{ field: 'spend', operator: 'GREATER_THAN', value: 0 }]),
    datePreset: 'last_30d',
    limit: 100
  }
});

const nextCursor = rows.paging.after;
```
</Tab>
<Tab value="Python">
```python
import json

rows = client.ad_insights.query_ad_insights(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    object_id="act_1234567890",
    level="ad",
    fields="ad_id,ad_name,spend,frequency,website_purchase_roas",
    filtering=json.dumps([{"field": "spend", "operator": "GREATER_THAN", "value": 0}]),
    date_preset="last_30d",
    limit=100,
)

next_cursor = rows["paging"]["after"]
```
</Tab>
<Tab value="curl">
```bash
curl -G "https://zernio.com/api/v1/ads/insights" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  --data-urlencode "accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  --data-urlencode "objectId=act_1234567890" \
  --data-urlencode "level=ad" \
  --data-urlencode "fields=ad_id,ad_name,spend,frequency,website_purchase_roas" \
  --data-urlencode 'filtering=[{"field":"spend","operator":"GREATER_THAN","value":0}]' \
  --data-urlencode "datePreset=last_30d" \
  --data-urlencode "limit=100"
```
</Tab>
</Tabs>

Response (`200`), rows in Meta's raw shape:

```json
{
  "objectId": "act_1234567890",
  "data": [
    {
      "ad_id": "120260000000000000",
      "ad_name": "Spring sale - US feed",
      "spend": "493.39",
      "frequency": "1.84",
      "website_purchase_roas": [{ "action_type": "offsite_conversion.fb_pixel_purchase", "value": "4.98" }]
    }
  ],
  "paging": { "after": "MjM4NDI2..." }
}
```

| Parameter | Meaning |
|---|---|
| `objectId` | Any insights-capable node: `act_<n>`, a campaign id, an ad set id or an ad id. |
| `level` | Row granularity (`account`, `campaign`, `adset`, `ad`), set independently of `objectId`. An account queried at `level=ad` is the normal agency shape. |
| `fields` | Comma-separated Meta field names, forwarded verbatim. |
| `breakdowns` | Comma-separated Meta breakdown names, forwarded verbatim. |
| `filtering` | A JSON-encoded array of Meta filter clauses (`field`, `operator`, `value`). |
| `datePreset`, or `fromDate` with `toDate` | Mutually exclusive. Dates are `YYYY-MM-DD`. |
| `timeIncrement` | Days per row (1 to 90), or `monthly`, or `all_days`. |
| `limit`, `after` | Page size (500 at most, 25 by default) and the cursor. |

Validation belongs to Meta, and so do the errors. Zernio keeps no copy of Meta's field catalogue, which would go stale, so an unknown field or an invalid field and breakdown combination returns a `400` carrying Meta's own message, and those messages usually enumerate the valid values.

Paging returns `paging.after` and never a `next` URL: Meta embeds a raw access token in the `next` URLs it generates, so Zernio strips them. Take `after` from the response and send it back as the `after` parameter.

### Attribution parameters

These apply to the live query and to report runs, and they change how `actions[]` is counted and segmented:

| Parameter | Meaning |
|---|---|
| `actionBreakdowns` | Segments `actions[]`, for example `action_type,action_destination`. |
| `actionAttributionWindows` | Comma-separated on the query endpoint (`7d_click,1d_view`), a native array in the report body. `dda` and `default` are accepted too. |
| `actionReportTime` | `impression`, `conversion` or `mixed`. |
| `useUnifiedAttributionSetting` | `true` counts using each ad set's own attribution setting. |

With `actionAttributionWindows` set, every action row comes back keyed per window:

```json
{ "action_type": "purchase", "value": "132", "1d_view": "119", "7d_click": "13" }
```

## Async reports

For long ranges or account-wide pulls, submit the same query as a job. `POST /v1/ads/insights/reports` takes the query as a JSON body with native types (windows as an array, booleans as booleans) and returns `202`.

```bash
curl -X POST "https://zernio.com/api/v1/ads/insights/reports" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "objectId": "act_1234567890",
    "level": "ad",
    "fields": "ad_id,spend,actions",
    "actionAttributionWindows": ["7d_click", "1d_view"],
    "useUnifiedAttributionSetting": true,
    "fromDate": "2027-01-01",
    "toDate": "2027-06-30"
  }'
```

Response (`202`):

```json
{ "reportRunId": "6234567890789", "status": "Job Not Started" }
```

Then poll `GET /v1/ads/insights/reports/{reportRunId}`. The same call carries the rows once the job finishes. Sleep between polls: Meta schedules the job, and the ranges and account sizes this path exists for take it well past the first read, so start at 5 seconds and double the wait up to a minute. `percentCompletion` is what to show a user while it runs.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

let report;
let waitMs = 5000;
do {
  await sleep(waitMs);
  waitMs = Math.min(waitMs * 2, 60000);
  ({ data: report } = await zernio.adinsights.getAdInsightsReport({
    path: { reportRunId: '6234567890789' },
    query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', limit: 500 }
  }));
  console.log(report.status, report.percentCompletion);
} while (!['Job Completed', 'Job Failed', 'Job Skipped'].includes(report.status));

console.log(report.data, report.paging.after);
```
</Tab>
<Tab value="Python">
```python
import time

wait = 5
while True:
    time.sleep(wait)
    wait = min(wait * 2, 60)
    report = client.ad_insights.get_ad_insights_report(
        report_run_id="6234567890789",
        account_id="66b2e19d8c3f5a7e9d0b1c2d",
        limit=500,
    )
    print(report["status"], report["percentCompletion"])
    if report["status"] in ("Job Completed", "Job Failed", "Job Skipped"):
        break

print(report["data"], report["paging"]["after"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/insights/reports/6234567890789?accountId=66b2e19d8c3f5a7e9d0b1c2d&limit=500" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), once complete:

```json
{
  "reportRunId": "6234567890789",
  "status": "Job Completed",
  "percentCompletion": 100,
  "dateStart": "2027-01-01",
  "dateStop": "2027-06-30",
  "data": [{ "ad_id": "120260000000000000", "spend": "493.39" }],
  "paging": { "after": "MjM4NDI2..." }
}
```

`status` is Meta's `async_status` verbatim, so it moves through `Job Not Started`, `Job Started`, `Job Running` and `Job Completed`, with `percentCompletion` alongside. `Job Failed` and `Job Skipped` are both terminal: stop polling and submit the report again, and treat any loop that waits only for `Job Completed` as one that can spin forever. Report runs hold no state on Zernio's side: Meta owns the job handle, so the `reportRunId` is the only thing to keep, and there is no expiry to manage and no cleanup call.

## Common errors

A `400` on a flexible query is Meta rejecting a field or a combination:

```json
{
  "error": "(#100) breakdowns[0] must be one of the following values: ...",
  "type": "platform_error",
  "platform": "meta",
  "platformError": { "code": 100 }
}
```

Meta's message lists the values it will accept; pick one from it. A `501` means the account is not Meta or Google Ads, the only 2 platforms this endpoint serves.

## Related

- [Campaigns](/platforms/meta-ads/campaigns#reading-the-campaign-tree): the rolled-up metrics on the tree.
- [Creative testing](/platforms/meta-ads/creative-testing): what the creative-asset breakdowns are for.
- [Conversions](/platforms/meta-ads/capi): the events these numbers count.
- [Query insights](/ad-insights/query-ad-insights) and [Create report run](/ad-insights/create-ad-insights-report): every parameter.

---
