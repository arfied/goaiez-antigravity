# Messaging & Call Ads

Create Meta ads that open a WhatsApp, Messenger or Instagram Direct conversation with POST /v1/ads/messaging, or dial a number with POST /v1/ads/call.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Create an ad that opens a conversation instead of a web page with `POST /v1/ads/messaging`, or one that dials your number with `POST /v1/ads/call`. Each call builds the whole hierarchy (campaign, ad set, creative, ad), or attaches the creatives to an ad set you already have, and picks the call to action, `destination_type` and optimization goal Meta requires for that destination. You need `accountId` and `adAccountId`, and the Page has to be paired with the destination before you call.

For WhatsApp, optional top-level `whatsappPhoneNumber` selects a number already paired with the Page. Omit it to use the Page's default pairing.

## Messaging ads

`destination` selects where the conversation opens:

| `destination` | Opens in | Call to action | Requires |
|---|---|---|---|
| `whatsapp` | WhatsApp | `WHATSAPP_MESSAGE` | The Page paired with a verified WhatsApp Business number |
| `messenger` | The Page's Messenger inbox | `MESSAGE_PAGE` | A Facebook Page |
| `instagram_direct` | The linked Instagram account's Direct inbox | `INSTAGRAM_MESSAGE` | An Instagram professional account linked to the Page |

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.messagingads.createMessagingAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    destination: 'messenger',
    name: 'Summer promo, Messenger',
    headline: 'Chat with us',
    body: 'Get a personal quote in 2 minutes.',
    imageUrl: 'https://cdn.example.com/promo.jpg',
    budgetAmount: 20,
    budgetType: 'daily',
    currency: 'USD',
    countries: ['US', 'ES']
  }
});

const adId = created.ad._id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.messaging_ads.create_messaging_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    destination="messenger",
    name="Summer promo, Messenger",
    headline="Chat with us",
    body="Get a personal quote in 2 minutes.",
    image_url="https://cdn.example.com/promo.jpg",
    budget_amount=20,
    budget_type="daily",
    currency="USD",
    countries=["US", "ES"],
)

ad_id = created["ad"]["_id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/messaging" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "destination": "messenger",
    "name": "Summer promo, Messenger",
    "headline": "Chat with us",
    "body": "Get a personal quote in 2 minutes.",
    "imageUrl": "https://cdn.example.com/promo.jpg",
    "budgetAmount": 20,
    "budgetType": "daily",
    "currency": "USD",
    "countries": ["US", "ES"]
  }'
```
</Tab>
</Tabs>

Response (`201`), trimmed. `adType` tells you which shape you got: `single` carries one `ad`, and a request with several creatives returns `multi` with `ads`, `platformCampaignId` and `platformAdSetId` instead.

```json
{
  "adType": "single",
  "message": "CTM ad submitted to Meta for review",
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9e7",
    "name": "Summer promo, Messenger",
    "status": "pending_review",
    "reviewStatus": "in_review",
    "platformObjective": "OUTCOME_ENGAGEMENT",
    "platformAdId": "120260000000000002",
    "platformCampaignId": "120250000000000006",
    "platformAdSetId": "120250000000000007"
  }
}
```

The ad set carries the matching `destination_type` with `CONVERSATIONS` optimization, and the campaign objective defaults to `OUTCOME_ENGAGEMENT`. Send `objective` to override it.

Both creative shapes work, with an image or a `video`: the single-creative fields above, or `creatives[]` for N ads in one ad set. Unlike `POST /v1/ads/create`, sending both is a `400` rather than ignoring the top-level fields. Either shape also goes on an [existing ad set](#attach-to-an-existing-ad-set).

Targeting takes the full geo shape, not only `countries`: pass `cities`, `regions`, `zips`, `metros` or `customLocations` for a local business, with keys from [the targeting search](/platforms/meta-ads/targeting#look-up-city-and-region-keys). The `["US"]` default applies only when no geo field at all is present, so a `cities` request targets that city and nothing else.

<Callout type="warn">
The Page-to-destination pairing must exist before you call. A missing pairing is Meta subcode `2446886` inside a `platform_error` envelope.
</Callout>

`POST /v1/ads/ctwa` is the deprecated predecessor of `destination: "whatsapp"` and stays available for existing integrations ([click-to-WhatsApp ads](/platforms/meta-ads/ctwa)). To attribute conversions that happen inside the WhatsApp thread, see [Conversions API for business messaging](/platforms/whatsapp/ctwa#conversions-api-for-business-messaging).

## Reuse an existing post instead of a fresh creative

Send `existingPostId` (Zernio-resolved, the same way `postId` resolves on a boost) or a raw Facebook `objectStoryId` (`pageId_postId`) instead of `headline`, `body` and `imageUrl` / `video`, per creative or at the top level. No media is uploaded and the organic post is kept as-is:

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "act_1234567890",
  "destination": "whatsapp",
  "name": "Reuse existing reel",
  "existingPostId": "811889972008357_123456789"
}
```

`existingPostId` and `objectStoryId` are mutually exclusive with each other and with `headline`, `body`, `imageUrl`, `video` and `welcomeMessage`. This only works on messaging and CTWA; call ads still need a fresh creative.

## Attach to an existing ad set

`adSetId` adds the creatives to a messaging ad set you already have, instead of building a campaign around them. The ad set keeps its learning phase, which is what makes this the way to refresh a creative without resetting delivery. Either creative shape works with it, and the target ad set's `destination_type` has to match the ad's `destination`: a WhatsApp ad needs a WhatsApp ad set, or Meta would take an ad that never delivers.

