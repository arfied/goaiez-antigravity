# Pinterest

Publish image and video pins to Pinterest boards with the Zernio API, with destination links, cover images, board creation and description edits.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish image and video pins to Pinterest with `POST /v1/posts` and `platform: "pinterest"`. The same account serves board management, description edits and analytics.

## Quick reference

| Property | Value |
|----------|-------|
| Title limit | 100 characters |
| Description limit | 800 characters, on create and on an [edit](#edit-a-published-pin) |
| Images per pin | 1 |
| Videos per pin | 1 |
| Image formats | JPEG, PNG, WebP, GIF |
| Image max size | 32 MB |
| Video formats | MP4, MOV |
| Video max size | 2 GB |
| Video duration | 4 seconds to 15 minutes |
| Scheduling | Yes |
| Editing published posts | Description only, 800 characters (blocked by Pinterest's closed beta) |
| Inbox | No (Pinterest has no API-accessible inbox) |
| Analytics | Yes (impressions, saves, clicks) |

## Before you start

Pinterest requires a board to pin to; create one in Pinterest or with [Create a board](#create-a-board). Every pin needs 1 image or 1 video, so there are no text-only pins and no carousels or multi-image posts. Pinterest is a search engine rather than a social feed: people find pins through search and browse, not through followers, so the title, description and board name matter more than the posting time, and a pin keeps surfacing for 3 to 6 months instead of hours. `link` is where a click on the pin sends people, so it is the field that drives traffic.

<Callout type="warn">
Send `boardId` on every post. It is optional, but a pin without one lands on whichever board Pinterest returns first, which is rarely the one you meant. When the account has no boards, or the board list cannot be read, the post fails with "Pinterest requires a boardId. Provide platformSpecificData.boardId." List the account's boards with `GET /v1/accounts/{accountId}/pinterest-boards`.
</Callout>

## Connect

Call `GET /v1/connect/pinterest` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). After OAuth the user picks a default board: [List boards](/connect/list-pinterest-boards-for-selection) then [Select board](/connect/select-pinterest-board), in standard or headless mode like the other selection platforms; see [platforms requiring secondary selection](/guides/connecting-accounts#platforms-requiring-secondary-selection). Change the default board later with [Update Pinterest board](/connect/update-pinterest-boards), with no second OAuth. The [connecting accounts guide](/guides/connecting-accounts) covers the flow and [scopes](/guides/connecting-accounts#scopes) in general; [Account health](/accounts/get-all-accounts-health) reports what a connected account can do with the scopes the user granted.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `boards:read` | List boards during connection and post creation |
| `boards:write` | [Create boards](#create-a-board) through the API |
| `pins:read` | Read pins (permalinks, analytics) |
| `pins:write` | Create pins |
| `user_accounts:read` | Account identity and account analytics |
| `ads:read`, `ads:write` | [Pinterest Ads](/platforms/pinterest-ads): read ad accounts, create and manage campaigns |

## Publish

A plain post becomes an image pin when the media is an image and a video pin when it is a video. `boardId`, `title` and `link` go in `platformSpecificData` on the Pinterest entry; `content` is the pin description.

### Image pin

A single image pinned to a board. Use a 2:3 aspect ratio (1000 x 1500 px) for the best display in the Pinterest feed.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Modern kitchen renovation ideas for small spaces',
    mediaItems: [
      { type: 'image', url: 'https://cdn.example.com/kitchen-ideas.jpg' }
    ],
    platforms: [{
      platform: 'pinterest',
      accountId: '66b2e19d8c3f5a7e9d0b1c2d',
      platformSpecificData: {
        title: 'Modern Kitchen Renovation Ideas',
        boardId: '1022106146582011234',
        link: 'https://myblog.com/kitchen-renovation'
      }
    }],
    publishNow: true
  }
});

console.log(published.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

published = client.posts.create_post(
    content="Modern kitchen renovation ideas for small spaces",
    media_items=[
        {"type": "image", "url": "https://cdn.example.com/kitchen-ideas.jpg"}
    ],
    platforms=[{
        "platform": "pinterest",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {
            "title": "Modern Kitchen Renovation Ideas",
            "boardId": "1022106146582011234",
            "link": "https://myblog.com/kitchen-renovation"
        }
    }],
    publish_now=True
)

print(published["post"]["platforms"][0]["platformPostUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Modern kitchen renovation ideas for small spaces",
    "mediaItems": [
      {"type": "image", "url": "https://cdn.example.com/kitchen-ideas.jpg"}
    ],
    "platforms": [{
      "platform": "pinterest",
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platformSpecificData": {
        "title": "Modern Kitchen Renovation Ideas",
        "boardId": "1022106146582011234",
        "link": "https://myblog.com/kitchen-renovation"
      }
    }],
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
        "platform": "pinterest",
        "status": "published",
        "platformPostUrl": "https://www.pinterest.com/pin/..."
      }
    ]
  }
}
```

Every sample below changes only the `mediaItems` or the `platforms` entry of this request.

### Video pin

A single video pinned to a board. `coverImageUrl` sets a custom cover; `coverImageKeyFrameTime` extracts a frame at that second instead.

```json
"mediaItems": [
  { "type": "video", "url": "https://cdn.example.com/recipe.mp4" }
],
"platforms": [{
  "platform": "pinterest",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "platformSpecificData": {
    "title": "5-Minute Breakfast Recipe",
    "boardId": "1022106146582011234",
    "link": "https://myrecipes.com/quick-breakfast",
    "coverImageUrl": "https://cdn.example.com/recipe-cover.jpg"
  }
}]
```

### Boards

Every pin belongs to a board. List the boards on a connected account, or create one through the API; the `boards:write` scope is part of every Pinterest connection, so no reconnect is needed.

#### List boards

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: boards } = await zernio.connect.getPinterestBoards({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

console.log(boards.boards);
```
</Tab>
<Tab value="Python">
```python
boards = client.connect.get_pinterest_boards(account_id="66b2e19d8c3f5a7e9d0b1c2d")

print(boards["boards"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/pinterest-boards" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "boards": [
    {
      "id": "1022106146582011234",
      "name": "Kitchen Ideas",
      "description": "Renovations and layouts",
      "privacy": "PUBLIC",
      "url": "https://www.pinterest.com/yourhandle/kitchen-ideas/"
    }
  ]
}
```

