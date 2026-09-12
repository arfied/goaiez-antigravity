# Build a Platform

Give each of your customers a profile, connect their accounts into it, post on their behalf and route webhooks back to the right customer.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';
import { Card, Cards } from 'fumadocs-ui/components/card';
import { Send, ChartLine, Inbox } from 'lucide-react';

When you finish this page each of your customers has a profile, their accounts are connected inside it, and every webhook lands on the right customer record. You need an API key and a database column to hold each customer's `profileId`. One profile per customer is the whole model: a customer with 3 Facebook Pages and 2 Instagram accounts connects all 5 into their one profile. Profiles are free; each connected account is metered ([pricing](/pricing)).

<Cards>
  <Card icon={<Send />} title="Publishing" href="/multi-tenant/publishing" description="Retry-safe posting for every customer: idempotency keys, media, per-profile queues" />
  <Card icon={<ChartLine />} title="Analytics dashboards" href="/multi-tenant/analytics" description="Serve each customer their own metrics from a sync worker" />
  <Card icon={<Inbox />} title="Inbox and DMs" href="/multi-tenant/inbox" description="One inbox per customer, written by webhooks" />
</Cards>

Requests carry `profileId` in, events carry `accountId` back, and your database maps both to a customer:

<Mermaid
  chart={`flowchart LR
  subgraph app ["Your app"]
    DB[("Your database
customer cus_8f3a2
profileId + account map")]
  end
  subgraph zernio ["Zernio"]
    PROF["Profile customer_8f3a2"]
    IG["Instagram @acme"]
    TT["TikTok @acme"]
    X["X @acme"]
  end
  DB -->|"your API calls carry profileId"| PROF
  PROF --> IG
  PROF --> TT
  PROF --> X
  IG -.->|"webhooks carry accountId"| DB
  linkStyle 4 stroke:#6b7280,color:#6b7280,stroke-dasharray:6 4;`}
/>

Every new team starts with a profile named "Default". Keep it for your own accounts and create one profile per customer next to it.

## Step 1: Create a profile per customer

Call `POST /v1/profiles` with a `name` when a customer signs up, and store `profile._id` on their record. Names are unique within your team, so your internal customer id is a good name.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.profiles.createProfile({
  body: {
    name: 'customer_8f3a2',
    description: 'Acme Corp',
  },
});

const profileId = created.profile._id; // store on your customer record
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.profiles.create_profile(
    name="customer_8f3a2",
    description="Acme Corp",
)

profile_id = created["profile"]["_id"]  # store on your customer record
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/profiles" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "name": "customer_8f3a2", "description": "Acme Corp" }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "message": "Profile created successfully",
  "profile": {
    "_id": "66a1f0c2a4b9d3e8f1a2b3c4",
    "name": "customer_8f3a2",
    "description": "Acme Corp",
    "isDefault": false,
    "createdAt": "2026-09-08T10:00:00Z"
  }
}
```

The [Create profile endpoint](/profiles/create-profile) lists every field.

## Step 2: Connect their accounts

Call `GET /v1/connect/{platform}` with the customer's `profileId` so the account lands in their profile. Pass `headless=true` to draw the selection screens yourself instead of using the ones Zernio hosts.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: connect } = await zernio.connect.getConnectUrl({
  path: { platform: 'instagram' },
  query: { profileId, redirect_url: 'https://your-app.com/callback' },
});
// Send the customer's browser to connect.authUrl
```
</Tab>
<Tab value="Python">
```python
connect = client.connect.get_connect_url(
    platform="instagram",
    profile_id=profile_id,
    redirect_url="https://your-app.com/callback",
)