```bash
curl -X POST "https://zernio.com/api/v1/ads/messaging" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "destination": "messenger",
    "adSetId": "120250000000000007",
    "name": "Summer promo, new creative",
    "headline": "Chat with us",
    "body": "Get a personal quote in 2 minutes.",
    "imageUrl": "https://cdn.example.com/promo-v2.jpg"
  }'
```

Response (`201`), trimmed:

```json
{
  "adType": "single",
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9e9",
    "status": "pending_review",
    "reviewStatus": "in_review",
    "platformAdId": "120260000000000004",
    "platformAdSetId": "120250000000000007"
  }
}
```

The ad set already owns the budget, the targeting and the schedule, so `budgetAmount`, `budgetType`, `endDate`, `objective`, `countries`, `interests`, `audienceId` and `campaignStatus` are rejected with a `400` alongside `adSetId` rather than quietly dropped: "adSetId attaches to an existing ad set, which already owns budgetAmount. Remove it, or omit adSetId to create a new campaign." Without `adSetId` the budget fields swap back to required.

## Call ads

`POST /v1/ads/call` builds an ad whose call to action is `CALL_NOW`, dialing `phoneNumber` in E.164. `linkUrl` is required alongside it.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: callAd } = await zernio.messagingads.createCallAd({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    name: 'Book a consult, calls',
    phoneNumber: '+15551234567',
    linkUrl: 'https://example.com/contact',
    headline: 'Talk to an expert',
    body: 'One call, no forms.',
    imageUrl: 'https://cdn.example.com/promo.jpg',
    budgetAmount: 30,
    budgetType: 'daily',
    currency: 'USD',
    countries: ['US']
  }
});
```
</Tab>
<Tab value="Python">
```python
call_ad = client.messaging_ads.create_call_ad(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    name="Book a consult, calls",
    phone_number="+15551234567",
    link_url="https://example.com/contact",
    headline="Talk to an expert",
    body="One call, no forms.",
    image_url="https://cdn.example.com/promo.jpg",
    budget_amount=30,
    budget_type="daily",
    currency="USD",
    countries=["US"],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/call" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "name": "Book a consult, calls",
    "phoneNumber": "+15551234567",
    "linkUrl": "https://example.com/contact",
    "headline": "Talk to an expert",
    "body": "One call, no forms.",
    "imageUrl": "https://cdn.example.com/promo.jpg",
    "budgetAmount": 30,
    "budgetType": "daily",
    "currency": "USD",
    "countries": ["US"]
  }'
```
</Tab>
</Tabs>

Response (`201`), trimmed. Call ads take the same shape as messaging ads, so `adType` is `single` here and `multi` for a request with several creatives.

```json
{
  "adType": "single",
  "message": "Call ad submitted to Meta for review",
  "ad": {
    "_id": "66d4a1b2c3e4f5a6b7c8d9e8",
    "name": "Book a consult, calls",
    "status": "pending_review",
    "reviewStatus": "in_review",
    "platformObjective": "OUTCOME_LEADS",
    "platformAdSetId": "120250000000000008"
  }
}
```

The ad set uses `destination_type: PHONE_CALL` optimizing `QUALITY_CALL`, and the campaign objective defaults to `OUTCOME_LEADS`. `linkUrl` must be a real website: Meta rejects a `tel:` URI as the creative's link with subcode `2061006`, because the number rides on the call to action button only. Use your contact page.

## Welcome message

`welcomeMessage` replaces the default greeting in the chat that opens. Both fields are required when the object is present:

```json
{
  "welcomeMessage": {
    "text": "How can we help with your quote?",
    "prefillText": "I would like a quote for..."
  }
}
```

`text` is Meta's `page_welcome_message` greeting, shown when the chat opens in place of Meta's generic one. `prefillText` sits in the user's input box ready to send, in place of Meta's generic opener, so one ad can steer the first message toward what it promotes. On the single-creative shape it is a top-level field; on `creatives[]` set it per entry. Omit it for Meta's defaults.

## Common errors

A `422` means no Facebook Page resolved for the account:

```json
{
  "error": "No Facebook page found. Connect a Facebook account with a page to create CTM ads.",
  "type": "invalid_request_error",
  "code": "linked_account_required",
  "platform": "meta"
}
```

Connect a Facebook account that manages a Page in the same profile, then retry.

A missing destination pairing fails later, at Meta, so it arrives as a `400` with `type: "platform_error"` and subcode `2446886`:

```json
{
  "error": "Meta Ads API error (400; code 100, subcode 2446886): Your Page is not linked to a WhatsApp account.",
  "type": "platform_error",
  "platform": "meta",
  "platformError": { "code": 100, "error_subcode": 2446886 }
}
```

Pair the Page in Meta Business Manager, then retry. A `403` with `code: "ads_allowance_exceeded"` means the team has no payment method on file and has reached its 500 free live ads; add a card to resume. A `502` means Meta accepted the request and then failed to produce the media; read `platformError.reason`.

## Related

- [Click-to-WhatsApp ads](/platforms/meta-ads/ctwa): the deprecated endpoint this one replaces.
- [Targeting](/platforms/meta-ads/targeting): the geo fields these endpoints accept.
- [WhatsApp](/platforms/whatsapp): the inbox the conversations land in.
- [Create messaging ad](/messaging-ads/create-messaging-ad) and [Create call ad](/messaging-ads/create-call-ad): every field.

---
