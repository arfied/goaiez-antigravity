# Google Business Profile

Publish updates, events and offers to a Google Business Profile location with the Zernio API, then manage its reviews, listing details and performance metrics.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Cards, Card } from 'fumadocs-ui/components/card';
import { CalendarClock, ChartLine, ClipboardList, FileImage, Inbox, MapPin, PenLine, Store } from 'lucide-react';
import { PlatformCapabilities } from '@/components/platform-capabilities';

Publish updates, events and offers to Google Business Profile (`googlebusiness`) with `POST /v1/posts` and `platform: "googlebusiness"`. The same account also serves reviews, listing management (hours, photos, services, menus, verification) and location-level performance metrics.

## Quick reference

<PlatformCapabilities rows={[
  { property: 'Character limit', value: '1,500' },
  { property: 'Images per post', value: '1' },
  { property: 'Videos per post', value: 'Not supported' },
  { property: 'Image formats', value: 'JPEG, PNG only (WebP auto-converted)' },
  { property: 'Image max size', value: '5 MB' },
  { property: 'Image min dimensions', value: '400 x 300 px' },
  { property: 'Post types', value: 'Text, Text+Image, Text+CTA, Event, Offer' },
  { property: 'Scheduling', value: 'Yes' },
  { property: 'Inbox (Reviews)', value: 'Yes' },
  { property: 'Inbox (DMs/Comments)', value: 'No' },
  { property: 'Analytics', value: 'Location-level only (per-post deprecated by Google)' },
]} />

## Before you start

Google Business Profile requires a verified location. Posts appear in Google Search, Google Maps and the Knowledge Panel rather than in a social feed, and they contribute to local search ranking. A post stays visible for about 7 days before Google archives it, so post at least weekly. Videos are not supported, and a text-only post works but gets less visibility than one with an image or a call-to-action button.

## Connect

Call `GET /v1/connect/googlebusiness` with `profileId` ([Get OAuth connect URL](/connect/get-connect-url)) and send the user's browser to the returned `authUrl`. After Google's consent screen the user picks which location to connect; Zernio hosts that screen by default, or pass `headless=true` and build it yourself with [List locations](/connect/list-google-business-locations) and [Select location](/connect/select-google-business-location). The [connecting accounts guide](/guides/connecting-accounts#platforms-requiring-secondary-selection) covers both modes and [scopes](/guides/connecting-accounts#scopes); [Account health](/accounts/get-all-accounts-health) reports what a connected account can do.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'googlebusiness' },
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
    platform="googlebusiness",
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    redirect_url="https://myapp.com/callback",
)
