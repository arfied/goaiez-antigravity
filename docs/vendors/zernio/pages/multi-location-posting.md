# Multi-Location Posting

List the Google Business Profile locations an account manages, post to several of them in one request, switch the account's default location, and give a location its own profile with POST /gmb-locations/assign.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have published one post to several Google Business Profile (`googlebusiness`) locations from a single connected account, switched the location that account posts to by default, and moved a location onto its own profile with `POST /v1/accounts/{accountId}/gmb-locations/assign`, which reuses the same OAuth grant. You need a connected account (`accountId`) whose Google login manages more than one location.

## Step 1: list the locations

Call `GET /v1/accounts/{accountId}/gmb-locations` ([List locations](/connect/get-gmb-locations)). It returns up to 100 locations and the one currently selected; pass `search` to filter by business name or raise `limit` (max 500) for larger accounts.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: locations } = await zernio.connect.getGmbLocations({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

console.log(locations.selectedLocationId, locations.locations);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

locations = client.connect.get_gmb_locations(account_id="66b2e19d8c3f5a7e9d0b1c2d")

print(locations["selectedLocationId"], locations["locations"])
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-locations \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "locations": [
    {
      "id": "12345678901234567890",
      "name": "Joe's Pizza Downtown",
      "accountId": "accounts/123456789",
      "accountName": "Joe's Pizza",
      "address": "123 Main St, San Francisco, CA",
      "category": "Pizza restaurant"
    },
    {
      "id": "22345678901234567890",
      "name": "Joe's Pizza Marina",
      "accountId": "accounts/123456789",
      "accountName": "Joe's Pizza",
      "address": "45 Bay St, San Francisco, CA",
      "category": "Pizza restaurant"
    }
  ],
  "hasMore": false,
  "selectedLocationId": "12345678901234567890",
  "cached": true
}
```

## Step 2: post to several locations

Repeat the `platforms` entry with the same `accountId` and a different `platformSpecificData.locationId` each time. The value is `locations/` followed by the location's `id` from Step 1. An entry without `locationId` posts to the selected location.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Now open at both locations. Visit us today.',
    mediaItems: [{ type: 'image', url: 'https://cdn.example.com/store.jpg' }],
    platforms: [
      {
        platform: 'googlebusiness',
        accountId: '66b2e19d8c3f5a7e9d0b1c2d',
        platformSpecificData: { locationId: 'locations/12345678901234567890' }
      },
      {
        platform: 'googlebusiness',
        accountId: '66b2e19d8c3f5a7e9d0b1c2d',
        platformSpecificData: { locationId: 'locations/22345678901234567890' }
      }
    ],
    publishNow: true
  }
});

console.log(published.post.platforms.map((p) => p.platformPostUrl));
```
</Tab>
<Tab value="Python">
```python
published = client.posts.create_post(
    content="Now open at both locations. Visit us today.",
    media_items=[{"type": "image", "url": "https://cdn.example.com/store.jpg"}],
    platforms=[
        {
            "platform": "googlebusiness",
            "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
            "platformSpecificData": {"locationId": "locations/12345678901234567890"}
        },
        {
            "platform": "googlebusiness",
            "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
            "platformSpecificData": {"locationId": "locations/22345678901234567890"}
        }
    ],
    publish_now=True
)

print([p["platformPostUrl"] for p in published["post"]["platforms"]])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Now open at both locations. Visit us today.",
    "mediaItems": [
      {"type": "image", "url": "https://cdn.example.com/store.jpg"}
    ],
    "platforms": [
      {
        "platform": "googlebusiness",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {"locationId": "locations/12345678901234567890"}
      },
      {
        "platform": "googlebusiness",
        "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
        "platformSpecificData": {"locationId": "locations/22345678901234567890"}
      }
    ],
    "publishNow": true
  }'
```
</Tab>
</Tabs>

Response (`201`), one `platforms` entry per location:

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "platforms": [
      { "platform": "googlebusiness", "status": "published", "platformPostUrl": "https://business.google.com/..." },
      { "platform": "googlebusiness", "status": "published", "platformPostUrl": "https://business.google.com/..." }
    ]
  }
}
```

## Step 3: switch the default location

Call `PUT /v1/accounts/{accountId}/gmb-locations` with `selectedLocationId` to change which location the account posts to when `locationId` is omitted ([Update location](/connect/update-gmb-location)). Pass `googleAccountId` (the `accountId` resource name from Step 1) as well, so the location is resolved directly instead of by enumerating the account; large accounts require it.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: switched } = await zernio.connect.updateGmbLocation({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { selectedLocationId: '22345678901234567890', googleAccountId: 'accounts/123456789' }
});

console.log(switched.selectedLocation.name);
```
</Tab>
<Tab value="Python">
```python
switched = client.connect.update_gmb_location(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    selected_location_id="22345678901234567890",
    google_account_id="accounts/123456789",
)

print(switched["selectedLocation"]["name"])
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-locations \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "selectedLocationId": "22345678901234567890",
    "googleAccountId": "accounts/123456789"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "message": "Google Business location updated successfully",
  "selectedLocation": {
    "id": "22345678901234567890",
    "name": "Joe's Pizza Marina"
  }
}
```

