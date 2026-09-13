# Phone number webhooks

Receive an event at each step of a provisioned number's life, from KYC submission and review to activation, suspension and release.

Phone number events follow a number provisioned through Zernio from KYC submission through review, activation, suspension and release. The names carry a `whatsapp.` prefix for legacy reasons and fire for every provisioned number. Subscribe with `POST /v1/webhooks/settings` and the event names below ([first event](/webhooks#first-event)); delivery, retries and signatures are the same for every event ([how webhooks behave](/webhooks#how-it-behaves)).

## Events

| Event | Description |
| --- | --- |
| [`whatsapp.number.kyc_submitted`](#whatsappnumberkyc_submitted) | An end customer completed a hosted KYC share link; the number enters review under your account. |
| [`whatsapp.number.activated`](#whatsappnumberactivated) | A number you provisioned finished setup and is ready to connect. |
| [`whatsapp.number.declined`](#whatsappnumberdeclined) | A regulated number order was declined in review and no number was activated. |
| [`whatsapp.number.action_required`](#whatsappnumberaction_required) | The regulator asked for more information on a placed order; the order stays pending until you provide it. |
| [`whatsapp.number.verification_required`](#whatsappnumberverification_required) | A regulated number needs end-user ID verification; carries the link to forward. |
| [`whatsapp.number.suspended`](#whatsappnumbersuspended) | An active number was suspended, for example after a failed payment; carries a `reason`. |
| [`whatsapp.number.reactivated`](#whatsappnumberreactivated) | A suspended number is usable again. |
| [`whatsapp.number.released`](#whatsappnumberreleased) | A number was released and is no longer usable (terminal); carries a `reason`. |
| [`phone_number.stock_available`](#phone_numberstock_available) | An out-of-stock country you watch has deliverable numbers again; once per watch. |

## How it behaves

### Events fire on status transitions

Every number carries a `status` you can read back with [Get phone number](/phone-numbers/get-phone-number). Zernio fires an event on each transition; the diagram labels drop the `whatsapp.number.` prefix:

<Mermaid
  chart={`stateDiagram-v2
  direction LR
  [*] --> pending_regulatory: kyc_submitted
  pending_regulatory --> active: activated
  pending_regulatory --> regulatory_declined: declined
  regulatory_declined --> pending_regulatory: remediate (API call)
  active --> suspended: suspended
  suspended --> active: reactivated
  active --> released: released
  suspended --> released: released
  released --> [*]`}
/>

One event on this page hangs off nothing in that diagram. `phone_number.stock_available` belongs to a stock watch, not to a number: it fires from the 6-hourly stock sweep before you own a number in that country at all, once per watch, and the watch is then consumed ([availability](/platforms/phone-numbers/availability)). Every other event on this page reports a transition of a number you already hold.

Polling can also catch short-lived transit statuses the events skip: `pending_payment`, `provisioning`, `verifying` (provisioned and awaiting confirmation, not billed until `active`) and `releasing`.

### Instant numbers skip the review states

Zernio moves a number in an [instant-provisioning country](/platforms/phone-numbers/availability) straight to `active`, so the first event you see is `whatsapp.number.activated`. The review states apply only to regulated (KYC) countries.

### `kyc_submitted` fires only for hosted share links

Zernio sends `whatsapp.number.kyc_submitted` when your customer finishes a [white-labeled KYC form](/platforms/phone-numbers/kyc#hand-the-form-off-white-label). When you submit KYC over the API you already know the moment of submission, so no event fires.

### `action_required` and `verification_required` leave the status unchanged

Zernio keeps the number at `pending_regulatory` while the regulator waits on you. These events say the review is blocked, not that the state changed.

### `regulatory_declined` can be remediated

[Resubmit corrected details](/phone-numbers/remediate-phone-number) and the number returns to `pending_regulatory`: the same number, no new billing. Zernio never bills a number that was declined.

### `released` is the only terminal state

Zernio recovers `suspended` numbers with `reactivated`, but a suspension left unresolved ends in release.

---

## `whatsapp.number.kyc_submitted`

An end customer completed a [hosted KYC share link](/platforms/phone-numbers/kyc#hand-the-form-off-white-label). The number enters regulatory review (`pending_regulatory`) under your account; `whatsapp.number.activated` or `whatsapp.number.declined` follows once the provider rules on it. Update your own UI from this event instead of polling.

<br />

**Payload for `whatsapp.number.kyc_submitted`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.number.kyc_submitted, verification.approved, verification.failed
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **number** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **profileId** `string`: No description

---

## `whatsapp.number.activated`

A number you provisioned through Zernio finished setup and is ready to connect. For regulated (non-US) numbers this can take 1 to 3 business days after the order is approved.

<br />

**Payload for `whatsapp.number.activated`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.number.activated
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **number** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **profileId** `string`: No description

---

## `whatsapp.number.declined`

A regulated number order was declined in regulatory review and no number was activated. The order never activates and you are never billed for it.

<br />

**Payload for `whatsapp.number.declined`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.number.declined
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **number** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **profileId** `string`: No description
- **reason** `string,null`: No description

---

## `whatsapp.number.action_required`

The regulator reviewing a placed number order asked for more information, for example a certificate of company incorporation. Nothing was rejected: the order stays pending and does not progress until you provide the information. `reason` carries the regulator's request verbatim when available. Provide the missing details from the dashboard's phone-numbers page or re-submit the relevant fields through [the remediation endpoint](/phone-numbers/remediate-phone-number); the review resumes automatically.

<br />

**Payload for `whatsapp.number.action_required`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.number.action_required
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **reason** `string`: No description
- **requirements** `array[object]`: Every requirement on the order with the reviewer's current verdict. Omitted when the order's requirements could not be read.
  - **requirementId** `string`: Same id as fields[].requirementId on the remediation endpoint.
  - **label** `string`: No description
  - **status** `string`: No description - one of: approved, pending, declined
- **reviewedAt** `string` (date-time): When the reviewer last commented on the order. Omitted when there is no reviewer comment.
- **number** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **profileId** `string`: No description

---

## `whatsapp.number.verification_required`

A regulated number requires the end user to complete an identity check, for example Australian mobile numbers. The payload carries a one-time `verificationUrl` to forward to the person whose ID is on file; the order completes once they pass.

<br />

**Payload for `whatsapp.number.verification_required`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.number.verification_required
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **number** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **profileId** `string`: No description
- **verificationUrl** `string`: No description

---

## `whatsapp.number.suspended`

An active number was suspended, for example after a failed payment. The number stops working until the issue is resolved, then `whatsapp.number.reactivated` follows. `reason` is a value such as `payment_failed` or `subscription_ended`.

<br />

**Payload for `whatsapp.number.suspended`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.number.suspended
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **number** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **profileId** `string`: No description
- **reason** `string,null`: No description

---

## `whatsapp.number.reactivated`

A suspended number was reactivated, for example after the payment recovered, and is usable again.

<br />

**Payload for `whatsapp.number.reactivated`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.number.reactivated
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **number** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **profileId** `string`: No description

---

## `whatsapp.number.released`

A number was released and is no longer usable, whether you released it, a billing cleanup released it, or an admin did. This is terminal. `reason` is a value such as `user_requested` or `cleanup_suspended`.

<br />

**Payload for `whatsapp.number.released`:**

- **id** `string`: No description
- **event** `string`: No description - one of: whatsapp.number.released
- **timestamp** `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.
- **number** `object`: 
  - **id** `string`: No description
  - **phoneNumber** `string`: No description
  - **country** `string`: No description
  - **profileId** `string`: No description
- **reason** `string,null`: No description

---

## `phone_number.stock_available`

An out-of-stock country you watch with [Create stock watch](/phone-numbers/create-phone-number-stock-watch) has deliverable numbers again. It fires once per watch and the watch is consumed. `stock.country` is the watched country and `stock.types[]` lists each number type that has stock with its `availableCount` at sweep time. Numbers are first come, first served, so purchase promptly.

<br />

**Payload for `phone_number.stock_available`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: phone_number.stock_available
- **stock** (required) `object`: 
  - **country** (required) `string`: ISO 3166-1 alpha-2 country code of the watched country.
  - **types** (required) `array[object]`: Number types deliverable at sweep time. Only types with stock are listed.
    - **numberType** (required) `string`: local, mobile, national or toll_free.
    - **availableCount** (required) `integer`: Deliverable numbers at sweep time; first come, first served.
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

## Related

- [Webhooks](/webhooks): create an endpoint, retries, signatures.
- [Phone numbers](/platforms/phone-numbers): purchase, KYC and availability.
- [Get phone number](/phone-numbers/get-phone-number): read `status` on demand.
- [Remediate a number](/phone-numbers/remediate-phone-number): fix a declined order.
- [WhatsApp webhooks](/webhooks/whatsapp): template and display-name reviews.

---
