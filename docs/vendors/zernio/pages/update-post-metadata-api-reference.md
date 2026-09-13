# Update post metadata API Reference

Updates metadata of a published video on the specified platform without re-uploading.
Currently only supported for YouTube. At least one updatable field is required.

Two modes:

1. Post-based (video published through Zernio): pass the Zernio postId in the URL and platform in the body.
2. Direct video ID (video uploaded outside Zernio, e.g. directly to YouTube): use _ as the postId,
   and pass videoId + accountId + platform in the body. The accountId is the Zernio account ID
   for the connected YouTube channel.


## POST /v1/posts/{postId}/update-metadata

**Update post metadata**

Updates metadata of a published video on the specified platform without re-uploading.
Currently only supported for YouTube. At least one updatable field is required.

Two modes:

1. Post-based (video published through Zernio): pass the Zernio postId in the URL and platform in the body.
2. Direct video ID (video uploaded outside Zernio, e.g. directly to YouTube): use _ as the postId,
   and pass videoId + accountId + platform in the body. The accountId is the Zernio account ID
   for the connected YouTube channel.


### Parameters

- **postId** (required) in path: Zernio post ID, or "_" when using direct video ID mode

### Request Body

- **platform** (required) `string`: The platform to update metadata on - one of: youtube
- **videoId** `string`: YouTube video ID (required for direct mode, ignored for post-based mode)
- **accountId** `string`: Zernio account ID (required for direct mode, ignored for post-based mode)
- **title** `string`: New video title (max 100 characters for YouTube)
- **description** `string`: New video description
- **tags** `array`: Array of keyword tags (max 500 characters combined for YouTube)
- **categoryId** `string`: YouTube video category ID
- **privacyStatus** `string`: Video privacy setting - one of: public, private, unlisted
- **thumbnailUrl** `string`: Public URL of a custom thumbnail image (JPEG, PNG, or GIF, max 2 MB, recommended 1280x720). Works on any video you own, including existing videos not published through Zernio. The channel must be verified (phone verification) to set custom thumbnails.
- **madeForKids** `boolean`: COPPA compliance flag. Set true for child-directed content (restricts comments, notifications, ad targeting).
- **containsSyntheticMedia** `boolean`: AI-generated content disclosure. Set true if the video contains synthetic content that could be mistaken for real. YouTube may add a label.
- **playlistId** `string`: YouTube playlist ID to add the video to (e.g. 'PLxxxxxxxxxxxxx'). Use GET /v1/accounts/{id}/youtube-playlists to list available playlists. Only playlists owned by the channel are supported.

### Responses

#### 200: Metadata updated successfully

**Response Body:**

- **success** `boolean`: No description
- **message** `string`: No description
- **videoId** `string`: Only present in direct video ID mode
- **updatedFields** `array[string]`: 

#### 400: Invalid request: unsupported platform, post not published, missing fields, or validation error.

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

#### 403: Forbidden

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

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 500: Platform API update failed

---
