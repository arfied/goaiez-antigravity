# Direct video and image URLs for an ad API Reference

Returns the direct signed URLs for every video and image asset used by an ad's live
creative, normalised across shapes: single image/video, carousel,
Reels/Story (`object_story_spec.video_data`) and dynamic
creative (`asset_feed_spec`). Video items include Meta's poster thumbnail and the
video's Meta id when available.

Reads Meta live rather than the stored creative blob because Meta's signed fbcdn
URLs carry an `oe=<hex>` expiration (image_url ~24 h, video source ~12 d). Treat
URLs as short-lived: re-fetch this endpoint before serving or downloading assets
instead of caching URLs beyond that window.

## GET /v1/ads/{adId}/media

**Direct video and image URLs for an ad**

Returns the direct signed URLs for every video and image asset used by an ad's live
creative, normalised across shapes: single image/video, carousel,
Reels/Story (`object_story_spec.video_data`) and dynamic
creative (`asset_feed_spec`). Video items include Meta's poster thumbnail and the
video's Meta id when available.

Reads Meta live rather than the stored creative blob because Meta's signed fbcdn
URLs carry an `oe=<hex>` expiration (image_url ~24 h, video source ~12 d). Treat
URLs as short-lived: re-fetch this endpoint before serving or downloading assets
instead of caching URLs beyond that window.

### Parameters

- **adId** (required) in path: Zernio ad id (24-char hex) or platform ad id.

### Responses

#### 200: Media assets

**Response Body:**

- **adId** `string`: No description
- **platform** `string`: 'facebook' or 'instagram'. Only Meta is supported for now.
- **media** `array[object]`: 
  - **type** `string`: No description - one of: image, video
  - **url** `string`: Direct file URL (signed; short-lived, see description).
  - **thumbnailUrl** `string`: Video poster URL (videos only).
  - **videoId** `string`: Meta video id (videos only), reusable as video.id on the create endpoints.
  - **length** `number`: Video length in seconds (videos only).
  - **index** `integer`: 0-based position for carousel children or asset_feed_spec entries.

#### 400: Invalid input

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Ad not found

#### 422: No active Meta connection for this ad. Reconnect the account.

#### 501: Only supported on Meta (facebook/instagram)

---

---
