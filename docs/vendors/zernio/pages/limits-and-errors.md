# Limits and errors

The per-user ops budget on live Google Ads calls, what the Google Ads API and Zernio's integration do not expose, and the errors Google returns most often.

Live Google Ads calls are metered per user, and anything Google rejects comes back carrying Google's own error. Zernio calls Google Ads API `v22`, so GAQL resource and field names resolve against that version; Zernio moves the version itself, and a migration needs no change on your side.

## Quotas and the ops budget

Every Google Ads API call across all Zernio customers shares one developer-token quota, which the background sync also lives off. User-driven live calls (the GAQL passthrough, search terms, Keyword Planner, and the keyword, targeting and campaign writes that go straight to Google) are therefore metered per user:

| Window | Default |
|---|---|
| Per user, per minute | 15 ops |
| Per user, per day | 500 ops |

Hitting a window returns a `429`, and the daily window resets at midnight Pacific, when Google resets its own quota. [Tracking tag](/platforms/google-ads/tracking-tags) reads and writes are not metered per user; they draw straight on the shared quota. The limits cover interactive use; for sustained high-volume pulls (scheduled GAQL sweeps, bulk keyword research, bulk tag audits), contact support first so the capacity is provisioned. [Rate limits](/guides/rate-limits) covers Zernio's own request limits.

## What you cannot do

Google's API, or Zernio's Google integration, does not expose:

- Conversion-goal, Shopping, Video or Demand Gen campaigns through `/v1/ads/create` (Search, Display and Performance Max only). Discovered campaigns of every type still sync into `/ads/tree` with metrics.
- A Responsive Display Ad with one image (Google requires landscape and square).
- GAQL writes: the passthrough is read-only `SELECT`.
- `segments.date` without a finite date filter (Google rejects it).
- Campaign-level negative keywords on `GET /v1/ads/keywords` (ad-group level only; read them from `GET /v1/ads/campaigns/{campaignId}/negative-keywords` or GAQL).
- Same-day keyword sync: the synced keyword list refreshes about weekly per customer.
- Phone-number matching on Customer Match (email only; `phone` is ignored).
- Instant conversions: Data Manager conversions take up to 3 hours to appear in reports.

## Common errors

| Error | Where | Cause and fix |
|---|---|---|
| `NOT_ENOUGH_SQUARE_MARKETING_IMAGE_ASSET`, `NOT_ENOUGH_MARKETING_IMAGE_ASSET` | Display create | One of the 2 required images is missing. Pass both `images.landscape` and `images.square`. |
| `400` carrying a GAQL message | GAQL passthrough | Google's query validator, verbatim; its messages name the offending field. The classic is an unbounded `segments.date`. |
| `429` | GAQL, search terms, Keyword Planner, keyword writes | You hit the per-user ops budget window. Back off; the daily reset is midnight Pacific. |

Google's own error is forwarded inside Zernio's `platform_error` envelope ([error handling](/guides/error-handling)):

```json
{
  "error": "Google rejected the ad: NOT_ENOUGH_SQUARE_MARKETING_IMAGE_ASSET",
  "type": "platform_error",
  "code": "platform_api_error",
  "platform": "google",
  "platformError": {
    "code": 3,
    "message": "Request contains an invalid argument.",
    "details": [ { "errors": [ { "errorCode": { "assetError": "NOT_ENOUGH_SQUARE_MARKETING_IMAGE_ASSET" }, "message": "Too few." } ] } ]
  }
}
```

## Related

- [Create ads](/platforms/google-ads/create-ads): the media requirements and the create call each limit applies to.
- [Insights and GAQL](/platforms/google-ads/insights): the reads the ops budget meters.
- [Error handling](/guides/error-handling): the envelope every error uses.

---
