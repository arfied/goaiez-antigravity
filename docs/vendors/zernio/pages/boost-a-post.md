# Boost a Post

Promote an existing LinkedIn Company Page post as an ad with POST /v1/ads/boost, keeping its reactions and comments, on any of the 6 goals boost accepts.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page an existing Company Page post runs as a LinkedIn ad. You need a `linkedinads` [account](/platforms/linkedin-ads#connect), the numeric ad account id from [List ad accounts](/ad-accounts/list-ad-accounts), and a published post.

## Boost an existing post

Call `POST /v1/ads/boost` with `postId`, `accountId`, `adAccountId`, `goal` and `budget`. Zernio wraps the post in a fresh Campaign Group, Campaign and Creative, and the post keeps its social proof (reactions, comments) while it runs as an ad.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: boosted } = await zernio.adcampaigns.boostPost({
  body: {
    postId: '65f1c0a9e2b5af0012ab34cd',
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '517258773',
    name: 'Boost product launch',
    goal: 'engagement',
    budget: { amount: 50, type: 'daily' },
    schedule: { startDate: '2027-01-04T09:00:00Z', endDate: '2027-01-11T09:00:00Z' }
  }
});

console.log(boosted.ad._id);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

boosted = client.ad_campaigns.boost_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="517258773",
    name="Boost product launch",
    goal="engagement",
    budget={"amount": 50, "type": "daily"},
    schedule={"startDate": "2027-01-04T09:00:00Z", "endDate": "2027-01-11T09:00:00Z"},
)

print(boosted["ad"]["_id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/boost" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "postId": "65f1c0a9e2b5af0012ab34cd",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "517258773",
    "name": "Boost product launch",
    "goal": "engagement",
    "budget": { "amount": 50, "type": "daily" },
    "schedule": { "startDate": "2027-01-04T09:00:00Z", "endDate": "2027-01-11T09:00:00Z" }
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "ad": {
    "_id": "66f0a1b2c3d4e5f6a7b8c9d0",
    "name": "Boost product launch",
    "platform": "linkedin",
    "status": "pending_review",
    "adType": "boost",
    "goal": "engagement",
    "budget": { "amount": 50, "type": "daily" },
    "schedule": { "startDate": "2027-01-04T09:00:00Z", "endDate": "2027-01-11T09:00:00Z" },
    "platformCampaignId": "645412093",
    "platformAdSetId": "810456563"
  },
  "message": "Ad created and submitted for review"
}
```

`schedule.startDate` and `endDate` are date-times, not dates. To boost a post Zernio did not publish, send LinkedIn's own post id as `platformPostId` instead of `postId`, keeping the rest of the body identical:

```json
{
  "platformPostId": "urn:li:share:7280421553219899392",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "517258773",
  "name": "Boost product launch",
  "goal": "engagement",
  "budget": { "amount": 50, "type": "daily" }
}
```

### Goals a boost accepts

`goal` takes 6 values on LinkedIn, every one the endpoint offers except `app_promotion`:

| `goal` | LinkedIn objective | Notes |
|---|---|---|
| `engagement` | `ENGAGEMENT` | The default choice for a post you want reacted to and commented on. |
| `traffic` | `WEBSITE_VISIT` | |
| `awareness` | `BRAND_AWARENESS` | Optimizes for impressions. |
| `video_views` | `VIDEO_VIEW` | The boosted post must carry a video. |
| `lead_generation` | `LEAD_GENERATION` | Requires `leadGenFormId`, the adForm id from [`POST /v1/ads/lead-forms`](/platforms/linkedin-ads/lead-forms). |
| `conversions` | `WEBSITE_CONVERSION` | Boost only: `POST /v1/ads/create` returns `422` for it on LinkedIn. The campaign's default delivery target is `MAX_CLICK`, because LinkedIn's conversion target optimizes toward a rule the boost does not attach; set `optimizationTargetType` yourself to override it. Attribution still comes from your [conversion rules](/platforms/linkedin-ads/conversions). |

`job_applicants` is the one LinkedIn goal that boost does not take: it needs the `jobs` creative on [`POST /v1/ads/create`](/platforms/linkedin-ads/creative-formats#jobs-dynamic).

Bidding options go in `platformSpecificData`, the same shape as on `/v1/ads/create`; see [Bidding](/platforms/linkedin-ads/create-ads#bidding). Boost is not idempotent: send an `Idempotency-Key` header so a retry replays the original `201` instead of creating a second ad ([Idempotency](/guides/idempotency)). Without the header an identical body is treated as a retry anyway: a `409` while one boost is in flight, and the ad already created within the last 10 minutes instead of a second one.

To sponsor an existing post under a campaign you build and control (choosing bidding, targeting and schedule the same way as any other format), use the [thought-leader format](/platforms/linkedin-ads/creative-formats#thought-leader) on `/v1/ads/create` instead.

## If it fails

A `422` on a boost is LinkedIn refusing to sponsor the post, most often because a personal profile authored it: LinkedIn's Marketing API sponsors organization-authored posts only. Zernio says so in `error` and passes LinkedIn's own rejection through in `platformError`:

```json
{
  "error": "LinkedIn's API can't sponsor posts from personal profiles (Thought Leader Ads), only posts authored by an organization Page. Create member-post ads in LinkedIn Campaign Manager.",
  "type": "platform_error",
  "code": "platform_api_error",
  "platform": "linkedin",
  "platformError": {
    "status": 422,
    "errorDetails": { "inputErrors": [{ "code": "INVALID_URN_TYPE" }] }
  }
}
```

Boost a post the Company Page authored instead. A `422` whose `platformError` mentions `organizationUgcAuthorizations` is a different refusal: the post is a Company Page post nobody has approved for sponsorship, and the author approves it at [linkedin.com/sponsorship-permissions](https://www.linkedin.com/sponsorship-permissions). Take `platformPostId` from "Copy link to post", which gives the `share` or `ugcPost` id; a feed permalink carries an `activity` id, which LinkedIn does not sponsor.

## Related

- [Create ads](/platforms/linkedin-ads/create-ads): ads built from scratch, and bidding.
- [Creative formats](/platforms/linkedin-ads/creative-formats#thought-leader): thought-leader ads.
- [Boost post](/ad-campaigns/boost-post): every field of the request.

---
