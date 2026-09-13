# Meta Business Agent

Provision and operate Meta's AI agent on a WhatsApp number by API, so the merchant never opens Business Manager

Meta Business Agent is Meta's own AI agent for WhatsApp. When it is on, it answers the number's customers directly: from the business information, FAQs, websites and files you give it, in the voice the skills describe, and it can call your APIs through connectors. Zernio wraps the whole configuration surface under `/v1/accounts/{accountId}/business-agent`, so an integrator can set it up for a merchant end to end. See the [Meta Business Agent reference](/api/business-agent).

<Callout type="warn">
Two steps have no API and stay with the merchant, by Meta's design:

1. **Accept the terms** in [WhatsApp Manager](https://business.facebook.com/latest/whatsapp_manager/) (Meta Business Agent tab). Until then every call answers `business_agent_terms_not_accepted`.
2. **Attach a payment method** to the Business Agent billable account in [Billing Hub](https://business.facebook.com/latest/billing_hub/). Without it the agent can be on but Meta delivers none of its messages. Testing with an allowlist does not need it.

`GET /v1/accounts/{accountId}/business-agent` reports the first under `manualSteps` (Zernio can tell it is still pending) and the second under `unverifiedSteps` (Meta exposes no billing state, so it is listed once an agent exists and you decide when to hide it).
</Callout>

### Which numbers qualify

The number must be managed through the Cloud API, in a supported vertical and country, in good standing, and not already running another AI product. A coexistence number that still has the WhatsApp Business app's own Business AI linked is not eligible: Meta allows one AI product per number, and that in-app agent also blocks the coexistence signup until the merchant disconnects it from the app. `eligible` on the status read is Meta's answer.

### Setup order

1. `GET .../business-agent` to read eligibility, terms and what exists.
2. `POST .../business-agent/onboard` once. It creates the agent, and Meta takes about a minute to prepare it; configuration calls made before that answer `business_agent_not_found`.
3. Give it knowledge: `PUT .../business-information`, `POST .../faqs`, `POST .../websites`, `POST .../files`.
4. Give it a voice with `POST .../skills`, and rich replies with `POST .../ui-skills`.
5. Talk to it with `POST .../test-messages` (Meta's sandbox pipeline, no token billing).
6. Turn it on: `PATCH .../settings` with `rollout: { enabled: true }`. Start with `ai_audience: ALLOWLISTED_ONLY` plus a few numbers on `.../allowlist` to test live before billing.

### Handoff and your inbox

While the agent holds a conversation, Zernio receives it in standby: inbound messages arrive on `message.received` with `metadata.standby: true` and the agent's replies on `message.sent` with `source: meta_business_agent`. Any message you send takes control back from the agent for that thread; hand it back with the conversation's thread-control endpoint. The agent also hands off on its own when it is unsure or the customer asks for a person, sending the handoff message you set in the settings.

### Costs

Meta bills agent messages per token (about 4 to 5 US cents per message) on the Business Agent billable account, separately from Zernio. Cap spend with `PUT .../business-agent/budget` (token or turn budgets over rolling windows); when a cap is reached the agent finishes its turn, stops replying and hands the thread to a human until the window rolls over.

---
