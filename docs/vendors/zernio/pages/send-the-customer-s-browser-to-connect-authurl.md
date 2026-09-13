# Send the customer's browser to connect["authUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/instagram?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://your-app.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://www.instagram.com/oauth/authorize?client_id=...",
  "state": "..."
}
```

The [connecting accounts guide](/guides/connecting-accounts) covers headless mode and the platforms that add a selection step (Facebook Pages, LinkedIn organizations, Pinterest boards). A customer who manages several Pages runs this flow once per Page, always with the same `profileId`.

Subscribe to [`account.connected`](/webhooks/accounts) to learn about new connections on your server. Its payload carries `accountId` and `profileId`, which is the mapping you store:

```json
{
  "event": "account.connected",
  "account": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "platform": "instagram",
    "username": "acmecorp"
  }
}
```

`account.disconnected` carries the same identifiers when a token dies or a customer removes access.

The onboarding flow end to end:

<Mermaid
  chart={`%%{init: {"themeVariables": {"signalColor": "#ec3013"}}}%%
sequenceDiagram
  participant C as Customer
  participant A as Your app
  participant Z as Zernio
  participant P as Instagram
  A->>Z: POST /v1/profiles
  Z-->>A: profile._id, stored on your customer record
  C->>A: clicks "Connect Instagram"
  A->>Z: GET /v1/connect/instagram?profileId=...
  Z-->>A: authUrl
  A->>C: redirect to authUrl
  C->>P: signs in and authorizes
  P->>C: redirect to your redirect_url
  Z-->>A: webhook account.connected (accountId + profileId)
  Note over A: store accountId with the customer`}
/>

## Step 3: Post on their behalf

Call `GET /v1/accounts` with the customer's `profileId`, then `POST /v1/posts` with those account ids.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: listed } = await zernio.accounts.listAccounts({
  query: { profileId },
});

const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Posted from your app.',
    platforms: listed.accounts.map((a) => ({
      platform: a.platform,
      accountId: a._id,
    })),
    publishNow: true,
  },
});

console.log(published.post.status);
```
</Tab>
<Tab value="Python">
```python
listed = client.accounts.list_accounts(profile_id=profile_id)

published = client.posts.create_post(
    content="Posted from your app.",
    platforms=[
        {"platform": a["platform"], "accountId": a["_id"]} for a in listed["accounts"]
    ],
    publish_now=True,
)

print(published["post"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts?profileId=66a1f0c2a4b9d3e8f1a2b3c4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X POST "https://zernio.com/api/v1/posts" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Posted from your app.",
    "platforms": [{ "platform": "instagram", "accountId": "66b2e19d8c3f5a7e9d0b1c2d" }],
    "publishNow": true
  }'
```
</Tab>
</Tabs>

Response (`200`) from the list:

```json
{
  "accounts": [
    {
      "_id": "66b2e19d8c3f5a7e9d0b1c2d",
      "platform": "instagram",
      "username": "acmecorp",
      "isActive": true,
      "profileId": "66a1f0c2a4b9d3e8f1a2b3c4"
    }
  ]
}
```

Response (`201`) from the post:

```json
{
  "message": "Post published successfully",
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "platforms": [
      { "platform": "instagram", "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "status": "published" }
    ]
  }
}
```

<Callout type="warn">
`POST /v1/posts` accepts any `accountId` your team owns, whichever profile it sits in. Pass a customer only the account ids stored against them in Step 2.
</Callout>

## Step 4: Route webhooks back to the right customer

Webhook endpoints are configured per team (up to 50), not per profile. Run one endpoint and route by the id in the payload:

| Events | Key in the payload | Look it up in |
|---|---|---|
| Post events (`post.published`, `post.failed`, ...) | `accountId` on each `platforms[]` entry | The account map from Step 2 |
| Account events (`account.connected`, `account.disconnected`) | `profileId` | Your customer record |
| Inbox events (`message.received`, ...) | `account.id` | The account map from Step 2 |

The [webhooks overview](/webhooks) covers delivery, retries and signatures; the [inbox guide](/multi-tenant/inbox) has a full handler.

## Step 5: Monitor account health

Tokens die when customers change passwords, revoke access or trip a platform security check. Build the reconnect loop before launch:

1. Subscribe to [`account.disconnected`](/webhooks/accounts). It carries `accountId` and `profileId`, so you know which customer to notify.
2. Prompt that customer to reconnect by starting a new [connect flow](/guides/connecting-accounts) with the same `profileId`.
3. Call [account health](/accounts/get-all-accounts-health) on a schedule to catch anything a webhook missed.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: health } = await zernio.accounts.getAllAccountsHealth({
  query: { profileId, status: 'error' },
});

