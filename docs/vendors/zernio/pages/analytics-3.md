# Analytics

Read spend, clicks and firmographic breakdowns (job title, seniority, industry, company size) for a LinkedIn ad with the Insights endpoints.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can read who saw a LinkedIn ad, by job title, seniority, industry and company, from `GET /v1/ads/{adId}/analytics`. You need the Zernio `_id` of an ad ([Create ads](/platforms/linkedin-ads/create-ads)). Standard metrics (spend, impressions, clicks, CPC, CPM) come rolled up on `GET /v1/ads`, `GET /v1/ads/tree` and `GET /v1/ads/{adId}`; the firmographic layer is what is LinkedIn-specific.

## Firmographic breakdowns

Call `GET /v1/ads/{adId}/analytics` with `breakdowns`, a comma-separated list of LinkedIn dimensions. `breakdowns` is optional: without it the response is `summary` and `daily` for the range and no `breakdowns` object. Each dimension you list costs one more call to LinkedIn, so ask for the ones you read:

| Dimension | LinkedIn pivot |
|---|---|
| `job_title` | MEMBER_JOB_TITLE |
| `job_function` | MEMBER_JOB_FUNCTION |
| `seniority` | MEMBER_SENIORITY |
| `industry` | MEMBER_INDUSTRY |
| `company` | MEMBER_COMPANY |
| `company_size` | MEMBER_COMPANY_SIZE |
| `country` | MEMBER_COUNTRY_V2 |
| `region` | MEMBER_REGION_V2 |

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: insights } = await zernio.adinsights.getAdAnalytics({
  path: { adId: '66f0a1b2c3d4e5f6a7b8c9d0' },
  query: { fromDate: '2027-01-01', toDate: '2027-01-31', breakdowns: 'job_title,seniority,company_size' }
});

console.log(insights.analytics.breakdowns.seniority);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

insights = client.ad_insights.get_ad_analytics(
    ad_id="66f0a1b2c3d4e5f6a7b8c9d0",
    from_date="2027-01-01",
    to_date="2027-01-31",
    breakdowns="job_title,seniority,company_size",
)

print(insights["analytics"]["breakdowns"]["seniority"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/66f0a1b2c3d4e5f6a7b8c9d0/analytics?fromDate=2027-01-01&toDate=2027-01-31&breakdowns=job_title,seniority,company_size" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), trimmed to the summary and one breakdown:

```json
{
  "ad": { "id": "66f0a1b2c3d4e5f6a7b8c9d0", "name": "Spring launch (single image)", "platform": "linkedin", "status": "active", "currency": "USD" },
  "analytics": {
    "summary": { "spend": 412.5, "impressions": 38210, "clicks": 512, "ctr": 1.34, "cpc": 0.81, "cpm": 10.8, "engagement": 640, "conversions": 12 },
    "daily": [
      { "date": "2027-01-01", "spend": 13.4, "impressions": 1240, "clicks": 17 }
    ],
    "breakdowns": {
      "seniority": [
        { "value": "urn:li:seniority:6", "name": "Senior", "spend": 210.1, "impressions": 19800, "clicks": 270, "ctr": 1.36, "cpc": 0.78, "cpm": 10.6, "engagement": 330 }
      ]
    }
  }
}
```

Each breakdown row carries `value` (LinkedIn's URN) and a `name` resolved server-side (job titles, companies and industries all come back human-readable), sorted by impressions. Without `fromDate` and `toDate` the range is the last 90 days, capped at 730 days.

The same `breakdowns` parameter works at the campaign level on `GET /v1/ads/campaigns/{campaignId}/analytics`; for LinkedIn, the campaign id is the Campaign Group. It is also the endpoint that serves [Meta's demographic breakdowns](/platforms/meta-ads/insights#demographic-and-placement-breakdowns), so both endpoints validate against every platform's dimensions at once. A Meta or TikTok dimension such as `age` on a LinkedIn ad is accepted and then dropped: the response is a `200` with no `breakdowns` object.

## LinkedIn constraints

- Firmographic pivots are aggregated over the whole requested range: LinkedIn only serves them with `timeGranularity: ALL`, so there is no per-day series per job title.
- Demographic pivots lag 12 to 24 hours.
- LinkedIn omits segments with fewer than 3 events, so small campaigns return short lists.

## Conversion attribution

Conversion-level metrics (post-click and view-through conversions, conversion value, cost) are read per conversion rule from the [attribution metrics endpoint](/platforms/linkedin-ads/conversions#attribution-metrics).

## If it fails

A `400` names the dimension no platform defines and lists every accepted one:

```json
{
  "error": "Invalid breakdown: job_titles. Supported: age, gender, country, publisher_platform, device_platform, region, platform_position, impression_device, video_asset, image_asset, body_asset, title_asset, country_code, platform, ac, language, job_title, job_function, seniority, industry, company, company_size.",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "breakdowns"
}
```

## Related

- [Conversions API](/platforms/linkedin-ads/conversions#attribution-metrics): attribution read-back per rule.
- [Get ad analytics](/ad-insights/get-ad-analytics) and [Get campaign analytics](/ad-insights/get-campaign-analytics): every metric in `summary`.
- [Get campaign tree](/ad-campaigns/get-ad-tree): rolled-up metrics per level.

---
