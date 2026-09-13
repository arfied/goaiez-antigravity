# Create Ads

Create a single image or video ad on LinkedIn in one call, target it by industry, company size, seniority and job function, set bidding, reuse an existing campaign, and duplicate a campaign.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can create a LinkedIn single image or video ad with `POST /v1/ads/create`, target it on LinkedIn's B2B facets, pick a bid, slot a creative into an existing campaign, and duplicate a campaign. You need a `linkedinads` [account](/platforms/linkedin-ads#connect), a numeric ad account id and a Company Page.

## Create a single image or video ad

Call `POST /v1/ads/create` with `accountId`, `adAccountId`, `organizationId` and the creative fields. No existing post is needed: Zernio creates a Direct Sponsored Content post authored by your Company Page (it never appears on the Page's feed), then wraps it in a Campaign Group, a Campaign and a Creative.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adcampaigns.createStandaloneAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '517258773',
    organizationId: '107655573',
    name: 'Spring launch (single image)',
    goal: 'traffic',
    budgetAmount: 50,
    budgetType: 'daily',
    headline: 'Schedule social posts in one API call',
    body: 'The social media API built for developers.',
    imageUrl: 'https://cdn.example.com/launch-1200x627.jpg',
    linkUrl: 'https://zernio.com',
    callToAction: 'LEARN_MORE',
    countries: ['US']
  }
});

console.log(created.ad._id);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.ad_campaigns.create_standalone_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="517258773",
    organization_id="107655573",
    name="Spring launch (single image)",
    goal="traffic",
    budget_amount=50,
    budget_type="daily",
    headline="Schedule social posts in one API call",
    body="The social media API built for developers.",
    image_url="https://cdn.example.com/launch-1200x627.jpg",
    link_url="https://zernio.com",
    call_to_action="LEARN_MORE",
    countries=["US"],
)

print(created["ad"]["_id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/create" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "517258773",
    "organizationId": "107655573",
    "name": "Spring launch (single image)",
    "goal": "traffic",
    "budgetAmount": 50,
    "budgetType": "daily",
    "headline": "Schedule social posts in one API call",
    "body": "The social media API built for developers.",
    "imageUrl": "https://cdn.example.com/launch-1200x627.jpg",
    "linkUrl": "https://zernio.com",
    "callToAction": "LEARN_MORE",
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
    "name": "Spring launch (single image)",
    "platform": "linkedin",
    "status": "pending_review",
    "adType": "standalone",
    "goal": "traffic",
    "budget": { "amount": 50, "type": "daily" },
    "platformAdId": "376524873",
    "platformCampaignId": "645412093",
    "platformAdSetId": "810456563"
  },
  "message": "Ad created"
}
```

`platformCampaignId` is the LinkedIn Campaign Group and `platformAdSetId` the LinkedIn Campaign; `_id` is what the [Insights](/platforms/linkedin-ads/analytics) and [tracking tag](/platforms/linkedin-ads/tracking-tags) endpoints take as `adId`.

`organizationId` names the Company Page that authors the post, as a numeric organization id or a full `urn:li:organization:N` URN. It is optional when the Page itself is connected as a Zernio account or the ad account is owned by an organization: Zernio infers it. The authenticated member must be an Administrator or Direct Sponsored Content Poster of that Page, and the Page must be associated with the ad account, or LinkedIn returns `403`.

Field rules:

- `headline` is required. Supply exactly one of `imageUrl` or `video`.
- `body` becomes the post's intro text. `longHeadline` (optional) becomes the secondary description line on image link ads.
- `goal: "traffic"` requires `linkUrl`.
- `callToAction` accepts `LEARN_MORE`, `SIGN_UP`, `DOWNLOAD`, `SUBSCRIBE`, `REGISTER`, `JOIN`, `ATTEND`, `REQUEST_DEMO`, `VIEW_QUOTE`, `APPLY`, `SEE_MORE`, `SHOP_NOW`, `BUY_NOW`, and defaults to `LEARN_MORE` when `linkUrl` is set.
- The recommended image ratio is 1.91:1 (1200 x 627), JPEG, PNG or GIF.
- `status: "PAUSED"` leaves the Campaign Group, Campaign and Creative paused so you can review them before they spend. Omitted, Zernio activates the Campaign Group and Campaign, and the ad goes live the moment LinkedIn approves it.

The same `imageUrl` plus `linkUrl` shape also publishes LinkedIn's article and newsletter link ad; see [Creative formats](/platforms/linkedin-ads/creative-formats#article-and-newsletter).

### Video ads

Swap `imageUrl` for `video: { url }`; the two are mutually exclusive. Zernio uploads the clip to LinkedIn under the Company Page, the campaign format becomes `SINGLE_VIDEO`, and the `video_views` goal becomes available (it requires a video). LinkedIn generates the poster frame, so no thumbnail is needed. The request blocks while LinkedIn transcodes the video; short clips take 10 to 30 seconds. The file must be MP4 H.264/AAC, 3 seconds to 30 minutes, 75 KB to 500 MB.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "517258773",
  "organizationId": "107655573",
  "name": "Spring launch (video)",
  "goal": "video_views",
  "budgetAmount": 50,
  "budgetType": "daily",
  "headline": "See it in action",
  "body": "The social media API built for developers.",
  "video": { "url": "https://cdn.example.com/launch.mp4" },
  "linkUrl": "https://zernio.com",
  "callToAction": "LEARN_MORE",
  "countries": ["US"]
}
```

