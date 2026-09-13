# Keywords

Read the Search keywords your Google campaigns run, add keywords to an ad group, and research new ones with the Keyword Planner.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can read the keywords your campaigns already run (synced, free to query), add keywords to an ad group, and research keywords you do not run yet (Keyword Planner, live against Google). You need a `googleads` [account](/platforms/google-ads#connect).

## Synced keyword criteria

Call `GET /v1/ads/keywords`. It returns the Google Search keyword criteria (positive and negative) synced from connected accounts, one row per ad-group keyword.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: synced } = await zernio.adcampaigns.listAdKeywords({
  query: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '1234567890',
    matchType: 'exact',
    negative: false,
    search: 'platform',
    limit: 100
  }
});

console.log(synced.keywords);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

synced = client.ad_campaigns.list_ad_keywords(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="1234567890",
    match_type="exact",
    negative=False,
    search="platform",
    limit=100,
)

print(synced["keywords"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/keywords?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=1234567890&matchType=exact&negative=false&search=platform&limit=100" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "keywords": [
    {
      "id": "66f2b3c4d5e6f7a8b9c0d1e2",
      "platform": "google",
      "adAccountId": "1234567890",
      "campaignId": "21874563210",
      "campaignName": "US DevOps Search",
      "adSetId": "165489732105",
      "adSetName": "US DevOps Search",
      "keyword": "internal developer platform",
      "matchType": "exact",
      "status": "active",
      "negative": false,
      "qualityScore": 7,
      "metrics": { "windowDays": 30, "clicks": 84, "impressions": 2210, "cost": 61.4, "conversions": 3, "firstPageCpc": 0.42, "firstPositionCpc": 1.9 }
    }
  ],
  "pagination": { "page": 1, "limit": 100, "total": 1, "pages": 1 }
}
```

Filters: `campaignId`, `adSetId` (the Google ad group), `status`, `matchType`, `negative` (`true` returns negative keywords only) and `search` (a case-insensitive substring on the keyword text). `metrics` is a trailing 30-day window in the account currency, and `qualityScore` is Google's 1 to 10 score (`null` when unrated).

The list refreshes about once a week per Google Ads customer: the keyword sweep rides the ads discovery pass on a slower slot to stay inside Google's shared daily API quota, so keywords added on Google can take several days to appear here. A newly connected account is populated on its first discovery pass, and a manual sync refreshes it immediately. Campaign-level negative keywords are not included, only ad-group-level criteria; read those with `GET /v1/ads/campaigns/{campaignId}/negative-keywords`. For live, current-state keyword data, query GAQL directly: see [Insights and GAQL](/platforms/google-ads/insights#raw-gaql-queries).

## Add keywords to an ad group

Call `POST /v1/ads/keywords` with `accountId`, the ad group as `adSetId` and `keywords`. It adds to the ad group without touching the keywords already there; `negative: true` adds ad-group-level negatives instead.

```bash
curl -X POST "https://zernio.com/api/v1/ads/keywords" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adSetId": "165489732105",
    "keywords": ["developer portal", { "text": "platform engineering", "matchType": "phrase" }]
  }'
