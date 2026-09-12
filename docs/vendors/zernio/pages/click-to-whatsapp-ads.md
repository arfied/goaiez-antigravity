# Click-to-WhatsApp Ads

Keep an existing POST /v1/ads/ctwa integration running, and see what the newer messaging endpoint replaces it with.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Create an ad that opens a WhatsApp conversation with `POST /v1/ads/ctwa`. The endpoint is deprecated and stays available for integrations that already call it; new work goes to [`POST /v1/ads/messaging`](/platforms/meta-ads/messaging-ads). You need `accountId`, `adAccountId` and a Facebook Page paired with a verified WhatsApp Business number.

Optional `whatsappPhoneNumber` selects a number already paired with the Page; omitting it keeps the default pairing. The same selector is available on [messaging ads](/platforms/meta-ads/messaging-ads).

## Create the ad [#click-to-whatsapp-ads]

The call to action is fixed to `WHATSAPP_MESSAGE` and the destination is fixed to `https://api.whatsapp.com/send`; Meta resolves the number from the Page-to-WhatsApp pairing in your Page settings. Zernio builds the campaign, ad set, creative and ad in the one call.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.messagingads.createCtwaAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Summer promo, WhatsApp',
    headline: 'Chat with us on WhatsApp',
    body: 'Get a personal quote in 2 minutes.',
    imageUrl: 'https://cdn.example.com/promo.jpg',
    budgetAmount: 20,
    budgetType: 'daily',
    currency: 'USD',
    countries: ['US', 'ES']
  }
});

const adId = created.ad._id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.messaging_ads.create_ctwa_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Summer promo, WhatsApp",
    headline="Chat with us on WhatsApp",
    body="Get a personal quote in 2 minutes.",
    image_url="https://cdn.example.com/promo.jpg",
    budget_amount=20,
    budget_type="daily",
    currency="USD",
    countries=["US", "ES"],
)

ad_id = created["ad"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/ctwa" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "name": "Summer promo, WhatsApp",
    "headline": "Chat with us on WhatsApp",
    "body": "Get a personal quote in 2 minutes.",
    "imageUrl": "https://cdn.example.com/promo.jpg",
    "budgetAmount": 20,
    "budgetType": "daily",
    "currency": "USD",
    "countries": ["US", "ES"]
  }'
```
</Tab>
</Tabs>

Response (`201`), trimmed:

```json
{
  "adType": "single",
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9e9",
    "name": "Summer promo, WhatsApp",
    "status": "pending_review",
    "reviewStatus": "in_review",
    "platformCampaignId": "120250000000000009",
    "platformAdSetId": "120250000000000010"
  },
  "message": "Ad created"
}
```

`adType` discriminates the response: `single` carries one `ad`, and a `creatives[]` request returns `adType: "multi"` with `ads[]` plus the shared `platformCampaignId` and `platformAdSetId`. To attribute conversions that happen inside the WhatsApp thread, see [Conversions API for business messaging](/platforms/whatsapp/ctwa#conversions-api-for-business-messaging).

`existingPostId` or `objectStoryId` reuses an organic post or reel instead of a fresh creative, in place of `headline` / `body` / `imageUrl` / `video`. See [reuse an existing post](/platforms/meta-ads/messaging-ads#reuse-an-existing-post-instead-of-a-fresh-creative) on the newer endpoint for the shape; it works the same way here.

## Local targeting

The endpoint accepts the full geo shape, not only countries. For a local business, send `cities`, `regions`, `zips`, `metros` or `customLocations` with keys from [the targeting search](/platforms/meta-ads/targeting#look-up-city-and-region-keys), and drop the country that already contains the city, which Meta rejects as an overlap. The `["US"]` default applies only when no geo field at all is present, so the request below targets Milan and nothing else.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "act_1234567890",
  "name": "Milan local",
  "currency": "EUR",
  "cities": [{ "key": "2643743", "radius": 25, "distance_unit": "kilometer" }],
  "dsaBeneficiary": "Acme Srl",
  "dsaPayor": "Acme Srl"
}
```

EU targeting needs `dsaBeneficiary` and `dsaPayor` unless the ad account has defaults set. The fields are the same as on [`POST /v1/ads/create`](/platforms/meta-ads/targeting).

## What to use instead

`POST /v1/ads/messaging` with `destination: "whatsapp"` takes the same body and the same fields, and also covers Messenger and Instagram Direct (`destination: "messenger"` or `"instagram_direct"`). Migrating is one line: change the path and add `destination`. Nothing else in the request changes, so this is the same sample sent to the new endpoint:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "act_1234567890",
  "destination": "whatsapp",
  "name": "Summer promo, WhatsApp",
  "headline": "Chat with us on WhatsApp",
  "body": "Get a personal quote in 2 minutes.",
  "imageUrl": "https://cdn.example.com/promo.jpg",
  "budgetAmount": 20,
  "budgetType": "daily",
  "currency": "USD",
  "countries": ["US", "ES"]
}
```

The response is the same shape as above. [Messaging and call ads](/platforms/meta-ads/messaging-ads) has the full request in Node.js, Python and curl.

## If it fails

A `422` means the Page is not paired with a WhatsApp number:

```json
{
  "error": "Page is not connected to a verified WhatsApp number",
  "type": "invalid_request_error"
}
```

Pair the Page with a verified WhatsApp Business number in Meta Business Manager, then retry; Meta reports the same thing as subcode `2446886`. A `403` with `code: "ads_allowance_exceeded"` means the team has no payment method on file and has reached its 500 free live ads.

## Related

- [Messaging and call ads](/platforms/meta-ads/messaging-ads): the endpoint that replaces this one.
- [WhatsApp](/platforms/whatsapp/ctwa): the conversation side and its conversions API.
- [Targeting](/platforms/meta-ads/targeting): every geo field.
- [Create CTWA ad](/messaging-ads/create-ctwa-ad): every field.

---
