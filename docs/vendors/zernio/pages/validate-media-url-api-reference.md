# Validate media URL API Reference

Check if a media URL is accessible and return metadata (content type, file size) plus per-platform size limit comparisons.

Performs a HEAD request (with GET fallback) to detect content type and size. Rejects private/localhost URLs for SSRF protection.

Platform limits are sourced from each platform's actual upload constraints.


## POST /v1/tools/validate/media

**Validate media URL**

Check if a media URL is accessible and return metadata (content type, file size) plus per-platform size limit comparisons.

Performs a HEAD request (with GET fallback) to detect content type and size. Rejects private/localhost URLs for SSRF protection.

Platform limits are sourced from each platform's actual upload constraints.


### Request Body

- **url** (required) `string`: Public media URL to validate

### Responses

#### 200: Media validation result

**Response Body:**

- **valid** `boolean`: No description
- **url** `string` (uri): No description
- **error** `string`: Error message if valid is false
- **contentType** `string`: No description (example: "image/jpeg")
- **size** `integer,null`: File size in bytes
- **sizeFormatted** `string`: No description (example: "245 KB")
- **type** `string`: No description - one of: image, video, unknown
- **platformLimits** `object`: Per-platform size limit comparison (only present when size and type are known) (example: {"instagram":{"limit":8388608,"limitFormatted":"8.0 MB","withinLimit":true},"twitter":{"limit":5242880,"limitFormatted":"5.0 MB","withinLimit":true},"bluesky":{"limit":1000000,"limitFormatted":"977 KB","withinLimit":true}})

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
