# Conversions API

Send server-side conversion events to a Meta pixel with POST /v1/ads/conversions, dedupe them against the browser pixel, and read Event Match Quality back.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Send conversions that happen off the website (a deal closed, a lead qualified, a trial converted) to a Meta pixel with `POST /v1/ads/conversions`. They reach Meta's Graph API `events` endpoint, so campaigns can optimize and report on them alongside the events the browser pixel captures.

## Before you start

You need a Meta ads account connected and a pixel to send to ([pixels](/platforms/meta-ads/pixels)). There is no pixel-scoped access token to paste: Zernio uses the ads connection you already have. Send plaintext identifiers; every piece of PII is normalized and hashed with SHA-256 server-side to Meta's spec before anything leaves Zernio.

## Find the pixel to send to

The Conversions API is Meta's server-side twin of the browser pixel. Zernio proxies it at `POST /v1/ads/conversions`, with `destinationId` set to the pixel id. `GET /v1/accounts/{accountId}/conversion-destinations` lists the pixels the connected ad accounts can reach.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: destinations } = await zernio.conversions.listConversionDestinations({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

const destinationId = destinations.destinations[0].id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

destinations = client.conversions.list_conversion_destinations(
    account_id="66b2e19d8c3f5a7e9d0b1c2d"
)

destination_id = destinations["destinations"][0]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/conversion-destinations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "metaads",
  "destinations": [
    { "id": "1729525464415281", "name": "Website pixel", "status": "active" }
  ]
}
```

On Meta a destination is a pixel (dataset), so `destinations[].id` is the same value as `promotedObject.pixelId` on a [conversion campaign](/platforms/meta-ads/conversion-campaigns).

## Send a conversion event

Each event needs `eventName`, `eventTime` (Unix seconds), `eventId` and `user`. `actionSource` says where it happened: `web`, `app`, `offline`, `crm`, `phone_call` or `system_generated`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sent } = await zernio.conversions.sendConversions({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    destinationId: '1729525464415281',
    events: [
      {
        eventName: 'Lead',
        eventTime: 1804064400,
        eventId: 'order_abc_123',
        value: 42.5,
        currency: 'USD',
        actionSource: 'crm',
        user: {
          email: 'customer@example.com',
          phone: '+14155551234',
          firstName: 'Jane',
          lastName: 'Doe',
          country: 'US'
        }
      }
    ]
  }
});
```
</Tab>
<Tab value="Python">
```python
sent = client.conversions.send_conversions(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    destination_id="1729525464415281",
    events=[
        {
            "eventName": "Lead",
            "eventTime": 1804064400,
            "eventId": "order_abc_123",
            "value": 42.5,
            "currency": "USD",
            "actionSource": "crm",
            "user": {
                "email": "customer@example.com",
                "phone": "+14155551234",
                "firstName": "Jane",
                "lastName": "Doe",
                "country": "US",
            },
        }
    ],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/conversions" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "destinationId": "1729525464415281",
    "events": [{
      "eventName": "Lead",
      "eventTime": 1804064400,
      "eventId": "order_abc_123",
      "value": 42.5,
      "currency": "USD",
      "actionSource": "crm",
      "user": {
        "email": "customer@example.com",
        "phone": "+14155551234",
        "firstName": "Jane",
        "lastName": "Doe",
        "country": "US"
      }
    }]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "metaads",
  "eventsReceived": 1,
  "eventsFailed": 0,
  "failures": [],
  "traceId": "AbCdEfGhIjKlMnOp"
}
```

Read `eventsFailed` and `failures[]` to catch a partial failure: each entry carries `eventIndex`, the `eventId` it echoes back, a `message` and a `code`. `traceId` is Meta's `fbtrace_id`, which Meta support can look up.

`eventName` takes a Meta standard event (`Purchase`, `Lead`, `CompleteRegistration`, `AddToCart`, `InitiateCheckout`, `AddPaymentInfo`, `Subscribe`, `StartTrial`, `ViewContent`, `Search`, `Contact`, `SubmitApplication`, `Schedule`) or a custom name of your own.

A commerce event carries `items[]` and `sourceUrl` as well. Zernio turns `items[]` into Meta's `contents` (the `id`, `price` and `quantity` of each row) plus `num_items`, and `sourceUrl` into `event_source_url`:

```json
{
  "eventName": "Purchase",
  "eventTime": 1804064400,
  "eventId": "order_abc_125",
  "value": 129.5,
  "currency": "USD",
  "sourceUrl": "https://shop.example.com/checkout/complete",
  "user": { "email": "customer@example.com" },
  "items": [{ "id": "SKU-991", "name": "Trail runner", "price": 129.5, "quantity": 1, "category": "shoes" }]
}
```

