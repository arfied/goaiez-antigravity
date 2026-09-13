# Ad image library API Reference

Lists the ad account's image library (Meta's `/act_X/adimages`), rows returned verbatim.
The default projection covers hash, url, name, dimensions and status; `fields` is a
raw-passthrough override. Any `hash` here is reusable wherever Meta accepts
`image_hash` (e.g. `imageHash` on POST /v1/ads/creatives).

## POST /v1/ads/images

**Upload an ad image from base64**

Uploads raw image bytes to the Meta ad account's image library, for callers whose
creatives aren't hosted at a public URL. Returns the image `hash` (Meta's identifier for
the asset) and the Meta-hosted `url`, which can be used directly as `imageUrl` on the
create endpoints. Max 30 MB decoded.

### Request Body

- **accountId** (required) `string`: Zernio SocialAccount id (posting or ads variant); its platform decides where the campaign is created.
- **adAccountId** (required) `string`: Platform ad account id (Meta act_<n>, Google customer id, LinkedIn account id, ...).
- **imageBase64** (required) `string`: Raw base64 image bytes, or a full data URL (the data:image/...;base64, prefix is stripped).
- **filename** `string`: Optional filename shown in Meta's image library. Defaults to ad_image.jpg.

### Responses

#### 201: Image uploaded

**Response Body:**

- **adAccountId** `string`: No description
- **image** `object`: 
  - **hash** `string`: Meta image hash, reusable wherever image_hash is accepted.
  - **url** `string`: Meta-hosted image URL; usable as imageUrl on the create endpoints.

#### 400: Invalid input, or Meta rejected the image

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

#### 502: Meta accepted the request then failed to produce the media (upload session, chunk transfer, processing timeout, or a response with no image hash). Inspect `platformError.reason`.

---

## GET /v1/ads/images

**Ad image library**

Lists the ad account's image library (Meta's `/act_X/adimages`), rows returned verbatim.
The default projection covers hash, url, name, dimensions and status; `fields` is a
raw-passthrough override. Any `hash` here is reusable wherever Meta accepts
`image_hash` (e.g. `imageHash` on POST /v1/ads/creatives).

### Parameters

- **accountId** (required) in query: Zernio SocialAccount id (posting or ads variant) used to resolve the Meta token.
- **adAccountId** (required) in query: Meta ad account id (act_<n>).
- **fields** (optional) in query: Comma-separated Graph field override. Supports nested {} projections and Graph field modifiers, so a nested edge can be paged explicitly: without a .limit() modifier the expansion runs at the Meta default page size and the tail is dropped silently.
- **limit** (optional) in query: Rows per page
- **after** (optional) in query: Cursor from paging.after of the previous page.

### Responses

#### 200: Ad images (raw Meta shape)

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
