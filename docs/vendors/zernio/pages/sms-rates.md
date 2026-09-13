# SMS Rates

Per-segment SMS prices for every sendable destination, plus US 10DLC registration fees.

SMS through the [SMS API](/platforms/sms) meters per segment. A segment is one 160-character GSM-7 message part, or 153 characters each when a message spans several parts; emoji and non-Latin scripts use UCS-2 at 70 and 67. The price depends on the destination country.

## Outbound rates by destination

Rates are "from" prices, the floor for that destination: the exact per-segment price depends on the receiving carrier, and no endpoint quotes an SMS price in advance. US long code is the anchor rate at $0.008 per segment. The 200+ destinations below are far more than the <NumberCountryCount /> countries where [numbers are available](/pricing/phone-numbers) for purchase.

<SmsRateTable />

<RatesGeneratedAt />

A message is carrier-rated after it is sent, so there is no SMS equivalent of the [call estimate](/pricing/calls), and `GET /v1/usage/sms` reports volumes and not cost. Once a message is rated, its cost lands on the invoice and in the `sms` total of `GET /v1/usage` ([Get usage](/usage/get-usage)).

<Callout type="warn">
Destinations outside this list are blocked at send time, not billed. The sendable-destination list is a toll-fraud guard: premium-rate destinations are how a leaked key turns into a five-figure bill.
</Callout>

## Inbound and MMS

| Item | Rate |
|---|---|
| Inbound SMS | Free on most numbers. Where the carrier charges an inbound fee (some US routes), it bills at the metered per-segment rate for that message |
| MMS | Metered per message at the route's MMS rate, itemized on your invoice per message |

## US 10DLC registration fees

US carriers require 10DLC registration before a US number can send SMS ([why, and how to register](/platforms/sms/registration)). Other countries can send as soon as SMS is enabled on the number, with no registration and no monthly fee.

| Fee | Price |
|---|---|
| Brand registration (one-time) | $9 |
| Campaign fee, standard use cases | $20 per month |
| Campaign fee, sole proprietor | $4 per month |
| Reusing an approved registration on more numbers | Free |

One approved registration covers all your numbers: [reuse it](/platforms/sms/registration#reuse-an-approval-skip-the-fee) on each additional number with no repeat fee.

## Related

- [Enable SMS on a number](/platforms/sms/registration)
- [Sending SMS](/platforms/sms/sending)
- [Phone number prices](/pricing/phone-numbers): which countries have SMS-capable stock
- [How billing works](/billing)

---
