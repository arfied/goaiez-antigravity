# Phone Number Prices

The monthly price of a phone number in every available country, with number type, provisioning speed and SMS-capable stock.

Every phone number has a flat monthly price set per country. The number is the billing unit: [Calls](/platforms/voice), [SMS](/platforms/sms) and [WhatsApp](/platforms/whatsapp) are features you enable on it, metered separately as you use them.

- Flat monthly price. The full month is charged when the number activates, and again on the 1st of each month while you hold it. Releasing a number stops future months; the current month is not prorated.
- No setup fees. The monthly rate is the whole price of the number.
- Price shown before purchase. The dashboard and the API both quote the price before you buy.

`GET /v1/phone-numbers/countries` ([listPhoneNumberCountries](/platforms/phone-numbers/availability#check-availability-live)) is the live source of truth and always returns the current price per country. The table below is a snapshot of the same prices.

## Price per country

<NumberPriceTable />

<RatesGeneratedAt />

Two of the columns need a key:

- Provisioning: instant countries activate the moment you buy. The rest are regulated markets that require a one-time [KYC form](/platforms/phone-numbers/kyc); the number activates within 1 to 3 business days of the regulatory review. If one of those is out of stock when you order, you can still [pre-order it](/platforms/phone-numbers/kyc#out-of-stock-pre-order-it).
- SMS-capable stock: countries where SMS-capable numbers exist. SMS support is confirmed per number when you [enable SMS](/platforms/sms/registration); search with `sms=true` to draw only from the SMS-capable pool. Calls work on every number in every country, and WhatsApp works on every number type except toll-free.

## Pre-order numbers

These number types are not held in stock anywhere. You order them with the same [KYC form](/platforms/phone-numbers/kyc#out-of-stock-pre-order-it) and the carrier sources one for your registration, usually within 2 to 4 weeks and never guaranteed. The price is the same flat monthly price as any other number, and nothing is billed until the number is active.

<PreOrderNumberTable />

## How the price is set

Prices are computed server-side from the carrier's monthly rate for each country and number type: 1.5x that rate, rounded up to a whole dollar, with a $3 minimum. Cheap markets (the US, Canada, the UK, Germany, Spain and most of Europe) all sit at the $3 minimum, and the most expensive number Zernio sells is $30. A country whose carrier cost is too high to price is not offered. When a carrier reprices a country, the [countries endpoint](/platforms/phone-numbers/availability#check-availability-live) reflects it immediately. The price you see at purchase is captured on the number: renewals always bill that same price, even if the country's list price changes later.

Each country has a default number type (local, mobile or national), chosen as the one that works across capabilities. Countries offering more than one type (including toll-free, where available) list each at its own price in the table above; pick one at purchase with `numberType`.

## What's metered on top

| Usage | Priced at |
|---|---|
| Outbound calls | Per minute by destination: [call rates](/pricing/calls) |
| SMS | Per segment by destination: [SMS rates](/pricing/sms) |
| WhatsApp | Meta usage fees billed by Meta: [WhatsApp rates](/pricing/whatsapp) |

## Next steps

- [Buy a number](/platforms/phone-numbers/provisioning)
- [Availability and capabilities by country](/platforms/phone-numbers/availability)
- [Port a number in](/platforms/phone-numbers/porting)

---
