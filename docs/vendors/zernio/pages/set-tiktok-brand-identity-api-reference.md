# Set TikTok brand identity API Reference

Set or update the Brand Identity (display name + avatar) for a
`tiktokads` SocialAccount. TikTok requires every ad to carry an
`identity_id + identity_type` pair. The Brand Identity is the
CUSTOMIZED_USER alternative to attributing ads to a real @username
(TT_USER). This route uploads the supplied image to TikTok, creates
the identity via `/v2/identity/create/`, and caches the resulting
`identity_id` on the account so subsequent `POST /v1/ads/create`
calls can opt into it via `identityType: 'CUSTOMIZED_USER'`.

Configurable on every `tiktokads` account, including linked-mode ones
(those with a posting account on the same profile). Configuration is
idempotent and harmless when posting is also connected: the default
ad-create path still prefers TT_USER, and CUSTOMIZED_USER is only used
per-ad when the caller explicitly opts in.

TikTok identities are immutable post-creation. Re-saving creates a new
identity on TikTok and swaps the cached id; the old identity stays
orphaned on TikTok's side (harmless, no billing impact).

Alternative: pass `brandIdentity` directly on `POST /v1/ads/create` to
configure on first ad creation in a single round-trip.


## PATCH /v1/connect/tiktok-ads

**Set TikTok brand identity**

Set or update the Brand Identity (display name + avatar) for a
`tiktokads` SocialAccount. TikTok requires every ad to carry an
`identity_id + identity_type` pair. The Brand Identity is the
CUSTOMIZED_USER alternative to attributing ads to a real @username
(TT_USER). This route uploads the supplied image to TikTok, creates
the identity via `/v2/identity/create/`, and caches the resulting
`identity_id` on the account so subsequent `POST /v1/ads/create`
calls can opt into it via `identityType: 'CUSTOMIZED_USER'`.

Configurable on every `tiktokads` account, including linked-mode ones
(those with a posting account on the same profile). Configuration is
idempotent and harmless when posting is also connected: the default
ad-create path still prefers TT_USER, and CUSTOMIZED_USER is only used
per-ad when the caller explicitly opts in.

TikTok identities are immutable post-creation. Re-saving creates a new
identity on TikTok and swaps the cached id; the old identity stays
orphaned on TikTok's side (harmless, no billing impact).

Alternative: pass `brandIdentity` directly on `POST /v1/ads/create` to
configure on first ad creation in a single round-trip.


### Request Body

- **accountId** (required) `string`: SocialAccount ID of the `tiktokads` account.
- **displayName** (required) `string`: Brand name shown above the ad on TikTok.
- **imageUrl** (required) `string`: Public URL of a square brand image (≥98×98 px, JPG/PNG, max 5 MB). Used as the brand avatar on the ad.

### Responses

#### 200: Brand identity configured (or updated)

**Response Body:**

- **success** `boolean`: No description (example: true)
- **identityId** `string`: The TikTok-assigned identity_id, cached on the account.
- **displayName** `string`: No description

#### 400: Missing fields, invalid JSON body, invalid accountId format, invalid lengths, or no advertiser found on the account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: TikTok Ads account not found

#### 500: Unexpected server error while caching the identity

#### 502: TikTok rejected the image upload or the identity creation (type: platform_error; an upstream 4xx status is forwarded instead of 502)

---

---
