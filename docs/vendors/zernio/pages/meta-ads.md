# Meta Ads

Create, boost and measure Facebook and Instagram ads with the Zernio API, using classic or business login.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Cards, Card } from 'fumadocs-ui/components/card';
import { Activity, ChartLine, ClipboardList, Code, Copy, Crosshair, Eye, FileImage, FlaskConical, Gauge, Layers, Library, Link, Megaphone, MessageSquare, MessagesSquare, Palette, Rocket, Server, ShoppingBag, Target, Users } from 'lucide-react';
import { SiWhatsapp } from 'react-icons/si';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Create, boost and measure Facebook and Instagram ads with `POST /v1/ads/create` and a connected Meta Ads token. This section covers the campaign tree (campaigns, ad sets, ads, creatives), targeting and audiences, the ad types that need their own request shape, measurement (insights, pixels, conversions) and the reads you need around a running ad account. You need a connection with the ads scopes and an ad account (`act_<n>`) it can act on. Use an existing ad account or [create one in the customer's own business portfolio](#create-an-ad-account). Ads are included with usage-based billing; a legacy plan without ads gets a `403` on every call here.

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Hierarchy', value: 'Campaign, ad set, ad (created in one call, or level by level)' },
  { property: 'Goals', value: <>10 values for <code>goal</code>: engagement, traffic, awareness, video_views, lead_generation, lead_conversion, conversions, app_promotion, catalog_sales, page_likes</> },
  { property: 'Buying types', value: <>AUCTION (default) and RESERVED (see <a href="/platforms/meta-ads/reach-and-frequency">Reach and Frequency</a>)</> },
  { property: 'Budget levels', value: 'CBO (campaign) and ABO (ad set); reads tell you which one a campaign uses' },
  { property: 'Bid strategies', value: 'LOWEST_COST_WITHOUT_CAP, LOWEST_COST_WITH_BID_CAP, COST_CAP, LOWEST_COST_WITH_MIN_ROAS' },
  { property: 'Creative formats', value: <>Single image, video, carousel (2 to 10 cards), dynamic creative, per-placement assets, catalog, instant form, messaging, call (see <a href="/platforms/meta-ads/creatives">Creatives</a>)</> },
  { property: 'Call to action values', value: <>The <code>callToAction</code> enum, plus <code>VIEW_INSTAGRAM_PROFILE</code> and the 3 messaging CTAs on boost (see the <a href="/platforms/meta-ads/reference#call-to-action-values">reference</a>)</> },
  { property: 'Special ad categories', value: 'HOUSING, EMPLOYMENT, CREDIT, ISSUES_ELECTIONS_POLITICS, FINANCIAL_PRODUCTS_SERVICES, ONLINE_GAMBLING_AND_GAMING' },
  { property: 'Image formats', value: 'JPEG, PNG (public URL or base64), max 30 MB' },
  { property: 'Video formats', value: 'MP4, MOV, max 4 GB' },
  { property: 'Ad previews', value: <>Yes, Meta iframe HTML for about 60 formats, before or after create (see <a href="/platforms/meta-ads/previews">Previews</a>)</> },
  { property: 'Dry-run validation', value: <>Yes, <code>validateOnly: true</code> validates the whole tree and creates nothing</> },
  { property: 'Insights', value: <>Synced metrics on <code>/v1/ads/tree</code>, live Meta queries and async report runs (see <a href="/platforms/meta-ads/insights">Insights</a>)</> },
  { property: 'Audiences', value: 'Customer list, website, lookalike, engagement, saved targeting' },
  { property: 'Conversions', value: 'Yes, server-side events with hashed PII, dedup, Event Match Quality and Limited Data Use' },
  { property: 'Lead forms', value: 'Yes, forms plus leads by webhook or polling' },
  { property: 'Scheduling', value: 'Yes, ad set start and end dates, editable after launch' },
  { property: 'Analytics', value: 'Yes' },
]} />

## Before you start

