# Call History & Recordings

List every call across your numbers and both channels with GET /v1/calls, open one call, and fetch a fresh recording URL.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can list every call on your numbers, open one and play its recording. You need at least one call on a [voice-enabled number](/platforms/voice/setup). Read history from `/v1/calls`, which covers both channels; act on a call (place, transfer, end) through the channel's own surface.

## Which surface to use

| Surface | Job |
|---|---|
| `/v1/calls` | Read. Lists, fetches and pulls recordings for every call on both channels (phone and WhatsApp), with `contactId` and `contactName` when the counterparty matches a CRM contact. |
| `/v1/voice/calls` | Write for phone (PSTN) calls: place, transfer, end, browser dial, estimate. Its list, get and recording routes return phone calls only, scoped to one number with `number`. |
| `/v1/whatsapp/calls` | Write for WhatsApp calls: place, check permissions, estimate. Its list, get and recording routes are scoped to one `accountId`. |

Read from `/v1/calls`; write on the channel surface. The channel-scoped reads still exist because they enforce account-scoped access, so use them only when you need that scoping.

## List calls

Call `GET /v1/calls`. It returns every call across all of your numbers and both channels, inbound and outbound, newest first, with no `accountId` and no request per number. Filter with `channel`, `direction`, `status`, `number` (calls involving one of your numbers) or `search` (digits from either side).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: history } = await zernio.calls.listCalls({ query: { limit: 50 } });
for (const c of history.calls) {
  console.log(c.channel, c.direction, c.status, c.durationSeconds, c.contactName);
}
// Next page: pass history.nextCursor as `before`
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

history = client.calls.list_calls(limit=50)
for c in history["calls"]:
    print(c["channel"], c["direction"], c["status"], c["durationSeconds"], c["contactName"])
