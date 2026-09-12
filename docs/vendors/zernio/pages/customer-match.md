# Customer Match

Create a Google Customer Match audience from a CRM email list with POST /v1/ads/audiences and a hashed member upload.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a CRM list is a Google Customer Match audience you can target. Create the empty `customer_list` audience with `POST /v1/ads/audiences`, then upload members with `POST /v1/ads/audiences/{audienceId}/users`. `adAccountId` is the Google Ads customer id (no dashes). Google matches on email only (any `phone` is ignored), and values are SHA-256 hashed server-side before upload.

## Create and fill the audience

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adaudiences.createAdAudience({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '1234567890',
    type: 'customer_list',
    name: 'Newsletter subscribers'
  }
});

const { data: upload } = await zernio.adaudiences.addUsersToAdAudience({
  path: { audienceId: created.audience.id },
  body: { users: [{ email: 'jane@example.com' }, { email: 'sam@example.com' }] }
});

console.log(upload.numReceived);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.ad_audiences.create_ad_audience(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="1234567890",
    type="customer_list",
    name="Newsletter subscribers",
)

upload = client.ad_audiences.add_users_to_ad_audience(
    audience_id=created["audience"]["id"],
    users=[{"email": "jane@example.com"}, {"email": "sam@example.com"}],
)

print(upload["numReceived"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/audiences" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "adAccountId": "1234567890", "type": "customer_list", "name": "Newsletter subscribers" }'

curl -X POST "https://zernio.com/api/v1/ads/audiences/66e5b2c3d4f5a6b7c8d9e0f1/users" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "users": [{ "email": "jane@example.com" }, { "email": "sam@example.com" }] }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "audience": {
    "id": "66e5b2c3d4f5a6b7c8d9e0f1",
    "name": "Newsletter subscribers",
    "type": "customer_list",
    "platform": "google"
  },
  "message": "Audience created"
}
```

The create is not idempotent, so never auto-retry it.

Response (`200`):

```json
{ "message": "Users added", "numReceived": 2, "numInvalid": 0 }
```

Each upload takes up to 10,000 users.

A `200` means Google accepted the rows, not that the audience can be targeted. Google matches the hashed emails against active Google accounts, which takes from about 10 minutes to 24 hours, and it serves a Customer Match list only once the matched list holds enough active users at the moment the ad is served. Google's own guidance is to upload at least 5,000 members to clear that threshold ([Customer Match](https://developers.google.com/google-ads/api/docs/remarketing/audience-types/customer-match)). A small test list that reports `numInvalid: 0` and then never serves is that threshold, not a failed upload.

To target the audience, pass its `platformAudienceId` (from `GET /v1/ads/audiences?accountId=&adAccountId=`) in `audienceInclude` on `POST /v1/ads/create`.

## If it fails

A `422` on the upload means the audience is not a `customer_list` or has no platform id yet:

```json
{
  "error": "Audience is not a customer_list type or has no platform ID yet",
  "type": "invalid_request_error",
  "code": "invalid_field_value",
  "param": "audienceId"
}
```

## Related

- [Create ads](/platforms/google-ads/create-ads): target the audience from a campaign.
- [Create custom audience](/ad-audiences/create-ad-audience) and [Add users to audience](/ad-audiences/add-users-to-ad-audience): every field.
- [LinkedIn Matched Audiences](/platforms/linkedin-ads/audiences): the same endpoint on LinkedIn.

---