console.log(health.summary.needsReconnect);
```
</Tab>
<Tab value="Python">
```python
health = client.accounts.get_all_accounts_health(profile_id=profile_id, status="error")

print(health["summary"]["needsReconnect"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/health?profileId=66a1f0c2a4b9d3e8f1a2b3c4&status=error" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "summary": { "total": 1, "healthy": 0, "warning": 0, "error": 1, "needsReconnect": 1 },
  "accounts": [
    {
      "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
      "platform": "instagram",
      "username": "acmecorp",
      "status": "error",
      "canPost": false,
      "tokenValid": false,
      "needsReconnect": true,
      "issues": ["Token expired"]
    }
  ]
}
```

## Rate limits with many customers

One API key covers every customer. Your [rate limit](/guides/rate-limits) grows with your team's connected accounts, and it is shared by all your customers, so queue bursts per customer (a bulk import, a mass reply) so one customer cannot starve the rest. Webhooks replace most polling. On a `429`, wait for the `Retry-After` header before retrying.

## Scoped API keys

An API key can reach every profile in your team by default. Call `POST /v1/api-keys` with `scope: "profiles"` and `profileIds` to create a key that sees only one customer's profile, and `permission: "read"` to make it read-only:

```bash
curl -X POST "https://zernio.com/api/v1/api-keys" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "acme-corp-readonly",
    "scope": "profiles",
    "profileIds": ["66a1f0c2a4b9d3e8f1a2b3c4"],
    "permission": "read"
  }'
```

Response (`201`):

```json
{
  "message": "API key created successfully",
  "apiKey": {
    "id": "6507a1b2c3d4e5f6a7b8c9d0",
    "name": "acme-corp-readonly",
    "key": "sk_...",
    "scope": "profiles",
    "profileIds": ["66a1f0c2a4b9d3e8f1a2b3c4"],
    "permission": "read"
  }
}
```

Use scoped keys for a per-customer backend service, a read-only analytics dashboard, or temporary access with `expiresIn` (days). They are an access-control tool; the rate limit belongs to the team, so a scoped key adds no throughput.

To close off whole areas of the API rather than whole profiles, send `disabledResourceGroups` on the same call: a denylist over ten groups (`publishing`, `engagement`, `messages`, `contacts`, `analytics`, `ads`, `telephony`, `accounts`, `billing`, `webhooks`), so a per-customer backend that only schedules posts disables the other nine. A key with any group disabled mints with a `zrk_` prefix instead of `sk_`, and refuses a call into a disabled group with `403`, `code: "insufficient_permissions"` and the `required_group` it wanted. There is no update endpoint: to change the groups, create a second key and revoke the first. The [Create API key endpoint](/api-keys/create-api-key) lists every field.

## What each customer costs you

Call `GET /v1/usage?range=cycle&groupBy=profile` for the current billing period's spend split per profile. `attribution.groups[]` carries one entry per profile id, and `sum(groups) + unattributed` equals `totals` exactly, so a customer's share of your invoice is a lookup on their `profileId`. Credits, 10DLC fees and Verify belong to no single profile and land in `unattributed`; a profile-scoped key sees only its own profiles' groups and says so with `attribution.restricted: true`. [Billing](/billing) has the rest of the metering contract.

## Offboard a customer

1. [Disconnect their accounts](/accounts/delete-account). Active connected accounts block profile deletion with a `400`.
2. [Delete the profile](/profiles/delete-profile). Remaining disconnected accounts and provisioned WhatsApp numbers move to another of your profiles and are never deleted.

## If it fails

A `409` from Step 1 means a profile with that name already exists in your team:

```json
{
  "error": "A profile with this name already exists",
  "code": "profile_name_conflict",
  "details": { "existingProfileId": "66a1f0c2a4b9d3e8f1a2b3c4" }
}
```

Use `details.existingProfileId` as the customer's profile instead of creating another. A `402` with `code: "PAYMENT_REQUIRED"` is a billing gate on your team, so it affects every customer at once; send yourself to its `dashboard_url` rather than retrying.

## Related

- [Publishing](/multi-tenant/publishing): retry-safe posting and per-profile queues.
- [Analytics dashboards](/multi-tenant/analytics): per-customer metrics from a sync worker.
- [Inbox and DMs](/multi-tenant/inbox): one inbox per customer, written by webhooks.
- [Connecting accounts](/guides/connecting-accounts): OAuth, headless mode and selection steps.
- [Webhooks](/webhooks): delivery, signatures and every event payload.

---
