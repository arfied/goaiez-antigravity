# Inbox webhooks

Receive an event for every DM, delivery receipt, reaction, comment and review that reaches the inbox, and know which platforms send which.

Inbox events cover everything that lands in or leaves the inbox: DMs and their delivery lifecycle, conversations, emoji reactions, comments on tracked posts, and reviews. Subscribe with `POST /v1/webhooks/settings` and the event names below ([first event](/webhooks#first-event)); delivery, retries and signatures are the same for every event ([how webhooks behave](/webhooks#how-it-behaves)). Message payloads carry an `account` block with `accountId` and `profileId`, so one endpoint can serve many profiles.

## Events

| Event | Description |
| --- | --- |
| [`message.received`](#messagereceived) | A new inbox message arrived. |
| [`message.sent`](#messagesent) | An outgoing message was sent from the inbox. |
| [`conversation.started`](#conversationstarted) | A new conversation began between an account and a contact, on any DM platform. Once per conversation. |
| [`conversation.control_changed`](#conversationcontrol_changed) | Meta Business Agent took over a WhatsApp conversation, handed it back, or another partner app took it. |
| [`message.edited`](#messageedited) | A sender edited a previously sent message. |
| [`message.deleted`](#messagedeleted) | A sender deleted (unsent) a message. |
| [`message.delivered`](#messagedelivered) | An outgoing message was delivered to the recipient. |
| [`message.read`](#messageread) | An outgoing message was read by the recipient. |
| [`message.failed`](#messagefailed) | An outgoing message failed to deliver (WhatsApp, SMS). |
| [`reaction.received`](#reactionreceived) | A participant added or removed an emoji reaction (Instagram, Facebook, WhatsApp, Telegram, Slack). |
| [`referral.received`](#referralreceived) | Someone opened an existing Instagram or Messenger conversation through an ig.me or m.me `ref` link or a returning Messenger ad click. |
| [`comment.received`](#commentreceived) | A new comment arrived on a tracked post. |
| [`review.new`](#reviewnew) | A new review was posted on a connected account. |
| [`review.updated`](#reviewupdated) | A review was edited or a reply was added. |

## How it behaves

### Not every platform sends every event

Zernio forwards what each platform exposes:

| Event | Platforms |
| --- | --- |
| `message.edited` | Instagram, Facebook Messenger, Telegram, WhatsApp |
| `message.deleted` | Instagram (incoming unsend), WhatsApp (both directions) |
| `message.delivered` | WhatsApp, Facebook Messenger, SMS |
| `message.read` | WhatsApp, Facebook Messenger, Instagram |
| `message.failed` | WhatsApp, SMS |
| `conversation.control_changed` | WhatsApp |
| `reaction.received` | Instagram, Facebook, WhatsApp, Telegram, Slack |
| `referral.received` | Instagram, Facebook Messenger |
| `review.new`, `review.updated` | Google Business Profile |

`conversation.started` covers every DM platform (Instagram, Messenger, Telegram, WhatsApp, X, Reddit, Bluesky) with one event.

### A legacy AppSumo plan gates the inbox

Every event on this page except `conversation.control_changed` needs inbox access. Without it `POST /v1/webhooks/settings` refuses the subscription itself with a `403` and code `feature_not_available`, rather than accepting it and delivering nothing. A legacy AppSumo plan keeps the inbox off until support enables it ([pricing](/pricing)).

### Pre-connect history fires nothing

<Callout type="warn">
  When an Instagram or Facebook account connects, Zernio replays the DM history it already holds on Meta into the inbox. That replayed history emits neither `conversation.started` nor `message.received`. This is the opposite of the [post backfill](/webhooks/posts#postexternalcreated), where every pre-existing native post is reported. Read replayed DMs from [List inbox conversations](/messages/list-inbox-conversations); they are stored as already read, so they never affect unread counts.
</Callout>

### Reactions arrive as their own event

Zernio sends `reaction.received` as its own event, never as `message.received`, so branch on it separately and a thumbs-up is never treated as an inbound DM. `reaction.action` is `added` or `removed`. `reaction.platformMessageId` is the platform-native id of the reacted-to message and is always present; `reaction.messageId` is the Zernio message id when it can be resolved. On WhatsApp, Instagram and Facebook removals the platform does not report which emoji went away, so `reaction.emoji` is an empty string when `action` is `removed`: match on `platformMessageId` to clear a reaction you mirror.

### Meta Business Agent answers from standby

On a WhatsApp number with Meta Business Agent enabled, the agent can answer a conversation on Meta's side while Zernio only observes. Zernio still delivers every inbound message as `message.received`, flagged `metadata.standby: true`, and the agent's own replies as `message.sent` with `source: "meta_business_agent"`, so a bot that replies to every `message.received` must skip standby ones or it takes the conversation away from the agent. `conversation.control_changed` tells you when control moves.

### Interactive replies ride on `message.received`

Zernio delivers button and list taps, flow responses, orders and referrals that arrive with a message under `metadata` on `message.received` (`interactiveType`, `interactiveId`, `flowResponseData`, `order`, `referral`). Only a referral that arrives without a message becomes `referral.received`.

---

## `message.received`

A new inbox message arrived. `message.text`, `message.attachments[]` and `message.sender` describe it; `conversation.id` is the `conversationId` for [`POST /v1/inbox/conversations/{conversationId}/messages`](/messages/send-inbox-message).

<br />

**Payload for `message.received`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: message.received
- **message** (required) `object`: 
  - **id** (required) `string`: Internal message ID
  - **conversationId** (required) `string`: Internal conversation ID
  - **platform** (required) `string`: No description - one of: instagram, facebook, telegram, whatsapp, sms
  - **platformMessageId** (required) `string`: Platform's message ID
  - **direction** (required) `string`: No description - one of: incoming, outgoing
  - **text** (required) `string,null`: Message text content
  - **attachments** (required) `array[object]`: 
    - **type** (required) `string`: Attachment type (image, video, file, sticker, audio, share)
    - **originalType** `string`: Instagram and Facebook only, and present only when it differs
from `type`. Meta's own attachment type before Zernio normalized
it: `ig_reel` and `reel` become `video`, while `ig_post`, `post`,
`ig_story` and `story_mention` all become `share`.

Read it before rendering, because `type: "share"` alone is
ambiguous. In particular a story mention arrives as
`type: "share"` with `originalType: "story_mention"`; treating an
unrecognized type as a generic document shows your agent
"document received" for what is usually a lead.

    - **url** (required) `string`: Where to fetch the attachment. **The contract differs by platform.**

- **WhatsApp**: points at `GET /v1/whatsapp/media/{mediaId}`, an
  authenticated Zernio endpoint. You MUST send
  `Authorization: Bearer <your API key>`; fetching it without that
  header returns `401`. Download and store the bytes when this
  webhook arrives: Meta drops inbound media after a limited
  retention window, after which the endpoint answers `400`
  permanently and the media is unrecoverable.
- **Instagram / Facebook / Telegram**: a direct platform CDN link
  that needs no authentication and expires on the platform's own
  schedule.

**Webhook attachments carry no `refreshUrl`.** That field is
stamped only when you read a message back over REST
(`GET /v1/inbox/conversations/{conversationId}/messages`). On
Instagram and Facebook the url above is a signed Meta CDN link
that expires, so do not persist it: store the message id and
resolve the media through
`GET /v1/inbox/conversations/{conversationId}/messages/{messageId}/attachments/{index}?accountId={accountId}`,
which re-mints it on demand. Every value that URL needs is
already in this payload: `message.conversationId`,
`message.platformMessageId`, `account.accountId`, and the
attachment's zero-based position in this array.

    - **payload** `object`: Additional attachment metadata
  - **sender** (required) `object`: 
    - **id** (required) `string`: Sender's platform identifier. For WhatsApp this is the phone
number (without leading `+`) when available, otherwise the
`businessScopedUserId`.

    - **contactId** `string`: Zernio CRM Contact id for this sender, when one exists (omitted for outgoing/business sender).
    - **name** `string`: No description
    - **username** `string`: No description
    - **picture** `string`: No description
    - **phoneNumber** `string,null`: WhatsApp only. Sender's phone number in E.164 format (with leading `+`).

**Nullable during the BSUID rollout (April 2026+).** WhatsApp
users who adopt a username can message businesses without
exposing a phone number, so this field is omitted for them.
Match by `businessScopedUserId` instead. See
`docs/whatsapp-bsuid-migration.md`.

    - **businessScopedUserId** `string`: WhatsApp only. Business-scoped user ID (BSUID), Meta's canonical
identifier for a WhatsApp user within your business. Present
when Meta includes it in the inbound payload (rollout in
progress since early April 2026). **Recommended primary identity
anchor** going forward; fall back to `phoneNumber` only when
this field is absent.

    - **parentBusinessScopedUserId** `string`: WhatsApp only. Parent BSUID for businesses with linked business
portfolios. Omitted for standalone portfolios.

    - **whatsappUsername** `string`: WhatsApp only. User's WhatsApp username (e.g. `@jane`). Not a
stable identifier, because users can change it. Useful for display,
not recommended as an identity anchor.

    - **instagramProfile** `object`: Instagram profile data for the sender. Only present for Instagram conversations.
      - **isFollower** `boolean,null`: Whether the sender follows your Instagram business account
      - **isFollowing** `boolean,null`: Whether your Instagram business account follows the sender
      - **followerCount** `integer,null`: The sender's follower count on Instagram
      - **isVerified** `boolean,null`: Whether the sender is a verified Instagram user
  - **sentAt** (required) `string` (date-time): When the message was sent, as reported by the platform and passed through unmodified. Full ISO 8601 date-time: Instagram and Facebook carry millisecond precision, while some platforms (for example WhatsApp and Telegram) report whole seconds. Use this field as the chronological ordering key. If two messages share the same value, fetch the conversation messages with sortOrder=desc for the deterministic order.
  - **isRead** (required) `boolean`: No description
  - **sentVia** `string,null`: Which Zernio surface produced the message. Always present and
always `null` on this event, since nobody on our side produced an
inbound message; it is only informative on `message.sent`, which
documents the vocabulary.
 - one of: human, api, broadcast, sequence, workflow, comment_automation, bulk-api, 
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **metadata** `object,null`: Platform-specific message context (present when the message is a quick reply tap, postback button tap, inline keyboard callback, a quote-reply to an earlier message, or a WhatsApp inbound that Meta Business Agent is answering)
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `message.sent`

An outgoing message was sent from the inbox, through the API or the dashboard.

<br />

**Payload for `message.sent`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: message.sent
- **message** (required) `object`: 
  - **id** (required) `string`: Internal message ID
  - **conversationId** (required) `string`: Internal conversation ID
  - **platform** (required) `string`: Every platform whose outgoing messages Zernio observes. sms is absent on purpose: its carrier receipts update delivery status and never raise message.sent. - one of: instagram, facebook, telegram, whatsapp, twitter, reddit, bluesky, slack
  - **platformMessageId** (required) `string`: Platform's message ID
  - **direction** (required) `string`: No description - one of: incoming, outgoing
  - **text** (required) `string,null`: Message text content
  - **attachments** (required) `array[object]`: 
    - **type** (required) `string`: Attachment type (image, video, file, sticker, audio, share)
    - **originalType** `string`: Instagram and Facebook only, and present only when it differs from `type`. Meta's own attachment type before Zernio normalized it. See the same field on message.received for the full mapping.
    - **url** (required) `string`: Where to fetch the attachment. For outgoing messages this is the
media URL as sent, so for WhatsApp it is the URL you supplied when
publishing (WhatsApp sends media by link), not a Zernio endpoint,
and it needs no Zernio credentials. Contrast the inbound direction:
`message.received` attachment URLs on WhatsApp point at the
authenticated `GET /v1/whatsapp/media/{mediaId}`.

As on `message.received`, webhook attachments carry no
`refreshUrl`: that field is stamped only on the REST read. Resolve
Instagram and Facebook media through
`GET /v1/inbox/conversations/{conversationId}/messages/{messageId}/attachments/{index}?accountId={accountId}`.

    - **payload** `object`: Additional attachment metadata
  - **sender** (required) `object`: **On this event the sender is your own business, not the person you
are talking to.** `id` is the Zernio account id and `name`,
`username` and `picture` are that connected account's own profile.

Do not read these to name or update a contact: doing so on an echo
relabels the customer's record with your business name. The other
party is `conversation.participantId` / `participantName` /
`participantUsername`, which are populated in both directions.

    - **id** (required) `string`: The Zernio account id of the connected account that sent the message, not a contact id.
    - **contactId** `string`: Always omitted on this event: the sender is the business, not a contact. Use conversation.contactId to join back to the CRM Contact.
    - **name** `string`: Display name of your connected account.
    - **username** `string`: Username of your connected account.
    - **picture** `string`: Profile picture of your connected account.
  - **sentAt** (required) `string` (date-time): When the message was sent, as reported by the platform and passed through unmodified. Full ISO 8601 date-time: Instagram and Facebook carry millisecond precision, while some platforms (for example WhatsApp and Telegram) report whole seconds. Use this field as the chronological ordering key. If two messages share the same value, fetch the conversation messages with sortOrder=desc for the deterministic order.
  - **isRead** (required) `boolean`: No description
  - **source** `string`: WhatsApp send origin. whatsapp_business_app when sent from the WhatsApp Business phone app on a Coexistence number; cloud_api when sent through Zernio (dashboard, API, or broadcasts); meta_business_agent when Meta Business Agent answered on the number. Absent on non-WhatsApp platforms. Says where WhatsApp saw the send come from, not which Zernio surface produced it: read sentVia for that. - one of: whatsapp_business_app, cloud_api, meta_business_agent
  - **sentVia** `string,null`: Which Zernio surface produced this message: `human` (an operator
in the Zernio inbox), `api` (a call to this API), `broadcast`,
`sequence`, `workflow`, `comment_automation`, or `bulk-api`
(POST /v1/whatsapp/bulk). Same vocabulary as the `source` filter
on the inbox analytics endpoints, and the same value a later
GET on this message returns.

Always present, and `null` whenever the lineage is unknown: a
message sent from the platform's own app, and every message
stored before this field shipped (2026-08). Existing messages
are NOT backfilled, so treat `null` as "unknown", never as
"sent by a human".
 - one of: human, api, broadcast, sequence, workflow, comment_automation, bulk-api, 
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **metadata** `object`: Platform-specific context for the sent message: a quote-reply reference, a WhatsApp location pin or WhatsApp contact cards. The key is present only when the send carried some context, and absent otherwise: it is never null and never an empty object. Read it to tell a location or contact-card message from a text one without a GET on the message.
  - **location** `object`: WhatsApp only. The location pin this message carries, in the same
shape the inbox send API accepts. Present on API sends that passed
`location`, and on Coexistence echoes of a pin shared from the
WhatsApp Business app. The message `text` is only the emoji
preview (`📍 <name>`); the pin itself lives here.

    - **latitude** `number`: Latitude in decimal degrees.
    - **longitude** `number`: Longitude in decimal degrees.
    - **name** `string`: Location name, when one was given.
    - **address** `string`: Street address, when one was given.
  - **contacts** `array[object]`: WhatsApp only. The contact cards this message carries. On API
sends this is the `contacts` array exactly as given to the inbox
send API (`name`, `phones[].phone` / `type`, `emails[]`); on
Coexistence echoes of a card shared from the WhatsApp Business
app it is Meta's shape (`phones[].wa_id`, `vcard`). The message
`text` is only the emoji preview (`👤 <name>`); the cards live
here.

    Type: `object` with additional properties
  - **quotedMessageId** `string`: `platformMessageId` of the message this send is a quote-reply to.

Present when the reply was sent through Zernio with `replyTo` on
the inbox send API (WhatsApp and Telegram). A WhatsApp API send
fires its `message.sent` off the delivery status, and the quote
reference is forwarded from the stored send there, so it arrives
on the same `message.sent` as any other WhatsApp send.

Not delivered on Instagram echoes. Zernio forwards
`reply_to.mid` whenever Meta puts it on an echo, but on
Instagram Meta does not send it, so a reply the operator quoted
in the Instagram app arrives with no `quotedMessageId`.
Facebook Messenger rides a separate subscription
(`message_echoes`) and has not been measured, so treat it as
unverified rather than supported.

Absent on WhatsApp Coexistence echoes. Meta omits the quote
context from `smb_message_echoes`, so a reply the operator sent
from the WhatsApp Business app arrives with no `quotedMessageId`
even though WhatsApp shows it as a quote-reply. Do not read the
absence of this field as "not a reply".

  - **threadTs** `string`: Slack only. Parent thread ts of the sent message. Pass it back as
`replyTo` on the inbox send API to keep replying inside the thread.

- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `conversation.started`

A new conversation began between one of your connected accounts and a contact, in either direction, on any DM platform. A given conversation fires it once, the first time it appears. Pre-connect history never fires it ([why](#pre-connect-history-fires-nothing)).

<br />

**Payload for `conversation.started`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: conversation.started
- **conversation** (required): `InboxWebhookConversationDetail` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **startedAt** (required) `string` (date-time): When the conversation document was created.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `conversation.control_changed`

WhatsApp only. Control of a conversation moved between Meta Business Agent and your app (Meta's `messaging_handovers`), or the agent was first seen answering a thread. `control.owner` is who answers now: `ai_agent` (Meta Business Agent), `app` (you) or `other` (another partner app on the number); `control.previousOwner` is who did before, `null` the first time the agent is seen on the thread. While the owner is `ai_agent`, inbound messages arrive on `message.received` with `metadata.standby: true` and the agent's replies on `message.sent` with `source: "meta_business_agent"`. Sending any message through the inbox takes control back; to hand it to the agent without sending, call [Hand a conversation to or from Meta Business Agent](/messages/set-conversation-thread-control) with `action: "release"`.

<br />

**Payload for `conversation.control_changed`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: conversation.control_changed
- **conversation** (required): `InboxWebhookConversationDetail` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **control** (required) `object`: 
  - **owner** (required) `string`: Who answers now. ai_agent: Meta Business Agent; app: you; other: another partner app on the number. - one of: app, ai_agent, other
  - **previousOwner** (required) `string,null`: Owner before this change, null when the thread had never been agent-handled. - one of: app, ai_agent, other, 
  - **metadata** `string`: Free-form string the transferring app attached to the handover, forwarded verbatim.
- **changedAt** (required) `string` (date-time): No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `message.edited`

The sender edited a previously sent message on Instagram, Facebook Messenger, Telegram or WhatsApp. The payload carries the full `editHistory`, oldest prior version first, and `message.text` is the latest version.

<br />

**Payload for `message.edited`:**

- **id** (required) `string`: No description
- **event** (required) `string`: No description - one of: message.edited
- **message** (required): `InboxWebhookMessage` - See schema definition
- **editHistory** (required) `array[InboxMessageEditHistoryEntry]`: Prior versions of the message, oldest first.
- **editCount** (required) `integer`: Total number of edits applied to this message.
- **editedAt** (required) `string` (date-time): When the most recent edit happened.
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `message.deleted`

The sender deleted (unsent) a message: on Instagram an incoming unsend, on WhatsApp in both directions, with `message.direction` telling the business's deletions from the customer's. The payload keeps the pre-delete `text` and `attachments` for moderation, compliance or archival. The Zernio dashboard does not show this content.

<br />

**Payload for `message.deleted`:**

- **id** (required) `string`: No description
- **event** (required) `string`: No description - one of: message.deleted
- **message** (required): `InboxWebhookMessage` - See schema definition
- **deletedAt** (required) `string` (date-time): No description
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `message.delivered`

An outgoing message was delivered to the recipient on WhatsApp, Facebook Messenger or SMS.

<br />

**Payload for `message.delivered`:**

- **id** (required) `string`: No description
- **event** (required) `string`: No description - one of: message.delivered, message.read, message.failed
- **message** (required): `InboxWebhookMessage` - See schema definition
- **statusAt** (required) `string` (date-time): When the platform reported this status.
- **error** `object,null`: Populated only on message.failed.
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `message.read`

An outgoing message was read by the recipient on WhatsApp, Facebook Messenger or Instagram.

<br />

**Payload for `message.read`:**

- **id** (required) `string`: No description
- **event** (required) `string`: No description - one of: message.delivered, message.read, message.failed
- **message** (required): `InboxWebhookMessage` - See schema definition
- **statusAt** (required) `string` (date-time): When the platform reported this status.
- **error** `object,null`: Populated only on message.failed.
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `message.failed`

An outgoing message failed to deliver on WhatsApp or SMS. `error` carries `code`, `title` and `message` from the platform, for example `131026` for a WhatsApp recipient that is not reachable; on SMS it carries the carrier's error code.

<br />

**Payload for `message.failed`:**

- **id** (required) `string`: No description
- **event** (required) `string`: No description - one of: message.delivered, message.read, message.failed
- **message** (required): `InboxWebhookMessage` - See schema definition
- **statusAt** (required) `string` (date-time): When the platform reported this status.
- **error** `object,null`: Populated only on message.failed.
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `reaction.received`

A participant added or removed an emoji reaction on a message, on Instagram, Facebook, WhatsApp, Telegram or Slack. On Telegram the bot must be an administrator in the chat; reactions in private chats are never delivered to bots.

<Callout type="warn">
  Instagram and Facebook accounts connected before August 2026 do not receive this event until their webhook subscription is refreshed. Meta only applies the reactions field when an account is connected and does not backfill it. Sending a reaction is unaffected. If an account sends reactions but never receives them, reconnect it.
</Callout>

<br />

**Payload for `reaction.received`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: reaction.received
- **reaction** (required) `object`: 
  - **emoji** (required) `string`: The emoji reacted with. May be an empty string when `action` is
`removed` on WhatsApp (Meta does not report which emoji was removed).

  - **action** (required) `string`: No description - one of: added, removed
  - **messageId** `string`: Internal Zernio message ID of the reacted-to message, when resolvable from the platform ID.
  - **platformMessageId** (required) `string`: Platform-native ID of the reacted-to message (e.g. WhatsApp wamid).
  - **sender** (required) `object`: Whoever added or removed the reaction. Usually the participant, but on WhatsApp, Slack, Instagram and Facebook Messenger it is the business own platform id when the business reacted from the native app or via the reactions API: compare it with conversation.participantId.
    - **id** (required) `string`: No description
    - **contactId** `string`: Zernio CRM Contact id for this sender, when one exists.
    - **name** `string`: No description
    - **username** `string`: No description
    - **picture** `string`: No description
    - **phoneNumber** `string,null`: WhatsApp only. Sender's phone number in E.164 format (with leading `+`), when available.
  - **reactedAt** (required) `string` (date-time): No description
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `referral.received`

Someone opened an existing Instagram or Facebook Messenger conversation through an ig.me or m.me `ref` link or a returning Messenger ad click. The payload forwards Meta's referral object verbatim: `ref` and `source` for links, `ad_id` and `ads_context_data` for ad clicks. A referral that rides an inbound message arrives on `message.received` under `metadata.referral` instead.

<br />

**Payload for `referral.received`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: referral.received
- **referral** (required) `object`: Meta's referral object, forwarded verbatim. Same shape as
`metadata.referral` on `message.received`: `ref` + `source` for
ig.me / m.me links, `ad_id` + `ads_context_data` for returning
Messenger ad clicks.

  - **ref** `string`: The `ref` parameter of the clicked ig.me / m.me link or ad.
  - **source** `string`: Meta-supplied source (`SHORTLINK`, `SHORTLINKS`, `IGME-SOURCE-LINK`, `ADS` - treat as opaque).
  - **type** `string`: Meta-supplied referral type (e.g. `OPEN_THREAD`).
  - **referer_uri** `string`: URI of the originating site, when Meta supplies one. Facebook Messenger only.
  - **ad_id** `string`: The Meta ad ID, on returning ad clicks. Facebook Messenger only.
  - **ads_context_data** `object`: Snapshot of the ad's public context at click time.
    - **ad_title** `string`: No description
    - **photo_url** `string`: No description
    - **video_url** `string`: No description
    - **post_id** `string`: No description
    - **product_id** `string`: No description
    - **flow_id** `string`: No description
- **sender** (required) `object`: Who clicked - the conversation participant.
  - **id** (required) `string`: Platform-scoped user ID (IGSID / PSID).
  - **contactId** `string`: Zernio CRM Contact id for this sender, when one exists.
- **conversation** (required): `InboxWebhookConversation` - See schema definition
- **account** (required): `InboxWebhookAccount` - See schema definition
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `comment.received`

A new comment arrived on a tracked post.

`comment.ad` is present when the comment was made on paid content: on Instagram it carries `ad.id` and `ad.title` from the Meta webhook, on Facebook `ad.promotionStatus` (`"active"` for boosted organic posts, `"ineligible"` for dark post creatives). It is absent for comments on organic posts that are not currently promoted, so `if (comment.ad)` filters ad-driven comments.

Instagram comments may carry `comment.author.instagramProfile` (`isFollower`, `isFollowing`, `followerCount`, `isVerified`). Meta reveals the follow relationship only for people who have messaged the account, and commenting does not grant that consent, so the object is absent for most first-time commenters. An absent object means unknown, never "not a follower". To resolve it on demand, call [Instagram follow status](/accounts/get-instagram-follow-status); to gate an auto-DM on it, use a comment automation's [audience rules](/platforms/instagram#comment-to-dm-automations).

<br />

**Payload for `comment.received`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: comment.received
- **comment** (required) `object`: 
  - **id** (required) `string`: Platform comment ID
  - **postId** (required) `string,null`: Internal post ID (null for posts not published through Zernio)
  - **platformPostId** (required) `string`: Platform's post ID
  - **platform** (required) `string`: No description - one of: instagram, facebook, threads, youtube, linkedin, bluesky, reddit, tiktok
  - **text** (required) `string`: Comment text content
  - **author** (required) `object`: 
    - **id** (required) `string`: Author's platform ID
    - **username** `string`: No description
    - **name** `string`: No description
    - **picture** `string,null`: No description
    - **isOwnAccount** `boolean`: True when this comment was authored by the connected account itself (Meta re-delivers the account's own replies as comments events). Populated on the Instagram and Facebook realtime webhooks only; absent means not evaluated, never "not the account".
    - **instagramProfile** `object`: Instagram only, best-effort. Present ONLY for commenters who have
messaged the account before: Meta gates the follow relationship behind
messaging consent, and commenting does not grant it. Absent otherwise -
treat a missing object as "unknown", never as "not a follower". To check
on demand, call GET /v1/accounts/{accountId}/follow-status/{userId}.

      - **isFollower** `boolean,null`: The commenter follows this account.
      - **isFollowing** `boolean,null`: This account follows the commenter.
      - **followerCount** `integer,null`: No description
      - **isVerified** `boolean,null`: No description
  - **createdAt** (required) `string` (date-time): No description
  - **isReply** (required) `boolean`: Whether this is a reply to another comment
  - **parentCommentId** (required) `string,null`: Parent comment ID if this is a reply
  - **ad** `object`: Ad context. Present only when the comment was made on paid content.
Instagram: populated from the webhook payload's value.media.ad_id and value.media.ad_title.
Facebook: populated via a Graph API lookup of the parent post's promotion_status.
Absent for comments on organic posts that are not currently promoted.

    - **id** `string`: Meta ad ID (Instagram only).
    - **title** `string`: Ad creative title (Instagram only).
    - **promotionStatus** `string`: Facebook promotion status returned by Graph API. Common values:
"active" (organic post currently boosted), "ineligible" (dark
post or ad creative, not promotable because it already is an ad).

  - **attachment** `object`: Facebook only. Present on graphic-only comments (sticker, GIF, photo) that
carry no text. URLs are ephemeral and may expire for Meta platforms (oe= expiry),
so fetch promptly. Instagram comments do not support attachments.

    - **type** (required) `string`: Attachment type: sticker, animated_image_share, or photo.
    - **imageUrl** `string`: Rendered image/preview URL (from attachment.media.image.src).
    - **url** `string`: Source URL (from attachment.url). For GIFs this is an l.facebook.com redirect.
- **post** (required) `object`: 
  - **id** (required) `string,null`: Internal post ID (null for posts not published through Zernio)
  - **platformPostId** (required) `string`: Platform's post ID
  - **content** (required) `string,null`: Post text, from our synced copy. No platform call is made on the comment path, so null when the post was never synced.
  - **imageUrl** (required) `string,null`: Post thumbnail or first media item URL. Platform CDN URLs expire, fetch promptly.
  - **permalink** (required) `string,null`: Public URL of the post. Null when no URL was ever stored for it, for example a platform draft or a post recovered without one.
- **account** (required) `object`: 
  - **id** (required) `string`: Account ID
  - **accountId** `string`: Account ID (same as id); canonical field for account filtering.
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `review.new`

A new review was posted on a connected Google Business Profile account, in real time through Pub/Sub.

<br />

**Payload for `review.new`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: review.new
- **review** (required): `ReviewWebhookReview` - See schema definition
- **account** (required) `object`: 
  - **id** (required) `string`: No description
  - **accountId** `string`: Account ID (same as id); canonical field for account filtering.
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `review.updated`

A review changed: the reviewer edited their text or rating, or a reply was added through the API or the Google Business Profile dashboard. The payload has the same shape as `review.new`; when a reply is present, `review.hasReply` is `true` and `review.reply` is populated.

<br />

**Payload for `review.updated`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: review.updated
- **review** (required): `ReviewWebhookReview` - See schema definition
- **account** (required) `object`: 
  - **id** (required) `string`: No description
  - **accountId** `string`: Account ID (same as id); canonical field for account filtering.
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

## Related

- [Webhooks](/webhooks): create an endpoint, retries, signatures.
- [Send a message](/messages/send-inbox-message): reply to `message.received` with `accountId` and `message`.
- [List inbox conversations](/messages/list-inbox-conversations): the same conversations on demand.
- [Comment automations](/comment-automations/list-comment-automations): DM people who comment a keyword.
- [Chat SDK](/resources/integrations/chat-sdk): a bot framework that consumes these events.

---
