# Send the user's browser to start["authUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/facebook?profileId=66a1f0c2a4b9d3e8f1a2b3c4&headless=true&redirect_url=https://your-app.com/cb" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://www.facebook.com/v21.0/dialog/oauth?client_id=..."
}
```

After consent, Meta sends the user to `https://your-app.com/cb?profileId=...&tempToken=...&userProfile=...&platform=facebook&step=select_page&connect_token=...`. In that handler, read `tempToken` and `userProfile` from the request URL, then list the Pages the user manages:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
// requestUrl: the URL your handler received; searchParams decodes the values
const { tempToken, userProfile: encodedProfile } = Object.fromEntries(new URL(requestUrl).searchParams);
const userProfile = JSON.parse(encodedProfile);

const { data: pages } = await zernio.connect.facebook.listFacebookPages({
  query: { profileId, tempToken },
});
```
</Tab>
<Tab value="Python">
```python
import json
from urllib.parse import parse_qs, urlparse
