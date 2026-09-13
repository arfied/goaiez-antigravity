# Business Profile Management

Read and update a Google Business Profile listing through the API, with verification, hours, photos, attributes and action links.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can read and update a Google Business Profile (`googlebusiness`) listing from the API: verification state, opening hours and description, photos, attributes such as delivery and Wi-Fi, and booking and ordering links. Every endpoint lives under `/v1/accounts/{accountId}/gmb-*` and targets the account's selected location; pass `locationId` as a query parameter to target another one the account manages. You need a connected Google Business Profile account (`accountId`).

Verification comes first, because the rest depends on it: reviews, edits and other listing data surface only once the location has Voice of Merchant. Check that before anything else on this page.

- [Verification](#verification): Voice of Merchant state, and how to run a verification.
- [Location details](#location-details): hours, description, website, phone numbers, categories.
- [Photos](#photos): list, add and delete listing media.
- [Attributes](#attributes): the per-category flags such as delivery and Wi-Fi.
- [Action links](#action-links): booking, ordering and appointment URLs.

The service list and food menus have their own page, [Services & Food Menus](/platforms/google-business/services-menus).

## Verification

`GET /v1/accounts/{accountId}/gmb-verifications` returns the location's Voice of Merchant state and its verification history ([Get verification state](/google-business/get-google-business-verifications)). `voiceOfMerchantState.hasVoiceOfMerchant` is `true` when the listing is verified and live on Google; when it is `false`, `verify.hasPendingVerification` tells a verification in progress from one never started.

To verify a location, fetch the methods Google offers with `POST /gmb-verifications/options`, start one with `POST /gmb-verifications`, then submit the code with `POST /gmb-verifications/{verificationId}/complete`. `verificationId` is the last segment of a verification's `name`.

<Callout type="warn">
`POST /gmb-verifications` is a real-world action: Google mails a postcard, places a call or sends an SMS or email to the business. A service-area business must include `context` with its service address on the options call, otherwise Google returns `400`.
</Callout>

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: state } = await zernio.gmbverifications.getGoogleBusinessVerifications({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

const { data: options } = await zernio.gmbverifications.fetchGoogleBusinessVerificationOptions({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { languageCode: 'en-US' }
});

const { data: started } = await zernio.gmbverifications.startGoogleBusinessVerification({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: { method: 'SMS', languageCode: 'en-US', phoneNumber: '+14155550123' }
});

const verificationId = started.verification.name.split('/').pop();

await zernio.gmbverifications.completeGoogleBusinessVerification({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', verificationId },
  body: { pin: '123456' }
});
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

state = client.gmb_verifications.get_google_business_verifications(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

options = client.gmb_verifications.fetch_google_business_verification_options(
    account_id="66b2e19d8c3f5a7e9d0b1c2d", language_code="en-US"
)

started = client.gmb_verifications.start_google_business_verification(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    method="SMS", language_code="en-US", phone_number="+14155550123"
)

verification_id = started["verification"]["name"].split("/")[-1]

client.gmb_verifications.complete_google_business_verification(
    account_id="66b2e19d8c3f5a7e9d0b1c2d", verification_id=verification_id, pin="123456"
)
```
</Tab>
<Tab value="curl">
```bash
curl https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-verifications \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X POST https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-verifications/options \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "languageCode": "en-US" }'

curl -X POST https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/gmb-verifications \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "method": "SMS", "languageCode": "en-US", "phoneNumber": "+14155550123" }'
