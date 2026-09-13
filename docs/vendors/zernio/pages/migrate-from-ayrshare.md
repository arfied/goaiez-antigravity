# Migrate from Ayrshare

Move social posting, profiles, media and scheduled posts from Ayrshare to Zernio, with a drop-in SDK for the social-media-api package or a field mapping for raw HTTP calls.

When you finish this page your Ayrshare integration creates posts through Zernio. You need your Ayrshare API key and Profile Keys (to export scheduled posts), a Zernio account and an [API key](https://zernio.com/dashboard/api-keys). Two paths:

| Path | For | Effort |
|------|-----|--------|
| [Drop-in SDK](#step-1-swap-the-sdk-drop-in-path) | Code that uses the Ayrshare `social-media-api` npm package | 5 minutes |
| [API migration](#what-changes) | Raw HTTP calls or a custom client | 1 to 2 hours |

## What changes

| What | Ayrshare | Zernio |
|------|----------|--------|
| Base URL | `api.ayrshare.com/api` | `zernio.com/api/v1` |
| Content field | `post` | `content` |
| Platforms | `["twitter", "facebook"]` | `[{platform, accountId}]` |
| Media | `mediaUrls: ["url"]` | `mediaItems: [{type, url}]` |
| Schedule | `scheduleDate` | `scheduledFor` |
| Publish now | Omit `scheduleDate` | `publishNow: true` |
| Multi-user | `Profile-Key` header | Profiles as resources, accounts addressed by `accountId` |
| Google Business Profile | `gmb` | `googlebusiness` |

A profile groups accounts, one per brand or per user ([profiles guide](/guides/profiles)); each connected account has an `accountId` that goes in `platforms[]`.

## Step 1: Swap the SDK (drop-in path)

If your code uses the Ayrshare `social-media-api` package, `@zernio/social-media-api` has the same method signatures:

```bash
npm uninstall social-media-api
npm install @zernio/social-media-api
```

```diff
- import SocialMediaAPI from 'social-media-api';
+ import SocialMediaAPI from '@zernio/social-media-api';

const social = new SocialMediaAPI(process.env.ZERNIO_API_KEY);
```

Existing calls (`post`, `history`, `upload`, `createProfile` and the rest) keep working:

| Category | Methods |
|----------|---------|
| Posts | `post`, `delete`, `getPost`, `retryPost`, `updatePost` |
| History | `history` |
| User | `user` |
| Profiles | `createProfile`, `deleteProfile`, `updateProfile`, `getProfiles` |
| Media | `upload`, `media`, `mediaUploadUrl`, `verifyMediaExists` |
| Analytics | `analyticsPost`, `analyticsSocial` |
| Comments | `postComment`, `getComments`, `deleteComments`, `replyComment` |
| Webhooks | `registerWebhook`, `unregisterWebhook`, `listWebhooks` |
| Scheduling | `setAutoSchedule`, `deleteAutoSchedule`, `listAutoSchedule` |
| Reviews | `reviews`, `review`, `replyReview`, `deleteReplyReview` |

`setProfileKey` scopes the following calls to one profile, the same way it did with Ayrshare; `getProfiles()` lists the keys. AI generation (`generatePost`, `generateRewrite`), RSS feeds, URL shortening and some media and analytics utilities return `501`; the [compatibility list](https://github.com/zernio-dev/social-media-api#not-yet-available) names each one. Source and issues: [github.com/zernio-dev/social-media-api](https://github.com/zernio-dev/social-media-api). This path still needs Steps 2 and 3, a profile with the accounts connected behind it; what it lets you skip is the request rewriting in Steps 4 to 6, and [Step 6](#step-6-move-scheduled-posts) has the shortcut for the scheduled queue.

## Step 2: Create a profile

Call `POST /v1/profiles` with a `name`, once per Ayrshare Profile Key:

```bash
curl -X POST https://zernio.com/api/v1/profiles \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "My Brand"}'
```

Response (`201`):

```json
{
  "message": "Profile created successfully",
  "profile": {
    "_id": "66a1f0c2a4b9d3e8f1a2b3c4",
    "name": "My Brand",
    "isDefault": false
  }
}
```

`profile._id` is the `profileId` for Step 3.

## Step 3: Connect the accounts

Call `GET /v1/connect/{platform}` with `profileId` for each platform you had in Ayrshare, and send the user to the returned `authUrl`:

```bash
curl "https://zernio.com/api/v1/connect/twitter?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://yourapp.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "authUrl": "https://twitter.com/i/oauth2/authorize?client_id=...",
  "state": "..."
}
```

Platform values: `twitter` `instagram` `facebook` `linkedin` `tiktok` `youtube` `pinterest` `reddit` `bluesky` `threads` `googlebusiness` `telegram` `snapchat` `discord` `slack` `whatsapp`. Ayrshare's `gmb` is `googlebusiness` here. The [connecting accounts guide](/guides/connecting-accounts) covers the platforms with a selection step and the ones without OAuth.

## Step 4: Get the account ids

Call `GET /v1/accounts` with `profileId`:

```bash
curl "https://zernio.com/api/v1/accounts?profileId=66a1f0c2a4b9d3e8f1a2b3c4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "accounts": [
    {
      "_id": "66b2e19d8c3f5a7e9d0b1c2d",
      "platform": "twitter",
      "username": "@yourhandle",
      "displayName": "Your Name",
      "isActive": true
    }
  ]
}
```

Store the `_id` per platform in your database: it replaces the Profile Key in every post call.

## Step 5: Change the post calls

Call `POST /v1/posts` with `content` and `platforms[]`, each entry carrying the `accountId` from Step 4. Ayrshare's `post` and its list of platform names become `content` and one object per account:

```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Hello world",
    "platforms": [
      {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"},
      {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2e"}
    ],
    "publishNow": true
  }'
```

Response (`201`):

```json
{
  "message": "Post published successfully",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "content": "Hello world",
    "status": "published",
    "platforms": [
      {
        "platform": "twitter",
        "status": "published",
        "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678"
      }
    ]
  }
}
```

Ayrshare's `scheduleDate` becomes `scheduledFor` plus a `timezone`, in place of `publishNow`:

```json
{
  "scheduledFor": "2027-01-01T12:00:00",
  "timezone": "America/New_York"
}
```

`publishNow` defaults to `false`, so a post with `scheduledFor` is scheduled. Ayrshare's `mediaUrls` becomes `mediaItems`, where each item names its own type:

```json
{
  "mediaItems": [
    {"type": "image", "url": "https://cdn.example.com/image.jpg"}
  ]
}
```

For files you host nowhere, request a presigned URL with `POST /v1/media/presign`, `PUT` the file to `uploadUrl` and use `publicUrl` in the post; the [media uploads guide](/guides/media-uploads) has the size limits and the accepted types. Ayrshare's post statuses map to `status` on the post ([post lifecycle](/guides/post-lifecycle)).

The same function in Node.js:

```javascript
const createPost = async (content, accounts, media = [], scheduledFor = null) => {
  const response = await fetch('https://zernio.com/api/v1/posts', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${process.env.ZERNIO_API_KEY}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      content,
      platforms: accounts, // [{platform: "twitter", accountId: "..."}]
      mediaItems: media.map(url => ({
        type: url.match(/\.(mp4|mov|webm)$/i) ? 'video' : 'image',
        url
      })),
      ...(scheduledFor ? { scheduledFor, timezone: 'UTC' } : { publishNow: true })
    }),
  });

  const data = await response.json();
  return data.post._id;
};
```

## Step 6: Move scheduled posts

Export the queue from Ayrshare with its `GET /api/history` (the Ayrshare API key plus the `Profile-Key` header).

On the drop-in SDK path there is nothing to remap: `social.post()` keeps Ayrshare's own field names, so call `setProfileKey` and replay each exported row through it, and skip the rest of this step. Calling the API directly, each row becomes one `POST /v1/posts` call, which needs the `accountId`s from Step 4:

| Ayrshare history field | Zernio field |
|---|---|
| `post` | `content` |
| `platforms[]`, a list of names | one `platforms[]` entry per account: `{ platform, accountId }` from Step 4 |
| `scheduleDate` | `scheduledFor`, with `timezone` alongside it |
| `mediaUrls[]` | `mediaItems[]`, each item naming its own `type` |

```javascript
const accountByPlatform = {
  twitter: '66b2e19d8c3f5a7e9d0b1c2d',
  linkedin: '66b2e19d8c3f5a7e9d0b1c2e',
};

// scheduledRows: the pending posts from Ayrshare's GET /api/history
for (const row of scheduledRows) {
  await fetch('https://zernio.com/api/v1/posts', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${process.env.ZERNIO_API_KEY}`,
      'Content-Type': 'application/json',
      'x-request-id': `ayrshare-${row.id}`, // same key on every retry of this row
    },
    body: JSON.stringify({
      content: row.post,
      platforms: row.platforms.map((p) => ({ platform: p, accountId: accountByPlatform[p] })),
      mediaItems: (row.mediaUrls ?? []).map((url) => ({
        type: /\.(mp4|mov|webm)$/i.test(url) ? 'video' : 'image',
        url,
      })),
      scheduledFor: row.scheduleDate,
      timezone: 'UTC',
    }),
  });
}
```

The `x-request-id` header makes a retried export idempotent, so a rerun replays the original response instead of creating a second post ([idempotency](/guides/idempotency)). Delete or pause each row in Ayrshare only after its Zernio post exists: in that order a crash leaves a duplicate you can find and cancel, and in the other order it leaves a post nobody sends.

## Step 7: Cut over

| Phase | Actions |
|-------|---------|
| Prep | Create profiles, connect accounts, ship the new calls behind a flag |
| Pilot | Run internal users on Zernio for a few days |
| Rollout | Enable for 10%, then 50%, then 100% of users |
| Cutoff | Disable Ayrshare; keep its keys for 30 days as a fallback |

## If it fails

A `400` with `Invalid accountId` means a `platforms[].accountId` is not a Zernio account id, which happens when a Profile Key or an Ayrshare id is still in the mapping. Use the `_id` from `GET /v1/accounts` (Step 4). Other errors you will meet on the first calls:

| Error | Cause | Fix |
|-------|-------|-----|
| Platform not supported | Wrong platform value | `googlebusiness`, not `gmb` |
| Media not found | The URL is not reachable | HTTPS and publicly accessible ([media uploads](/guides/media-uploads)) |
| Post not publishing | Wrong date format | ISO 8601, `scheduledFor` read in `timezone` |

Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Profiles](/guides/profiles) and [connecting accounts](/guides/connecting-accounts)
- [Rate limits](/guides/rate-limits) and [idempotency](/guides/idempotency)
- [Webhooks](/webhooks) replace polling `history`
- [Drop-in SDK source](https://github.com/zernio-dev/social-media-api)
- Support: support@zernio.com

---
