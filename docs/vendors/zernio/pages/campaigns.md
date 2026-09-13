# Campaigns

Create a Meta campaign, ad set and ad in one call, set the bid strategy and its value rule sets, and read the campaign tree back with rolled-up metrics.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

`POST /v1/ads/create` builds a campaign, an ad set and an ad in one call, and `GET /v1/ads/tree` reads the hierarchy back with metrics rolled up at every level. Every other page in this section changes a few fields of the create request below or edits one level of the tree afterwards. You need a connected Meta posting or `metaads` `accountId` and an `adAccountId` ([find them](/platforms/meta-ads#find-your-ad-account-id)).

New Meta campaigns default to `buyingType: "AUCTION"` on both `POST /v1/ads/create` and `POST /v1/ads/campaigns`; you can omit the field. Attaching to `existingCampaignId` leaves the existing campaign's buying type unchanged.

## Create the full tree in one call

Call `POST /v1/ads/create` with the flat body below: every field sits at the top level, and the platform is inferred from `accountId`, so there is no `platform` field. `budgetAmount` is in whole currency units of the ad account.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Spring sale - US feed',
    goal: 'traffic',
    budgetAmount: 75,
    budgetType: 'daily',
    headline: 'Spring sale, 30% off',
    body: 'Limited time. Upgrade today.',
    imageUrl: 'https://cdn.example.com/spring.jpg',
    callToAction: 'SHOP_NOW',
    linkUrl: 'https://example.com/spring',
    countries: ['US'],
    ageMin: 25,
    ageMax: 55,
    interests: [{ id: '6003139266461', name: 'DevOps' }]
  }
});

const adId = created.ad._id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Spring sale - US feed",
    goal="traffic",
    budget_amount=75,
    budget_type="daily",
    headline="Spring sale, 30% off",
    body="Limited time. Upgrade today.",
    image_url="https://cdn.example.com/spring.jpg",
    call_to_action="SHOP_NOW",
    link_url="https://example.com/spring",
    countries=["US"],
    age_min=25,
    age_max=55,
    interests=[{"id": "6003139266461", "name": "DevOps"}],
)

