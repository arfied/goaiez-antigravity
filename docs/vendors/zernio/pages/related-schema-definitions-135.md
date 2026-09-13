# Related Schema Definitions

## Ad

### Properties

- **_id** `string`: No description
- **name** `string`: No description
- **platform** `string`: No description - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai
- **status**: Delivery status. Derived from the platform `effective_status`, so it inherits ancestor pauses (an ACTIVE ad under a PAUSED campaign reads `paused`). For the ad's own on/off toggle use `configuredStatus`; for the review state use `reviewStatus`.
- **configuredStatus** `string,null`: The ad's own on/off toggle as configured on the platform (Meta `configured_status`: ACTIVE / PAUSED), unaffected by ancestor (ad set / campaign) pauses. Distinct from `status`, which is the ancestor-cascaded delivery status. Only present for Meta ads synced after this field was added.
- **reviewStatus**: Platform review state of this ad, independent of delivery `status` / `configuredStatus`. Absent when the platform reports no review signal.
- **adType** `string`: No description - one of: boost, standalone
- **creativeType** `string,null`: Creative format, classified from the media the creative carries. `null` when the creative carries no media to classify. An unsynced creative and a genuine text-only ad are indistinguishable, so neither is guessed at. Returned by `GET /v1/ads`, `GET /v1/ads/{adId}` and the ad nodes of `GET /v1/ads/tree`. - one of: carousel, video, document, image, 
- **goal** `string`: Available goals vary by platform. Meta (Facebook/Instagram) supports all 10 (incl. `lead_conversion` = website pixel lead optimization, `catalog_sales` = Advantage+ catalog ads and `page_likes` = Page Likes conversion location under Engagement). TikTok supports engagement, traffic, awareness, video_views, lead_generation, conversions, app_promotion. LinkedIn supports all Meta goals except app_promotion / lead_conversion / catalog_sales / page_likes. X supports engagement, traffic, awareness, video_views, app_promotion. Pinterest supports only engagement, traffic, awareness, video_views. Google Ads supports only engagement, traffic, awareness (video_views is rejected at create with 422 FEATURE_NOT_AVAILABLE). - one of: engagement, traffic, awareness, video_views, lead_generation, lead_conversion, conversions, app_promotion, catalog_sales, page_likes, job_applicants
- **isExternal** `boolean`: True for ads synced from platform ad managers
- **budget** `object,null`: No description
- **metrics**: No description
- **platformAdId** `string`: No description
- **platformAdAccountId** `string`: No description
- **platformCampaignId** `string`: No description
- **platformAdSetId** `string`: No description
- **campaignName** `string`: No description
- **adSetName** `string`: No description
- **platformObjective** `string,null`: Raw Meta campaign objective (e.g. OUTCOME_SALES, OUTCOME_LEADS, OUTCOME_TRAFFIC). Only present for Meta ads.
- **optimizationGoal** `string,null`: What the delivery system optimizes for, at ad-set level. The value space depends on `platform`:

- Meta: ad set `optimization_goal` (e.g. OFFSITE_CONVERSIONS, VALUE, LEAD_GENERATION, LINK_CLICKS).
- LinkedIn: the campaign's EFFECTIVE `optimizationTargetType`, refreshed from LinkedIn on every
  sync rather than echoing what was passed on create. `NONE` means manual bidding, and it is a
  real value, not missing data. Auto-bid values are MAX_IMPRESSION / MAX_CLICK / MAX_CONVERSION /
  MAX_VIDEO_VIEW / MAX_LEAD / MAX_REACH; target-cost values are TARGET_COST_PER_CLICK /
  TARGET_COST_PER_IMPRESSION / TARGET_COST_PER_VIDEO_VIEW; cost-cap values are the
  CAP_COST_AND_MAXIMIZE_* family.

- **costType** `string,null`: LinkedIn only. The campaign's EFFECTIVE cost model (billing event) as applied by LinkedIn,
refreshed on every sync rather than echoing what was passed on create. One of `CPM` (cost per
thousand impressions), `CPC` (cost per click) or `CPV` (cost per video view). On LinkedIn this is
the axis that pairs with `bidAmount`; there is no `bidStrategy`. For campaign type
SPONSORED_INMAILS, `CPM` bills as cost-per-send x 1000. `null` for non-LinkedIn ads.

- **servingStatuses** `array`: LinkedIn only. Why the parent campaign is (or is not) delivering, verbatim from LinkedIn.
A campaign can report `status: ACTIVE` and still serve nothing; this array is what says so.

