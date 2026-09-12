# Validate post content API Reference

Dry-run the full post validation pipeline without publishing. Catches issues like missing media for Instagram/TikTok/YouTube, hashtag limits, invalid thread formats, Facebook Reel requirements, and character limit violations.

Accepts the same body as POST /v1/posts. Does NOT validate accounts, process media, or track usage. Account lookups are limit-only: a twitter accountId is resolved, scoped to the caller, only to pick the 280 vs 25000 character limit. Missing, foreign, or invalid ids fall back to 280 and never error.

Returns errors for failures and warnings for near-limit content (>90% of character limit).


## POST /v1/tools/validate/post

**Validate post content**

Dry-run the full post validation pipeline without publishing. Catches issues like missing media for Instagram/TikTok/YouTube, hashtag limits, invalid thread formats, Facebook Reel requirements, and character limit violations.

Accepts the same body as POST /v1/posts. Does NOT validate accounts, process media, or track usage. Account lookups are limit-only: a twitter accountId is resolved, scoped to the caller, only to pick the 280 vs 25000 character limit. Missing, foreign, or invalid ids fall back to 280 and never error.

Returns errors for failures and warnings for near-limit content (>90% of character limit).


### Request Body

- **content** `string`: Post text content
- **platforms** (required) `array`: Target platforms (same format as POST /v1/posts)
- **mediaItems** `array`: Root media items shared across platforms

### Responses

#### 200: Validation result

**Response Body:**

*One of the following:*
  - **valid** `boolean`: No description
  - **message** `string`: No description (example: "No validation issues found.")
  - **warnings** `array[object]`: 
    - **platform** `string`: No description
    - **warning** `string`: No description
  - **valid** `boolean`: No description
  - **errors** `array[object]`: 
    - **platform** `string`: No description
    - **error** `string`: No description
  - **warnings** `array[object]`: 
    - **platform** `string`: No description
    - **warning** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
