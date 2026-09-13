# Analytics commands

Read post, platform and inbox analytics from the terminal with one runnable example and the response it prints.

The analytics commands read per-post and per-platform metrics, inbox volume and response times, and the activity log. Install the CLI with `npm install -g @zernio/cli` and log in with `zernio auth:login`, both covered on the [CLI page](/cli).

## First command

Read the most recent Instagram post with its metrics:

```bash
zernio analytics:posts --platform instagram --limit 1 --pretty
```

Output (the `200` body of [`GET /v1/analytics`](/analytics/get-analytics), trimmed):

```json
{
  "overview": { "totalPosts": 156, "publishedPosts": 156, "scheduledPosts": 0 },
  "posts": [
    {
      "_id": "65f1c0a9e2b5af0012ab34cd",
      "content": "Behind the scenes of the spring launch.",
      "publishedAt": "2027-01-01T17:00:05Z",
      "status": "published",
      "platform": "instagram",
      "platformPostUrl": "https://www.instagram.com/reel/ABC123xyz/",
      "analytics": {
        "impressions": 15420,
        "reach": 12350,
        "likes": 342,
        "comments": 28,
        "shares": 45,
        "saves": 0,
        "clicks": 189,
        "engagementRate": 2.78,
        "lastUpdated": "2027-01-02T08:30:00Z"
      }
    }
  ],
  "pagination": { "page": 1, "limit": 1, "total": 156, "pages": 156 }
}
```

`postId`, `profileId`, `platform`, `source`, `sortBy` and `order` are query parameters of the same endpoint, and each is a flag of the same name. The dates are the exception: `--from` and `--to` are sent as `fromDate` and `toDate`. The endpoint's `accountId` filter has no flag.

## Commands

`zernio --help` prints the full command list. The three groups are generated from the API, so each command maps to one endpoint in the reference:

| Group | Reference |
|---|---|
| `analytics:` | [Analytics](/analytics/get-analytics), including the per-platform insights |
| `inboxanalytics:` | [Inbox analytics](/inbox-analytics/get-inbox-volume) |
| `logs:` | [Activity logs](/logs/list-logs) |

## How it behaves

### Zernio returns a list until you name a post

Without `--postId` the command returns a page of posts plus the `overview` block above. With `--postId` it returns that one post, and it accepts both a Zernio post id and an external post id.

### Zernio looks back 90 days by default

`fromDate` defaults to 90 days ago and the range is capped at 366 days.

### Zernio answers 202 while a post is still syncing

A single post lookup returns `202` with `syncStatus` set to `pending` while the platform metrics are being fetched. Run the command again rather than treating it as a failure.

### Zernio answers 402 when the plan does not include analytics

```json
{
  "error": "Analytics add-on required",
  "code": "analytics_addon_required"
}
```

Usage-based plans include analytics. On a legacy plan, enable analytics on the plan and run the command again.

## Related

- [CLI](/cli): install, log in, and the first command
- [Get analytics](/analytics/get-analytics): the endpoint behind `analytics:posts`
- [Analytics webhooks](/webhooks/analytics): `analytics.synced` and the delta feed instead of polling
- [Rate limits](/guides/rate-limits): how many analytics calls a key can make
- [Management commands](/cli/management): the `usage:` commands that report plan usage

---
