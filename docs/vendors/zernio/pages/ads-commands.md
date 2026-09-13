# Ads commands

Read and manage ads, campaigns, audiences, lead forms, conversions and tracking tags from the terminal with one runnable example.

The ads commands read and manage ads, campaigns and ad sets, custom audiences, lead forms, conversion destinations and tracking tags. Install the CLI with `npm install -g @zernio/cli` and log in with `zernio auth:login`, both covered on the [CLI page](/cli).

## First command

List the active Facebook ads with their metrics:

```bash
zernio ads:list --platform facebook --status active --limit 5 --pretty
```

Output (the `200` body of [`GET /v1/ads`](/ad-campaigns/list-ads), trimmed to the first ad):

```json
{
  "ads": [
    {
      "_id": "66d4c1a9e2b5af0012ab7788",
      "name": "Spring launch, video",
      "platform": "facebook",
      "status": "active",
      "adType": "standalone",
      "creativeType": "video",
      "goal": "conversions",
      "isExternal": false,
      "budget": { "amount": 25, "type": "daily" },
      "metrics": {
        "spend": 412.68,
        "impressions": 58204,
        "clicks": 933,
        "ctr": 1.6,
        "cpc": 0.44,
        "conversions": 21
      },
      "platformAdId": "23851234567890123",
      "campaignName": "Spring launch"
    }
  ],
  "pagination": { "page": 1, "limit": 5, "total": 37, "pages": 8 }
}
```

`status`, `platform`, `campaignId`, `fromDate` and `toDate` are query parameters of the same endpoint, and each is a flag of the same name. The endpoint's `adSetId` and `pageId` filters have no flag, and the CLI exits 1 with `Unknown argument` on a flag a command does not register.

## Commands

`zernio --help` prints the full command list. The four ad groups are generated from the API, so each command maps to one endpoint in the reference:

| Group | Reference |
|---|---|
| `ads:` | [Ads](/ad-campaigns/list-ads), [Lead gen](/lead-gen/list-leads), [Conversions](/conversions/list-conversion-destinations) |
| `adcampaigns:` | [Campaigns and ad sets](/ad-campaigns/create-ad-campaign) |
| `adaudiences:` | [Ad audiences](/ad-audiences/create-ad-audience) |
| `trackingtags:` | [Tracking tags](/tracking-tags/create-tracking-tag) |

## How it behaves

### Zernio lists ads it did not create

`ads:list` returns ads created through Zernio and ads synced from the platform's own ads manager. Pass `--source zernio` to restrict the list to ads Zernio created.

### Zernio measures the last 90 days by default

Metrics cover the last 90 days unless you pass `--fromDate` and `--toDate`. A range wider than 730 days is rejected.

### Zernio answers 202 while history backfills

A `202` carries the same body plus `"backfillPending": true`, which is always `true` on that response. The ads are real and the numbers are incomplete; retry the command until it returns `200`.

### Zernio answers 403 when the plan does not include ads

Usage-based plans include ads. On a legacy plan every command in the four groups above fails with `403` and exits with code 1:

```json
{
  "error": "Ads add-on required"
}
```

This response carries no `code` field, so branch on the `403` status. A `403` that does carry `code: ads_allowance_exceeded` is the other case: the team has no card on file and has reached its 500 free live ads ([Ads pricing](/pricing/ads)). [Billing](/billing) covers what a plan includes.

## Related

- [CLI](/cli): install, log in, and the first command
- [Meta Ads](/platforms/meta-ads): campaign, ad set and ad levels
- [List ads](/ad-campaigns/list-ads): the endpoint behind `ads:list`
- [Ads webhooks](/webhooks/ads): leads and status changes as events

---
