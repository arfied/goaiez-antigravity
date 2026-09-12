# Related Schema Definitions

## CampaignBidding

A Google campaign's current bidding, mapped onto the same triplet PUT /v1/ads/campaigns/{campaignId} accepts.

### Properties

- **channel** `string`: campaign.advertising_channel_type. COST_CAP's underlying Google field differs by channel; see bidStrategy on PUT. - one of: SEARCH, DISPLAY
- **biddingStrategyType** `string`: Google's raw enum: MAXIMIZE_CONVERSIONS, TARGET_CPA, MAXIMIZE_CONVERSION_VALUE, TARGET_ROAS, TARGET_SPEND, MANUAL_CPC, TARGET_IMPRESSION_SHARE, or another Google adds later.
- **bidSpec** `object,null`: Null when the campaign is on a strategy PUT does not model (Manual CPC, Target Impression Share, ...); show biddingStrategyType instead in that case.
- **portfolio** `object,null`: Set only when the campaign is on a portfolio bid strategy (campaign.bidding_strategy); null otherwise.
- **cachedAt** `string,null`: When this data was fetched from Google. Null when it was never served from cache.
- **stale** `boolean`: True when Google's daily API quota was exhausted and this is the last successful fetch, not a live read.

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
