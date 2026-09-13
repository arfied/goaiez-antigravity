# Analytics

Serve each customer their own analytics dashboard from your database, filled by a sync worker that reads GET /v1/analytics with profileId.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page each customer's dashboard reads their own metrics from your database, and a background worker keeps it fresh with `GET /v1/analytics?profileId=`. You need the customer's `profileId` from the [core model](/multi-tenant). Dashboard renders never call Zernio: the [analytics endpoints](/guides/rate-limits#per-second-limits-for-analytics-endpoints) share one per-second budget across all your customers, and post analytics are cached for about 60 minutes upstream anyway, so a live call buys nothing.

<Mermaid
  chart={`flowchart LR
  subgraph zernio ["Zernio"]
    API["Analytics API"]
    EV["Webhooks"]
  end
  subgraph app ["Your app"]
    WORKER["Sync worker
hot window hourly, long tail weekly"]
    HOOK["Webhook endpoint"]
    DB[("Your database")]
    DASH["Customer dashboards"]
  end
  API -->|"worker pulls, profileId filter"| WORKER
  EV -->|"post.published"| HOOK
  WORKER -->|"upserts"| DB
  HOOK -->|"inserts"| DB
  DB -->|"reads"| DASH
  DASH -.->|"never"| API
  linkStyle 5 stroke:#f43f5e,color:#f43f5e,stroke-dasharray:6 4;`}
/>

Every analytics endpoint takes `profileId`, so a customer's profile is the filter with no extra bookkeeping:

| Endpoint | What it gives a customer's dashboard |
|---|---|
| [`GET /v1/analytics`](/analytics/get-analytics) | Per-post metrics with overview stats, paginated |
| [`GET /v1/analytics/daily-metrics`](/analytics/get-daily-metrics) | Day-by-day sums for charts |
| [`GET /v1/accounts/follower-stats`](/analytics/get-follower-stats) | Follower counts and growth at daily, weekly or monthly granularity |
| [`GET /v1/analytics/best-time`](/analytics/get-best-time-to-post) | Best posting times |

They also take `source`: `late` (the legacy value for posts published through Zernio) for posts your app created, `external` for posts the customer published on the platform itself, `all` (the default) for both.

## Step 1: Pull a customer's posts

Call `GET /v1/analytics` with `profileId`, `fromDate` and `page`. `fromDate` and `toDate` bound the publish dates of the posts returned, at most 366 days apart, and `fromDate` defaults to 90 days ago.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const profileId = '66a1f0c2a4b9d3e8f1a2b3c4';

const { data: analytics } = await zernio.analytics.getAnalytics({
  query: {
    profileId,
    fromDate: '2026-08-09',
    limit: 50,
    page: 1,
  },
});

console.log(analytics.pagination.pages);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
profile_id = "66a1f0c2a4b9d3e8f1a2b3c4"

analytics = client.analytics.get_analytics(
    profile_id=profile_id,
    from_date="2026-08-09",
    limit=50,
    page=1,
)

print(analytics["pagination"]["pages"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?profileId=66a1f0c2a4b9d3e8f1a2b3c4&fromDate=2026-08-09&limit=50&page=1" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "overview": {
    "totalPosts": 142,
    "publishedPosts": 140,
    "scheduledPosts": 2,
    "lastSync": "2026-09-08T09:12:00Z",
    "dataStaleness": { "staleAccountCount": 0, "syncTriggered": false }
  },
  "posts": [
    {
      "_id": "65f1c0a9e2b5af0012ab34cd",
      "platform": "instagram",
      "publishedAt": "2026-09-01T14:00:03Z",
      "platformPostUrl": "https://www.instagram.com/p/...",
      "analytics": { "likes": 312, "comments": 41, "impressions": 4210, "reach": 3105 },
      "platforms": [
        { "platform": "instagram", "syncStatus": "synced" }
      ]
    }
  ],
  "pagination": { "page": 1, "limit": 50, "total": 142, "pages": 3 }
}
```

## Step 2: Run a sync worker

There is no "changed since" cursor for metrics. `fromDate` filters by publish date, and likes keep arriving on posts published weeks ago, so a watermark of `fromDate = last sync time` only picks up new posts and stops refreshing everything older. Sync in 3 windows instead:

| Window | Cadence | Covers |
|---|---|---|
| Hot | Hourly | Posts from the last 30 days, where metrics still move |
| Long tail | Weekly | Everything older |
| Backfill | Once, at onboarding | The customer's full history, in chunks of at most 366 days |

One worker with a small concurrency cap iterates your customers; the same function serves both recurring windows. On a `429`, wait until the limit resets and retry the same page. With a cap of 5 you rarely see one. Follower stats refresh once per day upstream, so put `GET /v1/accounts/follower-stats` on a daily pass, not the hourly one.

<Tabs items={['Node.js', 'Python']}>
<Tab value="Node.js">
```typescript
import pLimit from 'p-limit';
import { RateLimitError } from '@zernio/node';

const limit = pLimit(5);

const daysAgo = (n: number) =>
  new Date(Date.now() - n * 86_400_000).toISOString().slice(0, 10);

// Hourly cron: syncAllCustomers(customers, 30)
// Weekly cron: syncAllCustomers(customers, 366)
export async function syncAllCustomers(customers: Customer[], windowDays: number) {
  await Promise.all(
    customers.map((c) => limit(() => syncCustomer(c, windowDays)))
  );
}

async function syncCustomer(customer: Customer, windowDays: number) {
  for (let page = 1; ; page++) {
    const { data: pageData } = await withRateLimitRetry(() =>
      zernio.analytics.getAnalytics({
        query: {
          profileId: customer.zernioProfileId,
          fromDate: daysAgo(windowDays),
          limit: 50,
          page,
        },
      })
    );

    await upsertPosts(customer.id, pageData.posts); // key on post id + platform

    if (page >= pageData.pagination.pages) break;
  }
}

async function withRateLimitRetry<T>(call: () => Promise<T>): Promise<T> {
  while (true) {
    try {
      return await call();
    } catch (err: unknown) {
      if (!(err instanceof RateLimitError)) throw err;
      // Reads X-RateLimit-Reset, and is undefined when that header is missing
      const seconds = err.getSecondsUntilReset() ?? 5;
      await new Promise((r) => setTimeout(r, seconds * 1000));
    }
  }
}
```
</Tab>
<Tab value="Python">
```python
import time
from datetime import date, datetime, timedelta

from zernio import ZernioRateLimitError


def days_ago(n: int) -> str:
    return (date.today() - timedelta(days=n)).isoformat()