```

Response (`201`), the same row shape as the list:

```json
{
  "keywords": [
    { "id": "66f2b3c4d5e6f7a8b9c0d1e3", "adSetId": "165489732105", "keyword": "developer portal", "matchType": "broad", "status": "active", "negative": false },
    { "id": "66f2b3c4d5e6f7a8b9c0d1e4", "adSetId": "165489732105", "keyword": "platform engineering", "matchType": "phrase", "status": "active", "negative": false }
  ]
}
```

To replace the whole set, send `targeting.keywords` or `targeting.negativeKeywords` on `PUT /v1/ads/{adId}`: each supplied list is the full desired set for the ad group, affecting sibling ads too. Matching uses case-insensitive text plus match type; omitting `matchType` means broad match. Unchanged criteria retain their IDs, status, bids, labels and history. Missing criteria are removed, and changing text or match type removes the old criterion and creates a new one.

## Change or remove one keyword

Pause or re-enable a single keyword with `PATCH /v1/ads/keywords/{keywordId}` ([Update keyword](/ad-campaigns/update-ad-keyword)), where `keywordId` is the `id` from the list above and `status` is `active` or `paused`. Nothing else in the ad group is touched:

```bash
curl -X PATCH "https://zernio.com/api/v1/ads/keywords/66f2b3c4d5e6f7a8b9c0d1e2" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "status": "paused" }'
```

Response (`200`), the updated row:

```json
{
  "keyword": {
    "id": "66f2b3c4d5e6f7a8b9c0d1e2",
    "adSetId": "165489732105",
    "keyword": "internal developer platform",
    "matchType": "exact",
    "status": "paused",
    "negative": false
  }
}
```

`DELETE` on the same path ([Remove keyword](/ad-campaigns/remove-ad-keyword)) removes the criterion from its ad group, positive or negative, and answers `{ "removed": true, "keywordId": "66f2b3c4d5e6f7a8b9c0d1e2" }`.

Google gives negative keywords no status, so `PATCH` on one returns `422`: remove it instead. A `404` means the `keywordId` is not a keyword on one of your connected accounts.

## Campaign negatives and shared lists

Campaign negatives are separate from the synced ad-group criteria. [`GET /v1/ads/campaigns/{campaignId}/negative-keywords`](/ad-campaigns/list-campaign-negative-keywords) reads them; [`PUT` on the same path](/ad-campaigns/replace-campaign-negative-keywords) replaces the full desired `keywords` set, up to 1,000 entries. Strings mean broad match; objects accept `text` and `matchType`. Send `keywords: []` to clear the campaign's negatives.

For negatives reused across campaigns, manage a shared list:

1. [`POST /v1/ads/accounts/negative-keyword-lists`](/ad-accounts/create-ad-negative-keyword-list) creates a list with `accountId`, `name` and optional `keywords` (up to 5,000). Include `customerId` when the connection has multiple customers. Creation does not attach the list to a campaign and is not idempotent.
2. [`GET` on that collection](/ad-accounts/list-ad-negative-keyword-lists) lists shared lists. [`GET /v1/ads/accounts/negative-keyword-lists/{listId}`](/ad-accounts/get-ad-negative-keyword-list) reads one with its keywords and criterion IDs; [`PUT` on that path](/ad-accounts/update-ad-negative-keyword-list) renames it.
3. [`PUT /v1/ads/accounts/negative-keyword-lists/{listId}/keywords`](/ad-accounts/replace-ad-negative-keyword-list-keywords) replaces the full desired set. Changes apply atomically and affect every attached campaign. Unchanged criteria retain their IDs. Each create or removal consumes one daily operation, and the entire batch must fit the remaining quota.
4. [`GET /v1/ads/campaigns/{campaignId}/negative-keyword-lists`](/ad-campaigns/list-campaign-negative-keyword-lists) reads attachments. [`PUT` on that path](/ad-campaigns/replace-campaign-negative-keyword-lists) replaces the full `listIds` set, up to 20 lists from the same Google customer. An empty array detaches all lists without deleting their keywords.
5. Detach a list from every campaign before [`DELETE /v1/ads/accounts/negative-keyword-lists/{listId}`](/ad-accounts/delete-ad-negative-keyword-list).

Shared-list reads are cached for 10 minutes and can fall back to the last successful copy for up to seven days when quota is exhausted. Check `cachedAt` and `stale` before treating the result as current.

## Keyword Planner: ideas

Call `POST /v1/ads/keywords/ideas` with seed keywords, a seed URL, or both. It runs Keyword Planner's idea generation.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: ideas } = await zernio.adinsights.generateKeywordIdeas({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    seedKeywords: ['platform engineering tools'],
    seedUrl: 'https://example.com/platform',
    countries: ['US', 'GB'],
    pageSize: 50
  }
});

console.log(ideas.data);
```
</Tab>
<Tab value="Python">
```python
ideas = client.ad_insights.generate_keyword_ideas(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    seed_keywords=["platform engineering tools"],
    seed_url="https://example.com/platform",
    countries=["US", "GB"],
    page_size=50,
)

print(ideas["data"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/keywords/ideas" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "seedKeywords": ["platform engineering tools"],
    "seedUrl": "https://example.com/platform",
    "countries": ["US", "GB"],
    "pageSize": 50
  }'
```
</Tab>
</Tabs>

Response (`200`), rows in Google's raw shape:

```json
{
  "customerId": "1234567890",
  "data": [
    {
      "text": "internal developer platform",
      "keywordIdeaMetrics": {
        "avgMonthlySearches": "2400",
        "competition": "MEDIUM",
        "competitionIndex": "48",
        "lowTopOfPageBidMicros": "1250000",
        "highTopOfPageBidMicros": "6800000",
        "monthlySearchVolumes": [ { "year": "2026", "month": "DECEMBER", "monthlySearches": "2900" } ]
      }
    }
  ],
  "paging": { "nextPageToken": "CAoQ..." }
}
```

## Keyword Planner: historical metrics

Call `POST /v1/ads/keywords/historical-metrics` with exact keywords you already have (up to 1,000 per call). It returns historical search volume, competition and top-of-page bid ranges, plus `averageCpcMicros` when `includeAverageCpc` is set.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "keywords": ["internal developer platform", "platform engineering tools"],
  "countries": ["US"],
  "includeAverageCpc": true
}
```

The response has the same `customerId` and `data` shape as ideas, with no `paging`: historical metrics is a single batch.

## Keyword Planner rules

Both Planner calls above share these. They do not apply to `GET /v1/ads/keywords`, which returns Zernio's synced rows with numeric counters.

- Rows come back verbatim from Google: counters are int64s encoded as strings (parse before doing math), and every bid or CPC value is in micros of the account currency (divide by 1,000,000).
- `countries` omitted means worldwide. `languageConstantId` picks the language (`1000` is English).
- `customerId` is only needed when the connection has several Google Ads accounts.
- Paging: `pageSize` plus `pageToken` from `paging.nextPageToken` (ideas only).

## If it fails

The Keyword Planner calls and the keyword writes on this page run live against Google and count against the per-user ops budget ([quotas](/platforms/google-ads/reference#quotas-and-the-ops-budget)). A `429` means that budget or Google's shared quota is exhausted; use the returned error and retry timing to decide when to retry.

Back off before retrying; see [rate limits](/guides/rate-limits) for the general rule.

## Related

- [Create ads](/platforms/google-ads/create-ads): keywords on a new Search campaign.
- [Insights and GAQL](/platforms/google-ads/insights): live keyword and search-term data.
- [List Search keywords](/ad-campaigns/list-ad-keywords), [Add Search keywords](/ad-campaigns/add-ad-keywords), [Keyword ideas](/ad-insights/generate-keyword-ideas) and [Historical metrics](/ad-insights/generate-keyword-historical-metrics): every field.

---
