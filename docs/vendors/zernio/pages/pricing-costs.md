# Pricing & Costs

Which WhatsApp charges Zernio bills, which ones Meta bills to your WhatsApp Business Account, and how the sandbox, a dedicated number and your own number differ.

WhatsApp has two billers. Zernio bills the number it provisions for you, the carrier leg of outbound calls, and the outbound-message meter; Meta bills template delivery and its per-minute calling fee straight to your WhatsApp Business Account, and Zernio never marks up or re-bills Meta's fees. Meta's rate tables by country are on [WhatsApp rates](/pricing/whatsapp); this page says what lands on which invoice.

## What Zernio charges

Every WhatsApp feature (broadcasts, sequences, flows, groups, the inbox) is included with every account, with no post or profile limits. Messages you send, counting one per broadcast recipient, meter after the first 10,000 each month ([outbound messages](/pricing#outbound-messages)). Two items bill on top:

| Item | Cost |
|---|---|
| Dedicated number | From $3/mo per active number, which Zernio provisions for you. US is $3/mo; other countries are priced per country and number type ($3 to $30/mo) and shown before purchase. Billed per number as a flat monthly price, charged at activation and on the 1st of each month. |
| Calling, inbound | Free |
| Calling, outbound | Carrier connection metered per minute by Zernio, plus an optional recording surcharge ($0.004/minute). Meta's own per-minute rate is billed by Meta directly to your WABA and varies by country ([Meta's outbound rate by country](/pricing/whatsapp#metas-outbound-rate-by-country)). Not available in every country: Meta blocks business-initiated calls in some, including the US. |

Setup is on [Calling](/platforms/whatsapp/calling); the per-call estimate endpoint there returns both legs before you dial.

## What Meta charges

Meta charges per delivered template message and per minute of outbound calling, billed directly to your WhatsApp Business Account, not through Zernio. Non-template messages inside the 24-hour customer service window carry no Meta fee, and neither do utility templates sent inside it; both still count as [outbound messages](/pricing#outbound-messages) on Zernio's side. Template delivery rates vary by category and recipient country: [WhatsApp rates](/pricing/whatsapp) and [Meta's rate cards](https://developers.facebook.com/docs/whatsapp/pricing#rates).

<Callout type="warn">
Meta needs a valid payment method on your WhatsApp Business Account ([Meta Business Suite](https://business.facebook.com)). Without one, Meta blocks template delivery once your free tier is exhausted, whatever the state of your Zernio account.
</Callout>

## Sandbox, dedicated and your own number

Which number you send from decides what Zernio bills:

- **Zernio's sandbox number.** Free: no number charge and no message charge. One locked template, one test phone at a time, for testing only ([Sandbox](/platforms/whatsapp/sandbox)).
- **Dedicated number.** A production number bought through Zernio, available to every account with a payment method on file. US numbers are pre-verified and provisioned instantly; numbers in other countries are priced per country and, where the country regulates them, need a one-time [KYC form](/platforms/phone-numbers/kyc) (and sometimes an end-user ID check) before activation. There is no deposit and no checkout redirect: with a payment method on file the number provisions inline and the monthly price joins your usage-based invoice, stamped at the rate quoted when you bought it. Without a card, the purchase returns `402` with `code: "PAYMENT_REQUIRED"`.
- **Your own number.** Connected through Embedded Signup or [credentials](/platforms/whatsapp/connection#connect-with-credentials-headless) with your own Meta System User token. No Zernio number fee; Meta's fees and the outbound-message meter apply as above.

## Common errors

Two gates return a status code rather than an invoice line.

| Error | Cause | Fix |
|-------|-------|-----|
| `402` on connect or on a number purchase | Zernio's billing gate: the team has no payment method | Add one, then retry ([connecting accounts](/guides/connecting-accounts#if-it-fails)) |
| `422` on enabling calling | The team is not on usage-based billing, the number's messaging limit is below Meta's ~2,000-daily-recipient threshold, or its country blocks business-initiated calls | Send messages from the number until Meta raises the limit, or take inbound calls only ([Calling](/platforms/whatsapp/calling)) |

## Related

- [WhatsApp rates](/pricing/whatsapp): Meta's template and calling rates by country.
- [Messages pricing](/pricing/messages): what counts as an outbound message.
- [Phone number pricing](/pricing/phone-numbers): the per-country number price.
- [Pricing](/pricing): every usage line on one invoice.

---
