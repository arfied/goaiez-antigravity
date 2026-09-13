# Conversions API

Send offline conversion events to LinkedIn with POST /v1/ads/conversions, manage conversion rules, and read attribution back.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page an offline conversion (deal closed, lead qualified, trial converted, purchase) reaches LinkedIn through `POST /v1/ads/conversions`, the same endpoint you use for Meta and Google. Zernio uses the LinkedIn account you already connected, so there is no separate Conversions API token to generate in Campaign Manager. Send PII as plaintext: Zernio SHA-256 hashes it per LinkedIn's spec before anything reaches LinkedIn (`externalId` is passed through as plaintext, per LinkedIn's requirement).

<Callout type="warn">
Accounts connected before Zernio shipped Conversions API support must reconnect. LinkedIn does not upgrade an existing OAuth grant with the `rw_conversions` scope; accounts that lack it return `403` with code `linkedin_reconnect_required`. One pass through the LinkedIn connect flow fixes it.
</Callout>

## Step 1: find or create a conversion rule

Call `GET /v1/accounts/{accountId}/conversion-destinations`. It returns every `CONVERSIONS_API` rule across every sponsored ad account the connected token can access, each with the `adAccountId` you pass back on later calls.

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
  "platform": "linkedinads",
  "destinations": [
    { "id": "25639412", "name": "Trial activation", "type": "LEAD", "status": "active", "adAccountId": "517258773" }
  ]
}
```

To create a rule, call `POST /v1/accounts/{accountId}/conversion-destinations`. A LinkedIn rule binds an event `type` (`LEAD`, `PURCHASE`, `ADD_TO_CART`, ...) to the destination. By default the new rule is auto-associated with every campaign in the ad account; pass `autoAssociationType: "OBJECTIVE_BASED"` to associate only the campaigns whose objective matches the rule type, or `"NONE"` to opt out and manage associations explicitly (Step 3).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: rule } = await zernio.conversions.createConversionDestination({
  path: { accountId: '66b2e19d8c3f5a7e9d0b1c2d' },
  body: {
    adAccountId: '517258773',
    name: 'Trial activation',
    type: 'Lead',
    attributionType: 'LAST_TOUCH_BY_CAMPAIGN'
  }
});

console.log(rule.destination.id);
```
</Tab>
<Tab value="Python">
```python
rule = client.conversions.create_conversion_destination(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="517258773",
    name="Trial activation",
    type="Lead",
    attribution_type="LAST_TOUCH_BY_CAMPAIGN",
)

print(rule["destination"]["id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/conversion-destinations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "adAccountId": "517258773",
    "name": "Trial activation",
    "type": "Lead",
    "attributionType": "LAST_TOUCH_BY_CAMPAIGN"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "platform": "linkedinads",
  "destination": { "id": "25639412", "name": "Trial activation", "type": "LEAD", "status": "active", "adAccountId": "517258773" }
}
```

The attribution windows keep LinkedIn's defaults unless you set them: `postClickAttributionWindowSize` (default 30 days) and `viewThroughAttributionWindowSize` (default 7 days) each take 1, 7, 30, 90 or 365, and 365 only for the `LEAD`, `PURCHASE`, `ADD_TO_CART`, `QUALIFIED_LEAD` and `SUBMIT_APPLICATION` rule types.

`adAccountId` is numeric or `urn:li:sponsoredAccount:...`. `type` is a unified name or a LinkedIn enum (`"QUALIFIED_LEAD"`, `"OUTBOUND_CLICK"`) for types outside the unified set:

| Unified | LinkedIn |
|---|---|
| `Purchase` | `PURCHASE` |
| `Lead` | `LEAD` |
| `CompleteRegistration` | `COMPLETE_SIGNUP` |
| `AddToCart` | `ADD_TO_CART` |
| `InitiateCheckout` | `START_CHECKOUT` |
| `AddPaymentInfo` | `ADD_BILLING_INFO` |
| `Subscribe` | `SUBSCRIBE` |
| `StartTrial` | `START_TRIAL` |
| `ViewContent` | `VIEW_CONTENT` |
| `Search` | `SEARCH` |
| `Contact` | `CONTACT` |
| `SubmitApplication` | `SUBMIT_APPLICATION` |
| `Schedule` | `SCHEDULE` |

Creation is not idempotent on LinkedIn: a retry creates a second rule.

## Step 2: send a conversion event

