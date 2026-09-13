# Conversions

Import offline and enhanced conversions into Google Ads through the Data Manager API with POST /v1/ads/conversions.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page an offline conversion (deal closed, lead qualified, subscription renewed) is imported into Google Ads through the Data Manager API `ingestEvents` method, the endpoint Google recommends for all new integrations as of December 2025 (replacing the legacy `uploadClickConversions`). Zernio uses the Google Ads account you already connected: no additional OAuth, no developer token to apply for.

The same endpoint handles both attribution paths: click attribution, when you include a `gclid`, `gbraid` or `wbraid` from the originating ad click, and Enhanced Conversions for Leads, when you include a hashed email or phone because no click id is available. PII is SHA-256 hashed server-side. For `@gmail.com` and `@googlemail.com` addresses Zernio strips dots and `+suffix` from the local part before hashing, per Google's spec, so `john.doe+promo@gmail.com` matches `johndoe@gmail.com`.

## Step 1: find a conversion action

Call `GET /v1/accounts/{accountId}/conversion-destinations`. It returns every enabled conversion action across the accessible customers.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: destinations } = await zernio.conversions.listConversionDestinations({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' }
});

console.log(destinations.destinations);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

destinations = client.conversions.list_conversion_destinations(account_id="66b2e19d8c3f5a7e9d0b1c2d")

print(destinations["destinations"])
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
  "platform": "googleads",
  "destinations": [
    { "id": "customers/1234567890/conversionActions/987654321", "name": "Offline purchase", "type": "PURCHASE", "status": "active" }
  ]
}
```

`id` is the conversion action resource name, and `type` its category (`PURCHASE`, `LEAD`, `SIGN_UP`, ...). Google locks the event type to the conversion action, not to the per-event `eventName`. When no listed action fits, [create one for uploads](#create-a-conversion-action-for-uploads).

## Step 2: send a conversion event

Call `POST /v1/ads/conversions` with `accountId`, the resource name as `destinationId` and the events. `eventTime` is unix seconds in UTC, not milliseconds: `1798848000` is 2027-01-02T00:00:00Z. A millisecond value lands the conversion thousands of years in the future, where Google drops it. `adjustmentTime` on the adjustments call below uses the same unit.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sent } = await zernio.conversions.sendConversions({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    destinationId: 'customers/1234567890/conversionActions/987654321',
    consent: { adUserData: 'GRANTED', adPersonalization: 'GRANTED' },
    events: [{
      eventName: 'Purchase',
      eventTime: 1798848000,
      eventId: 'order_abc_123',
      value: 129.99,
      currency: 'USD',
      user: {
        email: 'customer@example.com',
        phone: '+14155551234',
        clickIds: { gclid: 'EAIaIQobChMI...' }
      }
    }]
  }
});

console.log(sent.eventsReceived, sent.eventsFailed);
```
</Tab>
<Tab value="Python">
```python
sent = client.conversions.send_conversions(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    destination_id="customers/1234567890/conversionActions/987654321",
    consent={"adUserData": "GRANTED", "adPersonalization": "GRANTED"},
    events=[{
        "eventName": "Purchase",
        "eventTime": 1798848000,
        "eventId": "order_abc_123",
        "value": 129.99,
        "currency": "USD",
        "user": {
            "email": "customer@example.com",
            "phone": "+14155551234",
            "clickIds": {"gclid": "EAIaIQobChMI..."},
        },
    }],
)

print(sent["eventsReceived"], sent["eventsFailed"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/conversions" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "destinationId": "customers/1234567890/conversionActions/987654321",
    "consent": { "adUserData": "GRANTED", "adPersonalization": "GRANTED" },
    "events": [{
      "eventName": "Purchase",
      "eventTime": 1798848000,
      "eventId": "order_abc_123",
      "value": 129.99,
      "currency": "USD",
      "user": {
        "email": "customer@example.com",
        "phone": "+14155551234",
        "clickIds": { "gclid": "EAIaIQobChMI..." }
      }
    }]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "googleads",
  "eventsReceived": 1,
  "eventsFailed": 0,
  "failures": [],
  "traceId": "a1b2c3d4-..."
}
```

