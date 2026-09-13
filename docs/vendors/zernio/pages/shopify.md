# Shopify

Connect a Shopify store and create, schedule, update and delete its blog articles through the Blogs API; a store publishes no social posts.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Create, schedule, update and delete the blog articles of a Shopify store with the [Blogs API](/blogs/list-blogs) and a connected account with `platform: "shopify"`. Shopify is connect-only: a store never appears as a target in `POST /v1/posts`, publishes no social posts and reports no analytics.

## Quick reference

| Property | Value |
|----------|-------|
| Platform value | `shopify` |
| What it manages | Storefront blogs and blog articles |
| Auth | OAuth 2.0 (store domain required) or a custom-app Admin token |
| Scopes | `read_content`, `write_content` |
| Social posting | No |
| Analytics | No |
| Media requirements | None (article images are referenced by URL) |
| Scheduling | Yes, native (Shopify publishes at `publishDate`; no Zernio queue) |
| Drafts | Yes (`isPublished: false`) |
| Article body | HTML (`bodyHtml`) |
| SEO fields | Yes (`seo.title`, `seo.description`) |
| Pagination | Cursor (`limit` 1 to 50, default 20, plus `nextCursor`) |

## Before you start

Shopify requires the store's `myshopify.com` domain before OAuth can start. Shopify has no store picker and no lookup from a merchant to their shops, so an authorization URL can only be built for a domain you already know; collect it from the merchant first. A merchant who installs from the Shopify App Store never types it, because Shopify supplies the domain to Zernio on that path.

Blog and article ids are Shopify's own numeric ids, not Zernio object ids. Read them from API responses; never construct them.

## Connect

Call `GET /v1/connect/shopify` with `profileId` and `shop` on [Get Shopify OAuth connect URL](/connect/get-shopify-connect-url). Send the merchant to the returned `authUrl`; after they approve the install, Shopify calls Zernio's callback, the account is created on your profile, and the browser lands on your `redirect_url`. The [connecting accounts guide](/guides/connecting-accounts#shopify) covers the flow and [scopes](/guides/connecting-accounts#scopes) in general.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: connect } = await zernio.connect.getShopifyConnectUrl({
  query: {
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4',
    shop: 'your-store.myshopify.com',
    redirect_url: 'https://myapp.com/connected'
  }
});
// Send the merchant's browser to connect.authUrl
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

connect = client.connect.get_shopify_connect_url(
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    shop="your-store.myshopify.com",
    redirect_url="https://myapp.com/connected"
)
