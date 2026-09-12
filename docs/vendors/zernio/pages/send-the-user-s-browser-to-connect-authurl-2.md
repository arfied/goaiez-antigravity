# Send the user's browser to connect["authUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/snapchat?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://myapp.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://accounts.snapchat.com/accounts/oauth2/auth?client_id=...",
  "state": "..."
}
```

In standard mode the user picks a Public Profile on Zernio's screen and lands on your `redirect_url` with the connection details appended.

### OAuth scopes

The consent screen asks for 1 scope:

| Scope | What it enables |
|-------|-----------------|
| `snapchat-profile-api` | Manage the connected Public Profile: publish to Spotlight and Stories, read profile data and analytics |

### Headless mode

Add `headless=true` to the connect call to show your own Public Profile picker. After OAuth the user lands on your `redirect_url` with these query parameters:

- `tempToken`: temporary Snapchat access token
- `userProfile`: URL-encoded JSON with the user's info
- `publicProfiles`: URL-encoded JSON array of the available Public Profiles
- `connect_token`: short-lived token that authenticates the 2 calls below
- `platform=snapchat` and `step=select_public_profile`

List the Public Profiles with [List Snapchat profiles](/connect/list-snapchat-profiles), passing `connect_token` in the `X-Connect-Token` header:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: profiles } = await zernio.connect.snapchat.listSnapchatProfiles({
  headers: { 'X-Connect-Token': connectToken },
  query: { profileId: '66a1f0c2a4b9d3e8f1a2b3c4', tempToken }
});

console.log(profiles.publicProfiles);
```
</Tab>
<Tab value="Python">
```python
profiles = client.connect.list_snapchat_profiles(
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    temp_token=temp_token,
    x_connect_token=connect_token
)

print(profiles["publicProfiles"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/snapchat/select-profile?profileId=66a1f0c2a4b9d3e8f1a2b3c4&tempToken=$TEMP_TOKEN" \
  -H "X-Connect-Token: $CONNECT_TOKEN"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "publicProfiles": [
    {
      "id": "abc123-def456",
      "display_name": "My Brand",
      "username": "mybrand",
      "profile_image_url": "https://cf-st.sc-cdn.net/...",
      "subscriber_count": 15000
    },
    {
      "id": "xyz789-uvw012",
      "display_name": "Side Project",
      "username": "sideproject",
      "profile_image_url": "https://cf-st.sc-cdn.net/...",
      "subscriber_count": 5000
    }
  ]
}
```

Connect the chosen profile with [Select Snapchat profile](/connect/select-snapchat-profile):

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: selected } = await zernio.connect.snapchat.selectSnapchatProfile({
  headers: { 'X-Connect-Token': connectToken },
  body: {
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4',
    selectedPublicProfile: {
      id: 'abc123-def456',
      display_name: 'My Brand',
      username: 'mybrand'
    },
    tempToken,
    userProfile
  }
});

console.log(selected.account.accountId);
```
</Tab>
<Tab value="Python">
```python
selected = client.connect.select_snapchat_profile(
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    selected_public_profile={
        "id": "abc123-def456",
        "display_name": "My Brand",
        "username": "mybrand"
    },
    temp_token=temp_token,
    user_profile=user_profile,
    x_connect_token=connect_token
)

print(selected["account"]["accountId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/connect/snapchat/select-profile \
  -H "X-Connect-Token: $CONNECT_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "selectedPublicProfile": {
      "id": "abc123-def456",
      "display_name": "My Brand",
      "username": "mybrand"
    },
    "tempToken": "'"$TEMP_TOKEN"'",
    "userProfile": {
      "id": "user123",
      "username": "mybrand",
      "displayName": "My Brand"
    }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "message": "Snapchat connected successfully with public profile",
  "account": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "snapchat",
    "username": "mybrand",
    "displayName": "My Brand",
    "profilePicture": "https://cf-st.sc-cdn.net/...",
    "isActive": true,
    "publicProfileName": "My Brand"
  }
}
```

`account.accountId` is the `accountId` for every call below.

## Publish

A plain post becomes a Story: `contentType` defaults to `"story"`. Set it to `"saved_story"` or `"spotlight"` for the other 2 types.

| `contentType` | What it publishes | Lifetime | Text |
|------|-------------|----------|--------------|
| `story` | A snap in the profile's Story | 24 hours | No caption |
| `saved_story` | A permanent story on the Public Profile | Permanent | `content` is the title, max 45 characters |
| `spotlight` | A video in Snapchat's Spotlight feed | Permanent | `content` is the description, max 160 characters, hashtags allowed |

### Story

A Story is visible for 24 hours and carries no caption, so `content` is not used:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: published } = await zernio.posts.createPost({
  body: {
    mediaItems: [
      { type: 'video', url: 'https://cdn.example.com/backstage.mp4' }
    ],
    platforms: [
      {
        platform: 'snapchat',
        accountId: '66b2e19d8c3f5a7e9d0b1c2d',
        platformSpecificData: { contentType: 'story' }
      }
    ],
    publishNow: true
  }
});

console.log(published.post.status);
```
</Tab>
<Tab value="Python">
```python
published = client.posts.create_post(
    media_items=[
        {"type": "video", "url": "https://cdn.example.com/backstage.mp4"}
    ],
    platforms=[
        {
            "platform": "snapchat",
            "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
            "platformSpecificData": {"contentType": "story"}
        }
    ],
    publish_now=True
)

print(published["post"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "mediaItems": [
      {"type": "video", "url": "https://cdn.example.com/backstage.mp4"}
    ],
    "platforms": [
      {
        "platform": "snapchat",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {"contentType": "story"}
      }
    ],
    "publishNow": true
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "platforms": [
      {
        "platform": "snapchat",
        "status": "published"
      }
    ]
  }
}
```

