# Profiles

Create a profile, connect a second account of the same platform into it, and rename or delete it later.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have a second account of the same platform (a second TikTok account, another Facebook Page) connected in its own profile, with its `accountId`, and you can rename or delete a profile afterwards. You need an API key. A profile groups accounts: one per brand, per client or per user when you [build a platform](/multi-tenant). Every team starts with a profile named "Default", and every connected account lives in exactly one profile.

Profiles are free. Only [connected accounts](/pricing#what-counts-as-a-connected-account) are metered, so 10 profiles with 1 account each cost the same as 1 profile with 10 accounts. Legacy Stripe and AppSumo plans keep their profile cap; when it is full, `POST /v1/profiles` returns `403`.

A profile holds at most one account per platform: one Instagram account, one TikTok account, one YouTube channel, one LinkedIn Page. Starting the connect flow for a platform the profile already has replaces the existing connection with the account you authorize. To add a second account of the same platform, create another profile and connect the account there.

## Step 1: Create a profile

Call `POST /v1/profiles` with a `name`. Names are unique within a team. Send an `Idempotency-Key` header if your client retries ([idempotency](/guides/idempotency)).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.profiles.createProfile({
  body: { name: 'Second brand', color: '#4CAF50' },
});

const profileId = created.profile._id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.profiles.create_profile(name="Second brand", color="#4CAF50")

profile_id = created["profile"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/profiles" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "name": "Second brand", "color": "#4CAF50" }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "message": "Profile created successfully",
  "profile": {
    "_id": "66a1f0c2a4b9d3e8f1a2b3c4",
    "userId": "66a0e8b1c2d3e4f5a6b7c8d9",
    "name": "Second brand",
    "color": "#4CAF50",
    "isDefault": false,
    "createdAt": "2026-09-08T10:00:00Z"
  }
}
```

`profile._id` is the `profileId` for Step 2. Creating it in the [dashboard](https://zernio.com/dashboard) works the same way: the profile switcher next to the "Platforms" heading prints the selected profile's id with a copy button.

## Step 2: Connect the account into it

Call `GET /v1/connect/{platform}` with the new `profileId`. Send the user's browser to the returned `authUrl`; in the platform's own login screen they pick the second account, not the one already connected. The [connecting accounts guide](/guides/connecting-accounts) covers headless mode and the platforms that add a Page or organization selection step.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'tiktok' },
  query: { profileId, redirect_url: 'https://your-app.com/callback' },
});

console.log(connect.authUrl);
```
</Tab>
<Tab value="Python">
```python
connect = client.connect.get_connect_url(
    platform="tiktok",
    profile_id=profile_id,
    redirect_url="https://your-app.com/callback",
)

print(connect["authUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/tiktok?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://your-app.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://www.tiktok.com/v2/auth/authorize?client_key=...",
  "state": "..."
}
```

## Step 3: Get the account id

Call `GET /v1/accounts` with `profileId` once the user is back on your `redirect_url`. Posts target accounts by `_id`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: listed } = await zernio.accounts.listAccounts({
  query: { profileId },
});

const accountId = listed.accounts[0]._id;
```
</Tab>
<Tab value="Python">
```python
listed = client.accounts.list_accounts(profile_id=profile_id)

account_id = listed["accounts"][0]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts?profileId=66a1f0c2a4b9d3e8f1a2b3c4" \
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
      "platform": "tiktok",
      "username": "secondbrand",
      "displayName": "Second Brand",
      "isActive": true,
      "profileId": { "_id": "66a1f0c2a4b9d3e8f1a2b3c4", "name": "Second brand" }
    }
  ]
}
```

`accounts[]._id` is the `accountId` you pass in `platforms[]` on `POST /v1/posts`.

## Rename or delete a profile

`GET /v1/profiles` lists them, default first, with an exact-match `name` filter and `limit` and `skip` paging. `PUT /v1/profiles/{profileId}` changes the name, description, color and `isDefault`. `DELETE /v1/profiles/{profileId}` is permanent, and an active connected account blocks it with a `400`: disconnect the accounts first. Disconnected accounts and provisioned WhatsApp numbers in the profile are moved to another of your profiles rather than deleted.

## If it fails

A `403` on `POST /v1/profiles` means the plan's profile cap is full:

```json
{
  "error": "Profile limit reached. Your Build plan allows 10 profiles. You currently have 10.",
  "planName": "Build",
  "limit": 10,
  "current": 10,
  "details": {
    "resource": "profiles",
    "plan": "Build",
    "currentUsage": 10,
    "limit": 10,
    "window": "total"
  }
}
```

Delete a profile you no longer use, or move the team to usage-based billing, which has no profile cap. This response predates the error envelope and carries no `type` or `code`, so branch on the status and `details.resource`.

A `409` means a profile with that name already exists:

```json
{
  "error": "A profile with this name already exists",
  "type": "invalid_request_error",
  "code": "profile_name_conflict",
  "param": "name",
  "details": { "existingProfileId": "66a1f0c2a4b9d3e8f1a2b3c4" }
}
```

Use `details.existingProfileId` instead of creating another, or pick a different name. A request that reuses an `Idempotency-Key` still in flight also returns `409`, with code `idempotency_conflict`; retry that one after the first completes.

## Related

- [Create profile](/profiles/create-profile), [List profiles](/profiles/list-profiles) and [Delete profile](/profiles/delete-profile).
- [Start OAuth](/connect/get-connect-url): the `profileId` parameter.
- [List accounts](/accounts/list-accounts): filter by `profileId`.
- [Build a platform](/multi-tenant): one profile per customer.
- [Pricing](/pricing#what-counts-as-a-connected-account): what counts as a connected account.

---
