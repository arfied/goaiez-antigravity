# Buying a Number

Search a country's inventory with GET /v1/phone-numbers/available, buy a number with POST /v1/phone-numbers/purchase, then enable SMS, WhatsApp and voice routing on it.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you own a number that is active and billing, with its id for the feature endpoints. You need a profile id, usage-based billing and a payment method on file. Search confirms stock and capabilities in a country or prefix, with the monthly price on [`GET /v1/phone-numbers/countries`](/platforms/phone-numbers/availability#pricing-by-country); purchase draws from the same pool and auto-assigns a number, or buys one exact number you pass from the results.

## Step 1: Search available numbers

Call `GET /v1/phone-numbers/available` with `country` (default `US`). It queries the carrier's live inventory. Voice is always in the filter; `sms=true` draws only from the SMS-capable pool. Each result carries its full capability set and its `locality` (the town the number belongs to). Numbers a purchase would refuse are left out. When too few numbers match, the carrier pads the list with nearby ones and marks them `bestEffort: true`, so drop those when the prefix or town matters.

| Param | Purpose |
|---|---|
| `country` | ISO-2 country, must be [offerable](/platforms/phone-numbers/availability) |
| `type` | Number type; defaults to the country's WhatsApp-safe type |
| `prefix` | Area code or national dialing code, for example `415` |
| `locality` | City name |
| `contains` | Digit pattern the number must contain |
| `sms` | `true` to return SMS-capable numbers only |
| `limit` | Max results (default 20, max 100) |

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const profileId = '66a1f0c2a4b9d3e8f1a2b3c4';

const { data: inventory } = await zernio.phonenumbers.searchAvailablePhoneNumbers({
  query: { country: 'US', prefix: '415', sms: true, limit: 10 }
});
for (const n of inventory.numbers) {
  console.log(n.phoneNumber, n.features);
}
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
profile_id = "66a1f0c2a4b9d3e8f1a2b3c4"

inventory = client.phone_numbers.search_available_phone_numbers(
    country="US", prefix="415", sms=True, limit=10
)
for n in inventory["numbers"]:
    print(n["phoneNumber"], n["features"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/phone-numbers/available?country=US&prefix=415&sms=true&limit=10" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "country": "US",
  "numberType": "local",
  "requireSms": true,
  "numbers": [
    { "phoneNumber": "+14155550100", "features": ["voice", "sms", "mms"] },
    { "phoneNumber": "+14155550101", "features": ["voice", "sms", "mms"] }
  ]
}
```

## Step 2: Purchase

Call `POST /v1/phone-numbers/purchase` with `profileId` and `country`. With usage-based billing and a payment method on file, the number provisions in the same request and starts billing per month on your usage-based invoice. `wantsSms: true` draws an SMS-capable number and flags it for SMS (default off). `connectWhatsapp` defaults to on, so for a Calls/SMS-only number send `connectWhatsapp: false`.

To buy one exact number, pass a search result's `phoneNumber` (E.164). It is a hard constraint: when that number is gone by the time you buy, or WhatsApp's buy-time check rejects it, the purchase fails with `409` and `code: "PHONE_NUMBER_UNAVAILABLE"` instead of assigning another, so search again and pick another. It only works where the number activates instantly; a regulated country or type, which answers `202` with `kyc_required`, returns `400` when `phoneNumber` is set.

Without `phoneNumber` the assignment is Zernio's to make, and 2 optional fields narrow it. `areaCode` (1 to 4 digits, listed as `areaOptions` by [`GET /v1/phone-numbers/availability`](/platforms/phone-numbers/availability#check-one-country)) is a hard constraint: the purchase fails with a `409` rather than hand you a number in another area, and a later replacement stays in the same area. `wantsWhatsapp: true` declares WhatsApp intent on a standalone purchase (`connectWhatsapp: false`), so a number WhatsApp rejects at buy time is swapped for an eligible one during the purchase instead of arriving without WhatsApp.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: purchase } = await zernio.phonenumbers.purchasePhoneNumber({
  body: {
    profileId,
    country: 'US',
    wantsSms: true,
    connectWhatsapp: false,
  }
});
if (purchase.status === 'kyc_required') {
  console.log('Regulated country, collect KYC at', purchase.kycUrl);
} else {
  console.log('Provisioned', purchase.phoneNumber.phoneNumber, purchase.phoneNumber.id);
}
```
</Tab>
<Tab value="Python">
```python
purchase = client.phone_numbers.purchase_phone_number(
    profile_id=profile_id, country="US", wants_sms=True, connect_whatsapp=False
)
if purchase.get("status") == "kyc_required":
    print("Regulated country, collect KYC at", purchase["kycUrl"])
else:
    print("Provisioned", purchase["phoneNumber"]["phoneNumber"], purchase["phoneNumber"]["id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/purchase" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"profileId": "66a1f0c2a4b9d3e8f1a2b3c4", "country": "US", "wantsSms": true, "connectWhatsapp": false}'
```
</Tab>
</Tabs>

To buy the first number from Step 1's search instead of an auto-assigned one, add `phoneNumber`:

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/purchase" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"profileId": "66a1f0c2a4b9d3e8f1a2b3c4", "country": "US", "phoneNumber": "+14155550123", "connectWhatsapp": false}'
```

Response (`200`):

```json
{
  "message": "Phone number provisioned",
  "phoneNumber": {
    "id": "66d4e5f6a7b8c9d0e1f2a3b4",
    "phoneNumber": "+14155550100",
    "status": "active",
    "country": "US",
    "provisionedAt": "2027-01-01T12:00:00Z",
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4"
  }
}
```

`phoneNumber.id` is the `{id}` for every feature endpoint. One number goes on one profile: when the requested profile already holds a number, Zernio assigns the next free profile (or creates one) and returns it in `profileId`.

A regulated country returns `202` instead, and the number is ordered once the identity check clears:

```json
{
  "status": "kyc_required",
  "country": "GB",
  "numberType": "local",
  "kycUrl": "https://zernio.com/kyc/..."
}
```

Send the customer to `kycUrl`, or run the [KYC flow](/platforms/phone-numbers/kyc) yourself. If nothing is in stock when the form is submitted, the submission becomes a [pre-order](/platforms/phone-numbers/kyc#out-of-stock-pre-order-it) instead of failing.

Purchasing is not idempotent by default. Send a `purchaseIntentId` (any string, one per intended purchase): a retry with the same key returns `{ "status": "already_purchased", "numberId", "phoneNumber", "profileId" }` instead of buying a second number, and it survives the add-payment-method round trip. Separately, any second purchase within 10 minutes returns `409` with `code: "PURCHASE_VELOCITY"`; pass `allowMultiple: true` to confirm bulk provisioning is intentional.

The cap is your profile limit, and 50 where profiles are uncapped. The count includes every number that is provisioning, verifying, active, pending payment or pending regulatory review, and a purchase past the cap returns `400`.

## Step 3: Enable features

A fresh number already has Calls on. Add the rest from the number's own routes:

- SMS: [enable and register](/platforms/sms/registration) with `POST /v1/phone-numbers/{id}/sms`. US numbers need an approved carrier registration before messages deliver.
- WhatsApp: [connect to a WABA](/platforms/whatsapp/connection).
- Voice routing: [set the forward destination](/platforms/voice/setup), IVR and voicemail with `POST /v1/phone-numbers/{id}/voice`.

## Manage numbers

Call `GET /v1/phone-numbers` for every number on your team (released numbers excluded by default; filter with `status` or `profileId`), `GET /v1/phone-numbers/{id}` for one, and `DELETE /v1/phone-numbers/{id}` to release one. Releasing stops the monthly charge and returns the number to the carrier pool; it cannot be undone.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: owned } = await zernio.phonenumbers.listPhoneNumbers();
for (const n of owned.numbers) {
  console.log(n._id, n.phoneNumber, n.status, n.monthlyCents);
}

await zernio.phonenumbers.releasePhoneNumber({ path: { id: '66d4e5f6a7b8c9d0e1f2a3b4' } });
```
</Tab>
<Tab value="Python">
```python
owned = client.phone_numbers.list_phone_numbers()
for n in owned["numbers"]:
    print(n["_id"], n["phoneNumber"], n["status"], n["monthlyCents"])

client.phone_numbers.release_phone_number(id="66d4e5f6a7b8c9d0e1f2a3b4")
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/phone-numbers" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X DELETE "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`) of the list:

