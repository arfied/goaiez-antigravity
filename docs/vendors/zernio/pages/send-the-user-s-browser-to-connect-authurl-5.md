# Send the user's browser to connect["authUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/twitter?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://myapp.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://twitter.com/i/oauth2/authorize?client_id=...",
  "state": "..."
}
```

After approval the user lands on `redirect_url` with `connected=twitter&profileId=...&accountId=...` appended. That `accountId` is the value every sample in this section uses.

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `tweet.read` | Read posts (post confirmation, replies, analytics) |
| `tweet.write` | Publish and delete posts |
| `users.read` | Account identity and profile data |
| `offline.access` | Refresh token for long-lived access |
| `media.write` | Upload images and videos |
| `dm.read`, `dm.write` | X direct messages in the inbox |
| `tweet.moderate.write` | Hide and unhide replies (comment moderation) |
| `like.write` | Like posts on your behalf |
| `bookmark.write` | Bookmark posts |
| `follows.write` | Follow accounts on your behalf |

## Publish

Call `POST /v1/posts` with `content`, a `platforms` entry carrying `platform: "twitter"` and the `accountId` from the connect flow, and `publishNow: true`. Replace `publishNow` with `scheduledFor` and `timezone` to schedule instead.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: published } = await zernio.posts.createPost({
  body: {
    content: 'Shipping day. The API is live.',
    platforms: [
      { platform: 'twitter', accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
    ],
    publishNow: true
  }
});

console.log(published.post.platforms[0].platformPostUrl);
```
</Tab>
<Tab value="Python">
```python
published = client.posts.create_post(
    content="Shipping day. The API is live.",
    platforms=[
        {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    publish_now=True
)

print(published["post"]["platforms"][0]["platformPostUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Shipping day. The API is live.",
    "platforms": [
      {"platform": "twitter", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    "publishNow": true
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "platforms": [
      {
        "platform": "twitter",
        "status": "published",
        "platformPostId": "1852634789012345678",
        "platformPostUrl": "https://twitter.com/acmecorp/status/1852634789012345678"
      }
    ]
  }
}
```

Media, threads, replies, quotes and polls all change this one request. [Posts & Editing](/platforms/twitter/posts) has each of them.

## In this section

<Cards>
  <Card icon={<PenLine />} title="Posts & Editing" href="/platforms/twitter/posts" description="Create a post, attach media, publish a thread or an Article, and edit a published post" />
  <Card icon={<Quote />} title="Replies & Quotes" href="/platforms/twitter/replies-quotes" description="Reply to a post, reply with a thread and quote a post" />
  <Card icon={<Film />} title="Media & Video" href="/platforms/twitter/media" description="Media requirements and long video uploads on Premium accounts" />
  <Card icon={<ListChecks />} title="Fields, Geo & Polls" href="/platforms/twitter/fields-polls" description="Geo-restriction, paid partnership and AI labels, sensitive media, reply settings and polls" />
  <Card icon={<ChartLine />} title="Analytics & Engagement" href="/platforms/twitter/analytics-engagement" description="Post analytics, retweets, bookmarks and follows" />
  <Card icon={<Inbox />} title="Inbox" href="/platforms/twitter/inbox" description="Direct messages and comments in the inbox" />
  <Card icon={<AlertTriangle />} title="Limits & Errors" href="/platforms/twitter/reference" description="Every platformSpecificData field, what X's API does not expose and common errors" />
</Cards>

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `402` with `reason: "twitter_passthrough"` on `GET /v1/connect/twitter` | The team has no card on file, and X charges for every API call. | Send the user to the `dashboard_url` in the response, then call connect again. Rates are on [X API usage](/pricing#x-twitter-api-usage). |
| `?error=oauth_denied&platform=twitter` appended to your `redirect_url` | The user denied consent, or X rejected the callback. | Start the flow again. Treat an unknown `error` value as a generic failure; new values are added without notice. |
| `401` on a later call for that account | X revoked or expired the token. | Reconnect the account. [Account health](/accounts/get-all-accounts-health) reports it before a post fails. |

The publish-time errors are in [Limits & Errors](/platforms/twitter/reference#common-errors), and [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow and the `402` billing gates.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload images and videos instead of hosting them.
- [Twitter engagement](/twitter-engagement/retweet-post): retweet, bookmark, follow, search and look up posts.
- [X API usage](/pricing#x-twitter-api-usage): pass-through rates and the spend cap.

---
