# UPLOAD_URL is the uploadUrl from the presign response
curl -X PUT "$UPLOAD_URL" \
  -H "Content-Type: video/mp4" \
  --data-binary @launch.mp4
```
</Tab>
</Tabs>

Response (`200`) from the presign call:

```json
{
  "uploadUrl": "https://...",
  "publicUrl": "https://media.zernio.com/temp/1234567890_abc123_launch.mp4",
  "expiresIn": 3600
}
```

Response (`200`) from the `PUT`, with no body: storage answers with an empty `200` and an `ETag` header. From then on the file is served at `publicUrl`.

## Step 2: Create the post with an idempotency key

Call `POST /v1/posts` with an `x-request-id` header, the customer's account ids and `queuedFromProfile` set to their `profileId`. Generate one UUID per logical post and send the same UUID on every retry of that call; a replay within about 5 minutes returns `200` with the original post in `existingPost`. Treat that and a `409` (same content to the same account within 24 hours) as success. The [idempotency guide](/guides/idempotency) covers both layers.

`queuedFromProfile` places the post on the next free slot of that customer's [queue](/guides/queue-scheduling), so each profile drips its own posts with no scheduler of your own. For a post that must go out at an exact moment, send `scheduledFor` and `timezone` instead. For a customer's CSV import, use [`POST /v1/posts/bulk-upload`](/posts/bulk-upload-posts) with `dryRun=true` first.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: listed } = await zernio.accounts.listAccounts({
  query: { profileId },
});

const { data: queued } = await zernio.posts.createPost({
  headers: { 'x-request-id': randomUUID() },
  body: {
    content: 'Big news coming Friday.',
    mediaItems: [{ type: 'video', url: presigned.publicUrl }],
    platforms: listed.accounts.map((a) => ({
      platform: a.platform,
      accountId: a._id,
    })),
    queuedFromProfile: profileId,
  },
});

console.log(queued.post.status, queued.post.scheduledFor);
```
</Tab>
<Tab value="Python">
```python
listed = client.accounts.list_accounts(profile_id=profile_id)

queued = client.posts.create_post(
    x_request_id=str(uuid.uuid4()),
    content="Big news coming Friday.",
    media_items=[{"type": "video", "url": presigned["publicUrl"]}],
    platforms=[
        {"platform": a["platform"], "accountId": a["_id"]}
        for a in listed["accounts"]
    ],
    queued_from_profile=profile_id,
)

print(queued["post"]["status"], queued["post"]["scheduledFor"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/posts" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -H "x-request-id: 4b4986f4-77b5-4c22-a3a7-2c56f7a657e1" \
  -d '{
    "content": "Big news coming Friday.",
    "mediaItems": [{ "type": "video", "url": "https://media.zernio.com/temp/1234567890_abc123_launch.mp4" }],
    "platforms": [{ "platform": "instagram", "accountId": "66b2e19d8c3f5a7e9d0b1c2d" }],
    "queuedFromProfile": "66a1f0c2a4b9d3e8f1a2b3c4"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "message": "Post scheduled successfully",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "scheduled",
    "scheduledFor": "2027-01-04T14:00:00Z",
    "queuedFromProfile": "66a1f0c2a4b9d3e8f1a2b3c4",
    "platforms": [
      { "platform": "instagram", "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "status": "pending" }
    ]
  }
}
```

<Callout type="warn">
`POST /v1/posts` accepts any `accountId` your team owns, whichever profile it sits in. Pass a customer only the account ids stored against them.
</Callout>

A scheduler that fires every customer's posts with `publishNow: true` on the hour creates a burst that spends your request budget at once and runs into per-account velocity limits. Queue slots spread the same posts across the hour.

## Step 3: Read the outcome from webhooks

The `201` means accepted. What happened at publish time arrives on the [post webhooks](/webhooks/posts): `post.published` when every platform succeeded, `post.partial` or `post.failed` otherwise, and `post.platform.published` / `post.platform.failed` per platform as each one finishes. Route each event to the customer with the `accountId` on its `platforms[]` entries, then update your UI from `post.status` ([post lifecycle](/guides/post-lifecycle)). Do not poll `GET /v1/posts` to discover status changes; polling is for reconciliation.

<Mermaid
  chart={`%%{init: {"themeVariables": {"signalColor": "#ec3013"}}}%%
sequenceDiagram
  participant T as Customer
  participant A as Your app
  participant Z as Zernio
  T->>A: writes post, hits Publish
  A->>Z: POST /v1/posts (x-request-id, queuedFromProfile)
  Z-->>A: 201, status: scheduled, scheduledFor
  Note over Z: the slot arrives and Zernio publishes
  alt every platform succeeds
    Z-->>A: webhook post.published (per-platform URLs)
    A-->>T: show live post links
  else some or all fail
    Z-->>A: webhook post.partial / post.failed (error per platform)
    A-->>T: surface the platform error and offer a retry
  end`}
/>

A `post.published` delivery for the post above:

```json
{
  "id": "evt_5f8e2a1c",
  "event": "post.published",
  "post": {
    "id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "scheduledFor": "2027-01-04T14:00:00Z",
    "publishedAt": "2027-01-04T14:00:03Z",
    "platforms": [
      {
        "platform": "instagram",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "status": "published",
        "publishedUrl": "https://www.instagram.com/p/..."
      }
    ]
  },
  "timestamp": "2027-01-04T14:00:03Z"
}
```

If a published post is later deleted on the platform, `post.platform.deleted` fires (poll-driven, roughly hourly), so your UI can stop showing it as live.

## If it fails

A `409` from Step 2 means the same content is already scheduled or was posted to that account in the last 24 hours:

```json
{
  "error": "This exact content is already scheduled, publishing, or was posted to this account within the last 24 hours.",
  "details": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "instagram",
    "existingPostId": "65f1c0a9e2b5af0012ab34cd"
  }
}
```

Treat it as done and show the customer `existingPostId`. To repost the same content on purpose, change the caption, the media or the account.

## Related

- [Build a platform](/multi-tenant): the profile-per-customer model.
- [Idempotency](/guides/idempotency): `x-request-id` and content-hash dedup in depth.
- [Queue scheduling](/guides/queue-scheduling): slots, several queues per profile, previewing the next slot.
- [Media uploads](/guides/media-uploads): presigned URLs, size limits, per-platform rules.
- [Post lifecycle](/guides/post-lifecycle): every status and transition.

---