```json
{
  "numbers": [
    {
      "_id": "66d4e5f6a7b8c9d0e1f2a3b4",
      "phoneNumber": "+14155550100",
      "country": "US",
      "status": "active",
      "monthlyCents": 300,
      "hostedByZernio": true,
      "sipTrunkId": null,
      "provisionedAt": "2027-01-01T12:00:00Z"
    }
  ],
  "connected": []
}
```

`monthlyCents` is stamped at purchase, so a number keeps its price when the rate card changes. `connected` lists WhatsApp numbers you brought yourself through embedded signup; they are not billed and SMS and Calls cannot be enabled on them.

Response (`200`) of the release:

```json
{
  "message": "Phone number released",
  "phoneNumber": {
    "id": "66d4e5f6a7b8c9d0e1f2a3b4",
    "phoneNumber": "+14155550100",
    "status": "released",
    "releasedAt": "2027-02-01T12:00:00Z"
  }
}
```

## If it fails

A `402` with `code: "PAYMENT_REQUIRED"` on purchase means there is no payment method on file:

```json
{
  "error": "A payment method is required to purchase a phone number.",
  "type": "invalid_request_error",
  "code": "PAYMENT_REQUIRED"
}
```

Add a card in the [dashboard](https://zernio.com/dashboard?tab=billing), then retry with the same `purchaseIntentId`. A `409` with `code: "AREA_CODE_UNAVAILABLE"` means the requested `areaCode` has no deliverable inventory; pick another area or omit it. A `409` with `code: "PHONE_NUMBER_UNAVAILABLE"` means the `phoneNumber` you picked is no longer available; search again and pick another. A `409` on release with `code: "invalid_resource_state"` means the number is attached to a SIP trunk; detach it first. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Porting](/platforms/phone-numbers/porting): bring a number you already own instead of buying.
- [KYC](/platforms/phone-numbers/kyc): the identity step for regulated countries.
- [Availability and pricing](/platforms/phone-numbers/availability): countries, types and prices.
- [Purchase phone number](/phone-numbers/purchase-phone-number) and [List phone numbers](/phone-numbers/list-phone-numbers): every field.
- [Phone number webhooks](/webhooks/phone-numbers): `whatsapp.number.activated` and the other status events.

---
