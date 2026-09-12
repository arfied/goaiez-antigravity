# Get daily account metrics API Reference

Returns daily aggregate metrics across all ads in a SocialAccount as a single
time series, one row per calendar day in the requested range. Use this for
dashboards that draw a daily-spend or daily-conversions chart, instead of
calling `/v1/ads/tree` once per day.

`accountId` is required. The lookup is sibling-expanded so passing the `metaads`
ID also includes ads under the linked `facebook` / `instagram` posting account
(and vice-versa), the same convention as `/v1/ads/tree` and `/v1/ads`.

Date range defaults to the last 90 days. Capped at 730 days. Ranges older
than the ingested history return a `202` immediately with the covered part
and `backfillPending: true` while the rest is backfilled in the background;
repeat the request shortly until it returns 200 with full data.

With adAccountId set to a Google customer id this is the customer-level performance report (clicks, cost, impressions, conversions, all conversions per day).


## GET /v1/ads/timeline

**Get daily account metrics**

Returns daily aggregate metrics across all ads in a SocialAccount as a single
time series, one row per calendar day in the requested range. Use this for
dashboards that draw a daily-spend or daily-conversions chart, instead of
calling `/v1/ads/tree` once per day.

`accountId` is required. The lookup is sibling-expanded so passing the `metaads`
ID also includes ads under the linked `facebook` / `instagram` posting account
(and vice-versa), the same convention as `/v1/ads/tree` and `/v1/ads`.

Date range defaults to the last 90 days. Capped at 730 days. Ranges older
than the ingested history return a `202` immediately with the covered part
and `backfillPending: true` while the rest is backfilled in the background;
repeat the request shortly until it returns 200 with full data.

With adAccountId set to a Google customer id this is the customer-level performance report (clicks, cost, impressions, conversions, all conversions per day).


### Parameters

- **accountId** (required) in query: Account ID. Sibling-expanded to its linked posting↔ads pair.
- **adAccountId** (optional) in query: Optional platform-native ad account ID (e.g. Meta `act_…`, TikTok advertiser ID). Use when the connection wraps multiple platform ad accounts and the chart should show one only. Note: rows ingested before 2026-05-13 don't carry this column; the recurring 7-day re-sync repopulates them naturally.
- **fromDate** (optional) in query: Inclusive start of metrics range (YYYY-MM-DD). Defaults to 90 days ago.
- **toDate** (optional) in query: Inclusive end of metrics range (YYYY-MM-DD). Defaults to today. Max 730-day range.
- **platform** (optional) in query: Restrict to one platform.

### Responses

#### 200: Daily time series of aggregate metrics. Empty `rows` means the account has no ad activity in the range.

**Response Body:**

- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **rows** `array[object]`: 
  - **date** `string` (date): No description
  - **spend** `number`: Native currency units (matches /ads/tree convention).
  - **impressions** `integer`: No description
  - **reach** `integer`: Reach summed across the account's ads for this single day. A person seen by two ads the same day counts twice, and reach is de-duplicated per day only: do NOT sum it across days (people reached on multiple days would be double-counted).
  - **clicks** `integer`: No description
  - **engagement** `integer`: No description
  - **ctr** `number`: Click-through rate as a percentage (0 to 100).
  - **cpc** `number`: Cost per click in native currency.
  - **cpm** `number`: Cost per 1000 impressions in native currency.
  - **conversions** `number`: Sum of conversion events over the range. Fractional values are normal (attribution splitting + Google modeled conversions). Meta: events matching the campaign optimization goal. Google: tracked conversions. X / LinkedIn: reported website/lead conversions (added 2026-07).
  - **allConversions** `number`: All conversions, including actions excluded from the Conversions column (Google metrics.all_conversions). 0 on platforms without the concept.
  - **costPerConversion** `number`: No description
  - **actions** `object`: Per-action-type counts merged across all ads on this day. Keys are platform-native action types.
  - **actionValues** `object`: Monetary mirror of `actions` in native currency.
  - **purchaseValue** `number`: Sum of purchase-type action values on this day, native currency.
  - **roas** `number`: Derived purchaseValue / spend.

#### 202: Historical data is incomplete and backfill remains pending.

**Response Body:**

- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **rows** `array[object]`: 
  - **date** `string` (date): No description
  - **spend** `number`: Native currency units (matches /ads/tree convention).
  - **impressions** `integer`: No description
  - **reach** `integer`: Reach summed across the account's ads for this single day. A person seen by two ads the same day counts twice, and reach is de-duplicated per day only: do NOT sum it across days (people reached on multiple days would be double-counted).
  - **clicks** `integer`: No description
  - **engagement** `integer`: No description
  - **ctr** `number`: Click-through rate as a percentage (0 to 100).
  - **cpc** `number`: Cost per click in native currency.
  - **cpm** `number`: Cost per 1000 impressions in native currency.
  - **conversions** `number`: Sum of conversion events over the range. Fractional values are normal (attribution splitting + Google modeled conversions). Meta: events matching the campaign optimization goal. Google: tracked conversions. X / LinkedIn: reported website/lead conversions (added 2026-07).
  - **allConversions** `number`: All conversions, including actions excluded from the Conversions column (Google metrics.all_conversions). 0 on platforms without the concept.
  - **costPerConversion** `number`: No description
  - **actions** `object`: Per-action-type counts merged across all ads on this day. Keys are platform-native action types.
  - **actionValues** `object`: Monetary mirror of `actions` in native currency.
  - **purchaseValue** `number`: Sum of purchase-type action values on this day, native currency.
  - **roas** `number`: Derived purchaseValue / spend.
- **backfillPending** (required) `boolean`: Always true on this response. Part of the requested range is still being backfilled; retry until the request returns 200.

#### 400: Invalid request

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

---