Every other format (carousel, document, event, text ad, spotlight, follower, jobs, conversation, thought-leader) is a key inside `platformSpecificData`; see [Creative formats](/platforms/linkedin-ads/creative-formats).

## Target the audience

`countries: ['US']` above is the minimum LinkedIn accepts. The professional facets are what LinkedIn Ads are bought for, and they go in a nested `targeting` object, the same spec [Bid pricing and forecasts](/platforms/linkedin-ads/planning) prices and sizes before you spend:

```json
{
  "targeting": {
    "countries": ["US", "GB"],
    "industries": ["4"],
    "companySizes": ["D", "E", "F"],
    "seniorities": ["4", "5"],
    "jobFunctions": ["8"]
  }
}
```

| Field | Value | Matched as |
|---|---|---|
| `countries` | ISO 3166-1 alpha-2 codes | LinkedIn geo URNs |
| `regions` | `[{ "key": ... }]`, each key a `urn:li:geo:*` URN from [`GET /v1/ads/targeting/search?dimension=geo`](/ad-targeting/search-ad-targeting) | The same locations facet as `countries` |
| `industries` | Industry id fragments | `urn:li:industry:{id}` |
| `companySizes` | Staff-count codes (below) | `urn:li:staffCountRange:{range}` |
| `seniorities` | Seniority id fragments | `urn:li:seniority:{id}` |
| `jobFunctions` | Function id fragments | `urn:li:function:{id}` |
| `jobTitles` | Title id fragments | `urn:li:title:{id}` |
| `audienceInclude`, `audienceExclude` | Segment ids from [`GET /v1/ads/audiences`](/platforms/linkedin-ads/audiences) | `urn:li:adSegment:{id}` |
| `excludedLocations.countries` | ISO 3166-1 alpha-2 codes | The exclude clause |

A full URN passes through unchanged wherever an id fragment is accepted, so a value straight out of LinkedIn's own tooling works as it is.

`companySizes` takes a letter per staff-count range: `A` (1), `B` (2 to 10), `C` (11 to 50), `D` (51 to 200), `E` (201 to 500), `F` (501 to 1,000), `G` (1,001 to 5,000), `H` (5,001 to 10,000), `I` (10,001 and above).

3 rules decide whether the request survives:

- LinkedIn requires at least one location. Send none and the ad targets the United States; send a country code outside the 45 Zernio maps and that code resolves to the United States too, so validate codes before spending on them.
- `jobTitles` cannot be combined with `seniorities` or `jobFunctions`. LinkedIn rejects the whole ad, so Zernio returns a `400` with code `mutually_exclusive_fields` first.
- `cities`, `zips`, `metros`, `customLocations`, `incomeTier`, `behaviors` and Meta's work facets return a `400` with code `invalid_field_value` naming the field: LinkedIn's Marketing API has no equivalent. `ageMin`, `ageMax` and `gender` are accepted on the request and ignored by LinkedIn.

## Bidding

Bidding fields go in `platformSpecificData`, on both `POST /v1/ads/create` and `POST /v1/ads/boost`:

```json
{
  "platformSpecificData": {
    "costType": "CPC",
    "unitCost": 2.5,
    "optimizationTargetType": "MAX_CLICK",
    "creativeSelection": "OPTIMIZED",
    "audienceExpansionEnabled": true,
    "offsiteDeliveryEnabled": false,
    "connectedTelevisionOnly": false
  }
}
```

| Field | Notes |
|---|---|
| `costType` | `CPM` (default), `CPC`, `CPV`. Required when `unitCost` is set. |
| `unitCost` | Manual bid in whole account-currency units. Omit for LinkedIn automated bidding. |
| `optimizationTargetType` | Forwarded verbatim (`MAX_CLICK`, `TARGET_COST_PER_CLICK`, ...). |
| `creativeSelection` | `OPTIMIZED` (default) or `ROUND_ROBIN`. |
| `audienceExpansionEnabled`, `offsiteDeliveryEnabled`, `connectedTelevisionOnly` | Campaign delivery toggles, forwarded verbatim. |