Creating Meta ads needs 4 things on the Meta side: a Meta Business account with at least one ad account (`act_<n>`, existing or [created through the API](#create-an-ad-account)), a Facebook Page (ads publish as the Page, and Instagram placements need an Instagram professional account linked to that Page), the ads scopes on the connected account, and a pixel or dataset for any conversion-optimized goal ([conversion campaigns](/platforms/meta-ads/conversion-campaigns)).

<Callout type="warn">
An Instagram account connected with the default Instagram Login cannot run ads: that login never grants ads scopes. A `facebook` or `metaads` account must exist in the same profile, or ads calls on the Instagram `accountId` return `422 linked_account_required`.
</Callout>

Meta reviews every ad. A successful create means the ad exists, not that it delivers: `reviewStatus` (`in_review`, `approved`, `rejected`, `with_issues`) is separate from the delivery `status`, and both appear on every node the tree returns.

## Connect

Call `GET /v1/connect/facebook/ads` with `profileId`. Meta has two login options:

- **Classic**, the default (`loginMode=classic`), uses the connected Facebook posting account and its token. An active parent with `ads_management` and `ads_read` can be reused without another OAuth round trip.
- **Facebook Login for Business** (`loginMode=business`, also accepted on `GET /v1/connect/instagram/ads`) creates an independent `metaads` connection with a Business Integration System User token. No posting account is created or required. Open the returned `authUrl` in a browser to complete consent.

Meta needs a Page as the actor for ad creatives, so select a Page for either path when creating ads. Business login can connect without a granted Page for campaign management and insights, but cannot create Page-based creatives or list Page forms in that state. The [connecting accounts guide](/guides/connecting-accounts#facebook-login-for-business) covers Page selection, and the [classic ads-only flow](/guides/connecting-accounts#connect-meta-ads-only-skip-the-page-picker) covers headless connections and scoping sync to specific ad accounts.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `ads_management` | Create and manage campaigns, ad sets, ads, creatives, audiences, pixels and reservations |
| `ads_read` | Read ad accounts and insights |
| `pages_manage_ads` | Create, list and archive lead forms on the Page |
| `leads_retrieval` | Read submitted leads from lead ads |

`ads_management` covers campaign management, Reach and Frequency and the Business Manager list. [Creating an ad account](#create-an-ad-account) also requires `business_management` and business admin access. Accounts connected before ads were enabled on the team do not carry these scopes: reconnect them, and read [Account health](/accounts/get-all-accounts-health) to see what a connected account was granted. The general scope rule is on the [connecting accounts guide](/guides/connecting-accounts#scopes).

### Find your ad account id

Ad-account operations use `accountId` (the connected posting or Meta Ads account) and `adAccountId` (the Meta ad account). Call `GET /v1/ads/accounts` to list the ad accounts the connection can reach. Creating a new ad account instead takes an active `metaads` account as `accountId` and the owning `businessId`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: adAccounts } = await zernio.adaccounts.listAdAccounts({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

const adAccountId = adAccounts.accounts[0].id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

ad_accounts = client.ad_accounts.list_ad_accounts(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

ad_account_id = ad_accounts["accounts"][0]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/accounts?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "accounts": [
    {
      "id": "act_1234567890",
      "name": "Acme",
      "currency": "USD",
      "accountStatus": 1,
      "timezoneName": "America/New_York",
      "minimumDailyBudget": 1,
      "selectable": true,
      "unusableReason": null
    }
  ]
}
```

`accounts[].id` is the `adAccountId` for every other call. Budgets and bids are whole units of `currency` (`75` is $75.00 on a USD account), never cents.

### Create an ad account

[`POST /v1/ads/accounts`](/ad-accounts/create-ad-account) creates a Meta ad account in the customer's **own business portfolio**. Discover portfolios with [`GET /v1/ads/businesses`](/ad-accounts/list-meta-businesses), then send `accountId` (an active `metaads` connection), `businessId`, `name`, `currency` and `timezoneId`. The connection needs `business_management` and business admin access. System-user tokens can return an empty businesses list; supply the known business ID in that case.

`timezoneId` is Meta's **numeric timezone ID**, not an IANA name. The reference links Meta's timezone list. Use the returned `adAccountId` with the existing ads endpoints.

<Callout type="warn">
The new account has no payment method. The customer must add one in Ads Manager before ads deliver; Zernio cannot add it through the API. Meta caps how many ad accounts a business can create and may require business verification. Closing an account does not guarantee more capacity, and an ad account can never truly be deleted once created.
</Callout>

This call is **not idempotent** and Zernio never automatically retries it. After a timeout or a `502` with `details.creationStatus: "unknown"`, check the business in Ads Manager before creating again. A `201` with `connectionUpdated: false` still means the account exists: follow the returned recovery instructions to reconnect with its ID and the previous scoped IDs, instead of repeating the create.

## The campaign tree

Every Meta ad lives in a 3-level tree. Each level owns different settings, and that decides which endpoint you call:

<Mermaid
  chart={`flowchart TB
  C["Campaign: goal, bid strategy, budget (CBO)"] --> S1["Ad set: targeting, schedule, budget (ABO)"]
  C --> S2["Ad set"]
  S1 --> A1["Ad: creative"]
  S1 --> A2["Ad: creative"]
  S2 --> A3["Ad: creative"]`}
/>

| Level | Owns | Page |
|---|---|---|
| Campaign | Objective (`goal`), bid strategy, spend cap, the budget on CBO campaigns | [Campaigns](/platforms/meta-ads/campaigns) |
| Ad set (`adSetId`) | Targeting, schedule, optimization goal, `promotedObject`, the budget on ABO campaigns | [Ad sets](/platforms/meta-ads/ad-sets) |
| Ad | The creative: headline, body, media, call to action, link | [Creatives](/platforms/meta-ads/creatives) |

You rarely build the tree level by level. `POST /v1/ads/create` creates all 3 in one call, `POST /v1/ads/boost` wraps an existing post the same way, and `GET /v1/ads/tree` reads the hierarchy back with rolled-up metrics. Which level holds the budget (CBO or ABO) decides which update endpoint accepts a budget change; the [campaigns page](/platforms/meta-ads/campaigns#reading-the-campaign-tree) explains `budgetLevel`.

## Pages

### Build the ads

<Cards>
  <Card icon={<Megaphone />} title="Campaigns" href="/platforms/meta-ads/campaigns" description="Create the full tree in one call, campaign-only creates, bid strategy and reading the tree" />
  <Card icon={<Layers />} title="Ad sets" href="/platforms/meta-ads/ad-sets" description="Budget, status, delivery settings and the learning phase after launch" />
  <Card icon={<Palette />} title="Creatives" href="/platforms/meta-ads/creatives" description="Attach to an ad set, video, carousel, per-placement assets, Advantage+ enhancements, swap a creative" />
  <Card icon={<Library />} title="Creative library" href="/platforms/meta-ads/creative-library" description="Standalone creatives and the ad account image library" />
  <Card icon={<Eye />} title="Previews" href="/platforms/meta-ads/previews" description="Render an ad or a draft creative as Meta renders it" />
  <Card icon={<Crosshair />} title="Targeting" href="/platforms/meta-ads/targeting" description="Geo, demographic and interest targeting, and how to look up ids" />
  <Card icon={<Users />} title="Audiences" href="/platforms/meta-ads/audiences" description="Customer list, website, lookalike and engagement audiences" />
  <Card icon={<Rocket />} title="Boost a post" href="/platforms/meta-ads/boost" description="Turn a published post into an ad" />
  <Card icon={<FlaskConical />} title="Creative testing" href="/platforms/meta-ads/creative-testing" description="N ads in one ad set to test variations" />
  <Card icon={<Target />} title="Conversion campaigns" href="/platforms/meta-ads/conversion-campaigns" description="Conversion goals and the promoted object" />
  <Card icon={<ShoppingBag />} title="Catalog ads" href="/platforms/meta-ads/catalog-ads" description="Advantage+ catalog ads from a product set" />
  <Card icon={<MessagesSquare />} title="Messaging and call ads" href="/platforms/meta-ads/messaging-ads" description="Ads that open WhatsApp, Messenger or Instagram Direct, or dial a number" />
  <Card icon={<SiWhatsapp />} title="Click-to-WhatsApp ads" href="/platforms/meta-ads/ctwa" description="The deprecated CTWA endpoint, kept for existing integrations" />
  <Card icon={<ClipboardList />} title="Lead forms" href="/platforms/meta-ads/lead-forms" description="Create instant forms and receive leads" />
  <Card icon={<Gauge />} title="Reach and Frequency" href="/platforms/meta-ads/reach-and-frequency" description="Quote, reserve and buy fixed-price reserved campaigns" />
</Cards>

### Measure what they did

<Cards>
  <Card icon={<ChartLine />} title="Insights" href="/platforms/meta-ads/insights" description="Synced metrics, the ROAS and status fields, breakdowns, live Meta queries and report runs" />
  <Card icon={<Code />} title="Pixels" href="/platforms/meta-ads/pixels" description="Create, share and read pixels through the tracking tags API" />
  <Card icon={<Server />} title="Conversions" href="/platforms/meta-ads/capi" description="Send server-side conversion events and read Event Match Quality" />
  <Card icon={<Link />} title="URL tracking tags" href="/platforms/meta-ads/tracking-tags" description="Read and set the click-URL parameters on an ad" />
</Cards>

### Operate and look things up

<Cards>
  <Card icon={<Copy />} title="Lifecycle" href="/platforms/meta-ads/lifecycle" description="Dry run, pause, resume, duplicate and delete" />
  <Card icon={<Activity />} title="Account reads" href="/platforms/meta-ads/operational-reads" description="Change log, studies, finances, labels, budget schedules, businesses, Instagram identities, app promotion" />
  <Card icon={<Library />} title="Ad Library" href="/platforms/meta-ads/ad-library" description="Search the public ad archive" />
  <Card icon={<MessageSquare />} title="Ad comments" href="/platforms/meta-ads/ad-comments" description="Read comments on ads, dark posts included" />
  <Card icon={<FileImage />} title="Reference" href="/platforms/meta-ads/reference" description="Enums, media limits, what you cannot do and Meta error codes" />
</Cards>

## Common errors

3 errors are section-wide rather than endpoint-specific.

| Error | Cause | Fix |
|---|---|---|
| `403` `{ "error": "Ads add-on required" }` | The team is on a legacy plan without ads, or the Meta token is missing the ads permissions | Ads are included with usage-based billing; otherwise reconnect the account so the consent screen requests the ads scopes |
| `403` `ads_allowance_exceeded` | The team has no payment method on file and has reached its 500 free live ads | Add a card to resume creating and resuming ads |
| `422` `linked_account_required` | The call names an Instagram account connected with Instagram Login, which never carries ads scopes | Connect a `facebook` or `metaads` account on the same profile and call with its `accountId` |

Everything below the platform boundary comes back as `type: "platform_error"` with Meta's own message and subcode in `platformError`. The [reference](/platforms/meta-ads/reference#common-errors) lists the subcodes worth recognizing on sight.

## Related

- [Connecting accounts](/guides/connecting-accounts): the ads-only connect flow and Page selection.
- [Facebook](/platforms/facebook) and [Instagram](/platforms/instagram): the posting accounts an ad runs as.
- [Create standalone ad](/ad-campaigns/create-standalone-ad): every field of the create request.
- [Webhooks for ads](/webhooks/ads): `lead.received` and ad status events.
- [Error handling](/guides/error-handling): the envelope every `4xx` uses, including `platform_error` with Meta's own code.

---
