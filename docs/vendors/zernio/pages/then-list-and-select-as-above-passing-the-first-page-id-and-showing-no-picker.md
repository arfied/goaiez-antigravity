# then list and select as above, passing the first page id and showing no picker
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://www.facebook.com/v21.0/dialog/oauth?client_id=..."
}
```

The callback, list and select calls are the headless flow above. The `metaads` account is created alongside the Facebook account. Roughly 70% of the `metaads` surface (everything that does not emit `object_story_spec.page_id`) works whichever Page is bound, so the first Page is fine for ads-only callers. The remaining 30% (boosting Page posts, [Click-to-WhatsApp ads](/platforms/meta-ads/ctwa#click-to-whatsapp-ads), Lead Gen Forms) is Page-specific: when the user later needs those for a specific Page, show a picker then and POST the new `pageId` to `/v1/connect/facebook/select-page`. Zernio updates the existing Facebook account in place, with no second OAuth.

#### Facebook Login for Business

Call [`GET /v1/connect/{platform}/ads`](/connect/connect-ads) with `platform` set to `facebook` or `instagram`, `profileId` and `loginMode=business`. For example, the Facebook route is `GET /v1/connect/facebook/ads?profileId=...&loginMode=business`. Omitting `loginMode` uses `classic`.

Business login always returns an `authUrl` to open in the user's browser. The callback creates or reconnects an independent `metaads` account using a Business Integration System User token, without creating or requiring a posting account. `GET /v1/accounts` identifies it with `metadata.tokenType: "system-user"`. When Meta returns no `expires_in`, `tokenExpiresAt` is absent; Zernio does not re-exchange it as a personal token.

Select a granted Facebook Page for ad creatives and lead forms:

- Pass `pageId` on the connect request to choose a specific granted Page.
- Without `pageId`, Zernio reuses the previous Page or chooses the sole granted Page automatically.
- With several granted Pages and no selection, API callers receive a `400` listing the available Page IDs. Restart the connect request with `pageId`.
- With zero granted Pages, the connection can manage campaigns and sync insights, but cannot create Page-based creatives or list Page forms. Grant a Page when reconnecting to use those features.

A business reconnect preserves the connection's ID, history and ad-account scope. It must grant every previously scoped ad account, or every previous grant on an unscoped connection; missing or unverifiable grants return `409` before changing the account.

#### Scoping sync to specific ad accounts

By default, sync covers every `act_*` ad account the connected Meta token can see. That is fine for one person's account but leaks for agencies and multi-Business-Manager setups, where the token sees every account in every Business Manager the user has a role on. To restrict sync to an allowlist, pass `adAccountId` (one) or `adAccountIds` (several) on `GET /v1/connect/facebook/ads`:

```
?adAccountId=act_1330190928038136
?adAccountIds=act_1330190928038136,act_3686966528111132
```

Zernio validates each id against the token's `/me/adaccounts` and stores the list. The `account.ads.initial_sync_completed` webhook then carries `account.platformAdAccountId` (when the scope is exactly one account) and `account.platformAdAccountIds` (always), so you can confirm what was synced. Omit both params to keep the "sync everything visible" behaviour. The latest call wins: a new connect with new ids replaces the earlier allowlist.

## Platforms without OAuth

### Bluesky

Bluesky connects with an app password. `state` is `{userId}-{profileId}`: `userId` is `currentUserId` from `GET /v1/users`, `profileId` from `GET /v1/profiles`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: bluesky } = await zernio.connect.bluesky.connectBlueskyCredentials({
  body: {
    identifier: 'yourhandle.bsky.social',
    appPassword: 'your-app-password',
    state: '66a0e8b1c2d3e4f5a6b7c8d9-66a1f0c2a4b9d3e8f1a2b3c4',
  },
});
console.log(`Connected ${bluesky.account.username}`);
```
</Tab>
<Tab value="Python">
```python
bluesky = client.connect.connect_bluesky_credentials(
    identifier="yourhandle.bsky.social",
    app_password="your-app-password",
    state="66a0e8b1c2d3e4f5a6b7c8d9-66a1f0c2a4b9d3e8f1a2b3c4",
)
print(f"Connected {bluesky['account']['username']}")
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/connect/bluesky/credentials" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "identifier": "yourhandle.bsky.social",
    "appPassword": "your-app-password",
    "state": "66a0e8b1c2d3e4f5a6b7c8d9-66a1f0c2a4b9d3e8f1a2b3c4"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "message": "Bluesky connected successfully",
  "account": {
    "platform": "bluesky",
    "username": "yourhandle.bsky.social",
    "displayName": "Your Name",
    "isActive": true
  }
}
```