ad_id = created["ad"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/create" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "name": "Spring sale - US feed",
    "goal": "traffic",
    "budgetAmount": 75,
    "budgetType": "daily",
    "headline": "Spring sale, 30% off",
    "body": "Limited time. Upgrade today.",
    "imageUrl": "https://cdn.example.com/spring.jpg",
    "callToAction": "SHOP_NOW",
    "linkUrl": "https://example.com/spring",
    "countries": ["US"],
    "ageMin": 25,
    "ageMax": 55,
    "interests": [{ "id": "6003139266461", "name": "DevOps" }]
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9e2",
    "name": "Spring sale - US feed",
    "platform": "facebook",
    "status": "pending_review",
    "configuredStatus": "ACTIVE",
    "reviewStatus": "in_review",
    "adType": "standalone",
    "goal": "traffic",
    "budget": { "amount": 75, "type": "daily" },
    "platformAdId": "120260000000000000",
    "platformAdAccountId": "act_1234567890",
    "platformCampaignId": "120250000000000000",
    "platformAdSetId": "120250000000000001",
    "platformObjective": "OUTCOME_TRAFFIC",
    "optimizationGoal": "LINK_CLICKS",
    "creative": {
      "creativeId": "120250000000000005",
      "pageId": "811889972008357",
      "imageUrl": "https://cdn.example.com/spring.jpg",
      "linkUrl": "https://example.com/spring"
    }
  },
  "message": "Ad created"
}
```

`ad._id` is the `adId` for every per-ad call, `platformCampaignId` and `platformAdSetId` are the `campaignId` and `adSetId` for the campaign and ad set endpoints, and `creative.creativeId` can be reused with `existingCreativeId`. The ad is created `ACTIVE` and enters Meta review; pass `status: "PAUSED"` to review it before it spends. The request is not idempotent, so send an `Idempotency-Key` header before you add a retry ([idempotency](/guides/idempotency)). A top-level `tracking` object (`pixelId`, `urlTags`) attaches pixel measurement and click-URL params at create time; see [set tags at create time](/platforms/meta-ads/tracking-tags#set-tags-at-create-time).

The samples on the other pages of this section change only a few fields of this request:

| You want | Change | Page |
|---|---|---|
| `goal` of `conversions`, `lead_conversion`, `lead_generation`, `app_promotion` or `catalog_sales` | add `promotedObject` | [Conversion campaigns](/platforms/meta-ads/conversion-campaigns) |
| A video, a carousel or one asset per placement | replace `imageUrl` | [Creatives](/platforms/meta-ads/creatives) |
| N ads in one ad set | add `creatives[]` | [Creative testing](/platforms/meta-ads/creative-testing) |
| One more ad in an ad set that already runs | add `adSetId` | [Creatives](/platforms/meta-ads/creatives#attach-a-creative-to-an-existing-ad-set) |
| Cities, regions or ZIPs instead of countries | replace `countries` | [Targeting](/platforms/meta-ads/targeting) |
| A fixed-price reservation | add `buyingType: "RESERVED"` | [Reach and Frequency](/platforms/meta-ads/reach-and-frequency) |
| Validate without creating | add `validateOnly: true` | [Lifecycle](/platforms/meta-ads/lifecycle#dry-run-a-create) |

`POST /v1/ads/boost` uses a different body, with nested `budget`, `schedule` and `targeting` objects ([boost a post](/platforms/meta-ads/boost)); do not mix the two shapes.

### iOS 14+ SKAdNetwork attribution

For `goal: "app_promotion"` on iOS, add `isSkadnetworkAttribution: true` alongside `campaignAttribution: "SKADNETWORK"` and `promotedObject.applicationId` / `promotedObject.objectStoreUrl` ([every promoted object key](/platforms/meta-ads/conversion-campaigns#every-promoted-object-key)). `isSkadnetworkAttribution` is immutable once the campaign exists, and the campaign only receives `promotedObject` at all when it is `true`. Plain Android installs keep `promotedObject` on the ad set rather than the campaign. `campaignAttribution` requires `AUCTION` buying and only exists on this combined create call: creating an ad set on its own has no equivalent field.

## Create a campaign only

Call `POST /v1/ads/campaigns` to create a campaign without its first ad set and ad, when you provision the container first and let ad sets join it later through `existingCampaignId` on `POST /v1/ads/create`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: campaign } = await zernio.adcampaigns.createAdCampaign({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Q4 push',
    goal: 'conversions',
    budgetAmount: 50,
    budgetType: 'daily'
  }
});

const campaignId = campaign.campaignId;
```
</Tab>
<Tab value="Python">
```python
campaign = client.ad_campaigns.create_ad_campaign(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Q4 push",
    goal="conversions",
    budget_amount=50,
    budget_type="daily",
)

campaign_id = campaign["campaignId"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/campaigns" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "name": "Q4 push",
    "goal": "conversions",
    "budgetAmount": 50,
    "budgetType": "daily"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "adAccountId": "act_1234567890",
  "campaignId": "120250000000000000",
  "objective": "OUTCOME_SALES",
  "status": "PAUSED"
}
```

