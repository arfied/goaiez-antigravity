# Related Schema Definitions

## LinkedInAggregateAnalyticsTotalResponse

Response for TOTAL aggregation (lifetime totals)

### Properties

- **accountId** `string`: No description
- **platform** `string`: No description
- **accountType** `string`: No description
- **username** `string`: No description
- **aggregation** `string`: No description - one of: TOTAL
- **dateRange** `object,null`: No description
- **analytics** `object`: 
  - **impressions** `integer`: Total impressions across all posts
  - **reach** `integer`: Unique members reached across all posts
  - **reactions** `integer`: Total reactions across all posts
  - **comments** `integer`: Total comments across all posts
  - **shares** `integer`: Total reshares across all posts
  - **saves** `integer`: Total times posts were saved (personal accounts only)
  - **sends** `integer`: Total times posts were sent via LinkedIn messaging (personal accounts only)
  - **engagementRate** `number`: Overall engagement rate, as a percentage rounded to 2 decimals: (reactions + comments + shares + saves + sends) / impressions * 100. Clicks are not counted, and there is no fallback denominator, so this is 0 whenever impressions is 0. This is NOT the same formula as PostAnalytics.engagementRate on GET /v1/analytics.
- **note** `string`: No description
- **lastUpdated** `string`: No description

## LinkedInAggregateAnalyticsDailyResponse

Response for DAILY aggregation (time series breakdown)

### Properties

- **accountId** `string`: No description
- **platform** `string`: No description
- **accountType** `string`: No description
- **username** `string`: No description
- **aggregation** `string`: No description - one of: DAILY
- **dateRange** `object,null`: No description
- **analytics** `object`: Daily breakdown of each metric as date/count pairs. Reach not available with DAILY aggregation.
  - **impressions** `array`: 
  - **reactions** `array`: 
  - **comments** `array`: 
  - **shares** `array`: 
  - **saves** `array`: Daily saves (personal accounts only)
  - **sends** `array`: Daily sends via LinkedIn messaging (personal accounts only)
- **skippedMetrics** `array`: Metrics that were skipped due to API limitations
- **note** `string`: No description
- **lastUpdated** `string`: No description

---
