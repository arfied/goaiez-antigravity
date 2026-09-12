# List Performance Max asset groups API Reference

Read Performance Max asset groups and their linked text, image and YouTube assets. campaignId is the platform campaign id returned by creation or the campaign list. The campaign must be visible to the caller. Uses a 10-minute cache, with the last successful response served as stale when Google quota is exhausted. Removed groups and asset links are excluded. Campaign-level brand assets on campaigns with brand guidelines enabled are not included.

## GET /v1/ads/campaigns/{campaignId}/asset-groups

**List Performance Max asset groups**

Read Performance Max asset groups and their linked text, image and YouTube assets. campaignId is the platform campaign id returned by creation or the campaign list. The campaign must be visible to the caller. Uses a 10-minute cache, with the last successful response served as stale when Google quota is exhausted. Removed groups and asset links are excluded. Campaign-level brand assets on campaigns with brand guidelines enabled are not included.

### Parameters

- **campaignId** (required) in path: Google Ads campaign id.

### Responses

#### 200: Asset groups and linked assets.

**Response Body:**

- **assetGroups** (required) `array[GooglePmaxAssetGroup]`: 
- **cachedAt** (required) `string,null` (date-time): No description
- **stale** (required) `boolean`: No description

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 429: Google quota or operation budget exhausted with no cached response.

#### 501: Campaign is not on Google Ads.

---
