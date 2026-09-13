# Weekly cron: sync_all_customers(customers, 366)
def sync_all_customers(customers, window_days: int):
    for customer in customers:
        sync_customer(customer, window_days)


def sync_customer(customer, window_days: int):
    page = 1
    while True:
        try:
            page_data = client.analytics.get_analytics(
                profile_id=customer.zernio_profile_id,
                from_date=days_ago(window_days),
                limit=50,
                page=page,
            )
        except ZernioRateLimitError as err:
            # reset_time comes from X-RateLimit-Reset, and is None without it
            seconds = 5
            if err.reset_time:
                seconds = max(1, int((err.reset_time - datetime.now()).total_seconds()))
            time.sleep(seconds)
            continue

        upsert_posts(customer.id, page_data["posts"])  # key on post id + platform

        if page >= page_data["pagination"]["pages"]:
            break
        page += 1
```
</Tab>
</Tabs>

A single-post fetch (`GET /v1/analytics?postId=`) returns `202` while its sync is pending, so retry it shortly, and `424` when every platform failed to sync it.

## Step 3: Build charts from daily metrics

Call `GET /v1/analytics/daily-metrics` with `profileId` for day-by-day sums and a per-platform breakdown, one call per customer, instead of summing per-post rows yourself. `attribution` decides what a day means:

| | A day means | The question it answers |
|---|---|---|
| `attribution=publish` (default) | Each post's lifetime totals, summed on its publish date | "How did the content I published this week perform?" |
| `attribution=received` | Engagement bucketed by the day it arrived | "How much engagement did my account get this week?" |

A post published July 1 gets 40 likes that day and 10 more on July 20. Under `publish`, all 50 sit on July 1 and the July 20 bar never moves. Under `received`, July 1 shows 40 and July 20 shows 10, so the chart moves in weeks the customer did not post, which is what an account-overview widget wants.

<Mermaid
  chart={`%%{init: {"xyChart": {"width": 620, "height": 240}, "themeVariables": {"xyChart": {"backgroundColor": "transparent", "plotColorPalette": "#ec3013"}}}}%%
xychart-beta
  title "attribution=publish"
  x-axis ["Jul 1", "Jul 8", "Jul 15", "Jul 20"]
  y-axis "likes" 0 --> 50
  bar [50, 0, 0, 0]`}
/>

<Mermaid
  chart={`%%{init: {"xyChart": {"width": 620, "height": 240}, "themeVariables": {"xyChart": {"backgroundColor": "transparent", "plotColorPalette": "#ec3013"}}}}%%
xychart-beta
  title "attribution=received"
  x-axis ["Jul 1", "Jul 8", "Jul 15", "Jul 20"]
  y-axis "likes" 0 --> 50
  bar [40, 0, 0, 10]`}
/>

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: daily } = await zernio.analytics.getDailyMetrics({
  query: { profileId, fromDate: '2026-08-09', attribution: 'received' },
});

console.log(daily.dailyData.length);
```
</Tab>
<Tab value="Python">
```python
daily = client.analytics.get_daily_metrics(
    profile_id=profile_id,
    from_date="2026-08-09",
    attribution="received",
)

print(len(daily["dailyData"]))
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics/daily-metrics?profileId=66a1f0c2a4b9d3e8f1a2b3c4&fromDate=2026-08-09&attribution=received" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), one ready-made point per day:

```json
{
  "dailyData": [
    {
      "date": "2026-08-13",
      "postCount": 3,
      "platforms": { "instagram": 2, "twitter": 1 },
      "metrics": { "impressions": 4210, "reach": 3105, "likes": 312, "comments": 41, "shares": 18, "saves": 27, "clicks": 63, "views": 3890 }
    }
  ],
  "platformBreakdown": [
    { "platform": "instagram", "postCount": 2, "impressions": 2884, "likes": 240 }
  ]
}
```

For follower-growth charts, [`GET /v1/accounts/follower-stats`](/analytics/get-follower-stats) takes the same `profileId` plus `granularity` (`daily`, `weekly` or `monthly`), so pick the granularity that matches the chart.

## Step 4: Learn about new posts from webhooks

Subscribe to [`post.published`](/webhooks/posts) and insert the row when it fires; the `accountId` on each `platforms[]` entry maps back to the customer. Its analytics populate on the next sync pass, once the platform makes them available.

Posts the customer publishes outside your app are picked up by a background sync roughly every 90 minutes per account. When a customer pastes the URL of a post they published a moment ago and expects to see it, call [`POST /v1/posts/sync-external`](/analytics/sync-external-posts) with `accountId` and `url` to fetch it on demand. It is debounced per account (about 15 seconds), so use it for that moment and not as a bulk refresh.

An inactive account or one that needs reconnection returns `409` with `code: "ads_connection_required"`, even inside the debounce window. Stop polling that account until it is reconnected, then read [`GET /v1/accounts`](/accounts/list-accounts) for its current ID before resuming. Treat this as a connection problem, not a missing post or a transient sync failure.

## Step 5: Show freshness instead of promising real time

Some delays are Zernio's caching; most are the platforms':

| Data | Freshness |
|---|---|
| Post analytics (likes, comments) | Cached about 60 minutes; a stale request triggers a background refresh |
| Post insights (reach, impressions) | Platform-dependent, about 24 hours on Instagram |
| Follower stats | Refreshed once per day |
| External posts | Background sync about every 90 minutes per account |
| YouTube daily views | 2 to 3 days behind YouTube's Analytics API |

Store and render the freshness fields the API returns:

- `overview.lastSync` and `overview.dataStaleness` on list responses feed a "data as of" label.
- `syncStatus` per platform entry (`synced`, `pending`, `unavailable`): show a spinner on `pending` instead of a zero.
- `analytics.lastUpdated` per post, for post-detail views.

## If it fails

A `429` means the worker is over the per-second analytics budget for your team:

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

The response carries a `Retry-After` header and an `X-RateLimit-Reset` timestamp; the SDK errors carry that timestamp as `getSecondsUntilReset()` in Node.js and `reset_time` in Python. Sleep that long and retry the same page, as the worker above does. Lower the concurrency cap if it keeps happening. The [rate limits guide](/guides/rate-limits) lists the budget per account count.

## Related

- [Build a platform](/multi-tenant): the profile-per-customer model.
- [Inbox and DMs](/multi-tenant/inbox): the same architecture applied to messaging.
- [Rate limits](/guides/rate-limits): windows, headers and the account-based ladder.
- [Post webhooks](/webhooks/posts): payloads for the publishing lifecycle.

---
