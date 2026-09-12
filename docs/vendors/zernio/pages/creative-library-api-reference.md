# Creative library API Reference

Lists the ad account's creative library (Meta's `/act_X/adcreatives`), rows returned
verbatim. The default projection covers id, name, status, object type, thumbnail,
object_story_spec / asset_feed_spec and url_tags; `fields` is a raw-passthrough
override. Any creative id here is reusable on the create endpoints via
`existingCreativeId`.

## GET /v1/ads/creatives

**Creative library**

Lists the ad account's creative library (Meta's `/act_X/adcreatives`), rows returned
verbatim. The default projection covers id, name, status, object type, thumbnail,
object_story_spec / asset_feed_spec and url_tags; `fields` is a raw-passthrough
override. Any creative id here is reusable on the create endpoints via
`existingCreativeId`.

### Parameters

- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.
- **adAccountId** (required) in query: Meta ad account id (act_<n>).
- **fields** (optional) in query: Comma-separated Graph field override. Supports nested {} projections and Graph field modifiers, so a nested edge can be paged explicitly: without a .limit() modifier the expansion runs at the Meta default page size and the tail is dropped silently.
- **limit** (optional) in query: Rows per page
- **after** (optional) in query: Cursor from paging.after of the previous page.

### Responses

#### 200: Creatives (raw Meta shape)

**Response Body:**

- **adAccountId** `string`: No description
- **data** `array[object]`: 
  Type: `object`
- **paging** `object`: 
  - **after** `string,null`: Cursor for the next page; null when exhausted.

#### 400: Invalid input, or Meta rejected the query

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 501: Only supported on Meta (facebook/instagram)

---

## POST /v1/ads/creatives

**Create a standalone creative**

Creates a creative in the library WITHOUT an ad, reusable on the create endpoints via
`existingCreativeId`. Provide exactly one of `imageUrl` (uploaded server-side),
`imageHash` (from POST /v1/ads/images or the library list), or `carouselCards` (2-10
hand-built cards). The Page (and linked Instagram account, when present) is resolved
from `accountId` as the story actor. `creativeFeatures` configures Advantage+
enhancements. `promotion` is not supported and any object is rejected with 400.

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token and Page.
- **adAccountId** (required) `string`: Platform ad account id (Meta act_<n>, Google customer id, LinkedIn account id, ...).
- **headline** (required) `string`: No description
- **body** (required) `string`: Primary text
- **description** `string`: Link description below the headline; omitted = Meta scrapes the destination's OG description.
- **callToAction** `string`: CTA type (same whitelist as POST /v1/ads/create).
- **linkUrl** (required) `string`: No description
- **imageUrl** `string`: Publicly reachable image; uploaded to the account's library server-side.
- **imageHash** `string`: Existing library image hash (POST /v1/ads/images or GET /v1/ads/images).
- **carouselCards** `array`: No description
- **urlTags** `string`: Appended to every outbound URL (e.g. utm_source=fb).
- **promotion**: Not supported. Meta validates creative_sourcing_spec.promotion_metadata_spec on the create call and then discards it, so a Promotion set through the Marketing API never reaches the creative. Any object is rejected with 400 invalid_field_value. Send null or omit the field, and set the Promotion on the ad in Ads Manager. Verified on 2026-09-11 across Graph v19.0 to v25.0 and every write path.
- **creativeFeatures**: Meta only. Applied to each new creative, including standalone and attach shapes. With creatives[], these are defaults; an item replaces the whole feature map, including an empty map. auto_promotion_tag is an Advantage+ enhancement, not the Ads Manager Promotion setting.
- **multiAdvertiser** `string`: Meta only. Multi-advertiser ads: whether Meta may show this ad alongside other advertisers' in one unit. Meta auto-enrols since Aug 2024, so send OPT_OUT to leave. It is a top-level creative field, NOT a `creativeFeatures` key, and Meta rejects it there. - one of: OPT_IN, OPT_OUT

### Responses

#### 201: Creative created

**Response Body:**

- **adAccountId** `string`: No description
- **creativeId** `string`: Platform creative id, reusable via existingCreativeId.

#### 400: Invalid input, or Meta rejected the create

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: The account or requested resource was not found or is not accessible. An account ID may have been disconnected and removed. Read GET /v1/accounts for current account IDs.

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

#### 409: The account exists but is inactive or needs reconnection. Reconnect it, then read GET /v1/accounts for its current account ID before retrying. Code: ads_connection_required.

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

#### 422: No Facebook Page found to act as the story actor

#### 501: Only supported on Meta (facebook/instagram)

#### 502: Meta accepted the request then failed to produce the media (upload session, chunk transfer, processing timeout, or a response with no image hash). Inspect `platformError.reason`.

---
