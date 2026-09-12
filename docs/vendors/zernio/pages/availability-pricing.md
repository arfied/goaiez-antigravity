# Availability & Pricing

Read which countries and number types Zernio sells, what each costs per month, and which capabilities each supports, from GET /v1/phone-numbers/countries.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can drive a country picker from `GET /v1/phone-numbers/countries`, check one country's live stock and address rule with `GET /v1/phone-numbers/availability`, and know what a number costs before you buy it. You need an API key and usage-based billing, which telephony requires. Numbers are available in <NumberCountryCount /> countries; each has a monthly price and one or more number types, each with its own price and capabilities. The endpoint is the live source of truth and the tables below are a snapshot.

## Check availability (live)

Call `GET /v1/phone-numbers/countries`. It returns every offerable country, cheapest first, with its current monthly price and a flag per capability.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: offer } = await zernio.phonenumbers.listPhoneNumberCountries();
for (const c of offer.countries) {
  console.log(c.code, `$${(c.monthlyCents / 100).toFixed(2)}/mo`, {
    calls: c.callsAvailable,
    sms: c.smsAvailable,
    whatsapp: c.whatsappAvailable,
    kyc: c.needsKyc,
    inStock: c.inStock,
  });
}
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

offer = client.phone_numbers.list_phone_number_countries()
for c in offer["countries"]:
    print(c["code"], f"${c['monthlyCents'] / 100:.2f}/mo",
          {"calls": c["callsAvailable"], "sms": c["smsAvailable"],
           "whatsapp": c["whatsappAvailable"], "kyc": c["needsKyc"], "inStock": c["inStock"]})
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/phone-numbers/countries" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "countries": [
    {
      "code": "US",
      "tier": 1,
      "monthlyCents": 300,
      "needsKyc": false,
      "callsAvailable": true,
      "whatsappAvailable": true,
      "smsAvailable": true,
      "outboundCallingAvailable": false,
      "inStock": true,
      "types": [
        { "numberType": "local", "monthlyCents": 300, "needsKyc": false, "smsAvailable": true, "whatsappAvailable": true, "callsAvailable": true, "inStock": true },
        { "numberType": "toll_free", "monthlyCents": 300, "needsKyc": false, "smsAvailable": false, "whatsappAvailable": false, "callsAvailable": true, "inStock": true }
      ]
    }
  ]
}
```

The country-level fields mirror the first entry of `types`, the default type. `inStock` is a carrier-stock snapshot refreshed every 6 hours and on availability checks; the purchase re-checks. `preOrderable: true` on a type means it is out of stock but you can still order it through the [KYC form](/platforms/phone-numbers/kyc#out-of-stock-pre-order-it), and `fulfilment: "request"` marks the types the carrier stocks nowhere and only sources to order. `outboundCallingAvailable` is WhatsApp Business Calling outbound, not PSTN calls.

## Check one country

`GET /v1/phone-numbers/countries` answers for the whole catalog; `GET /v1/phone-numbers/availability` answers for one country, and adds the address rule the registrant has to satisfy. It takes `country`, an optional `numberType` (`local`, `mobile`, `national` or `toll_free`) and an optional `sms=true`, which narrows every answer to the SMS-capable pool. Call it before you put anyone through the [KYC form](/platforms/phone-numbers/kyc), because regulated review is asynchronous and takes 1 to 3 business days.

```bash
curl "https://zernio.com/api/v1/phone-numbers/availability?country=BR&numberType=local" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "country": "BR",
  "numberType": "local",
  "available": true,
  "addressConstraint": "geo",
  "areas": ["11"],
  "areaOptions": [
    { "ndc": "11", "name": "Sao Paulo", "count": 42 },
    { "ndc": "21", "name": "Rio de Janeiro", "count": 8 }
  ]
}
```

`addressConstraint: "geo"` means the registered address must be in one of the returned `areas`, the only areas holding stock: an address anywhere else passes pre-approval and the number can then never be assigned. `country` accepts any in-country address and `none` needs no address at all. When `available` is `false`, `preOrderable` says whether the number can still be [pre-ordered](/platforms/phone-numbers/kyc#out-of-stock-pre-order-it). `areaOptions` is the live inventory by area code; pass an `ndc` from it as `areaCode` on the [purchase](/platforms/phone-numbers/provisioning#step-2-purchase) or on the KYC submit to hold the order to that area.

## Watch an out-of-stock country

`inStock: false` is not a dead end. If the type is `preOrderable`, order it now: submit the [KYC form](/platforms/phone-numbers/kyc#out-of-stock-pre-order-it) and we get the number from stock the moment it returns, or sourced by the carrier, usually within 2 to 4 weeks. If you would rather wait, or the type cannot be pre-ordered, `POST /v1/phone-numbers/stock-watches` with a `country` (and an optional `numberType`) puts you on the list for the moment that country has deliverable numbers again.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/stock-watches" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "country": "IE", "numberType": "local" }'
```