The [Connect Bluesky endpoint](/connect/connect-bluesky-credentials) lists every field.

### Telegram

Telegram connects with an access code:

1. Call `GET /v1/connect/telegram` to [generate an access code](/connect/get-telegram-connect-status), valid for 15 minutes.
2. The user adds the Zernio Telegram bot as an admin of the channel or group and sends it the code.
3. Poll `PATCH /v1/connect/telegram` to [check the status](/connect/complete-telegram-connect) until it reports connected.

If the bot is already an admin of the channel or group, skip the code and [connect directly](/connect/initiate-telegram-connect) with `POST /v1/connect/telegram` and the chat id.

### Shopify

Shopify is OAuth, but the authorization URL is built per store, so you need the merchant's `myshopify.com` domain before you start. Shopify offers no store picker and no lookup from a merchant to their shops.

1. Collect the store domain from the merchant (`your-store.myshopify.com`; the bare `your-store` prefix is accepted).
2. Call `GET /v1/connect/shopify` with `profileId` and `shop` to [get the authorization URL](/connect/get-shopify-connect-url).
3. Redirect the merchant to the returned `authUrl`; after approval they land on your `redirect_url`.

A merchant who installs from the Shopify App Store never types the domain, because Shopify supplies it to Zernio directly.

To skip the browser flow, the merchant can create a custom app in their Shopify admin with the `read_content` and `write_content` scopes and hand you its Admin API access token, which you [exchange for a connected store](/connect/connect-shopify-with-token) with `POST /v1/connect/shopify/token`. `shop` is required there too: an Admin token does not identify its own store.

A connected store publishes no social posts. It powers the [Blogs API](/blogs/list-blogs); see the [Shopify page](/platforms/shopify).

## Change a selection without reconnecting

Change the selected Page, organization, board, location or subreddit on an existing account without a second OAuth:

- [Update Facebook Page](/connect/update-facebook-page)
- [Update LinkedIn organization](/connect/update-linkedin-organization)
- [Update Pinterest board](/connect/update-pinterest-boards)
- [Update Google Business Profile location](/connect/update-gmb-location)
- [Update Reddit subreddit](/connect/update-reddit-subreddits)

## If it fails

When the user denies consent or the platform rejects the callback, the browser lands on your `redirect_url` with `error` and `platform` appended, for example `?error=oauth_denied&platform=linkedin`. Treat an unknown `error` value as a generic failure; new values are added without notice.

The connect call itself returns `402` when a billing gate blocks it:

```json
{
  "error": "X (Twitter) requires a payment method due to API pass-through costs. Add a payment method to connect an X account.",
  "code": "PAYMENT_REQUIRED",
  "reason": "twitter_passthrough",
  "dashboard_url": "https://zernio.com/dashboard?tab=billing"
}
```

`reason` is `free_tier_exceeded` (more than the free connected accounts and no card on file), `twitter_passthrough` (any X account without a card) or `enterprise_required` (a contract cap). Send the user to `dashboard_url` to fix it.

## Related

- [List accounts](/accounts/list-accounts): every connected account and its `isActive` state.
- [Update an account](/accounts/update-account): change settings such as default Pages or boards.
- [Account health](/accounts/get-all-accounts-health): verify tokens and permissions.
- [Disconnect an account](/accounts/delete-account): remove it from the profile.
- [Instagram](/platforms/instagram#oauth-scopes): Instagram Login versus Facebook Login.

---
