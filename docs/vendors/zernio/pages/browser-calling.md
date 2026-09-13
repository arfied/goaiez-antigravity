# Browser Calling

Place a call from a web page: mint a WebRTC session on your server, register it in the browser with @telnyx/webrtc, then dial.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a user places a phone call from your web app with no phone. You need a [voice-enabled number](/platforms/voice/setup) and a backend that holds your API key. Browser calling is a 2-step handshake: your server mints a WebRTC session, the browser registers it, then your server dials. The split exists so Zernio never bridges a call to a browser that has not finished registering.

## Step 1: Mint a session on the server

Call `POST /v1/voice/calls/web` from your backend. The number to dial from is chosen later, at the dial step.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: session } = await zernio.voice.createVoiceWebSession({});
// Send session.token and session.credentialId to the browser
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

session = client.voice.create_voice_web_session()