- `[]` means no serving data: a non-LinkedIn ad, or a LinkedIn ad not yet re-synced.
- `["RUNNABLE"]` means the campaign is eligible to serve.
- Anything else is a hold. Known values include ACCOUNT_SERVING_HOLD, ACCOUNT_TOTAL_BUDGET_HOLD,
  ACCOUNT_END_DATE_HOLD, CAMPAIGN_START_DATE_HOLD, CAMPAIGN_END_DATE_HOLD,
  CAMPAIGN_TOTAL_BUDGET_HOLD, CAMPAIGN_AUDIENCE_COUNT_HOLD, CAMPAIGN_GROUP_START_DATE_HOLD,
  CAMPAIGN_GROUP_END_DATE_HOLD, CAMPAIGN_GROUP_TOTAL_BUDGET_HOLD, CAMPAIGN_GROUP_STATUS_HOLD and
  STOPPED. The list is open on purpose, so treat unrecognized values as holds rather than errors.

The end-date and total-budget holds are terminal and surface as `status: completed`; the rest
surface as `status: paused`. A hold is not the only cause of zero delivery: with
manual, target-cost or cost-cap bidding, a `bidAmount` of 0 stops delivery while
`servingStatuses` still reads `["RUNNABLE"]`. Check `costType` / `bidAmount` /
`optimizationGoal` as well.

- **platformAdAccountName** `string,null`: Human-readable advertiser/account name (Meta `AdAccount.name`, TikTok
`advertiser_name`, LinkedIn / X / Pinterest equivalents). Refreshed every
sync so platform-side renames propagate within one cycle. `null` when the
platform doesn't return a name or the sync hasn't run yet.

- **platformCreatedAt** `string,null`: Platform-reported creation timestamp (Meta `created_time`, TikTok `create_time`).
Distinct from `createdAt` which reflects when Zernio first synced the doc. To
sort or filter by "when the ad was actually created on the platform", read this field.
`null` for legacy ads synced before this field was added; aggregations fall back
to `createdAt` in that case.

- **bidStrategy**: Ad-set bid strategy (overrides campaign level on Meta). Populated for Meta and
TikTok. TikTok's native `bid_type` is normalized to the cross-platform Meta enum:
`BID_TYPE_NO_BID` -> `LOWEST_COST_WITHOUT_CAP`, `BID_TYPE_CUSTOM` ->
`LOWEST_COST_WITH_BID_CAP`, deep_bid_type=MIN_ROAS or roas_bid>0 ->
`LOWEST_COST_WITH_MIN_ROAS`, `BID_TYPE_MAX_CONVERSION` -> `LOWEST_COST_WITHOUT_CAP`.

- **bidAmount** `number,null`: Bid amount in WHOLE currency units of the ad account (USD: 5 = $5.00; JPY: 100 = ¥100).

- Meta source: `bid_amount` on the ad set (smallest-denomination int, decoded here). Populated
  when bidStrategy is `LOWEST_COST_WITH_BID_CAP` or `COST_CAP`; `null` for auto-bid
  (`LOWEST_COST_WITHOUT_CAP`).
- TikTok source: priority order `bid_price` -> `conversion_bid_price` -> `deep_cpa_bid`
  (whichever is set on the ad group). TikTok stores all three in whole currency units.
- LinkedIn source: the campaign's EFFECTIVE `unitCost`, refreshed on every sync rather than
  echoing what was passed on create. Its meaning depends on the bidding mode implied by
  `optimizationGoal`: bid amount (manual), target cost, or cost cap. It pairs with `costType`,
  NOT with `bidStrategy`, which LinkedIn does not have. A value of `0` is a real, delivery-
  stopping configuration and not "unset", so do not gate this field on `bidStrategy` for
  LinkedIn ads.

Source: facebook-business-sdk-codegen api_specs/specs/AdSet.json (`bid_amount`).

- **roasAverageFloor** `number,null`: Minimum ROAS as a decimal multiplier (2.0 = 2.0x ROAS). Populated when bidStrategy
is `LOWEST_COST_WITH_MIN_ROAS`.

- Meta source: decoded from `bid_constraints.roas_average_floor` (Meta stores as
  fixed-point int × 10000; we return the decimal).
- TikTok source: `roas_bid` on the ad group (already a decimal).

Source: facebook-business-sdk-codegen api_specs/specs/AdCampaignBidConstraint.json.

- **promotedObject** `object,null`: Meta promoted object containing conversion event details. Structure varies by objective. Only present for Meta ads.
- **creative** `object,null`: Platform-specific creative data. Fields vary by platform.
- **targeting** `object`: The ad set's targeting (age, gender, geo, interests, placements, audience inclusions/exclusions).
For ads created through Zernio this is the spec you supplied. For external ads (synced from
Meta Ads Manager, `isExternal: true`) targeting lives at the ad set and isn't stored at ingest,
so on the first `GET /v1/ads/{adId}` Zernio resolves it live from Meta and caches it on the ad;
the value is then Meta's raw `targeting` shape (snake_case, e.g. `geo_locations`, `age_min`),
the same object Ads Manager shows. May be absent if the ad set exposes no targeting or the lookup fails.

- **schedule** `object,null`: No description
- **rejectionReason** `string`: No description
- **createdAt** `string`: No description
- **updatedAt** `string`: No description

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
