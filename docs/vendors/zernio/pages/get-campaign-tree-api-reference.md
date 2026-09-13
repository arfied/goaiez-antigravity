# Get campaign tree API Reference

Returns a nested Campaign > Ad Set > Ad hierarchy with rolled-up metrics at each level.
Uses a two-stage aggregation: ads are grouped into ad sets, then ad sets into campaigns.
Metrics are computed over an optional date range, then rolled up from ad level to ad set
and campaign levels. Pagination is at the campaign level. Ads without a campaign or ad set
ID are grouped into synthetic "Ungrouped" buckets.
If no date range is provided, defaults to the last 90 days. Date range is capped at 730 days max.

Pass `timeIncrement=1` to also get a daily breakdown: each node gains a `daily[]` array of
per-day metrics (same fields as the aggregated `metrics`) in the same call. Use `dailyLevel`
(`campaign` default, or `adset` / `ad`) to choose which levels carry the series. This replaces
calling the tree once per day for per-campaign daily trends.

**Deleted objects stay in the tree.** Deleting an ad or a campaign is a soft delete: the Ad
documents move to `status: cancelled` and are kept indefinitely, so their historical spend
still counts toward the metrics of any date range they fall in. There is no pruning job and
no retention window. Filter on `status` if your view should hide them, but do that after
reading the totals, not before.


## GET /v1/ads/tree

**Get campaign tree**

Returns a nested Campaign > Ad Set > Ad hierarchy with rolled-up metrics at each level.
Uses a two-stage aggregation: ads are grouped into ad sets, then ad sets into campaigns.
Metrics are computed over an optional date range, then rolled up from ad level to ad set
and campaign levels. Pagination is at the campaign level. Ads without a campaign or ad set
ID are grouped into synthetic "Ungrouped" buckets.
If no date range is provided, defaults to the last 90 days. Date range is capped at 730 days max.

Pass `timeIncrement=1` to also get a daily breakdown: each node gains a `daily[]` array of
per-day metrics (same fields as the aggregated `metrics`) in the same call. Use `dailyLevel`
(`campaign` default, or `adset` / `ad`) to choose which levels carry the series. This replaces
calling the tree once per day for per-campaign daily trends.

**Deleted objects stay in the tree.** Deleting an ad or a campaign is a soft delete: the Ad
documents move to `status: cancelled` and are kept indefinitely, so their historical spend
still counts toward the metrics of any date range they fall in. There is no pruning job and
no retention window. Filter on `status` if your view should hide them, but do that after
reading the totals, not before.


### Parameters

- **undefined** (optional): No description
- **limit** (optional) in query: Campaigns per page
- **source** (optional) in query: `all` (default) returns both Zernio-created ads and those discovered from the platform's ad manager. Matches the web UI's default view. Pass `zernio` to restrict to isExternal=false only. Status is NOT filtered by default; use the `status` param for that.
- **platform** (optional) in query: No description
- **status** (optional) in query: Filter by derived campaign status (post-aggregation)
- **adAccountId** (optional) in query: One or more platform ad account IDs to scope the tree to (agency profiles connect a whole Business Manager but a team usually cares about a subset). Comma-separate for multiple (`?adAccountId=act_1,act_2,act_3`); single value keeps its old shape. Max 50 accounts per request; the plural aliases `adAccountIds` and `platformAdAccountIds` are rejected with a 400 to stop them from silently returning the unfiltered fleet.
- **pageId** (optional) in query: Meta only: Facebook Page ID. Prunes the tree to ads whose creative is backed by this Page: campaigns and ad sets with no ad on the Page drop out, and rolled-up metrics cover only the Page's ads. Mirrors the same filter on /v1/ads and /v1/ads/campaigns.
- **accountId** (optional) in query: Account ID
- **profileId** (optional) in query: Profile ID
- **campaignId** (optional) in query: Restrict the tree to a single campaign by its platform campaign id (the id the platform assigns, e.g. Meta's numeric campaign id). Filters the campaign set itself, so it works regardless of account size and pagination. Pass this when you already hold a campaign id instead of paging the tree to find it. Mirrors the `campaignId` filter on GET /v1/ads.
- **fromDate** (optional) in query: Start of the METRICS date range (YYYY-MM-DD). On its own it affects only the spend/impression numbers overlaid on each node, not which campaigns are returned. Pass `hasDelivery` or `minSpend` to also filter the campaign set to this window. Defaults to 90 days ago.
- **toDate** (optional) in query: End of metrics date range (YYYY-MM-DD). Defaults to today. Max 730-day range.
- **hasDelivery** (optional) in query: Return only campaigns that delivered between `fromDate` and `toDate`: spend above zero, or impressions served at zero spend. Unlike `status`, which reads a campaign's CURRENT state, this filters on what happened inside the window, so a campaign that spent then and is paused today is still returned. Filters the campaign set itself, so `pagination.total` counts only matching campaigns.
- **minSpend** (optional) in query: Return only campaigns whose spend between `fromDate` and `toDate` reaches this amount. Expressed in each campaign's OWN currency (the `currency` field on the campaign node): spend is stored per ad account in its native currency and one response can span several. Implies `hasDelivery`; `minSpend=0` applies no filter.
- **sort** (optional) in query: Campaign-level sort order. `newest` (default) / `oldest` order by the campaign's newest-ad createdAt. `spend_desc` / `spend_asc` order by aggregated spend in the requested date range; campaigns with no spend land at the end.
- **timeIncrement** (optional) in query: Set to `1` to also return a daily breakdown. Mirrors Meta Insights' `time_increment=1`: each node gains a `daily[]` array of per-day metrics (same fields as the aggregated `metrics`) alongside the range total, so you get per-entity daily trends in ONE call instead of calling the tree once per day. Only `1` (daily) is supported. The daily series covers the same date range and uses the same source data as `metrics`, except `reach` on Meta and TikTok: the range total is the platform's de-duplicated value, so daily reach does not sum to it. See `dailyLevel` to control which levels carry it.
- **dailyLevel** (optional) in query: Which tree levels get the `daily[]` series when `timeIncrement=1`. `campaign` (default) attaches it on campaign nodes only: the common per-campaign-trend case, and the smallest payload. `adset` adds it on ad sets too; `ad` adds it on every ad in `ads[]` as well (heaviest: a long range × up to 100 ads per ad set). Scope with `campaignId` to keep `ad`-level responses small. Ignored when `timeIncrement` is unset.

### Responses

#### 200: Nested campaign tree with pagination

**Response Body:**

- **campaigns** `array[AdTreeCampaign]`: 
- **pagination**: `Pagination` - See schema definition
- **backfillPending** `boolean`: Present and true while historical data is being backfilled.

#### 202: Historical data is incomplete and backfill remains pending.

**Response Body:**

- **campaigns** `array[AdTreeCampaign]`: 
- **pagination**: `Pagination` - See schema definition
- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **backfillPending** (required) `boolean`: Always true on this response. Part of the requested range is still being backfilled; retry until the request returns 200.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

---
