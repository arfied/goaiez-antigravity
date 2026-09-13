# List conversion events API Reference

Returns the most recent conversion events sent through
`POST /v1/whatsapp/conversions` for the given WhatsApp account.
Sourced from delivery logs (Axiom `late` dataset), so the visible
window is bounded by log retention (about 30 days). Useful for
rendering a "recent activity" panel on the conversions setup tab
without standing up a parallel persistence layer.

Per-event payload mirrors the structured log we write on every
successful send: `eventName`, `conversationId`, `eventsReceived`,
`eventsFailed`, `traceId`, `durationMs`, and the wall-clock
`timestamp`.


## GET /v1/whatsapp/conversions

**List conversion events**

Returns the most recent conversion events sent through
`POST /v1/whatsapp/conversions` for the given WhatsApp account.
Sourced from delivery logs (Axiom `late` dataset), so the visible
window is bounded by log retention (about 30 days). Useful for
rendering a "recent activity" panel on the conversions setup tab
without standing up a parallel persistence layer.

Per-event payload mirrors the structured log we write on every
successful send: `eventName`, `conversationId`, `eventsReceived`,
`eventsFailed`, `traceId`, `durationMs`, and the wall-clock
`timestamp`.


### Parameters

- **accountId** (required) in query: WhatsApp account ID
- **limit** (optional) in query: Max events to return (1-200, default 50).

### Responses

#### 200: Recent conversion events

**Response Body:**

- **events** `array[object]`: 
  - **timestamp** `string` (date-time): When the event was sent to Meta.
  - **eventName** `string`: One of LeadSubmitted, Purchase, AddToCart, InitiateCheckout, ViewContent.
  - **conversationId** `string,null`: No description
  - **eventsReceived** `integer,null`: Number of events Meta accepted on this send (usually 1).
  - **eventsFailed** `integer,null`: Number of events Meta rejected (usually 0).
  - **traceId** `string,null`: Meta fbtrace_id for cross-referencing in Events Manager.
  - **durationMs** `integer,null`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

## POST /v1/whatsapp/conversions

**Send WhatsApp conversion event**

Forward a WhatsApp Business Messaging conversion event (`LeadSubmitted`,
`Purchase`, `AddToCart`, `InitiateCheckout`, `ViewContent`) to Meta's
Conversions API with `action_source = business_messaging` and
`messaging_channel = whatsapp`. The endpoint looks up the originating
CTWA click ID (`ctwa_clid`) captured on the first inbound message of
the conversation and replays it on every event so Meta can attribute
the conversion back to the Click-to-WhatsApp ad that drove the chat.

Configuration prerequisite on the WhatsApp account metadata:
  - `metaCapiDatasetId`: the Meta dataset ID linked to the WABA.
    Provision one with `POST /v1/whatsapp/dataset`.

The WABA ID (already set automatically at connect time) is forwarded as
`user_data.whatsapp_business_account_id`, which is the per-channel
attribution identifier Meta requires for WhatsApp events. No Facebook
Page ID is needed (that field is the Messenger-branch identifier).

Identify the conversation by either `conversationId` (preferred) or
`phoneE164` (digits only, no `+`). At least one is required. If the
conversation has no captured `ctwa_clid`, the request returns 422
because there is nothing to attribute.

Token and dataset coupling: the WhatsApp account's accessToken must
have access to the configured `metaCapiDatasetId`. By default a WABA's
system-user token is scoped to the WABA's own Business Manager and
cannot post to a pixel owned by a different Business; Meta returns
code 100 in that case. Either share the dataset with the WhatsApp
app's Business in BM, or use a dataset already in the same Business
as the WABA.


### Request Body

- **accountId** (required) `string`: WhatsApp SocialAccount ID.
- **eventName** (required) `string`: Live-verified allowlist of event names accepted by Meta's
CAPI for Business Messaging (Graph API v25.0). Other
standard pixel events including `Lead`,
`CompleteRegistration`, `Subscribe`, `Schedule`, `Contact`,
`StartTrial`, `AddPaymentInfo`, `Search`, and
`SubmitApplication` are rejected with subcode 2804066
("Messaging Event Invalid Event Type") on
`action_source = business_messaging` events. Custom event
names are also rejected.

Use `LeadSubmitted` (NOT `Lead`) for lead-style conversions.
 - one of: LeadSubmitted, Purchase, AddToCart, InitiateCheckout, ViewContent
- **eventTime** `number`: Unix seconds. Defaults to the time of the request when
omitted. Meta's attribution window is 7 days from click;
events older than that lose attribution.

- **eventId** (required) `string`: Stable dedup key. Reuse to suppress duplicate events
(Meta dedupes against pixel events with the same id).

- **conversationId** `string`: Zernio Conversation `_id` (preferred lookup). The
conversation must have a captured `ctwa_clid` in metadata
(set automatically by the WhatsApp webhook on the first
inbound message after a CTWA ad click).

- **phoneE164** `string`: Contact phone number, digits only with no '+'. When used
in lieu of `conversationId`, the handler resolves to the
most recent CTWA-attributed conversation for this phone
on the supplied account.

- **value** `number`: Conversion value (e.g. order total).
- **currency** `string`: ISO 4217 currency code (e.g. `USD`).
- **contentIds** `array`: Optional product / content identifiers.
- **email** `string`: User email. Normalized + SHA-256 hashed before sending to Meta.
- **externalId** `string`: Stable customer identifier. Lowercased + SHA-256 hashed
before sending to Meta.

- **testCode** `string`: Meta `test_event_code` passthrough. Routes the event to
the Test Events tab in Events Manager instead of the
production dataset, useful for development.


### Responses

#### 200: Event submitted to Meta. Inspect `eventsFailed` and `failures[]`
to detect partial failures. A 200 does not mean Meta accepted the
event; the status reflects "request reached Meta" only.


**Response Body:**

- **platform** `string`: No description - one of: metaads
- **eventsReceived** `integer`: Events accepted by Meta.
- **eventsFailed** `integer`: Events rejected by Meta (see failures).
- **failures** `array[object]`: Per-event failure detail. Empty when all events were
accepted.

  - **eventIndex** `integer`: Index into the submitted events array.
  - **eventId** `string`: Echoes back the eventId of the failed event.
  - **message** `string`: No description
  - **code**: One of multiple types
- **traceId** `string`: Meta `fbtrace_id` for debugging. Surface in support
tickets.


#### 400: Invalid body.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Conversation not found.

#### 422: Configuration missing (no `metaCapiDatasetId` on the account, set
it via POST /v1/whatsapp/dataset) OR the resolved conversation has
no captured `ctwa_clid`.


---

---
