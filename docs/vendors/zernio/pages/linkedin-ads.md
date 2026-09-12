# LinkedIn Ads

Create, boost, target and measure LinkedIn campaigns on a linkedinads account with the ads endpoints.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { ChartLine, ClipboardList, Crosshair, FileImage, Gauge, Layers, Library, Link, Megaphone, Rocket, Server, Users } from 'lucide-react';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Create LinkedIn ads with `POST /v1/ads/create`, boost a Company Page post with `POST /v1/ads/boost`, and read results with the Insights endpoints, all on a `linkedinads` account. The pages in this section cover one job each; every endpoint is explained once, with its response.

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Hierarchy', value: 'Campaign Group > Campaign > Creative (created in one call, or reuse existing levels)' },
  { property: 'Goals (`goal`)', value: <>6 on <code>/v1/ads/create</code>: engagement, traffic, awareness, video_views, lead_generation, job_applicants. <code>conversions</code> is boost-only (see <a href="/platforms/linkedin-ads/boost">Boost a post</a>)</> },
  { property: 'Creative formats', value: <>Single image, video, carousel, document, event, text ad, spotlight, follower, jobs, conversation, thought-leader, article (see <a href="/platforms/linkedin-ads/creative-formats">Creative formats</a>)</> },
  { property: 'Bidding', value: 'CPM (default), CPC, CPV; manual `unitCost` or LinkedIn automated' },
  { property: 'Budget minimums', value: '$10/day per format; $100 lifetime for inactive campaigns' },
  { property: 'Targeting', value: <>Countries and regions, plus LinkedIn's B2B facets: <code>industries</code>, <code>companySizes</code>, <code>seniorities</code>, <code>jobFunctions</code> (see <a href="/platforms/linkedin-ads/create-ads#target-the-audience">Targeting</a>)</> },
  { property: 'Audiences', value: 'Contact list, company list, engagement retargeting, website retargeting' },
  { property: 'Insights', value: <>Spend, CPC and CPM plus firmographic breakdowns: job title, seniority, industry, company size (see <a href="/platforms/linkedin-ads/analytics">Analytics</a>)</> },
  { property: 'Pre-flight planning', value: 'Suggested bid and budget bounds, and impression, click and spend forecasts' },
  { property: 'Lead Gen Forms', value: 'Yes: create, list and archive forms, and pull responses (90-day retention)' },
  { property: 'Conversions API', value: 'Yes: rule CRUD, events and attribution read-back' },
  { property: 'URL tracking tags', value: 'Yes: campaign-level Dynamic UTM (read and update)' },
  { property: 'Image formats', value: 'JPEG, PNG, GIF, max 5 MB' },
  { property: 'Video formats', value: 'MP4 H.264/AAC, 3 seconds to 30 minutes, max 500 MB' },
]} />

## Before you start

LinkedIn Ads requires a connected LinkedIn account whose member can act on a sponsored ad account. You need:

- A LinkedIn ad account: the numeric sponsored account id, from [List ad accounts](/ad-accounts/list-ad-accounts).
- A Company Page. Ads built from scratch publish as Direct Sponsored Content ("dark posts") authored by the Page. The authenticated member must be an Administrator or Direct Sponsored Content Poster of that Page, and the Page must be associated with the ad account, or LinkedIn returns `403`.
- The ads scopes on the connection (below). An account connected before a scope existed must reconnect to pick it up.

LinkedIn reviews every ad, so a new campaign starts in `DRAFT` and review before it delivers.

## Connect

Call `GET /v1/connect/linkedin/ads` with `profileId` ([Connect ads](/connect/connect-ads)). The `linkedinads` account reuses the token of the profile's LinkedIn account when one is connected, so no second OAuth round trip happens; without one, the response carries an `authUrl` to send the user to, as in the [connecting accounts guide](/guides/connecting-accounts).

```bash
curl "https://zernio.com/api/v1/connect/linkedin/ads?profileId=66a1f0c2a4b9d3e8f1a2b3c4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "alreadyConnected": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platform": "linkedinads"
}
```

`accountId` is the value every ads endpoint takes. The standard LinkedIn connection already requests the ads scopes:

| Scope | What it enables |
|-------|-----------------|
| `r_ads` | Read ad accounts |
| `rw_ads` | Create and manage campaigns and creatives |
| `r_ads_reporting` | Ads reporting and analytics |
| `rw_conversions` | Conversions API (server-side conversion events) |
| `r_marketing_leadgen_automation` | Read Lead Gen Form responses |

