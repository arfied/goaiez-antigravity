# every request below sends: -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Create one key per app and rotate keys [through the API](/api-keys/list-api-keys). Go, Ruby, Java, PHP, .NET and Rust SDKs are on the [SDKs page](/sdks).

## Step 2: Create a profile

Call `POST /v1/profiles` with a `name`. A profile groups accounts: one per brand, or one per user of your app. Every id is a 24-character string in an `_id` field, and each step returns the id the next one needs.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.profiles.createProfile({
  body: { name: 'My first profile' }
});

const profileId = created.profile._id;
```
</Tab>
<Tab value="Python">
```python
created = client.profiles.create_profile(name="My first profile")

profile_id = created["profile"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/profiles \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"name": "My first profile"}'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "message": "Profile created successfully",
  "profile": {
    "_id": "66a1f0c2a4b9d3e8f1a2b3c4",
    "name": "My first profile"
  }
}
```

`profile._id` is the `profileId` for Step 3.

## Step 3: Connect an account

Call `GET /v1/connect/{platform}` with `profileId`. The response is the URL where your user authorizes Zernio. This example connects LinkedIn.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'linkedin' },
  query: { profileId }
});

console.log(connect.authUrl);
```
</Tab>
<Tab value="Python">
```python
connect = client.connect.get_connect_url(
    platform="linkedin",
    profile_id=profile_id,
)

print(connect["authUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/linkedin?profileId=66a1f0c2a4b9d3e8f1a2b3c4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://www.linkedin.com/oauth/v2/authorization?client_id=..."
}
```

Open `authUrl` and approve. LinkedIn asks whether to connect your personal profile or an organization, on a screen Zernio hosts, then sends the browser back with the account connected. Pass `redirect_url` on the same call to land on your own URL; Zernio appends `connected=linkedin&profileId=...&accountId=...&username=...`.

Replace `linkedin` with any value below:

<PlatformConnectTable />

Facebook, Pinterest, Google Business Profile and Snapchat add a similar selection step (Page, board, location or public profile). The [connecting accounts guide](/guides/connecting-accounts) covers them, plus Bluesky and Telegram, which do not use OAuth.

## Step 4: Get the account id

Call `GET /v1/accounts`. Posts target accounts by `_id`, not by username.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: listed } = await zernio.accounts.listAccounts();

const accountId = listed.accounts[0]._id;
```
</Tab>
<Tab value="Python">
```python
listed = client.accounts.list_accounts()

account_id = listed["accounts"][0]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/accounts \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "accounts": [
    {
      "_id": "66b2e19d8c3f5a7e9d0b1c2d",
      "platform": "linkedin",
      "username": "acme",
      "isActive": true
    }
  ]
}
```

`accounts[].platform` and `accounts[]._id` are the `platform` and `accountId` for Step 5.

## Step 5: Schedule a post

Call `POST /v1/posts` with `content`, `scheduledFor`, `timezone` and one `platforms` entry. `scheduledFor` is read in the `timezone` you send.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: scheduled } = await zernio.posts.createPost({
  body: {
    content: 'Hello world. This is my first post from the Zernio API.',
    scheduledFor: '2027-01-01T12:00:00',
    timezone: 'America/New_York',
    platforms: [
      { platform: 'linkedin', accountId }
    ]
  }
});

const postId = scheduled.post._id;
```
</Tab>
<Tab value="Python">
```python
scheduled = client.posts.create_post(
    content="Hello world. This is my first post from the Zernio API.",
    scheduled_for="2027-01-01T12:00:00",
    timezone="America/New_York",
    platforms=[
        {"platform": "linkedin", "accountId": account_id}
    ]
)

post_id = scheduled["post"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Hello world. This is my first post from the Zernio API.",
    "scheduledFor": "2027-01-01T12:00:00",
    "timezone": "America/New_York",
    "platforms": [
      {"platform": "linkedin", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ]
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
    "scheduledFor": "2027-01-01T17:00:00Z",
    "platforms": [
      { "platform": "linkedin", "status": "pending" }
    ]
  }
}
```

The same body covers every posting mode:

| You set | Result |
|---|---|
| `scheduledFor` and `timezone` | Published at that time |
| `publishNow: true` | Published immediately |
| Neither | Saved as a draft |

To post to several accounts, add entries to `platforms`. Per-platform options go in each entry's `platformSpecificData` ([platform pages](/platforms)).

## Step 6: Check the status

Call `GET /v1/posts/{postId}`. `status` is `scheduled` until the scheduled time, then `publishing`, then `published`, or `failed` or `partial` when a platform rejects it ([error handling](/guides/error-handling)).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: fetched } = await zernio.posts.getPost({
  path: { postId }
});

console.log(fetched.post.status);
```
</Tab>
<Tab value="Python">
```python
fetched = client.posts.get_post(post_id=post_id)

print(fetched["post"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/posts/65f1c0a9e2b5af0012ab34cd \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "scheduled",
    "scheduledFor": "2027-01-01T17:00:00Z",
    "platforms": [
      { "platform": "linkedin", "status": "pending" }
    ]
  }
}
```

Once published, each `platforms[]` entry carries `platformPostUrl`, the live link. [Webhooks](/webhooks) push each status change to you instead of polling.

## If it fails

A `409` means the same content is already scheduled or was posted to this account in the last 24 hours, as when you run Step 5 twice:

```json
{
  "error": "This exact content is already scheduled, publishing, or was posted to this account within the last 24 hours.",
  "details": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "linkedin",
    "existingPostId": "65f1c0a9e2b5af0012ab34cd"
  }
}
```

Change the content, or send an `x-request-id` header so a retry returns the original post instead ([idempotency](/guides/idempotency)). Every error uses the same envelope, described in [error handling](/guides/error-handling).

## Related

The same key serves the [inbox](/messages/list-inbox-conversations), [WhatsApp](/platforms/whatsapp), [phone numbers, SMS and voice](/platforms/phone-numbers), [ads](/ad-campaigns/list-ads), [comment-to-DM automations](/comment-automations/list-comment-automations), [broadcasts](/broadcasts/list-broadcasts), [workflows](/workflows/list-workflows) and [analytics](/analytics/get-analytics).

- [Media uploads](/guides/media-uploads): images and videos in a post.
- [Queue scheduling](/guides/queue-scheduling): recurring time slots, so posts need no `scheduledFor`.
- [Build a platform](/multi-tenant): one profile per user, scoped keys, webhook routing.
- [Rate limits](/guides/rate-limits), [idempotency](/guides/idempotency) and [error handling](/guides/error-handling) before production.
- [CLI](/cli): the same API from a terminal.

---