Every sample below changes only the `content` or the `platforms` entry of this request. An image works the same way with `{ "type": "image", "url": "https://cdn.example.com/backstage.jpg" }`.

### Saved Story

A Saved Story stays on the Public Profile. `content` becomes its title, at most 45 characters:

```json
{
  "content": "Behind the scenes",
  "platforms": [
    {
      "platform": "snapchat",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": { "contentType": "saved_story" }
    }
  ]
}
```

### Spotlight

Spotlight is Snapchat's public video feed and takes video only. `content` becomes the description, at most 160 characters including hashtags:

```json
{
  "content": "Sunset over the pier #sunset #nature",
  "platforms": [
    {
      "platform": "snapchat",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": { "contentType": "spotlight" }
    }
  ]
}
```

## Platform fields

All fields go in `platformSpecificData` on the Snapchat entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `contentType` | `"story"`, `"saved_story"`, `"spotlight"` | `"story"` | Where the media publishes. See [Publish](#publish). |

## Media requirements

Every post needs exactly 1 media item; a video outside these limits is rejected rather than compressed.

### Images

| Property | Requirement |
|----------|-------------|
| Formats | JPEG, PNG |
| Max file size | 20 MB |
| Recommended dimensions | 1080 x 1920 px |
| Aspect ratio | 9:16 (portrait) |

### Videos

| Property | Requirement |
|----------|-------------|
| Format | MP4 |
| Max file size | 500 MB |
| Duration | 5 to 60 seconds |
| Min resolution | 540 x 960 px |
| Recommended dimensions | 1080 x 1920 px |
| Aspect ratio | 9:16 (portrait) |

Zernio encrypts the file with AES-256-CBC before uploading it to Snapchat. Upload files through the [media endpoint](/guides/media-uploads) to get a URL that qualifies.

## Analytics

Call `GET /v1/analytics?platform=snapchat` ([Analytics API](/analytics/get-analytics)). Metrics are fetched per content type (`story`, `saved_story`, `spotlight`), and 3 of them come back: `views`, `reach`, which is Snapchat's unique viewer count, and `shares`. `likes`, `comments` and `saves` read 0, because Snapchat has none of them, and its screenshot count and completion rate stop at Snapchat rather than reaching this response.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'snapchat', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="snapchat",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=snapchat&fromDate=2026-08-01&toDate=2026-08-31" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), one entry per post:

```json
{
  "posts": [
    {
      "_id": "65f1c0a9e2b5af0012ab34cd",
      "platform": "snapchat",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "analytics": {
        "views": 15420,
        "reach": 12350,
        "shares": 45
      }
    }
  ]
}
```

## Inbox

Snapchat has no inbox: its messaging API is closed to third-party apps, so there are no DMs, and snap comments are not accessible through the API.

## What you cannot do

Snapchat's API does not expose:

- AR lenses or filters
- Ads
- Snap Map
- Snapchat sounds
- Collaborative stories
- Friends' stories
- DMs or comments
- Text-only posts (media is required)
- More than 1 media item per post

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `403` with code `PLATFORM_BETA_RESTRICTED` | The account is not on the closed-beta allowlist | Ask for beta access; there is no public release date. |
| "Public Profile required" | The Snapchat account has no Public Profile | Create a Public Profile (Person, Business or Official) and select it during connection. |
| "Media is required" | The post has no media | Add an image or video. Snapchat has no text-only posts. |
| "Only one media item supported" | The post has more than 1 media item | Send a single image or video. |
| Video rejected | The video breaks a Snapchat requirement | Check duration (5 to 60 seconds), format (MP4 only), minimum resolution (540 x 960 px) and size (under 500 MB). |
| "Title too long" (Saved Stories) | `content` is over 45 characters | Shorten `content` to 45 characters or fewer. |
| "Description too long" (Spotlight) | `content` is over 160 characters | Shorten `content` to 160 characters or fewer, hashtags included. |

While the account is not approved, the connect call fails before any OAuth screen appears:

```json
{
  "error": "Snapchat is in closed beta. New connections require approval.",
  "type": "permission_error",
  "code": "PLATFORM_BETA_RESTRICTED"
}
```

Branch on `code`, never on `error`. Request access for your account and retry the same call once it is approved; nothing else on the request needs to change. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts#platforms-requiring-secondary-selection): the OAuth flow and the Public Profile selection step.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Analytics](/analytics/get-analytics): post performance metrics.
- [Pricing](/pricing): what a connected account and analytics cost.

---
