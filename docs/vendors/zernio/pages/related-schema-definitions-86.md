# Related Schema Definitions

## AdAnalyticsResponse

### Properties

- **backfillPending** `boolean`: Present and true while historical data is being backfilled.
- **ad** `object`: 
  - **id** `string`: 
  - **name** `string`: 
  - **platform** `string`: 
  - **status** `string`: 
  - **currency** `string,null`: ISO 4217 code of the ad account that owns this ad (e.g. USD, THB, INR). All money values in `summary` and `daily` are in this currency. Null only on legacy ads synced before currency was persisted.
- **analytics** `object`: 
  - **summary**: 
  - **daily** `array`: 
  - **breakdowns** `object`: 

## AdMetrics

### Properties

- **spend** `number`: No description
- **impressions** `integer`: No description
- **reach** `integer`: Unique people reached in the requested date range. Meta (facebook/instagram) and TikTok: the platform's own de-duplicated reach for the exact range, fetched live and cached up to ~1 hour (may lag recent delivery; on a transient platform error the value temporarily falls back to a sum of per-day reach, which overcounts people reached on multiple days or by multiple child ads). Because it is de-duplicated, reach is NOT additive on these platforms: neither daily values nor child nodes sum to the range total. Google, LinkedIn, X, Pinterest and OpenAI report 0 (reach not synced). Frequency (impressions / reach) is only meaningful for Meta and TikTok.
- **clicks** `integer`: No description
- **ctr** `number`: Click-through rate (%)
- **cpc** `number`: Cost per click
- **cpm** `number`: Cost per 1000 impressions
- **engagement** `integer`: No description
- **conversions** `number`: Count of conversion events over the requested date range. FRACTIONAL: attribution splits one conversion across touchpoints and Google additionally reports modeled conversions, so values like 0.347 are normal. Meta: events matching the campaign's promoted_object.custom_event_type (PURCHASE, LEAD, etc.). Google: the account's tracked conversions. X and LinkedIn: their reported website/lead conversions (added 2026-07). 0 for non-conversion campaigns or when no events have fired.
- **allConversions** `number`: All conversions, including actions excluded from the Conversions column (Google metrics.all_conversions). 0 on platforms without the concept.
- **costPerConversion** `number`: Derived spend / conversions in the same currency as spend. 0 when conversions is 0.
- **actions** `object`: Per-action-type counts summed over the date range, keyed by the platform's action-type names. Meta: raw Insights action_type keys (link_click, offsite_conversion.fb_pixel_purchase, onsite_conversion.lead_grouped, ...), covering both engagement and conversion events. TikTok: pixel conversions (purchase, add_to_cart, initiate_checkout, view_content, complete_payment, lead) plus the paid-engagement family (follow, post_reaction for paid likes, comment, share). Follow is how FOLLOWERS-goal campaigns report their result. X: conversion types (purchase, sign_up, site_visit, download, custom). LinkedIn: conversion types (post_click, post_view, lead_gen). Google returns {} (its per-action names aren't synced per ad). Empty object when no actions are reported. NOTE: keys differ by platform, so branch on the ad's platform when interpreting them.
- **actionValues** `object`: Monetary mirror of `actions`, from Meta's Insights `action_values[]` array. Same keying: values are the revenue attributed to each action_type, in ad-account native currency (same unit as `spend`; see the campaign node's `currency` field). Use this to compute revenue-per-event (e.g. avg purchase value). Meta-only; other platforms return {}.
- **purchaseValue** `number`: Convenience sum of purchase-type action values, picked from `actionValues` via the same priority list as `conversions` so both fields describe the same events. In ad-account native currency. 0 when the campaign has no purchase event configured. Meta-only.
- **roas** `number`: Return on ad spend, derived as `purchaseValue / spend`. 0 when `spend` is 0. Equivalent to Meta's `purchase_roas` under default attribution. At ad-set and campaign levels this is recomputed from summed purchaseValue + spend (NOT averaged across children) so it's mathematically correct at every rollup level.
- **costPerAction** `object`: Derived `spend / actions[type]` for every action type with a non-zero count, in ad-account native currency. Same keys as `actions`. Rounded to 4 decimals because cheap actions cost well under a cent. Recomputed from summed spend + counts at every rollup level. Empty object when spend is 0 or no actions are reported.
- **outboundClicks** `integer`: Clicks leading off Meta's surfaces to the advertiser's destination. Meta-only; other platforms report 0.
- **outboundClicksCtr** `number`: Derived `outboundClicks / impressions * 100`, recomputed from sums at every rollup level.
- **inlineLinkClicks** `integer`: In-session link clicks. Differs from the attributed `link_click` count in `actions`/`engagementBreakdown.linkClicks`, which uses the attribution window. Meta-only.
- **inlineLinkClickCtr** `number`: Derived `inlineLinkClicks / impressions * 100`, recomputed from sums at every rollup level.
- **uniqueClicks** `integer`: People who clicked at least once. NOT additive: summed across days/children it overcounts people who clicked on multiple days or ads, so treat rollups as an upper bound (same caveat as `reach`). Meta-only.
- **uniqueCtr** `number`: Derived `uniqueClicks / impressions * 100` (NOT Meta's reach-based unique_ctr). Inherits the non-additivity caveat of `uniqueClicks`.
- **videoPlayActions** `integer`: Number of times the video started playing, summed over the date range and across children at ad-set/campaign level. 0 for non-video ads. Sources: Meta `video_play_actions`, TikTok `video_play_actions`.
- **video30SecWatchedActions** `integer`: Views of at least 30 seconds (or to the end, for shorter videos). Sources: Meta `video_30_sec_watched_actions` (Meta only).
- **videoThruplayWatchedActions** `integer`: ThruPlays (watched to completion, or at least 15 seconds). Sources: Meta `video_thruplay_watched_actions` (Meta only).
- **videoP25WatchedActions** `integer`: Views reaching 25% of the video's length. With the other percentile fields, powers hook/hold/drop-off analysis (e.g. hook rate = videoP25WatchedActions / videoPlayActions). Sources: Meta `video_p25_watched_actions`, TikTok `video_views_p25`.
- **videoP50WatchedActions** `integer`: Views reaching 50% of the video's length. Sources: Meta `video_p50_watched_actions`, TikTok `video_views_p50`.
- **videoP75WatchedActions** `integer`: Views reaching 75% of the video's length. Sources: Meta `video_p75_watched_actions`, TikTok `video_views_p75`.
- **videoP95WatchedActions** `integer`: Views reaching 95% of the video's length. Sources: Meta `video_p95_watched_actions` (Meta only).
- **videoP100WatchedActions** `integer`: Views reaching 100% of the video's length. Sources: Meta `video_p100_watched_actions`, TikTok `video_views_p100`.
- **videoAvgTimeWatchedActions** `number`: Average seconds watched per play. Aggregated over date ranges and across children as a play-weighted average (total watch time / total plays), never a plain average of averages. Sources: Meta `video_avg_time_watched_actions`, TikTok `average_video_play`.
- **costPerThruplay** `number`: Derived `spend / videoThruplayWatchedActions`, in ad-account native currency. Rounded to 4 decimals rather than the usual 2 because a ThruPlay routinely costs well under a cent. 0 when the ad has no ThruPlays (ThruPlay is Meta-only).
- **funnel**: No description
- **engagementBreakdown**: No description
- **lastSyncedAt** `string`: Present on individual ads only, not on campaign aggregations

## ErrorResponse

Canonical error envelope. `error` is the human-readable message; `type`,
`code`, `param`, `platform`, and `platformError` are top-level siblings
for programmatic handling. For upstream platform failures (`type:
platform_error`), `platformError` carries the provider's raw payload
verbatim (for Meta: `error_subcode`, `error_user_title`, `error_user_msg`).


### Properties

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

---
