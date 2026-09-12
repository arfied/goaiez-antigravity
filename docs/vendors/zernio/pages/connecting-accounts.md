# Connecting accounts

Connect an account to a profile with OAuth, a hosted or headless selection step, or credentials for Bluesky, Telegram and Shopify.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page an account is connected to a profile and you have its `accountId`. You need an API key, a profile id ([Step 2 of the quickstart](/#step-2-create-a-profile)) and a login on the platform. The 16 posting platforms and Shopify each connect one of the ways below; the rules specific to a platform are on its [platform page](/platforms).

## OAuth flow (most platforms)

Call `GET /v1/connect/{platform}` with `profileId`. Zernio returns an `authUrl`; send the user's browser there, and when they approve, the platform sends them back and the account is connected.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const profileId = '66a1f0c2a4b9d3e8f1a2b3c4';

const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'linkedin' },
  query: { profileId, redirect_url: 'https://myapp.com/callback' }
});
// Send the user's browser to connect.authUrl
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
profile_id = "66a1f0c2a4b9d3e8f1a2b3c4"

connect = client.connect.get_connect_url(
    platform="linkedin",
    profile_id=profile_id,
    redirect_url="https://myapp.com/callback",
)