## Step 4: give a location its own profile

An agency running one profile per client connects each location onto its own profile. Call `POST /v1/accounts/{accountId}/gmb-locations/assign` with the target `profileId` and the `selectedLocationId` to move onto it ([Assign location to another profile](/connect/assign-google-business-location)). The path `accountId` is the account whose OAuth grant is reused, so there is no second browser flow. Pass `googleAccountId` here too.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: assigned } = await zernio.connect.assignGoogleBusinessLocation({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4',
    selectedLocationId: 'locations/22345678901234567890',
    googleAccountId: 'accounts/123456789'
  }
});

console.log(assigned.account.accountId);
```
</Tab>
<Tab value="Python">
```python
assigned = client.connect.assign_google_business_location(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    selected_location_id="locations/22345678901234567890",
    google_account_id="accounts/123456789",
)

print(assigned["account"]["accountId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-locations/assign \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "selectedLocationId": "locations/22345678901234567890",
    "googleAccountId": "accounts/123456789"
  }'
```
</Tab>
</Tabs>

Response (`200`), a new connected account on the target profile:

```json
{
  "message": "Location assigned successfully",
  "account": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2e",
    "platform": "googlebusiness",
    "username": "Joe's Pizza Marina",
    "displayName": "Joe's Pizza Marina",
    "isActive": true,
    "selectedLocationName": "Joe's Pizza Marina",
    "selectedLocationId": "22345678901234567890"
  }
}
```

`account.selectedLocationId` comes back as bare digits, not a resource name. Post to the new account with its own `accountId` and no `locationId`.

## If it fails

A `400` on `PUT /v1/accounts/{accountId}/gmb-locations` means `selectedLocationId` is not one of the locations this Google login manages, or `googleAccountId` is not one of its accounts:

```json
{
  "error": "Location not in available locations",
  "type": "invalid_request_error"
}
```

Take the `id` and `accountId` values from Step 1 rather than typing them, then retry. A `409` on the assign call means the target profile already has a Google Business Profile account connected: switch that account's location with Step 3 instead. A `404` means `accountId` is not one of your connected accounts. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [List locations](/connect/get-gmb-locations): `search`, `filter` and `limit` for accounts with many locations.
- [Assign location to another profile](/connect/assign-google-business-location): one profile per client without a second OAuth.
- [Posts & Content Types](/platforms/google-business/posts): the base request.
- [Inbox](/platforms/google-business/inbox#read-reviews-across-several-locations): reviews across several locations in one call.
- [Profiles](/guides/profiles): how profiles group accounts.

---
