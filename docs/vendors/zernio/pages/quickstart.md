# Quickstart

Create an API key, connect an account and schedule your first post with 5 calls to the Zernio API.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have a post scheduled on a connected account, created with 5 API calls. You need a Zernio account and a login for one of the [16 platforms](/platforms). The first 2 connected accounts are free without a card, except X (platform value `twitter`), which needs one because X bills every API call ([pricing](/pricing)).

If your users connect their own accounts, the calls are the same with one profile per user: read [Build a platform](/multi-tenant) after your first post.

**Base URL:** `https://zernio.com/api/v1`

## Step 1: Create an API key

1. [Sign up](https://zernio.com) or log in.
2. Open [API keys](https://zernio.com/dashboard/api-keys) and click **Create API key**.
3. Copy the key now. Zernio stores a SHA-256 hash of it and never shows it again.

A key is `sk_` followed by 64 hex characters (67 in total). Every request sends it in the `Authorization: Bearer` header, and the SDKs read it from the `ZERNIO_API_KEY` environment variable:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```bash
npm install @zernio/node
export ZERNIO_API_KEY="sk_..."
```

```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio(); // reads ZERNIO_API_KEY
```
</Tab>
<Tab value="Python">
```bash
pip install zernio-sdk
export ZERNIO_API_KEY="sk_..."
```

```python
from zernio import Zernio

client = Zernio()  # reads ZERNIO_API_KEY
```
</Tab>
<Tab value="curl">
```bash
export ZERNIO_API_KEY="sk_..."