`goal` maps to the same Meta objective as on `POST /v1/ads/create`, and `objective` is the resolved value. A budget here is campaign-level (CBO) by definition; omit it for ABO, where each ad set brings its own budget. The campaign is created `PAUSED` unless you pass `status: "ACTIVE"`. `isSkadnetworkAttribution: true` also works here for a `goal: "app_promotion"` campaign container ([SKAdNetwork attribution](#ios-14-skadnetwork-attribution)); it is immutable once created. A campaign with zero ads is invisible to `GET /v1/ads/tree` and `GET /v1/ads/campaigns` until an ad set joins it (pass `includeEmpty=true` on the campaign list to see it), and deleting one needs `accountId` in the body ([lifecycle](/platforms/meta-ads/lifecycle#delete-a-campaign)).

For a campaign-only dry run, send `validateOnly: true` to `POST /v1/ads/campaigns`. It returns `200` with `status: "VALIDATED"` and creates no campaign.

## Bid strategy

Meta's bid strategies are accepted on `POST /v1/ads/create`, `POST /v1/ads/boost`, `PUT /v1/ads/ad-sets/{adSetId}` and `PUT /v1/ads/campaigns/{campaignId}`, and returned on `GET /v1/ads/tree`, `GET /v1/ads/campaigns` and `GET /v1/ads/{adId}`.

| `bidStrategy` | Required companion field | Meta behaviour |
|---|---|---|
| `LOWEST_COST_WITHOUT_CAP` (default) | none | Auto-bid; Meta spends the budget. |
| `LOWEST_COST_WITH_BID_CAP` | `bidAmount` | Auto-bid with a hard ceiling per result. |
| `COST_CAP` | `bidAmount` | Target average cost per result. |
| `LOWEST_COST_WITH_MIN_ROAS` | `roasAverageFloor` | Needs a value-optimized campaign (`OUTCOME_SALES` with a pixel or dataset). |

`bidAmount` is in whole currency units of the ad account (USD `5` is $5.00, JPY `100` is ¥100); Zernio converts it to Meta's smallest-denomination integer. `roasAverageFloor` is a decimal multiplier (`2.0` is 2.0x ROAS), sent to Meta as `bid_constraints.roas_average_floor` times 10,000. On create, send the 3 fields inside `platformSpecificData`; the flat top-level fields still work during Meta's deprecation window, and sending both shapes returns a `400`.

A $5 cost cap on the create request above:

```json
{
  "goal": "conversions",
  "promotedObject": { "pixelId": "1729525464415281", "customEventType": "PURCHASE" },
  "platformSpecificData": { "bidStrategy": "COST_CAP", "bidAmount": 5 }
}
```

To change a running ad set's bid, call `PUT /v1/ads/ad-sets/{adSetId}` with `bidStrategy` plus `bidAmount` or `roasAverageFloor` ([ad sets](/platforms/meta-ads/ad-sets)). `PUT /v1/ads/campaigns/{campaignId}` accepts `bidStrategy` only, because Meta has no `bid_amount` or `bid_constraints` at the campaign level, and only on a CBO campaign; an ABO campaign returns `409` pointing at the ad set endpoint.

### Value rule sets

A value rule set moves the auction bid up or down for audience segments worth more or less to you, without splitting them into their own ad sets. `POST /v1/ads/value-rule-sets` creates one on the ad account; each rule names a direction (`INCREASE`, 1 to 1000 percent, or `DECREASE`, 1 to 90) and the criteria it fires on.

```bash
curl -X POST "https://zernio.com/api/v1/ads/value-rule-sets" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "name": "Repeat markets",
    "rules": [{
      "name": "Bid up in Canada",
      "adjustSign": "INCREASE",
      "adjustValue": 30,
      "criteria": [{
        "criteriaType": "LOCATION",
        "operator": "CONTAINS",
        "criteriaValues": ["CA"],
        "criteriaValueTypes": ["LOCATION_COUNTRY"]
      }]
    }]
  }'
```

Response (`201`):

```json
{ "adAccountId": "act_1234567890", "valueRuleSetId": "120250000000000042" }
```

Attach the id with `valueRuleSetId` on `PUT /v1/ads/ad-sets/{adSetId}`, or on `POST /v1/ads/create` when the ad set is being created. Sending a different id replaces the association; there is no separate replace call. To detach, send `valueRulesApplied: false` and omit `valueRuleSetId`: the 2 fields together return a `400` with code `mutually_exclusive_fields`, because Meta keeps the adjustments live whenever the id is present. Read the association back with `GET /v1/ads/ad-sets/{adSetId}?fields=value_rule_set_id`; Meta exposes no readable field for the boolean.

Rules only apply to ad sets on `LOWEST_COST_WITHOUT_CAP` or `COST_CAP`, and Meta rejects the rest itself. The rest of the set:

| Call | What it does |
|---|---|
| [`GET /v1/ads/value-rule-sets`](/ad-accounts/list-value-rule-sets) | Lists the account's sets in the same shape the update takes, ids included |
| [`GET /v1/ads/value-rule-sets/{valueRuleSetId}`](/ad-accounts/get-value-rule-set) | One set with every nested rule and criterion id |
| [`PUT /v1/ads/value-rule-sets/{valueRuleSetId}`](/ad-accounts/update-value-rule-set) | Replaces the set: echo an `id` to keep a rule, omit one to delete it |
| [`DELETE /v1/ads/value-rule-sets/{valueRuleSetId}`](/ad-accounts/delete-value-rule-set) | Deletes the set; detach the ad sets first, since this call leaves them pointing at it |

<Callout type="warn">
The update is a full replace, not a patch. `GET` the set first and send every rule and criterion you want to keep, with its `id`: anything left out is deleted on Meta with no warning. Rule order is semantic too, since only the first rule that matches an overlapping audience adjusts the bid.
</Callout>

Limits: 6 rule sets per ad account, 10 rules per set, 4 criteria per rule.

## Reading the campaign tree

Call `GET /v1/ads/tree`. It returns campaigns, their ad sets and their ads nested, with metrics rolled up over the date range (default: the last 90 days, at most 730 days). Pagination is per campaign.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: tree } = await zernio.adcampaigns.getAdTree({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(tree.campaigns[0].budgetLevel);
```
</Tab>
<Tab value="Python">
```python
tree = client.ad_campaigns.get_ad_tree(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    from_date="2026-08-01",
    to_date="2026-08-31",
)

print(tree["campaigns"][0]["budgetLevel"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/tree?accountId=66b2e19d8c3f5a7e9d0b1c2d&fromDate=2026-08-01&toDate=2026-08-31" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), trimmed to one campaign:

```json
{
  "campaigns": [
    {
      "platformCampaignId": "120250000000000000",
      "platform": "facebook",
      "campaignName": "Spring sale - US feed - Campaign",
      "status": "active",
      "reviewStatus": "approved",
      "platformCampaignStatus": "ACTIVE",
      "campaignIssuesInfo": null,
      "adCount": 1,
      "adSetCount": 1,
      "budgetLevel": "adset",
      "campaignBudget": null,
      "isBudgetScheduleEnabled": false,
      "currency": "USD",
      "metrics": { "spend": 493.39, "impressions": 88210, "clicks": 1205, "roas": 4.98 },
      "adSets": [
        {
          "platformAdSetId": "120250000000000001",
          "adSetName": "Spring sale - US feed - Ad Set",
          "status": "active",
          "adSetBudget": { "amount": 75, "type": "daily" },
          "optimizationGoal": "LINK_CLICKS",
          "ads": [
            { "_id": "66d4a1b2c3e4f5a6b7c8d9e2", "name": "Spring sale - US feed", "status": "active", "configuredStatus": "ACTIVE", "reviewStatus": "approved" }
          ]
        }
      ]
    }
  ],
  "pagination": { "page": 1, "limit": 20, "total": 1, "pages": 1 }
}
```

Each campaign node carries:

| Field | Values | Meaning |
|-------|--------|---------|
| `status` | `active`, `paused`, `pending_review`, `rejected`, `completed`, `cancelled`, `error` | Delivery status derived from the child ads |
| `reviewStatus` | `in_review`, `approved`, `rejected`, `with_issues`, `null` | Meta's review, separate from `status` |
| `platformCampaignStatus` | string | Meta's raw `Campaign.effective_status` |
| `campaignIssuesInfo` | object[] or `null` | Meta's raw `issues_info[]` when delivery issues exist |
| `budgetLevel` | `campaign`, `adset`, `null` | `campaign` is CBO, `adset` is ABO |
| `campaignBudget` | `{ amount, type }` or `null` | Set on CBO campaigns only |
| `adSetBudget` (on ad set nodes) | `{ amount, type }` or `null` | Set on ABO campaigns only |
| `isBudgetScheduleEnabled` | boolean | Meta's `Campaign.is_budget_schedule_enabled` |
| `currency` | ISO 4217 | Budgets are in the ad account's currency and are never normalized |

A `202` carrying `backfillPending: true` and a `Retry-After` header, instead of the `200`, means part of the requested range is still being backfilled, which a freshly connected ad account hits: the body has the same shape, so retry after the delay until the call returns `200`.

`source=all` (the default, the same view as the dashboard) returns ads created through Zernio and ads discovered from Ads Manager; `source=zernio` restricts to the former. Deleted objects stay in the tree as `cancelled` so their historical spend keeps counting; filter on `status` after reading the totals. `timeIncrement=1` adds a `daily[]` series to each node.

Reading those numbers is [Insights](/platforms/meta-ads/insights): the monetary fields on `metrics` are in [ROAS and revenue per event](/platforms/meta-ads/insights#roas-and-revenue-per-event), and the 3 status fields an ad node carries are in [status axes on ads](/platforms/meta-ads/insights#status-axes-on-ads).

### Filter by Facebook Page

A Meta ad account serves ads for every Page in the Business Manager. `pageId` on `GET /v1/ads/tree`, `GET /v1/ads` and `GET /v1/ads/campaigns` prunes to one Page:

- On the tree: only ads whose creative is backed by that Page. Campaigns and ad sets with no ad on the Page drop out, and rolled-up metrics cover only the Page's ads.
- On the campaign list: campaigns with at least one ad on the Page, with `adCount` and metrics computed over those ads only.
- On the ad list: each ad's `creative.pageId`, also returned on reads, so you can group client-side.

Meta only. The few creatives with no Page signal (some Instagram-only ones) never match.

## Update a CBO campaign budget

Call `PUT /v1/ads/campaigns/{campaignId}` with `platform` and `budget` when the campaign node reads `budgetLevel: "campaign"`. `platform` is required because Meta campaign ids are not globally unique across the platforms Zernio supports.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: updated } = await zernio.adcampaigns.updateAdCampaign({
  path: { campaignId: '120250000000000000' },
  body: {
    platform: 'facebook',
    budget: { amount: 250, type: 'daily' }
  }
});
```
</Tab>
<Tab value="Python">
```python
updated = client.ad_campaigns.update_ad_campaign(
    campaign_id="120250000000000000",
    platform="facebook",
    budget={"amount": 250, "type": "daily"},
)
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT "https://zernio.com/api/v1/ads/campaigns/120250000000000000" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "facebook",
    "budget": { "amount": 250, "type": "daily" }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "updated": 1,
  "budget": { "amount": 250, "type": "daily" },
  "budgetLevel": "campaign"
}
```

The same endpoint renames the campaign with `name`. For an ABO campaign, update the budget on the [ad set](/platforms/meta-ads/ad-sets#update-budget-and-status-abo) instead.

## Campaign spend cap

The same endpoint accepts `platformSpecificData.spendCap`: a lifetime ceiling for the whole campaign, in whole currency units, independent of the daily or lifetime budget. Set a $1,000 cap:

```json
{ "platform": "facebook", "platformSpecificData": { "spendCap": 1000 } }
```

Remove it with `null`, never with `0`:

```json
{ "platform": "facebook", "platformSpecificData": { "spendCap": null } }
```

Meta rejects a spend cap of zero (subcode `1885099`, "Spend Cap Can't Be Zero"); its removal sentinel is a magic number Zernio sends when it receives `null`. An unknown key inside `platformSpecificData`, or a non-Meta `platform`, returns a `400`. This is the campaign cap; the account-level cap is read from [account finances](/platforms/meta-ads/operational-reads#account-finances).

## If it fails

A `409` with `code: "BUDGET_LEVEL_MISMATCH"` means you sent a budget to the wrong level of the tree:

```json
{
  "error": "Campaign is ABO, route to /v1/ads/ad-sets/{adSetId} instead",
  "type": "invalid_request_error",
  "code": "BUDGET_LEVEL_MISMATCH"
}
```

Read `budgetLevel` on the campaign node and send the budget to `PUT /v1/ads/campaigns/{campaignId}` when it is `campaign`, or to `PUT /v1/ads/ad-sets/{adSetId}` when it is `adset`. A `400` with `type: "platform_error"` carries Meta's own message and subcode in `platformError`; the [reference](/platforms/meta-ads/reference#common-errors) lists the ones worth recognizing.

## Related

- [Ad sets](/platforms/meta-ads/ad-sets): ABO budgets, schedule, optimization goal and the learning phase.
- [Lifecycle](/platforms/meta-ads/lifecycle): pause, resume, duplicate, delete and dry run.
- [Insights](/platforms/meta-ads/insights): breakdowns and live Meta queries beyond the rolled-up metrics.
- [Create standalone ad](/ad-campaigns/create-standalone-ad) and [Get campaign tree](/ad-campaigns/get-ad-tree): every field.
- [Idempotency](/guides/idempotency): the `Idempotency-Key` header on creates.

---
