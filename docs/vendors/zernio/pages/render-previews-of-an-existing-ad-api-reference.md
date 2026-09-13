# Render previews of an existing ad API Reference

Renders an EXISTING ad per placement via Meta's `/{ad_id}/previews`. Each preview is an HTML
`<iframe>` snippet embeddable directly. Unknown `formats` values return Meta's 400 verbatim.


## GET /v1/ads/{adId}/preview

**Render previews of an existing ad**

Renders an EXISTING ad per placement via Meta's `/{ad_id}/previews`. Each preview is an HTML
`<iframe>` snippet embeddable directly. Unknown `formats` values return Meta's 400 verbatim.


### Parameters

- **adId** (required) in path: Zernio ad id (24-char hex).
- **formats** (optional) in query: Comma-separated Meta ad_format values (max 10), one preview per format. Defaults to DESKTOP_FEED_STANDARD.

### Responses

#### 200: Rendered previews

**Response Body:**

- **adId** `string`: No description
- **previews** `array[object]`: 
  - **format** `string`: No description
  - **html** `string,null`: Meta's <iframe> snippet; null when Meta returned no preview for the format.

#### 400: Invalid input, or Meta rejected the ad_format; the message carries Meta's error

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Ad not found

#### 429: Meta rate limit reached

#### 501: Only supported on Meta (facebook/instagram)

---

---
