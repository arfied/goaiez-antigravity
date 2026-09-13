# Call Rates

Per-minute rates for outbound phone calls to every destination, plus optional recording and transcription pricing.

Phone (PSTN) calls placed through the [Voice API](/platforms/voice) meter per minute at the rate for the route, from any [number you own](/pricing/phone-numbers) to any destination below. There are no connection fees and no monthly minimums; you pay for connected minutes only.

Call `GET /v1/voice/calls/estimate` ([getVoiceCallEstimate](/platforms/voice/outbound#estimate-the-cost-first)) for the expected per-minute cost of any destination number before you dial, to show pricing to your users or to gate expensive routes. The table below is a snapshot of the same rate deck.

## Outbound rates by destination

Rates are per minute to mobile numbers, the most common route. Calls to landlines in the same destination are often cheaper, and the invoice line reflects the route the call took. Fractional minutes bill as fractions: a 30-second call at $0.010 per minute costs $0.005.

<CallRateTable />

<RatesGeneratedAt />

<Callout type="warn">
Rates change when carriers reprice a route. The table is refreshed from the deck the billing engine uses, but for anything you show to your own users, quote live with [getVoiceCallEstimate](/platforms/voice/outbound#estimate-the-cost-first). A destination not listed here bills at its current route rate, which the estimate endpoint also returns.
</Callout>

## Recording and transcription

Both are optional, off by default, and configurable per number or per call:

| Option | Rate |
|---|---|
| Call recording | $0.004 per minute on top |
| Call transcription | $0.03 per minute on top |

## Inbound calls

Inbound calls to your numbers meter per minute as well, and there is no rate card for them. The inbound leg bills from the carrier's settled record for the call rather than from a published sheet, so the table above does not cover it and nothing quotes it in advance: `GET /v1/voice/calls/estimate` prices an outbound destination. The amount you paid is on each call as `billing.billableCostUSD`, from `GET /v1/calls` with `direction=inbound` ([call history](/platforms/voice/history#list-calls)).

Calls [forwarded](/platforms/voice/setup) to a SIP or WebSocket destination (AI agents, browser calling) have no PSTN leg on the forward side, so you pay the inbound leg only. Forwarding to a phone number adds a second leg, priced by that destination in the table above.

## Related

- [WhatsApp call rates](/pricing/whatsapp): WhatsApp calling is priced differently, and Meta bills its per-minute fee directly to your WABA.
- [Voice and calls](/platforms/voice): setup, outbound, AI agents, browser calling.
- [How billing works](/billing): proration, invoicing, spend visibility.

---