The two scopes that most often need a reconnect are `rw_conversions` (a `403` with `linkedin_reconnect_required` when missing) and `r_marketing_leadgen_automation` (lead-response reads return a `400` with code `ads_connection_required` asking for the reconnect). [Scopes](/guides/connecting-accounts#scopes) explains how a reconnect picks them up.

## The campaign hierarchy

Every LinkedIn ad lives in a three-level tree: Campaign Group, Campaign, Creative. The Campaign owns bidding, targeting, schedule and budget; the Creative is the ad content. `POST /v1/ads/create` provisions all three in one call. Zernio's `adSetId` is the LinkedIn Campaign id (on Meta the same field is an ad set, on Google and TikTok an ad group); pass it, or `existingCampaignId` for a Campaign Group, to slot into levels you already have ([Create ads](/platforms/linkedin-ads/create-ads#reuse-a-campaign-or-campaign-group-you-already-have)).

## In this section

<Cards>
  <Card icon={<Rocket />} title="Boost a post" href="/platforms/linkedin-ads/boost" description="Promote an existing Company Page post" />
  <Card icon={<Megaphone />} title="Create ads" href="/platforms/linkedin-ads/create-ads" description="Single image and video ads, bidding, reuse and duplication" />
  <Card icon={<Layers />} title="Creative formats" href="/platforms/linkedin-ads/creative-formats" description="Carousel, document, event, text, spotlight, follower, jobs, conversation, thought-leader" />
  <Card icon={<Users />} title="Matched Audiences" href="/platforms/linkedin-ads/audiences" description="Contact lists, company lists, engagement and website retargeting" />
  <Card icon={<Gauge />} title="Bid pricing and forecasts" href="/platforms/linkedin-ads/planning" description="Suggested bids, budget bounds and supply forecasts before you spend" />
  <Card icon={<ChartLine />} title="Analytics" href="/platforms/linkedin-ads/analytics" description="Firmographic breakdowns of who saw your ads" />
  <Card icon={<ClipboardList />} title="Lead Gen Forms" href="/platforms/linkedin-ads/lead-forms" description="Create forms and pull lead responses" />
  <Card icon={<Server />} title="Conversions API" href="/platforms/linkedin-ads/conversions" description="Conversion rules, server-side events, attribution read-back" />
  <Card icon={<Link />} title="URL tracking tags" href="/platforms/linkedin-ads/tracking-tags" description="Campaign-level Dynamic UTM parameters" />
  <Card icon={<Library />} title="Ad Library" href="/platforms/linkedin-ads/ad-library" description="Search the public ad archive by keyword or advertiser" />
  <Card icon={<FileImage />} title="Media and limits" href="/platforms/linkedin-ads/reference" description="Media requirements, what you cannot do, and common LinkedIn errors" />
</Cards>

## Common errors

Connecting is where this page's own errors live. [Media and limits](/platforms/linkedin-ads/reference#common-errors) collects the ones LinkedIn returns once you are creating ads.

| Error | Cause | Fix |
|-------|-------|-----|
| `400` with code `reconnect_required` | The profile holds a LinkedIn account whose stored token cannot reach ad accounts | Reconnect the LinkedIn account, then call the ads connect again. |
| `402` | The billing gate is closed for the team | Add a payment method and retry. |
| `403` with code `ads_addon_required` | The team is on a legacy plan that does not include ads access | Move the team to usage-based billing, where every account includes ads ([Pricing](/pricing)). |
| `404` | The `profileId` is unknown, or the profile has no LinkedIn account to inherit a token from | Check the profile id, and connect LinkedIn before its ads. |

[Error handling](/guides/error-handling) covers the envelope and the stable codes.

## Related

- [Create standalone ad](/ad-campaigns/create-standalone-ad): group, campaign and creative in one call.
- [Boost post](/ad-campaigns/boost-post): promote an existing organic post.
- [List ad accounts](/ad-accounts/list-ad-accounts): the sponsored accounts a connection can reach.
- [Bid pricing](/ad-targeting/get-linkedin-bid-pricing) and [Supply forecast](/ad-targeting/get-linkedin-supply-forecast).
- [Send conversions](/conversions/send-conversions): server-side conversion events.

---
