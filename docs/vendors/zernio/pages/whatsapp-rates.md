# WhatsApp Rates

Per-minute WhatsApp calling rates by country, what Meta bills directly to your WABA, and what Zernio charges.

WhatsApp has two billers. Zernio bills the [dedicated number](/pricing/phone-numbers) (from $3 per month) and the carrier connection leg of calls; messaging, broadcasts, templates, flows and the inbox carry no Zernio usage fees beyond the [outbound message](/pricing#outbound-messages) meter. Meta bills your WhatsApp Business Account (WABA) directly for template message delivery and its per-minute calling fee. Zernio never marks up or re-bills Meta's fees; they appear on your Meta invoice, charged to the payment method on your WABA.

This page is the rate card: Meta's rates by country, and the Zernio lines beside them. [WhatsApp pricing and costs](/platforms/whatsapp/pricing) owns the other half, what the sandbox, a dedicated number and your own number each cost, and the billing gates that return `402` and `422`.

## Numbers

Any [available country's number](/pricing/phone-numbers) can be connected to a WhatsApp Business Account, at $3 to $30 per month depending on the country and number type, billed as a flat monthly price.

## Messages

| Item | Rate | Billed by |
|---|---|---|
| Non-template messages (within the 24-hour customer service window) | No Meta fee | Nobody |
| Utility templates sent within the customer service window | No Meta fee | Nobody |
| Template messages (marketing, utility, authentication) | Per delivery, varies by category and recipient country | Meta |

Template delivery rates are Meta's own; see [Meta's rate cards by country](https://developers.facebook.com/docs/whatsapp/pricing#rates). Zernio passes them through untouched.

The rates above are Meta's side of the bill. On Zernio's side, every WhatsApp message you send counts as one [outbound message](/pricing#outbound-messages), including one per broadcast recipient: the first 10,000 each month are free, then $0.0001 per message. The allowance is per team and resets on the 1st.

## Calls

[WhatsApp Business Calling](/platforms/whatsapp/calling) splits per-minute costs across the two billers:

| Leg | Rate | Billed by |
|---|---|---|
| Inbound: carrier connection | Per minute at the [PSTN call rate](/pricing/calls) of the number the call is bridged to | Zernio |
| Inbound: Meta per-minute fee | None. Meta does not charge for inbound calls | Nobody |
| Outbound: carrier connection | Per minute at the [PSTN call rate](/pricing/calls) of the number the call is bridged to | Zernio |
| Outbound: Meta per-minute fee | The table below | Meta, directly to your WABA |

The carrier connection is the leg between Zernio and the phone, SIP endpoint or AI agent you route the call to. It bills off the same PSTN rate deck as [standalone calls](/pricing/calls), priced by the country of that destination, so a WhatsApp call costs its row in that table plus Meta's row below. A call routed to a SIP or WebSocket destination has no PSTN leg, and no carrier charge.

### Meta's outbound rate by country

These are Meta's current per-minute rates for business-initiated WhatsApp calls, by the recipient's country. Meta blocks business-initiated calls on numbers registered in the US, Canada, Egypt, Vietnam and Nigeria; inbound still works on them.

<WhatsAppCallRateTable />

<RatesGeneratedAt />

Countries not listed bill at Meta's default rate for the destination (up to $0.05 per minute). The per-call estimate in the [calling API](/platforms/whatsapp/calling) resolves both legs, Meta's fee and the carrier connection, before you place the call. Optional [call recording](/pricing/calls#recording-and-transcription) adds $0.004 per minute on the Zernio side.

<Callout type="warn">
Meta needs a valid payment method on your WhatsApp Business Account ([Meta Business Suite](https://business.facebook.com)) for template delivery and outbound calling. Without one, Meta blocks delivery once its free allowance is exhausted, whatever the state of your Zernio account.
</Callout>

## Related

- [WhatsApp pricing and costs](/platforms/whatsapp/pricing): what the sandbox, a dedicated number and your own number cost, and the billing gates
- [WhatsApp Business Calling](/platforms/whatsapp/calling): setup and permissions
- [Phone number prices](/pricing/phone-numbers)

---