`platformData` carries anything Zernio has not normalized: its keys are shallow-merged into Meta's `custom_data`, and a field Zernio already builds (`value`, `currency`, `contents`, `num_items`) wins the collision. It never reaches `user_data`, so match keys belong on `user`.

## Match keys

The more identifiers on `user`, the higher Meta's match rate. Send plaintext:

| Group | Fields |
|---|---|
| Contact | `email`, `phone`, `firstName`, `lastName` |
| Location and demographics | `country`, `city`, `state`, `zip`, `dob` (YYYYMMDD), `gender` (`f` or `m`) |
| Identifiers | `externalId` (your stable user or device id), `ipAddress`, `userAgent`, `leadId` |
| Click ids, under `user.clickIds` | `fbc` (from the `fbclid` URL parameter), `fbp` (the `_fbp` cookie) |

`ipAddress`, `userAgent`, `fbc` and `fbp` go to Meta unhashed, which is what Meta requires. The highest-signal web keys are `fbc`, `fbp`, `externalId`, `ipAddress` and `userAgent`: capture them at first touch and send them on every event.

## Deduplication

Send a stable `eventId` on every event and the same value from the browser pixel. Meta dedupes the pair within a 48-hour window. A missing or inconsistent `eventId` between pixel and server double-counts the conversion, which is the usual cause of an inflated report.

## Batching and test mode

Up to 1,000 events per request; Zernio chunks a larger batch. On Meta a chunk is all or nothing, so one malformed event rejects the chunk it is in and every rejection shows up in `failures[]`.

`testCode: "TEST12345"` at the root of the request routes the batch to the Test Events tab in Meta Events Manager without touching production pixel data. It is Meta's `test_event_code` passed straight through.

## Consent and limited data use

The batch-level `consent` object drives Meta's Limited Data Use handling:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "destinationId": "1729525464415281",
  "consent": { "adUserData": "DENIED" },
  "events": [
    { "eventName": "Purchase", "eventTime": 1804064400, "eventId": "order_abc_124", "user": { "email": "customer@example.com" } }
  ]
}
```

Either flag set to `DENIED` (`adUserData` or `adPersonalization`) stamps every event in the batch with `data_processing_options: ["LDU"]` plus geolocation mode (`country: 0`, `state: 0`), which tells Meta to work out from the event's own geo signals which US privacy law applies. `GRANTED`, or no `consent` at all, sends the events under Meta's default processing. The same object also drives Google's EEA and UK consent mapping on this endpoint, so split batches by consent state rather than by platform.

## Event match quality

`GET /v1/ads/conversions/quality` reads Meta's Event Match Quality and pixel-to-server coverage back without opening Events Manager. Pass the pixel as `destinationId`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: quality } = await zernio.conversions.getConversionsQuality({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', destinationId: '1729525464415281' }
});
```
</Tab>
<Tab value="Python">
```python
quality = client.conversions.get_conversions_quality(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    destination_id="1729525464415281",
)
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/conversions/quality?accountId=66b2e19d8c3f5a7e9d0b1c2d&destinationId=1729525464415281" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "metaads",
  "rows": [
    {
      "eventName": "Purchase",
      "compositeScore": 6.2,
      "matchKeys": [{ "identifier": "email", "coveragePercentage": 80 }],
      "eventCoveragePercentage": 75
    }
  ]
}
```

One row per event name: `compositeScore` is the EMQ score from 0 to 10, `matchKeys[]` is per-identifier coverage, and `eventCoveragePercentage` is how well the server events dedupe against the pixel. Web events only, because Meta's API exposes web EMQ and not app or offline.

## Common errors

A rejected batch is not an error status. Meta returns `200` and reports the rejection in the body, so branch on `eventsFailed`, never on the status code.

### Partial failure

Response (`200`):

```json
{
  "platform": "metaads",
  "eventsReceived": 0,
  "eventsFailed": 1,
  "failures": [
    { "eventIndex": 0, "eventId": "order_abc_123", "message": "Invalid event_time", "code": 100 }
  ]
}
```

Fix the event at `eventIndex` and resend the chunk; `eventId` makes the retry safe.

A `400` means the request body never reached Meta: `accountId`, `destinationId` or `events` is missing, or an event has a malformed shape. A `403` means the connection has no ads access, which on a legacy plan without ads is expected; ads are included with usage-based billing.

## Related

- [Pixels](/platforms/meta-ads/pixels): create the pixel these events land on.
- [Conversion campaigns](/platforms/meta-ads/conversion-campaigns): optimize an ad set toward the events you send.
- [WhatsApp](/platforms/whatsapp/ctwa#conversions-api-for-business-messaging): the messaging-specific conversions path.
- [Send conversions](/conversions/send-conversions) and [Conversions quality](/conversions/get-conversions-quality): every field.

---
