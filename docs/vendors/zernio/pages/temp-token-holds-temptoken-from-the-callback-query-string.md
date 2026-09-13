# TEMP_TOKEN holds tempToken from the callback query string
curl "https://zernio.com/api/v1/connect/facebook/select-page?profileId=66a1f0c2a4b9d3e8f1a2b3c4&tempToken=$TEMP_TOKEN" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "pages": [
    { "id": "123456789", "name": "My Brand Page", "username": "mybrand", "category": "Brand" }
  ]
}
```

Connect the chosen Page with `POST /v1/connect/facebook/select-page`. `userProfile` is the decoded object, not the encoded string:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: result } = await zernio.connect.facebook.selectFacebookPage({
  body: {
    profileId,
    pageId: pages.pages[0].id,
    tempToken,
    userProfile,
    redirect_url: 'https://your-app.com/final-success',
  },
});
// Send the browser to result.redirect_url
```
</Tab>
<Tab value="Python">
```python
result = client.connect.select_facebook_page(
    profile_id=profile_id,
    page_id=pages["pages"][0]["id"],
    temp_token=temp_token,
    user_profile=user_profile,
    redirect_url="https://your-app.com/final-success",
)
