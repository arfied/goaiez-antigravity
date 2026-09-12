# Related Schema Definitions

## AdTreeResponse

### Properties

- **campaigns** `array`: No description
- **pagination**: No description
- **backfillPending** `boolean`: Present and true while historical data is being backfilled.

## AdTreeCampaign

Campaign with nested ad sets and rolled-up metrics

### Properties

- **platformCampaignId** `string`: No description
- **platform** `string`: No description - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai
- **campaignName** `string`: No description
- **createdTime** `string,null`: Earliest `platformCreatedAt` (platform ad creation time; falls back to `createdAt`, Zernio's sync time, for ads synced before that field existed) across every ad in the campaign. Not the platform campaign's own creation time (Meta's `Campaign.created_time` etc. is not synced). A campaign created empty and populated later will show its first ad's time, not the campaign's. Usable for sorting "most recently created" without the numeric-campaign-id heuristic. Same source as `AdTreeAdSet.createdTime` and `Ad.platformCreatedAt`; mirrors `AdCampaign.earliestAd`.
- **status**: Delivery status derived from child ad statuses. Distinct from `reviewStatus`, which reflects the platform-side review state.
- **reviewStatus**: Platform-side review state of the campaign. Independent of the
children-derived delivery `status`: a campaign can have ads
already active (status=active) while the campaign itself is
still being reviewed by the platform (reviewStatus=in_review).
For Meta, derived from `effective_status` + `issues_info` on
the Campaign, plus ad-level PENDING_REVIEW rollup.

- **platformCampaignStatus** `string,null`: Raw platform-level campaign status (Meta `effective_status`: ACTIVE, PAUSED, DELETED, ARCHIVED, IN_PROCESS, WITH_ISSUES). Distinct from per-ad `platformStatus`.
- **campaignIssuesInfo** `array,null`: Platform-reported campaign issues (Meta `issues_info[]`). Populated only when the platform has delivery issues to report; contains the specific error codes and messages.
- **adCount** `integer`: Total ads across all ad sets
- **adSetCount** `integer`: No description
- **budget** `object,null`: Effective budget (back-compat). For CBO this mirrors `campaignBudget`, for ABO this mirrors the child ad-set budget. Use `budgetLevel` to disambiguate.
- **campaignBudget** `object,null`: Campaign-level budget (Campaign Budget Optimization / CBO). Populated only when the platform set the budget at the campaign level. For ABO campaigns this is null and the budget lives on the child ad set.
- **budgetLevel** `string,null`: Canonical CBO/ABO indicator. `campaign` = CBO (Advantage Campaign Budget, budget lives on the campaign). `adset` = ABO (budget lives on each ad set). Route budget updates to the matching Meta entity. - one of: campaign, adset
- **isBudgetScheduleEnabled** `boolean`: Meta-only. Mirrors Campaign.is_budget_schedule_enabled: true when the campaign uses budget scheduling (time-based budget changes). Independent of CBO/ABO. (default: false)
- **currency** `string,null`: ISO 4217 currency code (e.g. USD, EUR, CLP, JPY) for all budget amounts in this campaign node. Budgets are NOT normalized to USD.
- **metrics**: No description
- **platformAdAccountId** `string`: No description
- **platformAdAccountName** `string,null`: Human-readable advertiser/account name from the platform. Refreshed on every sync.
- **accountId** `string`: No description
- **profileId** `string`: No description
- **advertisingChannelType** `string,null`: Google-only. Raw campaign.advertising_channel_type (SEARCH, PERFORMANCE_MAX, LOCAL_SERVICES, VIDEO, DEMAND_GEN, DISPLAY, SHOPPING, ...). Serving surface, distinct from platformObjective (advertiser intent). Null/absent for non-Google platforms.
- **platformObjective** `string,null`: Raw Meta campaign objective (e.g. OUTCOME_SALES, OUTCOME_LEADS, OUTCOME_TRAFFIC)
- **optimizationGoal**: A single string when every ad set shares one optimization goal; a JSON array of the distinct goals when ad sets differ (never a comma-joined string); array element order is not guaranteed, treat it as an unordered set; the key is absent when no ad set carries a goal. Meta: e.g. OFFSITE_CONVERSIONS, VALUE, LEAD_GENERATION. LinkedIn: the campaign optimizationTargetType (e.g. MAX_CLICK, MAX_IMPRESSION, NONE); `NONE` with a manual costType is a campaign LinkedIn will not deliver.
- **bidStrategy**: Campaign-level bid strategy. Ad sets inherit this unless they override.
- **bidAmount** `number,null`: Representative bid for the campaign, bubbled up from the top-spending ad set (whole currency units). Meta: populated when the ad-set bidStrategy is LOWEST_COST_WITH_BID_CAP or COST_CAP. LinkedIn: the campaign unitCost, which has no bidStrategy gate and where 0 is a real, delivery-stopping value rather than unset.
- **roasAverageFloor** `number,null`: Representative ROAS floor for the campaign, bubbled up from the top-spending ad set. Decimal multiplier (2.0 = 2.0x).
- **promotedObject** `object,null`: Meta promoted object at campaign level (conversion event details)
- **adSets** `array`: No description
- **daily** `array`: Per-day metric series for this campaign. Present only when `GET /v1/ads/tree` is called with `timeIncrement=1` (any `dailyLevel`). This is the per-campaign daily trend. Summing its additive fields reproduces the campaign `metrics` total, except `reach`: on Meta the range total is de-duplicated, so daily reach does not sum to it.

## Pagination

### Properties

- **page** `integer`: No description
- **limit** `integer`: No description
- **total** `integer`: No description
- **pages** `integer`: No description

---