Unknown keys inside `platformSpecificData` return a `400`, and so do these LinkedIn-only keys on another platform's account. To pick a bid inside LinkedIn's allowed range before creating anything, call [Bid pricing](/platforms/linkedin-ads/planning#suggested-bid-and-budget-bounds).

## Reuse a campaign or campaign group you already have

By default each create mints a fresh Campaign Group, Campaign and Creative. Two optional, mutually exclusive fields slot into a hierarchy you already have; passing both returns a `400`.

`adSetId` takes the numeric LinkedIn Campaign id and creates only a new Creative under it, skipping both provisioning steps. Bidding, targeting, schedule and budget are inherited from the Campaign, so sending those fields (including `budgetType` and `budgetAmount`) returns a `400`:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "517258773",
  "goal": "engagement",
  "platformSpecificData": { "thoughtLeader": { "postUrn": "urn:li:ugcPost:7482739454772752385" } },
  "adSetId": "810456563"
}
```

`existingCampaignId` takes a LinkedIn Campaign Group id and skips only the group provisioning: a new Campaign is created under that group, with bidding, targeting and schedule yours to set. If the create fails, only the entities Zernio authored are cleaned up; the pre-existing parent is never touched.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "517258773",
  "goal": "engagement",
  "headline": "Schedule social posts in one API call",
  "body": "The social media API built for developers.",
  "imageUrl": "https://cdn.example.com/launch-1200x627.jpg",
  "linkUrl": "https://zernio.com",
  "budgetType": "daily",
  "budgetAmount": 20,
  "existingCampaignId": "1173166923"
}
```

## Duplicate a campaign

Call `POST /v1/ads/campaigns/{campaignId}/duplicate` with `platform: "linkedin"` and the source Campaign Group id. LinkedIn has no native copy endpoint, so Zernio walks the source Campaign Group, its Campaigns and their Creatives and recreates the tree.

```bash
curl -X POST "https://zernio.com/api/v1/ads/campaigns/645412093/duplicate" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "linkedin",
    "statusOption": "PAUSED",
    "renameStrategy": "DEEP_RENAME",
    "renameSuffix": " (Copy)"
  }'
```

Response (`200`):

```json
{
  "copiedCampaignId": "645498211",
  "discovery": "triggered"
}
```

`discovery` reports whether Zernio ran an ads sync straight after the copy so it shows up in [`GET /v1/ads/tree`](/ad-campaigns/get-ad-tree) within seconds instead of at the next scheduled pass: `triggered` when it ran, `skipped` when the request sent `syncAfter: false`, and `failed` when the sync itself errored. The copy exists on LinkedIn in all 3 cases; `failed` and `skipped` only delay when Zernio sees it, so poll the tree.

The copy carries the campaign fields verbatim (`type`, `costType`, `creativeSelection`, `unitCost`, `optimizationTargetType`, `targetingCriteria`, `dailyBudget`, `totalBudget`, ...) and every Creative's content object, for DSC-authored, reference, document and thought-leader creatives alike. `statusOption` is `PAUSED` (default: the whole clone stays `DRAFT`), `ACTIVE` (the clone launches the moment LinkedIn approves it) or `INHERITED_FROM_SOURCE` (evaluated per entity: any Group, Campaign or Creative whose source is `ACTIVE` gets its clone activated too). If any step fails, only the entities the duplicate authored are cleaned up; the source tree is never touched.

<Callout type="warn">
`INHERITED_FROM_SOURCE` on an `ACTIVE` campaign starts a second front of spend the moment the clone activates. Keep `PAUSED` unless the caller wants a live clone.
</Callout>

## Budget minimums

LinkedIn enforces a $10/day minimum for any ad format and a $100 minimum lifetime budget for inactive campaigns. [Bid pricing](/platforms/linkedin-ads/planning#suggested-bid-and-budget-bounds) returns the account-and-targeting-specific bounds; its `dailyBudgetLimits.min` is the authoritative number.

## If it fails

A `403` on create means the member lacks the Direct Sponsored Content Poster or Administrator role on the Page, or the Page is not associated with the ad account. LinkedIn's REST error is forwarded verbatim inside Zernio's `platform_error` envelope:

```json
{
  "error": "LinkedIn rejected the request",
  "type": "platform_error",
  "code": "platform_api_error",
  "platform": "linkedin",
  "platformError": { "status": 403, "message": "Not enough permissions to access: ..." }
}
```

Fix the Page role in LinkedIn, then repeat the call. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Creative formats](/platforms/linkedin-ads/creative-formats): every other creative shape.
- [Boost a post](/platforms/linkedin-ads/boost): promote an existing Company Page post instead.
- [Bid pricing and forecasts](/platforms/linkedin-ads/planning): pick a bid and budget before creating.
- [Create standalone ad](/ad-campaigns/create-standalone-ad): every field of the request.
- [Duplicate a campaign](/ad-campaigns/duplicate-ad-campaign): the full body.

---
