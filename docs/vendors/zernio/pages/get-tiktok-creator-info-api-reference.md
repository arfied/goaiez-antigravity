# Get TikTok creator info API Reference

Returns TikTok creator details, available privacy levels, posting limits, and commercial content options for a specific TikTok account. Only works with TikTok accounts.

## GET /v1/accounts/{accountId}/tiktok/creator-info

**Get TikTok creator info**

Returns TikTok creator details, available privacy levels, posting limits, and commercial content options for a specific TikTok account. Only works with TikTok accounts.

### Parameters

- **accountId** (required) in path: The TikTok account ID
- **mediaType** (optional) in query: The media type to get creator info for (affects available interaction settings)

### Responses

#### 200: TikTok creator info and posting options

**Response Body:**

- **creator** `object`: 
  - **nickname** `string`: Creator display name
  - **avatarUrl** `string`: Creator avatar URL
  - **isVerified** `boolean`: Whether the creator is verified
  - **canPostMore** `boolean`: Whether the creator can publish more posts right now
- **privacyLevels** `array[object]`: Available privacy level options for this creator
  - **value** `string`: Privacy level value to use when creating posts (e.g. PUBLIC_TO_EVERYONE, MUTUAL_FOLLOW_FRIENDS, FOLLOWER_OF_CREATOR, SELF_ONLY)
  - **label** `string`: Human-readable label
- **postingLimits** `object`: 
  - **maxVideoDurationSec** `integer`: Maximum video duration in seconds
  - **interactionSettings** `object`: Per-interaction descriptors for the comment, duet and stitch toggles. Each key matches the tiktokSettings field of the same name on the create-post request. allow_duet and allow_stitch are null when mediaType is photo, because TikTok does not apply duet or stitch to photo posts.
    - **allow_comment** `object`: Descriptor for the allow_comment toggle.
      - **enabled** `boolean`: Whether the creator permits this interaction. False means they disabled it in the TikTok app. This is availability, never the value the user selected.
      - **required** `boolean`: Whether tiktokSettings.allow_comment must be supplied when creating a post. Always true, because TikTok forbids defaulting it.
      - **default** `boolean`: Initial value a post composer should render. A UI seed only, never applied server-side when the field is omitted.
      - **label** `string`: Human-readable toggle label.
    - **allow_duet** `object,null`: Descriptor for the allow_duet toggle. Null when mediaType is photo.
    - **allow_stitch** `object,null`: Descriptor for the allow_stitch toggle. Null when mediaType is photo.
- **commercialContentTypes** `array[object]`: Available commercial content disclosure options
  - **value** `string`: No description
  - **label** `string`: No description
  - **requires** `array[string]`: 

#### 400: Account is not a TikTok account

**Response Body:**

- **error** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 429: Creator has reached TikTok daily posting limit

**Response Body:**

- **error** `string`: No description

---

---
