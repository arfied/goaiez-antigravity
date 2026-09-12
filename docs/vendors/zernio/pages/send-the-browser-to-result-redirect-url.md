# Send the browser to result["redirect_url"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/connect/facebook/select-page" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "pageId": "123456789",
    "tempToken": "'"$TEMP_TOKEN"'",
    "userProfile": { "id": "1234567890", "username": "mybrand", "displayName": "My Brand Page" },
    "redirect_url": "https://your-app.com/final-success"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "message": "Facebook page connected successfully",
  "redirect_url": "https://your-app.com/final-success?connected=facebook&profileId=66a1f0c2a4b9d3e8f1a2b3c4&accountId=66b2e19d8c3f5a7e9d0b1c2d&username=My+Brand+Page",
  "account": {
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "facebook",
    "username": "mybrand",
    "isActive": true,
    "selectedPageName": "My Brand Page"
  }
}
```

`account.accountId` is the new `accountId`.

### Connect Meta Ads only (skip the Page picker)

For the classic login (`loginMode=classic`, the default), if your user only needs ads (audience uploads, the [Conversions API](/platforms/meta-ads/capi), analytics, listing ads and campaigns), start at `GET /v1/connect/facebook/ads` instead and let your backend pick the first Page without showing a picker. The user goes from Meta's consent screen straight to your "Connected" screen. To connect ads independently of a posting account, use [Facebook Login for Business](#facebook-login-for-business).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: adsStart } = await zernio.connect.connectAds({
  path: { platform: 'facebook' },
  query: { profileId, headless: true, redirect_url: 'https://your-app.com/cb' },
});
// Send the user's browser to adsStart.authUrl, then list and select
// as above with pages.pages[0].id and no picker.
```
</Tab>
<Tab value="Python">
```python
ads_start = client.connect.connect_ads(
    platform="facebook",
    profile_id=profile_id,
    headless=True,
    redirect_url="https://your-app.com/cb",
)
