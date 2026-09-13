# Insights & GAQL

Read rolled-up Google Ads metrics from the campaign tree, and run any read-only GAQL query with GET /v1/ads/insights.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can read spend, CPC and CPM per campaign, ad group and ad from Zernio's synced metrics, and run any read-only GAQL `SELECT` against Google with `GET /v1/ads/insights`. You need a `googleads` [account](/platforms/google-ads#connect).

| You want | Use | Notes |
|---|---|---|
| A dashboard: spend, CPC, CPM per campaign, ad group or ad | [`GET /v1/ads/tree`](#rolled-up-metrics-from-the-campaign-tree), `GET /v1/ads`, `GET /v1/ads/{adId}` | Served from Zernio's synced metrics, no Google call on the request path. |
| One dimension split out | `GET /v1/ads/{adId}/analytics`, `GET /v1/ads/campaigns/{campaignId}/analytics` | The cross-platform breakdowns endpoint. |
| The search terms that triggered your ads | [`GET /v1/ads/search-terms`](#search-terms) | Google's `search_term_view`, ordered by cost. |
| Anything Google's reporting can answer | [`GET /v1/ads/insights`](#raw-gaql-queries) | Raw GAQL passthrough. |

## Rolled-up metrics from the campaign tree

Call `GET /v1/ads/tree` with `accountId` for spend, CPC and CPM at campaign, ad group and ad level in one read. `fromDate` and `toDate` set the metrics window (the last 90 days by default, 730 days at most), and `platform=google` scopes a profile that also has Meta or LinkedIn ads.

```bash
curl "https://zernio.com/api/v1/ads/tree?accountId=66b2e19d8c3f5a7e9d0b1c2d&platform=google&fromDate=2027-01-01&toDate=2027-01-31" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), trimmed to one campaign:

```json
{
  "campaigns": [
    {
      "platformCampaignId": "21874563210",
      "campaignName": "US DevOps Search",
      "platform": "google",
      "status": "active",
      "advertisingChannelType": "SEARCH",
      "currency": "USD",
      "adSetCount": 1,
      "adCount": 1,
      "metrics": { "spend": 412.5, "impressions": 18400, "clicks": 512, "ctr": 2.78, "cpc": 0.81, "cpm": 22.42, "conversions": 12.4 },
      "adSets": [
        {
          "platformAdSetId": "165489732105",
          "adSetName": "US DevOps Search",
          "status": "active",
          "adCount": 1,
          "metrics": { "spend": 412.5, "impressions": 18400, "clicks": 512, "ctr": 2.78, "cpc": 0.81, "cpm": 22.42, "conversions": 12.4 },
          "ads": [
            { "_id": "66f0a1b2c3d4e5f6a7b8c9d0", "name": "US DevOps Search", "status": "active", "metrics": { "spend": 412.5, "impressions": 18400, "clicks": 512 } }
          ]
        }
      ]
    }
  ],
  "pagination": { "page": 1, "limit": 20, "total": 1, "pages": 1 }
}
```

`platformAdSetId` is the Google ad group, `advertisingChannelType` is Google's own serving surface (`SEARCH`, `DISPLAY`, `PERFORMANCE_MAX`, `SHOPPING`, `VIDEO`, ...), and `currency` is the ad account's own, never normalised to USD. `conversions` is fractional because Google reports modeled and attributed conversions. A `202` carrying `backfillPending: true` means part of the range is still being backfilled: retry until the call answers `200`.

Discovered campaigns of every type sync into the tree with metrics, including Performance Max, Shopping and Video. Deleting an ad or a campaign is a soft delete: the objects stay in the tree as `cancelled`, and their historical spend still counts toward any date range they fall in.

## Campaign budgets and impression share

[`GET /v1/ads/campaigns`](/ad-campaigns/list-ad-campaigns) includes Google budget metadata in `budget` and `campaignBudget` after the next successful sync. It does not fetch Google live. [`GET /v1/ads/campaigns/{campaignId}/analytics`](/ad-insights/get-campaign-analytics) returns the latest synced budget as `campaign.budget`, which is `null` before sync.

| Budget field | Meaning |
|---|---|
| `amountMicros` | Exact decimal micros as a string. Daily budgets use Google's `amount_micros`; custom-period budgets use `total_amount_micros`. |
| `explicitlyShared` | Whether the budget is shared, or `null` when unknown. Shared writes require `allowSharedBudgetUpdate: true`; unknown sharing status cannot be overridden. |
| `resourceName` | Google's `campaign_budget.resource_name`, or `null` when unavailable. |
| `deliveryMethod` | Google's delivery method, typically `STANDARD`, or `null` when unavailable. |

For Google campaigns, the campaign analytics endpoint also adds these fields under `analytics.summary` for the requested inclusive date range:

- `searchImpressionShare`
- `searchBudgetLostImpressionShare`
- `searchRankLostImpressionShare`
- `searchTopImpressionShare`
- `searchAbsoluteTopImpressionShare`

These are ratios, not percentages: `0.42` represents 42%. Unavailable values are `null`, and Google's threshold sentinel values are preserved. The fields come from one query over the whole range, without daily segmentation, and are cached for 10 minutes. They are not daily values to sum from `analytics.daily`.

### Read freshness per response

`analytics.impressionShareCache.cachedAt` and `analytics.impressionShareCache.stale` describe the impression-share query independently of the synced spend, clicks and other metrics. Keep that freshness separate from the rest of the campaign report.

Google Search ad details on [`GET /v1/ads/{adId}`](/ad-campaigns/get-ad) use top-level `cachedAt` and `stale` for the current RSA text, pins and URLs. Google mutations invalidate that read. Enrichment requires a stored `advertisingChannelType: "SEARCH"`; other or unknown channels return stored details. If enrichment fails, the endpoint returns the stored ad with `200` and no cache metadata. A successful status alone does not confirm a fresh Google read.

## Raw GAQL queries

Call `GET /v1/ads/insights` with a `googleads` account and `query`. It runs any read-only GAQL `SELECT` and returns Google's rows verbatim.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: report } = await zernio.adinsights.queryAdInsights({
  query: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    query: `SELECT campaign.name, metrics.clicks, metrics.cost_micros, segments.date
            FROM campaign
            WHERE segments.date DURING LAST_30_DAYS
            ORDER BY metrics.cost_micros DESC`
  }
});

console.log(report.data);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

report = client.ad_insights.query_ad_insights(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    query="""SELECT campaign.name, metrics.clicks, metrics.cost_micros, segments.date
             FROM campaign
             WHERE segments.date DURING LAST_30_DAYS
             ORDER BY metrics.cost_micros DESC""",
)

print(report["data"])
```
</Tab>
<Tab value="curl">
```bash
curl -G "https://zernio.com/api/v1/ads/insights" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  --data-urlencode "accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  --data-urlencode "query=SELECT campaign.name, metrics.clicks, metrics.cost_micros, segments.date FROM campaign WHERE segments.date DURING LAST_30_DAYS ORDER BY metrics.cost_micros DESC"
```
</Tab>
</Tabs>

Response (`200`), camelCase rows verbatim:

```json
{
  "customerId": "1234567890",
  "fieldMask": "campaign.name,metrics.clicks,metrics.costMicros,segments.date",
  "data": [
    {
      "campaign": { "name": "US DevOps Search" },
      "metrics": { "clicks": "512", "costMicros": "412500000" },
      "segments": { "date": "2027-01-31" }
    }
  ],
  "paging": { "nextPageToken": null }
}
```

`paging.nextPageToken` is `null` above because one page held every row. Google caps a page at 10,000 rows, so a report that can exceed it has to follow the cursor: pass the token back as `pageToken` and repeat until it comes back `null`.

```typescript
const gaql = `SELECT campaign.name, metrics.clicks, metrics.cost_micros, segments.date
              FROM campaign
              WHERE segments.date DURING LAST_30_DAYS
              ORDER BY metrics.cost_micros DESC`;

const rows = [];
let pageToken;

do {
  const { data: page } = await zernio.adinsights.queryAdInsights({
    query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', query: gaql, pageToken }
  });
  rows.push(...page.data);
  pageToken = page.paging.nextPageToken ?? undefined;
} while (pageToken);
```

This is the same endpoint that serves [Meta's flexible insights](/platforms/meta-ads/insights#flexible-queries): the account's platform picks the contract. For Meta you pass `objectId` plus `fields`; for Google you pass `query`.

You can query campaign, keyword, search-term, geo, demographic, asset and shopping resources, `change_event`, and any `segments.*`. That covers the reports the synced metrics do not model: search terms, quality score, auction-insight adjacents, per-segment splits.

| Rule | Detail |
|---|---|
| Read-only | `SELECT` statements only. |
| Paging | Fixed at 10,000 rows per page; follow `paging.nextPageToken` with `pageToken`. |
| `customerId` | Only needed when the connection has several Google Ads accounts. |
| Validation | Google's, verbatim: an invalid query returns a `400` carrying Google's message. |
| Numbers | Counters are int64s encoded as strings; monetary fields are micros of the account currency. |

<Callout type="warn">
Selecting `segments.date` requires a finite date filter (`DURING LAST_30_DAYS`, an explicit `BETWEEN`, ...). Google rejects an unbounded date-segmented query, and its message says so verbatim.
</Callout>

GAQL queries run live against Google and count toward the per-user ops budget; see [quotas](/platforms/google-ads/reference#quotas-and-the-ops-budget).

## Search terms

Call `GET /v1/ads/search-terms` with `accountId` for the search queries that triggered your ads, with matched-keyword status and spend, the raw material for wasted-spend analysis and negative-keyword lists. It defaults to the last 30 days and orders rows by cost, descending; `campaignId` and `adGroupId` narrow it.

```bash
curl "https://zernio.com/api/v1/ads/search-terms?accountId=66b2e19d8c3f5a7e9d0b1c2d&fromDate=2027-01-01&toDate=2027-01-31" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "customerId": "1234567890",
  "data": [
    {
      "searchTerm": "internal developer platform pricing",
      "status": "NONE",
      "matchType": "NEAR_PHRASE",
      "campaignId": "21874563210",
      "campaignName": "US DevOps Search",
      "adGroupId": "165489732105",
      "adGroupName": "US DevOps Search",
      "impressions": 340,
      "clicks": 21,
      "costMicros": 18400000,
      "conversions": 1,
      "conversionsValue": 129.99
    }
  ],
  "paging": { "nextPageToken": null },
  "cachedAt": "2027-01-31T08:00:00.000Z",
  "stale": false
}
```

`status` says whether the term is already a keyword or a negative (`ADDED`, `EXCLUDED`, `ADDED_EXCLUDED`, `NONE`). `stale: true` means Google's daily quota was exhausted and this is the last successful fetch, not a live read.

## If it fails

A `400` carries Google's query validator message verbatim, naming the offending field:

```json
{
  "error": "Error in query: A date segment (segments.date) must be filtered with a finite date range.",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "query",
  "platform": "google"
}
```

Add a finite date filter and repeat the query.

## Related

- [Keywords](/platforms/google-ads/keywords): synced keyword criteria and the Keyword Planner.
- [Limits and errors](/platforms/google-ads/reference#quotas-and-the-ops-budget): the per-user ops budget.
- [Query ad insights](/ad-insights/query-ad-insights), [Search terms](/ad-insights/get-ads-search-terms) and [Get campaign tree](/ad-campaigns/get-ad-tree): every parameter.

---
