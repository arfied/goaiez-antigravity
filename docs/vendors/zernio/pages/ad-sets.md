# Ad Sets

Edit a Meta ad set after launch: ABO budget, status, optimization goal, schedule, promoted object, read its learning phase live, and delete it.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

The ad set decides delivery: who sees the ad, when, how Meta optimizes and, on ABO campaigns, how much is spent. `POST /v1/ads/create` builds one for you ([campaigns](/platforms/meta-ads/campaigns)); the calls on this page edit it afterwards without recreating the ad, or delete it. You need the `adSetId`, which is `platformAdSetId` on the create response and on every ad set node of `GET /v1/ads/tree`.

| Setting | Owned by | Edit with |
|---|---|---|
| Budget (ABO) | Ad set | [`PUT /v1/ads/ad-sets/{adSetId}`](#update-budget-and-status-abo) |
| Status | Ad set | The same endpoint, or [`/status`](#status-only-toggles) |
| Bid strategy, `bidAmount`, `roasAverageFloor` | Ad set | The same endpoint ([bid strategy](/platforms/meta-ads/campaigns#bid-strategy)) |
| Targeting | Ad set | `PUT /v1/ads/{adId}` with `targeting`; on Meta the edit lands on the ad's ad set |
| Optimization goal, billing event, schedule, `promotedObject` | Ad set | [`platformSpecificData`](#post-launch-delivery-edits) |
| Budget (CBO) | Campaign | [`PUT /v1/ads/campaigns/{campaignId}`](/platforms/meta-ads/campaigns#update-a-cbo-campaign-budget) |
| Deletion (cascades to the ads) | Ad set | [`DELETE /v1/ads/ad-sets/{adSetId}`](#delete-an-ad-set) |

`campaignAttribution` (AEM or SKADNETWORK, for iOS 14+ app promotion) is create-only: it has no standalone ad-set creation endpoint and no edit path here, only `POST /v1/ads/create` ([SKAdNetwork attribution](/platforms/meta-ads/campaigns#ios-14-skadnetwork-attribution)).

## Update budget and status (ABO)

Call `PUT /v1/ads/ad-sets/{adSetId}` with `platform` and at least one of `budget`, `status`, `name` or `bidStrategy`, when the campaign node reads `budgetLevel: "adset"`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: adSet } = await zernio.adcampaigns.updateAdSet({
  path: { adSetId: '120250000000000001' },
  body: {
    platform: 'facebook',
    budget: { amount: 75, type: 'daily' },
    status: 'active'
  }
});
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

ad_set = client.ad_campaigns.update_ad_set(
    ad_set_id="120250000000000001",
    platform="facebook",
    budget={"amount": 75, "type": "daily"},
    status="active",
)
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT "https://zernio.com/api/v1/ads/ad-sets/120250000000000001" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "facebook",
    "budget": { "amount": 75, "type": "daily" },
    "status": "active"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "budget": { "amount": 75, "type": "daily" },
  "budgetLevel": "adset",
  "status": "active",
  "statusUpdated": 1,
  "statusSkipped": 0
}
```

Budget amounts are whole currency units of the ad account (USD `75` is $75.00). Read `currency` off the campaign node of the [tree](/platforms/meta-ads/campaigns#reading-the-campaign-tree) before you show or send a number; budgets are never normalized to one currency. `statusUpdated: 0` on a resume is normal when the ads are all waiting on Meta review.

When `statusSkipped` is above 0, `statusSkippedReasons` appears and says why in one sentence per group: `2 ads in terminal status`, `1 ad already active`, or `3 ads switched on but not yet delivering; the platform sets their status`. It is left out of the response when nothing was skipped.

## Status-only toggles

For pause and resume without touching the budget, `PUT /v1/ads/ad-sets/{adSetId}/status` takes `{ status, platform }` and mirrors `PUT /v1/ads/campaigns/{campaignId}/status`. The [lifecycle page](/platforms/meta-ads/lifecycle#pause--resume) covers the campaign-level, bulk and single-ad variants.

## Post-launch delivery edits

The Meta-only delivery settings go in `platformSpecificData` on the same `PUT /v1/ads/ad-sets/{adSetId}`:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: delivery } = await zernio.adcampaigns.updateAdSet({
  path: { adSetId: '120250000000000001' },
  body: {
    platform: 'facebook',
    platformSpecificData: {
      optimizationGoal: 'OFFSITE_CONVERSIONS',
      billingEvent: 'IMPRESSIONS',
      startDate: '2027-03-01T09:00:00Z',
      endDate: '2027-03-31T23:59:00Z',
      promotedObject: { pixelId: '1729525464415281', customEventType: 'PURCHASE' }
    }
  }
});
```
</Tab>
<Tab value="Python">
```python
delivery = client.ad_campaigns.update_ad_set(
    ad_set_id="120250000000000001",
    platform="facebook",
    platform_specific_data={
        "optimizationGoal": "OFFSITE_CONVERSIONS",
        "billingEvent": "IMPRESSIONS",
        "startDate": "2027-03-01T09:00:00Z",
        "endDate": "2027-03-31T23:59:00Z",
        "promotedObject": {"pixelId": "1729525464415281", "customEventType": "PURCHASE"},
    },
)
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT "https://zernio.com/api/v1/ads/ad-sets/120250000000000001" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "facebook",
    "platformSpecificData": {
      "optimizationGoal": "OFFSITE_CONVERSIONS",
      "billingEvent": "IMPRESSIONS",
      "startDate": "2027-03-01T09:00:00Z",
      "endDate": "2027-03-31T23:59:00Z",
      "promotedObject": { "pixelId": "1729525464415281", "customEventType": "PURCHASE" }
    }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "budgetLevel": "adset",
  "platformSpecificData": {
    "optimizationGoal": "OFFSITE_CONVERSIONS",
    "billingEvent": "IMPRESSIONS",
    "startDate": "2027-03-01T09:00:00Z",
    "endDate": "2027-03-31T23:59:00Z",
    "promotedObject": { "pixelId": "1729525464415281", "customEventType": "PURCHASE" }
  }
}
```

| Field | Meaning |
|---|---|
| `optimizationGoal` | Forwarded verbatim. Meta validates it against the campaign objective and rejects incompatible pairs. |
| `billingEvent` | What Meta charges for (`IMPRESSIONS`, `LINK_CLICKS`, `THRUPLAY`). On create it is a top-level field of `POST /v1/ads/create`, like `optimizationGoal`, `promotedObject` and `startDate` there. |
| `startDate`, `endDate` | ISO 8601 strings. Setting `endDate` schedules the stop. An existing end cannot be cleared here: `null` fails validation with a `400`, and only a new date-time replaces the old one. |
| `promotedObject` | The full object, replaced wholesale. Accepted keys are on [conversion campaigns](/platforms/meta-ads/conversion-campaigns#every-promoted-object-key). |
| `dailyMinSpendTarget`, `lifetimeMinSpendTarget` | Meta's minimum spend for this ad set, in whole currency units. Reserves a share of a campaign (CBO) budget, so the campaign must own the budget. |

<Callout type="warn">
`platformSpecificData` and `promotedObject` are strict. An unknown or misspelled key returns a `400` naming the path (`platformSpecificData.optimisationGoal`) instead of being dropped, and a non-Meta `platform` returns `400` too. A dropped `promotedObject` key used to change what the ad set optimized toward without warning.
</Callout>

Meta validates combinations on its side and its message surfaces verbatim: switching an `OUTCOME_TRAFFIC` ad set to `OFFSITE_CONVERSIONS` without a pixel on `promotedObject` returns Meta's own error, not a generic failure.

## Read an ad set live

Call `GET /v1/ads/ad-sets/{adSetId}` with `accountId` to read the ad set from Meta instead of from Zernio's synced copy, when you need the current state right now, learning phase included.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: live } = await zernio.adcampaigns.getAdSetDetails({
  path: { adSetId: '120250000000000001' },
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

console.log(live.adSet.learning_stage_info?.status);
```
</Tab>
<Tab value="Python">
```python
live = client.ad_campaigns.get_ad_set_details(
    ad_set_id="120250000000000001",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
)

print(live["adSet"].get("learning_stage_info"))
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/ad-sets/120250000000000001?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), Meta's ad set node as returned:

```json
{
  "adSet": {
    "id": "120250000000000001",
    "name": "Spring sale - US feed - Ad Set",
    "status": "ACTIVE",
    "effective_status": "ACTIVE",
    "optimization_goal": "LINK_CLICKS",
    "billing_event": "IMPRESSIONS",
    "daily_budget": "7500",
    "learning_stage_info": {
      "status": "LEARNING",
      "conversions": 12,
      "last_sig_edit_ts": 1788606000
    }
  }
}
```

The default projection includes `learning_stage_info`; pass `fields` to replace it with your own Meta field list. Values here are Meta's raw shapes: `daily_budget` is in minor units, unlike every Zernio budget field.

### Learning phase

`learning_stage_info.status` is Meta's own verdict on whether the ad set has exited the learning phase:

| `status` | Meaning |
|---|---|
| `LEARNING` | Still learning. Delivery and cost are unstable, and a significant edit restarts the clock. |
| `SUCCESS` | Exited learning. |
| `FAIL` | Learning limited: the ad set never gathered about 50 optimization events in a week. Broaden targeting, raise the budget or consolidate ad sets. |
| `WAIVING` | Meta waived the requirement for this ad set. |

`last_sig_edit_ts` is the last significant edit in Unix seconds and can be absent on a `SUCCESS` entry. Meta omits `learning_stage_info.status` on paused ad sets and on many older active ones (they return only `attribution_windows`): treat a missing `status` as unknown, not as exited. Zernio also records the learning stage during the regular ads sync, so ad documents from `GET /v1/ads` and `GET /v1/ads/tree` carry it without a live call; this endpoint is for when you need it fresher than the last sync pass.

## Add an ad to an existing ad set

Pass `adSetId` on `POST /v1/ads/create` to attach one more ad to an ad set Meta has already optimized on; it inherits budget, targeting, schedule and goal. See [attach a creative to an existing ad set](/platforms/meta-ads/creatives#attach-a-creative-to-an-existing-ad-set).

## Duplicate an ad set

`POST /v1/ads/ad-sets/{adSetId}/duplicate` copies the ad set, by default with its ads and creatives, optionally into another campaign. See [duplicate an ad set or an ad](/platforms/meta-ads/lifecycle#duplicate-an-ad-set-or-an-ad).

## Delete an ad set

`DELETE /v1/ads/ad-sets/{adSetId}` removes the ad set on the platform and cascades to its ads; the campaign above it is never touched. Every ad document under it is marked `status: "cancelled"` locally, and `adCount` says how many.

```bash
curl -X DELETE "https://zernio.com/api/v1/ads/ad-sets/120250000000000001" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{ "deleted": true, "adCount": 3 }
```

The delete is hard on Meta and on Google (which removes the ad group), and soft where the platform has no hard delete: LinkedIn moves the campaign to `PENDING_DELETION`, Pinterest archives the ad group, X soft-flags the line item. All of them stay readable for reporting. To stop spend without losing the ad set, [pause it](#status-only-toggles) instead.

## If it fails

A `400` with `param` set means a key inside `platformSpecificData` or `promotedObject` is not one Zernio accepts:

```json
{
  "error": "Unknown key platformSpecificData.optimisationGoal",
  "type": "invalid_request_error",
  "param": "platformSpecificData.optimisationGoal"
}
```

Fix the spelling against the table above. A `409` with `code: "BUDGET_LEVEL_MISMATCH"` means the campaign is CBO: send the budget to `PUT /v1/ads/campaigns/{campaignId}` instead.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the create call, bid strategy and the tree.
- [Conversion campaigns](/platforms/meta-ads/conversion-campaigns): every `promotedObject` key.
- [Lifecycle](/platforms/meta-ads/lifecycle): pause, resume and duplicate.
- [Update ad set](/ad-campaigns/update-ad-set) and [Live ad set details](/ad-campaigns/get-ad-set-details): every field.

---
