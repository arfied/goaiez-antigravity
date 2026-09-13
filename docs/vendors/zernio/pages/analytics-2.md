# Analytics

Read daily performance metrics and the search keywords that triggered impressions for a Google Business Profile location.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can read a Google Business Profile (`googlebusiness`) location's daily impressions, website clicks, calls, direction requests, conversations, bookings and food orders, and the search keywords that triggered its impressions. You need a connected Google Business Profile account (`accountId`). Both endpoints read location-level data going back at most 18 months.

<Callout type="warn">
Per-post analytics do not exist for Google Business Profile. Google [deprecated the per-post insights endpoint](https://developers.google.com/my-business/content/sunset-dates) and did not ship a replacement, so per-post views, clicks and likes no longer exist on Google's side. The two location-level endpoints below are the engagement data Google exposes.
</Callout>

## Read performance metrics

Call `GET /v1/analytics/googlebusiness/performance` with `accountId` and a date range ([Performance metrics](/analytics/get-google-business-performance)). `startDate` defaults to 30 days ago and `endDate` to today; `metrics` narrows the response to a comma-separated list and defaults to every metric. Google delivers the data 2 to 3 days late.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: performance } = await zernio.analytics.getGoogleBusinessPerformance({
  query: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    startDate: '2026-08-01',
    endDate: '2026-08-31'
  }
});

console.log(performance.metrics.WEBSITE_CLICKS.total);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

performance = client.analytics.get_google_business_performance(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    start_date="2026-08-01",
    end_date="2026-08-31"
)

print(performance["metrics"]["WEBSITE_CLICKS"]["total"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics/googlebusiness/performance?accountId=66b2e19d8c3f5a7e9d0b1c2d&startDate=2026-08-01&endDate=2026-08-31" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), one key per metric with the sum and the daily series:

```json
{
  "success": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platform": "googlebusiness",
  "dateRange": { "startDate": "2026-08-01", "endDate": "2026-08-31" },
  "metrics": {
    "WEBSITE_CLICKS": {
      "total": 42,
      "values": [
        { "date": "2026-08-01", "value": 3 },
        { "date": "2026-08-02", "value": 1 }
      ]
    },
    "CALL_CLICKS": {
      "total": 7,
      "values": [{ "date": "2026-08-01", "value": 1 }]
    },
    "BUSINESS_IMPRESSIONS_MOBILE_SEARCH": {
      "total": 156,
      "values": [{ "date": "2026-08-01", "value": 8 }]
    }
  },
  "dataDelay": "Data may be delayed 2-3 days"
}
```

The metrics are `BUSINESS_IMPRESSIONS_DESKTOP_MAPS`, `BUSINESS_IMPRESSIONS_DESKTOP_SEARCH`, `BUSINESS_IMPRESSIONS_MOBILE_MAPS`, `BUSINESS_IMPRESSIONS_MOBILE_SEARCH`, `BUSINESS_CONVERSATIONS`, `BUSINESS_DIRECTION_REQUESTS`, `CALL_CLICKS`, `WEBSITE_CLICKS`, `BUSINESS_BOOKINGS`, `BUSINESS_FOOD_ORDERS` and `BUSINESS_FOOD_MENU_CLICKS`.

## Read search keywords

Call `GET /v1/analytics/googlebusiness/search-keywords` with `accountId` and a month range ([Search keywords](/analytics/get-google-business-search-keywords)). Google aggregates keywords by month and drops those under an impression threshold it does not publish. `startMonth` defaults to 3 months ago and `endMonth` to the current month.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: keywords } = await zernio.analytics.getGoogleBusinessSearchKeywords({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', startMonth: '2026-06', endMonth: '2026-08' }
});

for (const k of keywords.keywords) console.log(k.keyword, k.impressions);
```
</Tab>
<Tab value="Python">
```python
keywords = client.analytics.get_google_business_search_keywords(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    start_month="2026-06",
    end_month="2026-08"
)

for k in keywords["keywords"]:
    print(k["keyword"], k["impressions"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics/googlebusiness/search-keywords?accountId=66b2e19d8c3f5a7e9d0b1c2d&startMonth=2026-06&endMonth=2026-08" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platform": "googlebusiness",
  "monthRange": { "startMonth": "2026-06", "endMonth": "2026-08" },
  "keywords": [
    { "keyword": "restaurant near me", "impressions": 245 },
    { "keyword": "best tapas barcelona", "impressions": 89 },
    { "keyword": "zernio", "impressions": 34 }
  ],
  "note": "Keywords below a minimum impression threshold are excluded by Google"
}
```

## If it fails

A `400` on the performance endpoint means a metric name in `metrics` is not one Google supports; the response lists the valid ones:

```json
{
  "error": "Invalid metrics: INVALID_METRIC",
  "validMetrics": [
    "BUSINESS_IMPRESSIONS_DESKTOP_MAPS",
    "BUSINESS_IMPRESSIONS_DESKTOP_SEARCH",
    "BUSINESS_IMPRESSIONS_MOBILE_MAPS",
    "BUSINESS_IMPRESSIONS_MOBILE_SEARCH",
    "BUSINESS_CONVERSATIONS",
    "BUSINESS_DIRECTION_REQUESTS",
    "CALL_CLICKS",
    "WEBSITE_CLICKS",
    "BUSINESS_BOOKINGS",
    "BUSINESS_FOOD_ORDERS",
    "BUSINESS_FOOD_MENU_CLICKS"
  ]
}
```

Pick names from `validMetrics` and retry. On the keywords endpoint a `400` with "Invalid startMonth format. Use YYYY-MM." means the month is not `YYYY-MM`. A `403` means `accountId` is not one of your accounts.

Both endpoints also answer `402` when the team has no analytics access, which is the first thing a legacy plan hits:

```json
{
  "error": "Analytics add-on required",
  "code": "analytics_addon_required"
}
```

Analytics is included on usage-based billing ([pricing](/pricing)); a legacy plan needs it enabled before either endpoint returns data. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Performance metrics](/analytics/get-google-business-performance): every query parameter.
- [Search keywords](/analytics/get-google-business-search-keywords): the month-range parameters.
- [Business Profile Management](/platforms/google-business/business-profile): the listing details behind these numbers.
- [Rate limits](/guides/rate-limits#analytics-responses-are-cached): how often analytics refresh.

---
