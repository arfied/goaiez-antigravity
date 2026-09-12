# Send the merchant's browser to connect["authUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/shopify?profileId=66a1f0c2a4b9d3e8f1a2b3c4&shop=your-store.myshopify.com&redirect_url=https://myapp.com/connected" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://your-store.myshopify.com/admin/oauth/authorize?client_id=...",
  "state": "..."
}
```

`shop` accepts the full domain or the bare `your-store` prefix. `redirect_url` must be an absolute http(s) URL or an app scheme such as `myapp://callback`; a relative path is rejected with `400 INVALID_REDIRECT_URL`. Connecting the same profile to the same store again refreshes the stored token in place instead of creating a second account.

### Custom-app Admin token

To skip the browser flow, the merchant creates a custom app in their Shopify admin (Settings, then Apps and sales channels, then Develop apps) with the `read_content` and `write_content` scopes and hands you its Admin API access token, which starts with `shpat_`. Exchange it with [Connect a Shopify store with a custom-app Admin token](/connect/connect-shopify-with-token):

```bash
curl -X POST "https://zernio.com/api/v1/connect/shopify/token" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "shop": "your-store.myshopify.com",
    "accessToken": "shpat_..."
  }'
```

Response (`200`):

```json
{
  "account": {
    "_id": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "shopify",
    "username": "your-store.myshopify.com",
    "displayName": "Your Store",
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4"
  }
}
```

`shop` is required here too: an Admin token does not identify its own store. Zernio validates the token against the store before saving anything, and custom-app tokens do not expire.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `read_content` | Read the store's blogs and articles |
| `write_content` | Create, update and delete blogs and articles |

Content scopes only. Zernio requests no access to customers, orders, products or payments.

### When the merchant uninstalls

Shopify invalidates its own token the moment the app is uninstalled and sends Zernio the `app/uninstalled` webhook, so there is nothing to revoke and nothing to call. Zernio deactivates that store's account: it stops appearing in `GET /v1/accounts` as active, and any request naming its `accountId` fails. Scheduled articles on the account are held through the disconnect grace period rather than deleted at once, so a reinstall inside that window brings them back with the account. Reinstalling runs the same [connect](#connect) flow and issues a fresh token.

## Publish

There are no social posts. A connected store publishes blog articles: a store has one or more blogs (Shopify creates a "News" blog by default), and each blog holds articles. All content lives on Shopify; Zernio proxies it and stores nothing.

### List blogs

Start by listing the blogs to get the `id` you will write into ([List blogs](/blogs/list-blogs)):

```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/blogs" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "platform": "shopify",
  "blogs": [
    { "id": "121793282419", "platform": "shopify", "title": "News", "handle": "news" }
  ],
  "nextCursor": null
}
```

### Create an article

Call `POST /v1/accounts/{accountId}/blogs/{blogId}/articles` with `title` and `bodyHtml` ([Create an article](/blogs/create-blog-article)).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: created } = await zernio.blogs.createBlogArticle({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', blogId: '121793282419' },
  body: {
    title: 'Autumn collection preview',
    bodyHtml: '<p>The first pieces land next month.</p>',
    tags: ['autumn', 'new-arrivals'],
    author: 'Maria Costa',
    excerpt: 'An early look at what is arriving this September.',
    isPublished: true
  }
});

console.log(created.article.id);
```
</Tab>
<Tab value="Python">
```python
created = client.blogs.create_blog_article(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    blog_id="121793282419",
    title="Autumn collection preview",
    body_html="<p>The first pieces land next month.</p>",
    tags=["autumn", "new-arrivals"],
    author="Maria Costa",
    excerpt="An early look at what is arriving this September.",
    is_published=True
)