#### Create a board

The response carries the new board's `id`, which works at once as `platformSpecificData.boardId`. `privacy` is `PUBLIC` (default), `PROTECTED` or `SECRET`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.connect.createPinterestBoard({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    name: 'Summer Recipes',
    description: 'My favorite summer recipes',
    privacy: 'PUBLIC'
  }
});

console.log(created.board.id);
```
</Tab>
<Tab value="Python">
```python
created = client.connect.create_pinterest_board(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    name="Summer Recipes",
    description="My favorite summer recipes",
    privacy="PUBLIC",
)

print(created["board"]["id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/pinterest-boards" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Summer Recipes",
    "description": "My favorite summer recipes",
    "privacy": "PUBLIC"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "board": {
    "id": "1022106146582019876",
    "name": "Summer Recipes",
    "description": "My favorite summer recipes",
    "privacy": "PUBLIC",
    "url": "https://www.pinterest.com/yourhandle/summer-recipes/"
  }
}
```

Pinterest rejects a duplicate board name on the same account, and you cannot pin to a secret board you do not own. A board-creation error from Pinterest comes back with its original message and a `502`.

### Edit a published pin

[Edit post](/posts/edit-post) replaces the description of a pin that is already live, up to the same 800 characters a create takes. The title, media, destination `link` and board cannot be changed, a title derived from the first line of the original content at publish time stays as it was, and there is no time window, no edit limit and the pin id is unchanged.

<Callout type="warn">
Pinterest's pin-update endpoint is a closed beta. Until Pinterest allowlists the app, every edit request is rejected with a "beta feature not yet enabled" error. Nothing on your side changes that; Pinterest grants the access.
</Callout>

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: edited } = await zernio.posts.editPost({
  path: { postId: '65f1c0a9e2b5af0012ab34cd' },
  body: {
    platform: 'pinterest',
    content: 'Updated pin description with the new recipe link details'
  }
});

console.log(edited.id, edited.url);
```
</Tab>
<Tab value="Python">
```python
edited = client.posts.edit_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    platform="pinterest",
    content="Updated pin description with the new recipe link details"
)

print(edited["id"], edited["url"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts/65f1c0a9e2b5af0012ab34cd/edit \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "platform": "pinterest",
    "content": "Updated pin description with the new recipe link details"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "id": "1022106146582013456",
  "url": "https://www.pinterest.com/pin/1022106146582013456/",
  "message": "Post edited successfully"
}
```

If the post was published to several Pinterest accounts, pass `accountId` to pick which copy to edit; without it, the first `pinterest` entry on the post is edited.

## Platform fields

All fields go in `platformSpecificData` on the Pinterest entry.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `boardId` | string | | The board to pin to. Send it on every post. Get board ids from `GET /v1/accounts/{accountId}/pinterest-boards`, or [create a board](#create-a-board) with `POST` on the same path. |
| `boardSectionId` | string | | Section inside the board. The pin lands on the board itself when omitted. Pinterest rejects the pin if the section does not belong to `boardId`, so send both together. No Zernio endpoint returns section ids: read them from Pinterest's own board sections API, which the same connected account can call. |
| `title` | string (max 100 chars) | First line of content | Pin title, searchable by Pinterest users. |
| `link` | string (URL) | | Destination when someone clicks the pin. Must be valid HTTPS, no URL shorteners. The field that drives traffic. Alias: `url`. |
| `coverImageUrl` | string (URL) | | Custom cover image for video pins. |
| `coverImageKeyFrameTime` | number (seconds) | `0` | Extract a video frame at this many seconds as the cover. Ignored when `coverImageUrl` is set. |
| `isAiGenerated` | boolean | `false` | `true` creates the pin with Pinterest's AI_MODIFIED disclosure (an "AI modified" label). `false` omits the disclosure; Pinterest may still label a pin from its own detection. |

## Media requirements

### Images

| Property | Requirement |
|----------|-------------|
| Max images | 1 per pin |
| Formats | JPEG, PNG, WebP, GIF |
| Max file size | 32 MB |
| Recommended | 1000 x 1500 px (2:3) |
| Min dimensions | 100 x 100 px |

| Ratio | Dimensions | Use |
|-------|------------|-----|
| 2:3 | 1000 x 1500 px | Standard pin, the best display in the feed |
| 1:1 | 1000 x 1000 px | Square pin |
| 1:2.1 | 1000 x 2100 px | Long pin (max height) |

Animated GIFs auto-play in the feed and are treated as images, not video. The limit is 32 MB, and under 10 MB loads fast.

### Videos

| Property | Requirement |
|----------|-------------|
| Max videos | 1 per pin |
| Formats | MP4, MOV |
| Max file size | 2 GB |
| Duration | 4 seconds to 15 minutes |
| Aspect ratio | 2:3, 1:1 or 9:16 |
| Resolution | 1080p recommended |
| Frame rate | 25 fps or more |

| Property | Minimum | Recommended |
|----------|---------|-------------|
| Resolution | 240p | 1080p |
| Bitrate | | 10 Mbps |
| Audio | | AAC, 128 kbps |

### Media URLs

A media URL must be publicly accessible with no authentication, return the media bytes with the correct `Content-Type` header, not redirect to an HTML page, and sit on a fast host. Google Drive, Dropbox, OneDrive and iCloud sharing links return a webpage and fail. To host files instead, use the [media endpoint](/guides/media-uploads).

## Analytics

Call `GET /v1/analytics?platform=pinterest` ([Analytics API](/analytics/get-analytics)).

| Metric | Available |
|--------|-----------|
| Impressions | <Yes /> |
| Saves | <Yes /> |
| Clicks | <Yes /> |

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: analytics } = await zernio.analytics.getAnalytics({
  query: { platform: 'pinterest', fromDate: '2026-08-01', toDate: '2026-08-31' }
});

console.log(analytics.posts);
```
</Tab>
<Tab value="Python">
```python
analytics = client.analytics.get_analytics(
    platform="pinterest",
    from_date="2026-08-01",
    to_date="2026-08-31"
)

print(analytics["posts"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/analytics?platform=pinterest&fromDate=2026-08-01&toDate=2026-08-31" \
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
      "platform": "pinterest",
      "status": "published",
      "publishedAt": "2026-08-14T10:00:05Z",
      "platformPostUrl": "https://www.pinterest.com/pin/1022106146582013456/",
      "analytics": {
        "impressions": 4380,
        "saves": 96,
        "clicks": 141
      }
    }
  ]
}
```

## Inbox

Pinterest has no inbox API: no DMs, no comments and no reviews.

## What you cannot do

Pinterest's API does not expose:

- Idea Pins (multi-page stories)
- Claiming a website
- Rich Pins (they need meta tags on your website)
- Shopping catalogs
- Changing a published pin's media, destination link or board (only the description; see [Edit a published pin](#edit-a-published-pin))
- Multi-image posts or carousels

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Invalid URL or request data." | Pinterest could not process the URL or request data | Check that the media URL is public, returns media bytes and uses HTTPS. |
| "Unable to reach the URL. Please check the URL is correct and try again." | Pinterest's servers cannot fetch the media | Test the URL in an incognito window. No authentication, no redirect to an HTML page. |
| "Pinterest rate limit reached." | Too many API calls in a short window | Space out pins; avoid bursts of 10 or more at once. |
| "Pinterest requires a boardId. Provide platformSpecificData.boardId." | No board in the request | Send `boardId`. List boards with `GET /v1/accounts/{accountId}/pinterest-boards`. |

A `publishNow: true` post that Pinterest rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`:

```json
{
  "message": "Post created but publishing failed",
  "error": "All platforms failed",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "failed",
    "platforms": [
      {
        "platform": "pinterest",
        "status": "failed",
        "errorMessage": "Unable to reach the URL. Please check the URL is correct and try again."
      }
    ]
  }
}
```

`207` is a 2xx status, so `fetch(...).ok` is `true`; branch on the status code and on `post.status`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow and board selection.
- [Create post](/posts/create-post): every field of the request.
- [Edit post](/posts/edit-post): update the description of a published pin.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Pinterest boards](/connect/get-pinterest-boards) and [Create Pinterest board](/connect/create-pinterest-board): the boards endpoints.

---
