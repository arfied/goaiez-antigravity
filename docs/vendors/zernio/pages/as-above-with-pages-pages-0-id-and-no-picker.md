# as above with pages["pages"][0]["id"] and no picker.
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/facebook/ads?profileId=66a1f0c2a4b9d3e8f1a2b3c4&headless=true&redirect_url=https://your-app.com/cb" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
