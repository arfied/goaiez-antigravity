# Related Schema Definitions

## AdCampaign

### Properties

- **platformCampaignId** `string`: No description
- **platform** `string`: No description - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai
- **campaignName** `string`: No description
- **status**: Delivery status derived from child ad statuses. Distinct from `reviewStatus`.
- **reviewStatus**: Platform-side review state of the campaign. See AdTreeCampaign.reviewStatus for the full description.
- **platformCampaignStatus** `string,null`: Raw platform-level campaign status (Meta `effective_status`).
- **campaignIssuesInfo** `array,null`: Platform-reported campaign issues (Meta `issues_info[]`).
- **adCount** `integer`: No description
- **budget**: Effective budget. Google metadata arrives after the next successful sync.
- **campaignBudget**: Campaign-level budget. Null for ad-set budgets.
- **budgetLevel** `string,null`: Canonical CBO/ABO indicator. See AdTreeCampaign.budgetLevel. - one of: campaign, adset
- **isBudgetScheduleEnabled** `boolean`: Meta-only. Mirrors Campaign.is_budget_schedule_enabled. (default: false)
- **currency** `string,null`: ISO 4217 currency code for all budget amounts. Budgets are NOT normalized to USD.
- **metrics**: No description
- **platformAdAccountId** `string`: No description
- **platformAdAccountName** `string,null`: Human-readable advertiser/account name from the platform. Refreshed on every sync.
- **accountId** `string`: No description
- **profileId** `string`: No description
- **advertisingChannelType** `string,null`: Google-only. Raw campaign.advertising_channel_type. See AdTreeCampaign.advertisingChannelType.
- **platformObjective** `string,null`: Raw Meta campaign objective (e.g. OUTCOME_SALES, OUTCOME_LEADS, OUTCOME_TRAFFIC)
- **optimizationGoal**: A single string when every ad set shares one optimization goal; a JSON array of the distinct goals when ad sets differ (never a comma-joined string); array element order is not guaranteed, treat it as an unordered set; the key is absent when no ad set carries a goal. Meta: e.g. OFFSITE_CONVERSIONS, VALUE, LEAD_GENERATION. LinkedIn: the campaign optimizationTargetType (e.g. MAX_CLICK, MAX_IMPRESSION, NONE); `NONE` with a manual costType is a campaign LinkedIn will not deliver.
- **bidStrategy**: Campaign-level bid strategy. Ad sets inherit this unless they override.
- **bidAmount** `number,null`: Representative bid from the top-spending ad set (whole currency units). Meta: populated when bidStrategy is LOWEST_COST_WITH_BID_CAP or COST_CAP. LinkedIn: the campaign unitCost, ungated, where 0 is a real delivery-stopping value.
- **roasAverageFloor** `number,null`: Representative ROAS floor from the top-spending ad set. Decimal multiplier (2.0 = 2.0x).
- **promotedObject** `object,null`: Meta promoted object at campaign level (conversion event details)
- **earliestAd** `string`: No description
- **latestAd** `string`: No description

## Pagination

### Properties

- **page** `integer`: No description
- **limit** `integer`: No description
- **total** `integer`: No description
- **pages** `integer`: No description

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