Call `POST /v1/ads/conversions` with `accountId`, the rule id as `destinationId` and the events.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sent } = await zernio.conversions.sendConversions({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    destinationId: rule.destination.id,
    events: [{
      eventName: 'Lead',
      eventTime: 1798848000,
      eventId: 'order_abc_123',
      value: 42.5,
      currency: 'USD',
      user: {
        email: 'customer@example.com',
        firstName: 'Jane',
        lastName: 'Doe',
        country: 'US',
        clickIds: { li_fat_id: 'AQH...' }
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
    destination_id=rule["destination"]["id"],
    events=[{
        "eventName": "Lead",
        "eventTime": 1798848000,
        "eventId": "order_abc_123",
        "value": 42.5,
        "currency": "USD",
        "user": {
            "email": "customer@example.com",
            "firstName": "Jane",
            "lastName": "Doe",
            "country": "US",
            "clickIds": {"li_fat_id": "AQH..."},
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
    "destinationId": "25639412",
    "events": [{
      "eventName": "Lead",
      "eventTime": 1798848000,
      "eventId": "order_abc_123",
      "value": 42.5,
      "currency": "USD",
      "user": {
        "email": "customer@example.com",
        "firstName": "Jane",
        "lastName": "Doe",
        "country": "US",
        "clickIds": { "li_fat_id": "AQH..." }
      }
    }]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "platform": "linkedinads",
  "eventsReceived": 1,
  "eventsFailed": 0,
  "failures": []
}
```

`eventName` is informational on LinkedIn: the rule's `type`, set at creation, decides the conversion category. `clickIds.li_fat_id` is optional and improves the match rate; capture it from `li_fat_id` on landing-page URLs after enabling enhanced conversion tracking on the LinkedIn Insight Tag.

### User identifiers

LinkedIn requires at least one of: a SHA-256-hashed email, a LinkedIn first-party click id (`li_fat_id`), an Acxiom or Oracle MOAT identifier, or both `firstName` and `lastName`. Send as many as you have; hashed identifiers improve the match rate.

### Deduplication

Pass a stable `eventId` on every event. If you also fire the LinkedIn Insight Tag with the same event id, LinkedIn discards the Conversions API copy and counts only the Insight Tag event ([Idempotency](/guides/idempotency) covers the same rule on posts).

## Step 3: associate campaigns

By default `createConversionDestination` auto-associates the new rule with every active, paused and draft campaign in the ad account (`autoAssociationType: "ALL_CAMPAIGNS"`). Auto-association runs once at create time, so campaigns added later need an explicit association with `POST /v1/accounts/{accountId}/conversion-destinations/{destinationId}/associations`:

```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/conversion-destinations/25639412/associations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "adAccountId": "517258773", "campaignIds": ["337643194", "345396555"] }'
```

Response (`200`), a per-campaign result so you can retry only the rows that failed:

```json
{
  "platform": "linkedinads",
  "succeeded": ["337643194"],
  "failed": [{ "campaignId": "345396555", "reason": "Campaign objective does not match the rule type" }]
}
```

## Attribution metrics

Read conversion attribution back from LinkedIn's adAnalytics endpoint, pivoted by date, with `GET /v1/accounts/{accountId}/conversion-destinations/{destinationId}/metrics`:

```bash
curl "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/conversion-destinations/25639412/metrics?adAccountId=517258773&startDate=2027-01-01&endDate=2027-01-31&granularity=DAILY" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "platform": "linkedinads",
  "granularity": "DAILY",
  "rows": [
    {
      "start": "2027-01-01",
      "end": "2027-01-01",
      "metrics": {
        "externalWebsiteConversions": 4,
        "externalWebsitePostClickConversions": 3,
        "externalWebsitePostViewConversions": 1,
        "conversionValueInLocalCurrency": 170,
        "qualifiedLeads": 0,
        "costInLocalCurrency": 38.2
      }
    }
  ]
}
```

LinkedIn's retention rules apply: `granularity=DAILY` covers roughly the last 6 months; `MONTHLY` and `YEARLY` extend to 24 months; `granularity=ALL` with a range over 6 months rounds to month boundaries.

## Soft delete

LinkedIn does not expose a hard delete on conversion rules. `DELETE /v1/accounts/{accountId}/conversion-destinations/{destinationId}?adAccountId=517258773` flips `enabled: false` (the same operation Campaign Manager's delete performs), and the rule remains fetchable as `status: "inactive"`.

## Limits

- LinkedIn's `BATCH_CREATE` cap is 5,000 events, and Zernio chunks a larger `events` array into 5,000-event calls for you. Each chunk is all-or-nothing on LinkedIn's side.
- 600 requests per minute and 300,000 per day per token (LinkedIn-side).
- `eventTime` must be within the last 90 days. Zernio does not enforce the bound, so an older event reaches LinkedIn and is rejected there.

## If it fails

A `403` with code `linkedin_reconnect_required` on any call here means the connection lacks `rw_conversions`:

```json
{
  "error": "The LinkedIn connection lacks the rw_conversions scope. Reconnect the account.",
  "type": "permission_error",
  "code": "linkedin_reconnect_required"
}
```

Send the user through the LinkedIn connect flow again ([scopes](/guides/connecting-accounts#scopes)) and repeat the call.

## Related

- [Analytics](/platforms/linkedin-ads/analytics): firmographic breakdowns of the same campaigns.
- [Meta Ads Conversions API](/platforms/meta-ads/capi) and [Google Ads conversions](/platforms/google-ads/conversions): the same endpoint on the other networks.
- [Send conversions](/conversions/send-conversions) and [Create a conversion destination](/conversions/create-conversion-destination): every field.

---
