# X

Publish posts, threads, replies, quotes and polls to X with the Zernio API, then read analytics, retweet, bookmark, follow and answer DMs from the same account.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Cards, Card } from 'fumadocs-ui/components/card';
import { AlertTriangle, ChartLine, Film, Inbox, ListChecks, PenLine, Quote } from 'lucide-react';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Publish text, images, GIFs, videos, threads, replies, quotes and polls to X (platform value `twitter`) with `POST /v1/posts` and `platform: "twitter"`. The same account also serves analytics, retweets, bookmarks, follows, DMs and comments.

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Character limit', value: '280 (free) / 25,000 (Premium)' },
  { property: 'Images per post', value: '4 (or 1 GIF)' },
  { property: 'Videos per post', value: '1' },
  { property: 'File limits', value: <>Formats, sizes and duration (see <a href="/platforms/twitter/media">Media &amp; Video</a>)</> },
  { property: 'Threads', value: 'Yes (threadItems)' },
  { property: 'Scheduling', value: 'Yes' },
  { property: 'Inbox (DMs)', value: 'Yes' },
  { property: 'Inbox (Comments)', value: 'Yes' },
  { property: 'Analytics', value: 'Yes' },
]} />

## Before you start

X requires a card on file. X bills every API call at its published price and Zernio passes that cost through with no markup, so `GET /v1/connect/twitter` returns `402` with `reason: "twitter_passthrough"` until the team has a payment method. The rates and the monthly spend cap are on [X API usage](/pricing#x-twitter-api-usage).

A free X account is limited to 280 characters, a Premium account to 25,000. URLs count as 23 characters whatever their length and emojis count as 2. X also rejects a post whose text matches one it already has.

<Callout type="warn">
When you cross-post from a platform with a higher limit (LinkedIn 3,000, Facebook 63,206), set `customContent` on the X entry with a version under 280 characters. Otherwise the X copy fails at publish time with "Tweet text is too long".
</Callout>

## Connect

Call `GET /v1/connect/twitter` with `profileId` ([Get OAuth connect URL](/connect/get-connect-url)) and send the user's browser to the returned `authUrl`. The [connecting accounts guide](/guides/connecting-accounts) covers the flow and [scopes](/guides/connecting-accounts#scopes) in general; [Account health](/accounts/get-all-accounts-health) reports what a connected account can do with the scopes the user granted.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'twitter' },
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
    platform="twitter",
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    redirect_url="https://myapp.com/callback",
)
