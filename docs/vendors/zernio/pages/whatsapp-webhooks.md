# WhatsApp webhooks

Receive an event when Meta reviews a WhatsApp template or display name, and when Meta detects a lead or purchase in a Click-to-WhatsApp conversation.

WhatsApp events cover template and display-name reviews and Meta's automatic lead and purchase detection in Click-to-WhatsApp conversations. Number lifecycle events are on [phone number webhooks](/webhooks/phone-numbers). Subscribe with `POST /v1/webhooks/settings` and the event names below ([first event](/webhooks#first-event)); delivery, retries and signatures are the same for every event ([how webhooks behave](/webhooks#how-it-behaves)).

## Events

| Event | Description |
| --- | --- |
| [`whatsapp.template.status_updated`](#whatsapptemplatestatus_updated) | Meta finished reviewing or re-reviewing a WhatsApp Business template on a connected WABA. |
| [`whatsapp.template.category_updated`](#whatsapptemplatecategory_updated) | Meta reclassified a template's category, as a 24-hour advance notice and again when applied. |
| [`whatsapp.account.name_status_updated`](#whatsappaccountname_status_updated) | Meta finished reviewing a display-name change on a connected number. |
| [`whatsapp.automatic_event`](#whatsappautomatic_event) | Meta's automatic event identification detected a lead or purchase in a Click-to-WhatsApp conversation. |

## How it behaves

### Zernio forwards Meta's review outcomes as they land

Zernio forwards Meta's `message_template_status_update` field as `whatsapp.template.status_updated` and `template_category_update` as `whatsapp.template.category_updated`, on the WhatsApp Business Account. Meta includes neither the previous status nor the template's category in a status update. A display-name change fires `whatsapp.account.name_status_updated` only for a review outcome; a name applied without review produces no event.

### Category changes arrive twice

Zernio sends `whatsapp.template.category_updated` with `template.changeType: "scheduled"` for Meta's 24-hour advance notice and again with `"applied"` when the change takes effect. `template.category` is always the category right now. The category decides Meta's per-delivery rate for the template ([WhatsApp rates](/pricing/whatsapp#messages)) and the [delivery window](/platforms/whatsapp/templates#delivery-window) it can carry; Meta clears a custom window when it recategorises a template, so read `message_send_ttl_seconds` back after an `applied` change.

### Automatic events carry the Conversions API match key

Zernio delivers `ctwaClid` on `whatsapp.automatic_event`. Meta omits that clid on a minority of referrals, on any number, most often WhatsApp Status placements; this event can supply it there. Zernio also writes the clid back onto the conversation, so `POST /v1/whatsapp/conversions` becomes usable for the conversation.

Detection is not available for EU, UK and JP businesses, and elsewhere the business owner opts into it inside Meta's [Embedded Signup](/platforms/whatsapp/connection#step-2-start-embedded-signup) flow when the number is connected. There is no Zernio field for it and no way to subscribe your way into it: an endpoint subscribed to `whatsapp.automatic_event` on a number whose owner did not opt in receives nothing.

---

## `whatsapp.template.status_updated`

Meta finished reviewing or re-reviewing a [WhatsApp Business template](/platforms/whatsapp/templates) on a connected WABA. Branch on `template.status` (`APPROVED`, `REJECTED`, `PENDING`, `PAUSED`, `DISABLED`, `IN_APPEAL`, `PENDING_DELETION`); `template.reason` is Meta's free-form reason or `"NONE"`.

<br />

**Payload for `whatsapp.template.status_updated`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: whatsapp.template.status_updated
- **account** (required) `object`: 
  - **accountId** (required) `string`: No description
  - **profileId** (required) `string`: No description
  - **platform** (required) `string`: No description - one of: whatsapp
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
- **template** (required) `object`: 
  - **templateId** (required) `string`: Meta's `message_template_id`, returned as a string.
  - **name** (required) `string`: Meta's `message_template_name`.
  - **language** (required) `string`: Meta's `message_template_language` (e.g. `en_US`).
  - **status** (required) `string`: New status. Forwarded verbatim from Meta's `event` field.
`PENDING_DELETION` is the 24h-grace state after a delete
request before the template is actually removed.
 - one of: APPROVED, REJECTED, PENDING, PAUSED, DISABLED, IN_APPEAL, PENDING_DELETION
  - **reason** (required) `string`: Meta's free-form reason for the transition. `"NONE"` on
approval; an explanation string on rejection.

- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `whatsapp.template.category_updated`

Meta reclassified a template's category. `template.changeType` is `scheduled` or `applied`; `template.category` is the current category.

<br />

**Payload for `whatsapp.template.category_updated`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: whatsapp.template.category_updated
- **account** (required) `object`: 
  - **accountId** (required) `string`: No description
  - **profileId** (required) `string`: No description
  - **platform** (required) `string`: No description - one of: whatsapp
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
- **template** (required) `object`: 
  - **templateId** (required) `string`: Meta's `message_template_id`, returned as a string.
  - **name** (required) `string`: Meta's `message_template_name`.
  - **language** (required) `string`: Meta's `message_template_language` (e.g. `en_US`).
  - **changeType** (required) `string`: `scheduled` is Meta's 24h advance notice of an upcoming
reclassification; `applied` is the change taking effect.
 - one of: scheduled, applied
  - **category** (required) `string`: The category right now, regardless of changeType. - one of: UTILITY, MARKETING, AUTHENTICATION
  - **previousCategory** `string`: Present only when changeType is `applied`. The category before this change. - one of: UTILITY, MARKETING, AUTHENTICATION
  - **scheduledCategory** `string`: Present only when changeType is `scheduled`. The category that will take effect at `effectiveAt`. - one of: UTILITY, MARKETING, AUTHENTICATION
  - **effectiveAt** `string` (date-time): Present only when changeType is `scheduled`. ISO-8601 timestamp when the scheduled category takes effect.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `whatsapp.account.name_status_updated`

Meta finished reviewing a WhatsApp display-name change on a connected number. Branch on `name.status` (`APPROVED`, `DECLINED`, `PENDING_REVIEW`; Meta's `DEFERRED` maps to `PENDING_REVIEW`, the review is still open); `name.requestedName` and `name.rejectionReason` are Meta's values or null.

<br />

**Payload for `whatsapp.account.name_status_updated`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: whatsapp.account.name_status_updated
- **account** (required) `object`: 
  - **accountId** (required) `string`: No description
  - **profileId** (required) `string`: No description
  - **platform** (required) `string`: No description - one of: whatsapp
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
- **name** (required) `object`: 
  - **status** (required) `string`: Normalized from Meta's `decision` (REJECTED -> DECLINED, DEFERRED -> PENDING_REVIEW; the review is still open on DEFERRED, not a rejection). - one of: APPROVED, DECLINED, PENDING_REVIEW
  - **requestedName** (required) `string,null`: The display name Meta reviewed. Null if Meta did not send one.
  - **rejectionReason** (required) `string,null`: Meta's free-form decline reason. Null on approval, or when Meta sends the literal string "NONE".
  - **displayPhoneNumber** (required) `string,null`: The phone number this review is for, as Meta reported it.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `whatsapp.automatic_event`

Meta's automatic event identification detected a lead or purchase in a [Click-to-WhatsApp](/platforms/whatsapp/ctwa) conversation. Branch on `eventName` (`LeadSubmitted` or `Purchase`); Purchase events may carry the detected amount in `customData` (`currency`, `value`).

<br />

**Payload for `whatsapp.automatic_event`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.automatic_event
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **accountId** `string`: SocialAccount id of the WhatsApp number whose conversation was flagged.
- **conversationId** `string`: Zernio conversation id, when the thread could be resolved.
- **platformMessageId** `string`: The wamid of the message Meta's analysis flagged.
- **eventName** `string`: Meta-detected event: `LeadSubmitted` | `Purchase`.
- **ctwaClid** `string`: Meta's CTWA click id, the Conversions API match key.
- **customData** `object`: Purchase events may carry the detected amount.
  - **currency** `string`: No description
  - **value** `number`: No description
- **detectedAt** `string` (date-time): No description

## Related

- [Webhooks](/webhooks): create an endpoint, retries, signatures.
- [WhatsApp templates](/platforms/whatsapp/templates): create the templates these reviews are about.
- [Click-to-WhatsApp](/platforms/whatsapp/ctwa): ads and conversions.
- [Phone number webhooks](/webhooks/phone-numbers): number activation, suspension and release.
- [Inbox webhooks](/webhooks/inbox): the messages themselves.

---
