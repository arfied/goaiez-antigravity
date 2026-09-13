# Send the user's browser to connect["authUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/linkedin?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://myapp.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://www.linkedin.com/oauth/v2/authorization?client_id=..."
}
```

After approval the user lands on `redirect_url` with `connected=linkedin&profileId=...&accountId=...&username=...` appended; an existing query string is kept. `redirect_url` must be an absolute http(s) URL or an app scheme such as `myapp://callback`; a relative path is rejected with `400 INVALID_REDIRECT_URL`, and a malformed `profileId` with `400`. The [Start OAuth endpoint](/connect/get-connect-url) lists every parameter.

## Scopes

Zernio requests every scope a platform needs in this single OAuth flow; scopes cannot be requested one at a time. Each platform page lists what its consent screen asks for. To see what a connected account can do with the scopes the user granted, call [Account health](/accounts/get-all-accounts-health): it returns `canPost` and `canFetchAnalytics` per account.

## Platforms requiring secondary selection

Six platforms, one of them conditionally, need the user to pick which Page, organization, board, location or public profile to connect after OAuth:

| Platform | What to select | Endpoints |
|----------|---------------|-----------|
| Facebook | Page | [List Pages](/connect/list-facebook-pages) → [Select Page](/connect/select-facebook-page) |
| LinkedIn | Organization or personal profile | [List orgs](/connect/list-linkedin-organizations) → [Select org](/connect/select-linkedin-organization) |
| Pinterest | Board | [List boards](/connect/list-pinterest-boards-for-selection) → [Select board](/connect/select-pinterest-board) |
| Google Business Profile | Location | [List locations](/connect/list-google-business-locations) → [Select location](/connect/select-google-business-location) |
| Snapchat | Public profile | [List profiles](/connect/list-snapchat-profiles) → [Select profile](/connect/select-snapchat-profile) |
| Instagram (`loginMethod=facebook_login` only) | Page with a linked Instagram account | [List Pages](/connect/list-instagram-pages) → [Select account](/connect/select-instagram-account) |

Instagram is conditional: the default Instagram Login (`loginMethod` omitted) creates the account with no selection step. Only `loginMethod=facebook_login` adds one, because the user has to say which Page to connect. The [Instagram page](/platforms/instagram#oauth-scopes) explains the two methods; both modes below work for it.

### Standard vs headless mode

**Standard mode** (default): Zernio hosts the selection screen. The user picks their Page or organization there, then lands on your `redirect_url`.

**Headless mode**: you build the selection screen. Pass `headless=true` when starting the flow. After OAuth, the user lands on your `redirect_url` with `tempToken`, `userProfile` (URL-encoded JSON), `step=select_page` and `connect_token` query params. Your backend passes them to the list and select endpoints to connect the account.

`step` names the selection endpoint to call next: `select_page` (Facebook), `select_organization` (LinkedIn), `select_board` (Pinterest), `select_location` (Google Business Profile), `select_public_profile` (Snapchat), `select_phone_number` (WhatsApp), `select_account` (Instagram via Facebook Login). Instagram sends no `userProfile`, because its select-account endpoint does not take one.

The headless flow for a Facebook Page starts like any OAuth flow, with `headless: true`:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: start } = await zernio.connect.getConnectUrl({
  path: { platform: 'facebook' },
  query: { profileId, headless: true, redirect_url: 'https://your-app.com/cb' },
});
// Send the user's browser to start.authUrl
```
</Tab>
<Tab value="Python">
```python
start = client.connect.get_connect_url(
    platform="facebook",
    profile_id=profile_id,
    headless=True,
    redirect_url="https://your-app.com/cb",
)
