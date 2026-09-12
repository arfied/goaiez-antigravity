# Snapchat

Publish Stories, Saved Stories and Spotlight videos to a Snapchat Public Profile with the Zernio API, once your account is approved for the closed beta.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Publish Stories, Saved Stories and Spotlight videos to Snapchat with `POST /v1/posts` and `platform: "snapchat"`. Snapchat is in closed beta, so new connections need approval before anything on this page works.

## Quick reference

| Property | Value |
|----------|-------|
| Title limit | 45 characters (Saved Stories) |
| Description limit | 160 characters (Spotlight, including hashtags) |
| Media per post | 1 (a single image or video) |
| Image formats | JPEG, PNG |
| Image max size | 20 MB |
| Video format | MP4 only |
| Video max size | 500 MB |
| Video duration | 5 to 60 seconds |
| Post types | Story, Saved Story, Spotlight |
| Scheduling | Yes |
| Inbox | No |
| Analytics | Yes (views, unique viewers, shares) |

## Before you start

Snapchat requires a Public Profile (Person, Business or Official); a regular Snapchat account cannot publish through the API. Every post carries exactly 1 image or video: there are no text-only posts, no carousels and no albums, and 9:16 vertical media is expected. Zernio encrypts each file with AES-256-CBC before uploading it to Snapchat, so nothing changes on your side.

<Callout type="warn">
Snapchat is in closed beta. `GET /v1/connect/snapchat` returns `403` with code `PLATFORM_BETA_RESTRICTED` for any account that is not on the beta allowlist, and there is no public release date yet. Everything on this page applies once your account is approved.
</Callout>

## Connect

Call `GET /v1/connect/snapchat` with `profileId` on [Get OAuth connect URL](/connect/get-connect-url). After the user authorizes, they pick which Public Profile to connect, so Snapchat is one of the [platforms requiring secondary selection](/guides/connecting-accounts#platforms-requiring-secondary-selection): in standard mode Zernio hosts that screen, in headless mode you build it. The [connecting accounts guide](/guides/connecting-accounts) covers the OAuth flow and [scopes](/guides/connecting-accounts#scopes) in general.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'snapchat' },
  query: { profileId: '66a1f0c2a4b9d3e8f1a2b3c4', redirect_url: 'https://myapp.com/callback' }
});
// Send the user's browser to connect.authUrl
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

connect = client.connect.get_connect_url(
    platform="snapchat",
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    redirect_url="https://myapp.com/callback"
)
