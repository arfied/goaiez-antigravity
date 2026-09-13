# Rate Limits

Requests per minute by connected account count, the per-second window on analytics, posting velocity caps, and how to handle a 429.

You get 60, 600 or 1,200 requests per minute, set by how many accounts your team has connected, on a sliding window that frees capacity continuously. Read `X-RateLimit-Remaining` on every response and wait `Retry-After` seconds on a `429`. One API key is enough for any volume, including a [multi-tenant integration](/multi-tenant) serving many customers: the limit grows as you connect accounts, so create [scoped keys](/multi-tenant#scoped-api-keys) for access control, never for throughput.

## First call

Every response carries the limit headers. `-i` prints them:

```bash
curl -i "https://zernio.com/api/v1/accounts" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```text
HTTP/2 200
X-RateLimit-Limit: 600
X-RateLimit-Remaining: 599
X-RateLimit-Reset: 1798761660
Content-Type: application/json

{ "accounts": [ ... ] }
```

| Header | Description |
|--------|-------------|
| `X-RateLimit-Limit` | The limit for the window that applies to this endpoint (per minute, or per second on analytics endpoints) |
| `X-RateLimit-Remaining` | Requests remaining in the current window |
| `X-RateLimit-Reset` | Unix timestamp (seconds) when the next slot frees up |
| `Retry-After` | `429` responses only: seconds to wait before retrying |

Read the headers instead of hard-coding the table below; your limit may be configured higher than the default.

## Request limits

| Connected accounts | Requests per minute |
|---|---|
| 0 to 2 (free) | 60 |
| 3 to 2,000 | 600 |
| 2,001 and more | 1,200 |

Connected accounts are counted across the whole team (owner plus invited members). Legacy AppSumo lifetime accounts get a flat 600 requests per minute.

### Per-second limits for analytics endpoints

Analytics endpoints are limited on a 1-second window instead of a 1-minute one. The per-second cap derives from your per-minute limit, with a floor of 6 so a dashboard that fans out several analytics requests per page render is not throttled on the lowest limit:

```
requests_per_second = max(6, requests_per_minute / 60)
```

| Connected accounts | Requests per minute | Requests per second (analytics) |
|---|---|---|
| 0 to 2 (free) | 60 | 6 |
| 3 to 2,000 | 600 | 10 |
| 2,001 and more | 1,200 | 20 |

Legacy AppSumo lifetime accounts get a flat 10 requests per second on analytics endpoints. These endpoints use the per-second window:

- `GET /api/v1/analytics`
- `GET /api/v1/analytics/delta`
- `GET /api/v1/analytics/best-time`
- `GET /api/v1/analytics/content-decay`
- `GET /api/v1/analytics/daily-metrics`
- `GET /api/v1/analytics/format-breakdown`
- `GET /api/v1/analytics/post-timeline`
- `GET /api/v1/analytics/posting-frequency`

Every other endpoint uses the 1-minute window from the table above.

## Posting velocity limits

Separate from request limits, each account has caps on how fast it can publish, so the platforms do not throttle or flag it:

| Limit | Value |
|-------|-------|
| Velocity cap | 25 posts per hour per account |
| Daily caps | Instagram 100, Facebook 100, Threads 250, X 50, Pinterest 25, TikTok 15 videos and 15 photos on Business app connections, 50 for every other platform |

A post rejected by a velocity limit returns `429` with the cooldown in the message. TikTok's 2 counts are separate and run over a rolling 24 hours, and a post past the cap is held and published automatically when the window frees a slot instead of being rejected ([daily posting caps](/platforms/tiktok#daily-posting-caps)). Legacy AppSumo accounts carry their own, lower per-account daily caps (5, 8 or 20 posts per account per day). The effective cap is the lower of the two, so a daily-limit error quoting 5, 8 or 20 on an Instagram account is the AppSumo cap, not the platform one.

## How it behaves

### Zernio retries platform limits 3 times

The platforms enforce limits of their own on top of Zernio's, and some are shared by every Zernio customer because Zernio talks to the platform as one application. A `429` or `5xx` from a platform is retried up to 3 times. A `429` that carries a reset time under 5 minutes away waits for the reset plus 1 second before retrying. A reset further away, or a platform reporting its quota as exhausted, fails immediately with the platform's message so your post or request does not sit for minutes.

### Reddit's budget is shared

Reddit rate-limits per OAuth application, and Zernio is one Reddit application, so the budget is shared across all Zernio customers. Low request volume on your side does not guarantee headroom. When Reddit's `x-ratelimit-remaining` header reaches 0, Zernio fails the request straight away with `Quota resets in Ns` taken from Reddit's `x-ratelimit-reset` (a number of seconds, not a timestamp), and does not retry it. Reddit's `RATELIMIT` error on posting and messaging surfaces as a `429` with `Reddit rate limit reached... Retry in Ns.` when Reddit states a wait. Treat the seconds in the message as a `Retry-After` header: wait that long, then retry.

### Google Ads draws on one developer token

Google Ads API calls draw on a developer-token quota shared by every Google Ads account connected to Zernio, so a team with one account and a card on file can still hit it. Google resets it once a day, currently in the early afternoon UTC. When it is exhausted, ads requests return `429` with code `rate_limited` and Google's own `Retry in N seconds` in the message; Zernio does not retry these, because each retry counts against the same quota. Connecting a Google Ads account then redirects back with `error=google_ads_quota_exhausted`; the token exchange itself succeeded, so retry the connect after the reset. Disconnecting other ad accounts or adding a payment method frees no quota, since the budget is app-wide.

### TikTok caps direct posting per application

TikTok caps the number of distinct accounts that can direct-post through one application per rolling 24 hours, shared across all Zernio customers on TikTok's developer app. Accounts connected through TikTok's Business app, which is every new connection, are exempt. The error text, the priority rules and the draft workaround are on the [TikTok page](/platforms/tiktok#direct-posting-at-capacity).

### Analytics responses are cached

Post analytics are cached for 60 minutes, and a request against a stale cache triggers a background refresh. Follower stats refresh once a day. YouTube daily views arrive 2 to 3 days late from YouTube's Analytics API. See the [analytics endpoints](/analytics/get-analytics).

## If it fails

A `429` means the window is full:

```json
{
  "error": "Rate limit exceeded. Please retry after 12 seconds.",
  "details": {
    "currentCount": 601,
    "limit": 600,
    "retryAfterSeconds": 12
  }
}
```

Wait the number of seconds in `Retry-After`, then retry the same request. The header is relative, so it is right even when your server's clock is skewed; `details.retryAfterSeconds` carries the same value. This body carries `error` and `details` only, so branch on the status code rather than on a `code` field; the endpoint-specific quota errors above, such as the Google Ads one, are the `429`s that carry `type: "rate_limit_error"` and `code: "rate_limited"`.

To stay under the limit, poll less: [webhooks](/webhooks) push post status changes, [bulk upload](/posts/bulk-upload-posts) creates many posts in one request, and `limit` and `skip` page through lists instead of fetching everything.

## Related

- [Error handling](/guides/error-handling): the `429` envelope and `rate_limit_error`.
- [Idempotency & safe retries](/guides/idempotency): retry a create without double-posting.
- [Multi-tenant](/multi-tenant): scoped keys and per-customer queues.
- [Analytics](/analytics/get-analytics): the endpoints on the per-second window.

---