Response (`201`):

```json
{
  "id": "67b1c2d3e4f5a6b7c8d9e0f1",
  "country": "IE",
  "countryName": "Ireland",
  "numberType": "local",
  "createdAt": "2027-01-04T09:00:00Z"
}
```

Stock is re-checked every 6 hours. When it comes back, Zernio emails the account holder and sends [`phone_number.stock_available`](/webhooks/phone-numbers#phone_numberstock_available). The watch is consumed when it fires, so create it again if you miss the stock. One watch covers one country and number type, and a repeat request returns the existing watch with a `200`. A `409` means either that the country is in stock right now, so buy instead, or that you already hold the maximum of 20 watches. List them with `GET /v1/phone-numbers/stock-watches` and cancel one with `DELETE /v1/phone-numbers/stock-watches/{id}`.

## Pricing by country

Price is per number, per month, charged in full at activation and on the 1st of each month (no daily proration), and separate from usage (call minutes, SMS segments). The [phone number price list](/pricing/phone-numbers) breaks this table out by number type and marks SMS-capable stock. Countries marked with a dagger provision instantly; every other country returns `needsKyc: true` and requires a one-time [KYC form](/platforms/phone-numbers/kyc), after which the number activates within 1 to 3 business days of the regulatory review.

<NumberPriceBands />

Countries under **Pre-order** hold no stock: order a number with the [KYC form](/platforms/phone-numbers/kyc#out-of-stock-pre-order-it) and it is sourced for you, usually within 2 to 4 weeks. Their per-type prices are in the [price list](/pricing/phone-numbers#pre-order-numbers).

<RatesGeneratedAt />

Prices are computed server-side: 1.5x the carrier's monthly cost, rounded up to a whole dollar, $3 minimum and $30 at the top of the range. A country whose carrier cost is too high to price is not offered. The endpoint above always returns the current price; treat the bands as indicative.

## Number types

Numbers are `local`, `mobile`, `national` or `toll_free`, depending on the country's inventory. Each country has a default type, chosen to work across capabilities; where a country offers more than one, pick another at purchase with `numberType`. Toll-free is not sold in every country, and it can never connect WhatsApp: `numberType: "toll_free"` with the default `connectWhatsapp: true` returns a `400`, so buy it with `connectWhatsapp: false` and use it for calls and SMS.

## Capabilities by country

| Capability | Where |
|---|---|
| Calls (PSTN) | Every country, on by default on every number. |
| SMS | Australia, Belgium, Canada, United Kingdom, Lithuania, Netherlands, Poland, Puerto Rico, Sweden, United States, U.S. Virgin Islands and South Africa. Even there, SMS is confirmed per number, not per country. |
| WhatsApp | Every country. Any number except toll-free can be connected to a WhatsApp Business Account. |
| WhatsApp outbound calling | Everywhere except the US, Canada, Egypt, Vietnam and Nigeria, where Meta blocks business-initiated calls (inbound still works). This is [WhatsApp Business Calling](/platforms/whatsapp/calling), a different feature from PSTN calls. |

<Callout type="warn">
SMS is a per-number capability. `smsAvailable` says where SMS-capable stock exists; the real check happens when you [enable SMS](/platforms/sms/registration) on a specific number, and a number that cannot text is refused. Search with `sms=true` and purchase with `wantsSms: true` to draw only from the SMS-capable pool.
</Callout>

## If it fails

A `400` on `GET /v1/phone-numbers/available` or `POST /v1/phone-numbers/purchase` means the country is not offerable:

```json
{
  "error": "Country not available",
  "type": "invalid_request_error"
}
```

Pick a `code` from the countries response. A `422` with `code: "USAGE_BILLING_REQUIRED"` on purchase means the team is not on usage-based billing. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Buying a number](/platforms/phone-numbers/provisioning): search inventory and purchase.
- [Porting](/platforms/phone-numbers/porting): bring a number you already own.
- [Phone number price list](/pricing/phone-numbers): every country by number type.
- [List offerable countries](/phone-numbers/list-phone-number-countries): every field of the response.

---
