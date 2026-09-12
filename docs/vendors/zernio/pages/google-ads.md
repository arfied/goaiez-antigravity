# Google Ads

Create Search, Display and Performance Max campaigns, mine keywords, upload Customer Match lists, send conversions and query GAQL on a googleads account.

import { Cards, Card } from 'fumadocs-ui/components/card';
import { ChartLine, Gauge, Link, Megaphone, Search, Server, Users } from 'lucide-react';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Create Google Search, Display and Performance Max campaigns with `POST /v1/ads/create`, read keywords and run Keyword Planner, and query anything Google's reporting can answer with the Insights endpoints, all on a `googleads` account.

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Hierarchy', value: 'Campaign > Ad Group > Ad (created in one call)' },
  { property: 'Campaign types (create)', value: 'Search (Responsive Search Ads), Display (Responsive Display Ads)' },
  { property: 'Goals (`goal`)', value: 'engagement, traffic, awareness. video_views and conversion goals are rejected at create with a 422' },
  { property: 'Keyword targeting', value: 'Broad, phrase, exact; synced keyword list plus Keyword Planner' },
  { property: 'Insights', value: <>Rolled-up metrics on <code>/ads/tree</code> plus raw GAQL passthrough (see <a href="/platforms/google-ads/insights">Insights and GAQL</a>)</> },
  { property: 'Discovered campaigns', value: 'All types sync into /ads/tree with metrics, including Performance Max' },
  { property: 'Audiences', value: 'Customer Match (create plus member upload)' },
  { property: 'Conversions API', value: 'Data Manager API (click ids plus Enhanced Conversions for Leads), EEA/UK consent' },
  { property: 'URL tracking tags', value: 'Campaign-level trackingUrlTemplate plus finalUrlSuffix (read and update)' },
  { property: 'Search ad text', value: '15 headlines (30 characters) and 4 descriptions (90 characters)' },
  { property: 'Display images', value: 'Landscape 1.91:1 and square 1:1 both required, JPEG or PNG, 5120 KB' },
]} />

## Before you start

Google Ads uses a dedicated OAuth connection, separate from YouTube and Google Business Profile. You need a Google Ads account (the customer id, from [List ad accounts](/ad-accounts/list-ad-accounts)); accounts under an MCC manager work too. There is no MCC or Standard Access application on your side: Zernio operates under its own approved developer token.

<Callout type="warn">
Display campaigns need 2 images. Google's Responsive Display Ads require both a landscape (1.91:1) and a square (1:1) marketing image; sending only one is rejected upstream with `NOT_ENOUGH_SQUARE_MARKETING_IMAGE_ASSET`.
</Callout>

Google's API quota is shared across Zernio, so user-driven calls (GAQL, search terms, Keyword Planner, keyword writes) are metered per user; see [Quotas and the ops budget](/platforms/google-ads/reference#quotas-and-the-ops-budget).

## Connect

Call `GET /v1/connect/googleads/ads` with `profileId` ([Connect ads](/connect/connect-ads)). Google Ads is a standalone connection with no parent posting account, so the response always carries an `authUrl` the first time; send the user there, as in the [connecting accounts guide](/guides/connecting-accounts). Once connected, the same call returns `alreadyConnected: true` with the `accountId`.

```bash
curl "https://zernio.com/api/v1/connect/googleads/ads?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://myapp.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), the first time:

```json
{
  "authUrl": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...",
  "state": "..."
}
```

Response (`200`), once the profile already has a `googleads` account:

```json
{
  "alreadyConnected": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platform": "googleads",
  "username": "Acme Search",
  "displayName": "Google Ads (Acme Search)"
}
```

Branch on `alreadyConnected`: when it is there, skip the redirect and use `accountId`. The two shapes never arrive together.

The connection requests 2 scopes ([scopes](/guides/connecting-accounts#scopes)):

| Scope | What it enables |
|-------|-----------------|
| `https://www.googleapis.com/auth/adwords` | Full Google Ads API access: campaigns, ad groups, ads, reporting, conversions |
| `https://www.googleapis.com/auth/datamanager` | Data Manager API: Customer Match audience uploads |

Zernio's `adSetId` is the Google ad group id (on Meta the same field is an ad set, on LinkedIn a campaign); `adAccountId` is the customer id without dashes.

## In this section

<Cards>
  <Card icon={<Megaphone />} title="Create ads" href="/platforms/google-ads/create-ads" description="Search, Display and Performance Max campaigns in one call, RSA text edits and pinning, and sitelink/callout/snippet assets" />
  <Card icon={<Search />} title="Keywords" href="/platforms/google-ads/keywords" description="Synced keyword criteria and the Keyword Planner" />
  <Card icon={<Users />} title="Customer Match" href="/platforms/google-ads/audiences" description="CRM-list audiences with hashed member upload" />
  <Card icon={<ChartLine />} title="Insights and GAQL" href="/platforms/google-ads/insights" description="Rolled-up metrics and raw GAQL queries" />
  <Card icon={<Server />} title="Conversions" href="/platforms/google-ads/conversions" description="Offline and enhanced conversions through the Data Manager API" />
  <Card icon={<Link />} title="URL tracking tags" href="/platforms/google-ads/tracking-tags" description="Campaign tracking templates and final URL suffixes" />
  <Card icon={<Gauge />} title="Limits and errors" href="/platforms/google-ads/reference" description="The ops budget, what you cannot do, and Google's recurring errors" />
</Cards>

## Common errors

Connecting is where this page's own errors live; the per-job pages carry the rest, and [Limits and errors](/platforms/google-ads/reference#common-errors) collects the recurring ones.

| Error | Cause | Fix |
|-------|-------|-----|
| Redirect with `error=google_ads_auth_failed` | The user declined the Google consent screen, or the token exchange failed | Restart the connect flow. Google's own string arrives in `error_message`. |
| Redirect with `error=google_ads_quota_exhausted` | Google's shared API quota ran out during the flow | Retry the connect flow later. |
| `429` on [List ad accounts](/ad-accounts/list-ad-accounts) | Google's API quota is temporarily exhausted, so the call fails rather than returning an empty list | Back off and retry ([Quotas and the ops budget](/platforms/google-ads/reference#quotas-and-the-ops-budget)). |
| `403` with code `ads_addon_required` | The team is on a legacy plan that does not include ads access | Move the team to usage-based billing, where every account includes ads ([Pricing](/pricing)). |

[Error handling](/guides/error-handling) covers the envelope and the stable codes.

## Related

- [Create standalone ad](/ad-campaigns/create-standalone-ad): campaign, ad group and ad in one call.
- [List Search keywords](/ad-campaigns/list-ad-keywords): synced keyword criteria.
- [Keyword ideas](/ad-insights/generate-keyword-ideas) and [Historical metrics](/ad-insights/generate-keyword-historical-metrics).
- [Query ad insights](/ad-insights/query-ad-insights): raw GAQL passthrough.
- [Send conversions](/conversions/send-conversions): Data Manager events.

---
