# Inbox

Receive and reply to WhatsApp conversations through the inbox API, send templates, interactive and commerce messages, and track delivery with webhooks.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

WhatsApp conversations live in the same [inbox API](/messages/list-inbox-conversations) as every other platform: list with `GET /v1/inbox/conversations`, reply with `POST /v1/inbox/conversations/{conversationId}/messages` and `accountId` plus `message`. Two WhatsApp rules sit on top. Free-form messages are allowed for 24 hours after the customer's last message; outside that window, and to open a conversation, you send an approved [template](/platforms/whatsapp/templates). Delivery is reported per message as sent, delivered and read.

## Direct messages

| Feature | Supported |
|---------|-----------|
| List conversations | <Yes /> |
| Fetch messages | <Yes /> |
| Send text messages | <Yes /> |
| Send attachments | <Yes /> (images, videos, documents, audio) |
| Send voice notes | <Yes /> with `voiceNote: true` on an audio attachment |
| Send interactive messages | <Yes /> (reply buttons, list, CTA URL, flow) with `interactive` or `buttons` |
| Send commerce messages | <Yes /> (single product, product list, catalog, carousel) with `interactive`; see [Commerce messages](#commerce-messages) |
| Send location and contact cards | <Yes /> with `location` and `contacts` |
| Reply to a specific message | <Yes /> with `replyTo` (the quoted message's `platformMessageId`) |
| React to a message | <Yes /> (emoji) with [Add message reaction](/messages/add-message-reaction) |
| Typing indicator | <Yes /> with [Send typing indicator](/messages/send-typing-indicator) |
| Archive and unarchive | <Yes /> |
| Comments | <No /> (WhatsApp has none) |

Attachments:

| Type | Max size |
|------|----------|
| Image | 5 MB |
| Video | 16 MB |
| Document | 100 MB |
| Audio | 16 MB |

Formats are on [Media requirements](/platforms/whatsapp/reference#media-requirements). A voice note (`voiceNote: true`) renders with the waveform, autoplay and profile-picture bubble; the file must be `.ogg` with the Opus codec, mono, and dashboard microphone recordings are transcoded on the server. Without the flag an Opus file still shows a microphone icon but as a plain audio attachment.

## Send a template

A template opens a conversation, or continues one whose 24-hour window has closed. To open one, call `POST /v1/inbox/conversations` with the phone in `participantId` (digits, country code included, no `+`) and `templateName`, `templateLanguage` and `templateParams`. `templateParams` is one flat array in the order the variables appear across the template: text-header variables first, then body variables, then dynamic URL buttons.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const accountId = '66b2e19d8c3f5a7e9d0b1c2d';

const { data: opened } = await zernio.messages.createInboxConversation({
  body: {
    accountId,
    participantId: '13105551234',
    templateName: 'order_confirmation',
    templateLanguage: 'en',
    templateParams: ['Ana', 'ORD-12345']
  }
});

const conversationId = opened.data.conversationId;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
account_id = "66b2e19d8c3f5a7e9d0b1c2d"

opened = client.messages.create_inbox_conversation(
    account_id=account_id,
    participant_id="13105551234",
    template_name="order_confirmation",
    template_language="en",
    template_params=["Ana", "ORD-12345"],
)

conversation_id = opened["data"]["conversationId"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/inbox/conversations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "participantId": "13105551234",
    "templateName": "order_confirmation",
    "templateLanguage": "en",
    "templateParams": ["Ana", "ORD-12345"]
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "success": true,
  "data": {
    "messageId": "wamid.HBgLMTMxMDU1NTEyMzQVAgARGBI5QTNEMEY3RjQ4RjE2QjA3QzYA",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
    "participantId": "13105551234"
  }
}
```

`data.conversationId` is the id the list endpoint returns for the same conversation. Inside an existing conversation, send the template through `POST /v1/inbox/conversations/{conversationId}/messages` with `template.elements`, one element carrying the template reference in Meta's send shape:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "template": {
    "elements": [{
      "name": "order_confirmation",
      "language": "en",
      "components": [{
        "type": "body",
        "parameters": [
          { "type": "text", "text": "Ana" },
          { "type": "text", "text": "ORD-12345" }
        ]
      }]
    }]
  }
}
```

`templateButtonParams` fills a button that takes its value at send time (`url` suffix, `copy_code` coupon, `flow` token), `headerMedia` swaps the header image, video or document for this send, and `headerLocation` is required for a template whose header is a location. A utility message without a template is possible through Meta Direct Send: `category: "utility"` with `message` and no `templateName`; the `400` codes `DIRECT_SEND_NOT_ELIGIBLE`, `DIRECT_SEND_LIMITED` and `DIRECT_SEND_BLOCKED` say when the WABA cannot.

## Commerce messages

Send products from your Meta catalog and swipeable carousels with `POST /v1/inbox/conversations/{conversationId}/messages` and the `interactive` field. Like every interactive message these are session messages, so the 24-hour window must be open.

<Callout type="info">
Product, product list, catalog and product-carousel messages need a Meta catalog connected to your WABA in [Commerce Manager](https://business.facebook.com/commerce/). Media carousels need no catalog.
</Callout>

<Tabs items={['Product', 'Product List', 'Catalog', 'Carousel']}>
<Tab value="Product">

A single product card. `body` is optional; the product image is the header.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "interactive": {
    "type": "product",
    "body": {"text": "Back in stock"},
    "action": {
      "catalog_id": "194836987003835",
      "product_retailer_id": "sku-blue-tshirt"
    }
  }
}
```

</Tab>
<Tab value="Product List">

Up to 30 products in up to 10 sections. A text header is required.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "interactive": {
    "type": "product_list",
    "header": {"type": "text", "text": "Our summer picks"},
    "body": {"text": "Tap to browse"},
    "action": {
      "catalog_id": "194836987003835",
      "sections": [
        {
          "title": "T-shirts",
          "product_items": [
            {"product_retailer_id": "sku-blue-tshirt"},
            {"product_retailer_id": "sku-red-tshirt"}
          ]
        }
      ]
    }
  }
}
```

</Tab>
<Tab value="Catalog">

A "View catalog" button. The thumbnail is optional (it defaults to your catalog's first item), and `action` may be omitted entirely.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "interactive": {
    "type": "catalog_message",
    "body": {"text": "Browse our full catalog"},
    "action": {
      "name": "catalog_message",
      "parameters": {"thumbnail_product_retailer_id": "sku-blue-tshirt"}
    }
  }
}
```

</Tab>
<Tab value="Carousel">

2 to 10 swipeable cards. Media cards carry an image or video header plus a CTA URL button and need no catalog; product cards reference catalog items and must all share one `catalog_id`. Cards are all product or all media. `card_index` is filled sequentially when omitted.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "interactive": {
    "type": "carousel",
    "body": {"text": "New arrivals this week"},
    "action": {
      "cards": [
        {
          "card_index": 0,
          "type": "cta_url",
          "header": {"type": "image", "image": {"link": "https://example.com/a.jpg"}},
          "body": {"text": "The classic"},
          "action": {"name": "cta_url", "parameters": {"display_text": "Shop now", "url": "https://example.com/a"}}
        },
        {
          "card_index": 1,
          "type": "cta_url",
          "header": {"type": "image", "image": {"link": "https://example.com/b.jpg"}},
          "action": {"name": "cta_url", "parameters": {"display_text": "Shop now", "url": "https://example.com/b"}}
        }
      ]
    }
  }
}
```

</Tab>
</Tabs>

When a customer submits a cart from any commerce message, `message.received` fires with `metadata.order`: `catalog_id`, an optional customer note in `text`, and `product_items` with `product_retailer_id`, `quantity`, `item_price` and `currency`. When a customer taps "Message business" on a product, their message carries `metadata.referredProduct` with the `catalog_id` and `product_retailer_id` they asked about. Both render in the dashboard inbox, whose composer can send products, catalogs and media carousels.

## Meta Business Agent standby

On a number with Meta Business Agent enabled, the agent can answer conversations on Meta's side while Zernio only observes. Inbound messages still arrive as [`message.received`](/webhooks/inbox#messagereceived) with `metadata.standby: true`, the agent's replies arrive as `message.sent` with `source: "meta_business_agent"`, and the conversation's `threadControl` in [List inbox conversations](/messages/list-inbox-conversations) says who answers now. Sending any message takes control back from the agent, so a bot that replies to every inbound message must skip standby ones. To hand a conversation back to the agent without sending, or to take it before an operator reads the thread, call [Hand a conversation to or from Meta Business Agent](/messages/set-conversation-thread-control) with `action: "release"` or `"take"`; every change fires [`conversation.control_changed`](/webhooks/inbox#conversationcontrol_changed).

## Webhooks

WhatsApp emits the full message and delivery lifecycle:

| Event | When it fires |
|-------|---------------|
| `message.received` | A new incoming WhatsApp message |
| `message.sent` | An outgoing message is sent. `source` is `cloud_api` for sends through Zernio, `whatsapp_business_app` for sends from the phone on a coexistence number and `meta_business_agent` for replies Meta Business Agent wrote |
| `message.delivered` | An outgoing message reaches the recipient |
| `message.read` | The recipient reads an outgoing message |
| `message.failed` | An outgoing message fails to deliver; the payload's `error` object carries the Cloud API error code (for example `131026`, message undeliverable) |
| `message.deleted` | The business deletes a sent message with `DELETE /v1/inbox/conversations/{conversationId}/messages/{messageId}` |
| `reaction.received` | A participant adds or removes an emoji reaction. `reaction.emoji` is empty on removal, because the Cloud API does not report which emoji was removed |
| `conversation.control_changed` | Meta Business Agent takes over a conversation, hands it back, or another partner app takes it ([standby](#meta-business-agent-standby)) |

Subscribe with [Create webhook settings](/webhooks/create-webhook-settings); payloads are on [inbox webhooks](/webhooks/inbox). The Cloud API does not expose incoming-message edits or recipient-side unsends, so `message.edited` and an incoming `message.deleted` are never emitted for WhatsApp.

## If it fails

WhatsApp caps how many messages one recipient can receive in a short window and rejects the excess with code `131056`:

```json
{
  "error": "Too many messages sent to this recipient",
  "code": "131056"
}
```

Pace sends to a single recipient at about 10 per minute. Other recipients are unaffected, so parallelize across recipients rather than flooding one. A `400` with code `TEMPLATE_REQUIRED` on `POST /v1/inbox/conversations` means the message tried to open a conversation without a template, and Meta's other codes (`131047` the closed 24-hour window, `132001` template not found) are on [Common errors](/platforms/whatsapp/reference#common-errors). Zernio does not retry a send on its own, because a send is not idempotent; pass an `Idempotency-Key` header to make your own retries safe. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Templates](/platforms/whatsapp/templates): the messages that open a conversation.
- [Broadcasts](/platforms/whatsapp/broadcasts): the same template to many recipients.
- [Flows](/platforms/whatsapp/flows): forms and booking screens sent as interactive messages.
- [Inbox API](/messages/list-inbox-conversations) and [Send message](/messages/send-inbox-message): every field.
- [Messages pricing](/pricing/messages): what outbound messages cost.

---