print(created["article"]["id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/blogs/121793282419/articles" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Autumn collection preview",
    "bodyHtml": "<p>The first pieces land next month.</p>",
    "tags": ["autumn", "new-arrivals"],
    "author": "Maria Costa",
    "excerpt": "An early look at what is arriving this September.",
    "isPublished": true
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "platform": "shopify",
  "article": {
    "id": "589234567890",
    "blogId": "121793282419",
    "platform": "shopify",
    "title": "Autumn collection preview",
    "handle": "autumn-collection-preview",
    "tags": ["autumn", "new-arrivals"],
    "isPublished": true,
    "publishedAt": "2026-09-08T09:00:00Z"
  }
}
```

### Draft

`isPublished: false` keeps the article as a draft; it reads back with `publishedAt: null`:

```json
{
  "title": "Autumn collection preview",
  "bodyHtml": "<p>The first pieces land next month.</p>",
  "isPublished": false
}
```

### Scheduled article

A future `publishDate` schedules the article natively on Shopify. Shopify publishes it at that time with no Zernio queue involved, and until then the article reads back as `isPublished: false` with `publishedAt` set to the future date:

```json
{
  "title": "Autumn collection preview",
  "bodyHtml": "<p>The first pieces land next month.</p>",
  "publishDate": "2027-01-01T12:00:00+01:00"
}
```

### Featured image and SEO

`image.url` sets the featured image; Shopify downloads it, so the URL must be publicly reachable. `seo.title` and `seo.description` map to Shopify's `title_tag` and `description_tag` metafields, which themes read for the page title and meta description:

```json
{
  "title": "Autumn collection preview",
  "bodyHtml": "<p>The first pieces land next month.</p>",
  "image": { "url": "https://cdn.example.com/autumn.jpg", "altText": "Wool coats on a rail" },
  "seo": { "title": "Autumn collection preview", "description": "An early look at the autumn pieces." }
}
```

### Every operation

| Operation | Endpoint |
|-----------|----------|
| [List blogs](/blogs/list-blogs) | `GET /v1/accounts/{accountId}/blogs` |
| [Create a blog](/blogs/create-blog) | `POST /v1/accounts/{accountId}/blogs` |
| [Get a blog](/blogs/get-blog) | `GET /v1/accounts/{accountId}/blogs/{blogId}` |
| [Update a blog](/blogs/update-blog) | `PATCH /v1/accounts/{accountId}/blogs/{blogId}` |
| [Delete a blog](/blogs/delete-blog) | `DELETE /v1/accounts/{accountId}/blogs/{blogId}` |
| [List articles](/blogs/list-blog-articles) | `GET /v1/accounts/{accountId}/blogs/{blogId}/articles` |
| [Create an article](/blogs/create-blog-article) | `POST /v1/accounts/{accountId}/blogs/{blogId}/articles` |
| [Get an article](/blogs/get-blog-article) | `GET /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}` |
| [Update an article](/blogs/update-blog-article) | `PATCH /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}` |
| [Delete an article](/blogs/delete-blog-article) | `DELETE /v1/accounts/{accountId}/blogs/{blogId}/articles/{articleId}` |

## Platform fields

There is no `platformSpecificData` for Shopify, because a store is not a `POST /v1/posts` target. The article fields:

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `title` | string | | Required. |
| `bodyHtml` | string | | Article body as HTML. |
| `handle` | string | (slug of the title) | URL slug. Shopify sets it once, at creation; renaming the article later leaves the URL unchanged, so set it explicitly when the URL matters. |
| `tags` | Array\<string\> | | Shopify returns them alphabetized, not in the order you sent. |
| `author` | string | | Display name of the author. |
| `excerpt` | string | | Short summary shown in blog listings. |
| `image` | \{url, altText?\} | | Featured image, downloaded by Shopify from a public URL. |
| `seo` | \{title?, description?\} | | Maps to Shopify's `title_tag` and `description_tag` metafields. |
| `isPublished` | boolean | | `false` creates a draft. |
| `publishDate` | datetime | | ISO 8601 with offset or `Z`. A future date schedules publication on Shopify. |

## Media requirements

None. Article images are referenced by URL in `image.url` and downloaded by Shopify, not uploaded to Zernio.

## Analytics

Shopify exposes no analytics through Zernio.

## Inbox

Shopify has no inbox.

## What you cannot do

Shopify's connection does not expose:

- Social posts (`POST /v1/posts` rejects a Shopify account)
- Analytics
- An inbox
- Media uploads
- Products, orders and customers (the granted scopes do not permit them)
- Restoring a deleted blog or article (deletes are permanent, and deleting a blog deletes every article inside it)

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `400 INVALID_REDIRECT_URL` | `redirect_url` is a relative path | Pass an absolute http(s) URL or an app scheme. |
| `400` on `GET /v1/connect/shopify` | `shop` is not a `myshopify.com` store domain | Pass `your-store.myshopify.com` or the bare `your-store` prefix. |
| `400` on a Blogs endpoint | `blogId` is not numeric, or the account is on a platform without blogs | Read the id from [List blogs](/blogs/list-blogs); use a Shopify account. |
| `403 insufficient_permissions` | Shopify rejected the request | Reconnect the store to restore access. |
| `404 blog_article_not_found` | The article was deleted, or the id belongs to another blog | Deletes are permanent; list the blog's articles to find a current id. |
| `405` | The platform lacks this specific Blogs operation | Not available for this account. |
| `429` | Rate limited by Zernio or by Shopify | Retry later. See [rate limits](/guides/rate-limits). |

Deleting an article returns `204`, and a later read of it returns `404`:

```json
{
  "error": "Article not found",
  "type": "not_found",
  "code": "blog_article_not_found"
}
```

Zernio stores nothing to restore it from. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts#shopify): the OAuth and Admin-token paths.
- [Blogs API](/blogs/list-blogs): every blog and article endpoint.
- [Get Shopify OAuth connect URL](/connect/get-shopify-connect-url): every parameter of the connect call.
- [Connect with an Admin token](/connect/connect-shopify-with-token): the token-paste alternative.
- [Platforms overview](/platforms): the 16 posting platforms.

---