`traceId` is Google's request id; quote it if conversions do not appear as expected. Conversions take up to 3 hours to appear in Google Ads reports. Each request takes up to 2,000 events, and Zernio chunks larger batches automatically.

### EEA and UK consent

Google enforces consent signaling for European users under the February 2026 Data Manager restrictions. Pass `consent` at the request root with `adUserData` and `adPersonalization` set to `GRANTED` or `DENIED`. Omit the field for non-EEA requests where you have no consent data; Google applies region defaults.

### Deduplication

Pass a stable `eventId` on every event. Zernio maps it to Google's `transactionId`, so repeated uploads of the same conversion are deduped.

## Create a conversion action for uploads

When no existing action fits, call `POST /v1/accounts/{accountId}/conversion-destinations` with `adAccountId`, `name` and `type` (a unified name such as `Purchase`, `Subscribe`, `CompleteRegistration`, `Lead` or `Schedule`, or a Google `ConversionActionCategory` value such as `PURCHASE`, `SUBSCRIBE_PAID`, `SIGNUP`, `IMPORTED_LEAD` or `BOOK_APPOINTMENT`). Google creates it with `type=UPLOAD_CLICKS`, which is immutable after creation. Calling again with the same name reuses the existing action; the same name with a different category returns `409` with code `IDEMPOTENCY_CONFLICT`. `countingType` (`MANY_PER_CLICK`, the default, or `ONE_PER_CLICK`) and `primaryForGoal` (default `false`, so the action is record-only until you opt it into Smart Bidding) are Google-only.

```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/conversion-destinations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "adAccountId": "1234567890", "name": "Offline purchase", "type": "Purchase" }'
```

Response (`201`):

```json
{
  "platform": "googleads",
  "destination": { "id": "customers/1234567890/conversionActions/987654321", "name": "Offline purchase", "type": "PURCHASE", "status": "active" }
}
```

The `id` it returns is the `destinationId` for the upload above.

## Website tag actions

Website conversion actions with their tag snippets (the gtag.js code a customer pastes onto their site) are a separate surface from the upload actions above: `GET /v1/ads/conversions/actions` lists them and `POST /v1/ads/conversions/actions` creates a `WEBPAGE` action ([List conversion actions](/conversions/list-conversion-actions)).

## Adjust an uploaded conversion

Call `POST /v1/ads/conversions/adjustments` to retract a conversion (refund, chargeback, churn), restate its value, or enhance it with first-party identifiers after the fact. Identify the original by `orderId` (the `eventId` you sent; required for `ENHANCEMENT`) or by `gclid` plus `conversionTime`. `RESTATEMENT` takes the corrected total in `restatementValue`, not a delta.

```bash
curl -X POST "https://zernio.com/api/v1/ads/conversions/adjustments" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "destinationId": "customers/1234567890/conversionActions/987654321",
    "adjustments": [{ "adjustmentType": "RETRACTION", "adjustmentTime": 1799107200, "orderId": "order_abc_123" }]
  }'
```

Response (`200`):

```json
{ "platform": "googleads", "adjustmentsReceived": 1, "adjustmentsFailed": 0, "failures": [] }
```

Adjustments go through the classic Google Ads API, so this endpoint is Google-only; Meta and LinkedIn return `405`.

## If it fails

`eventsFailed` above 0 means Google rejected some events; `failures[]` names each one by `eventIndex` and `eventId`:

```json
{
  "platform": "googleads",
  "eventsReceived": 0,
  "eventsFailed": 1,
  "failures": [
    { "eventIndex": 0, "eventId": "order_abc_123", "message": "Consent is required for EEA users", "code": "CONSENT_REQUIRED" }
  ],
  "traceId": "a1b2c3d4-..."
}
```

The status is `200` even on partial failure, so branch on `eventsFailed`, fix the named events and resend only those.

## Related

- [Google Ads tracking tags](/platforms/google-ads/tracking-tags): click-URL parameters on the campaign.
- [LinkedIn Conversions API](/platforms/linkedin-ads/conversions) and [Meta Ads Conversions API](/platforms/meta-ads/capi): the same endpoint on the other networks.
- [Send conversions](/conversions/send-conversions) and [Adjust uploaded conversions](/conversions/adjust-conversions): every field.

---
