# Create conversation API Reference

Start a direct message conversation with a user. If a conversation with that recipient already exists, the message is added to the existing thread.

Supported platforms: X, Bluesky, Reddit, WhatsApp, SMS, and Slack. Other platforms return PLATFORM_NOT_SUPPORTED.

**Slack.** Pass a workspace member id as participantId (list them with GET /v1/accounts/{accountId}/slack-members). Zernio opens the DM channel with that member and sends the message; the thread then behaves like any other Slack conversation in the inbox. The member must belong to the connected workspace.

**WhatsApp.** This is the endpoint for sending an approved template message to a phone number. Provide templateName, templateLanguage, and templateParams (variable values for the text header, body and dynamic URL buttons, in that order), with the recipient phone in participantId. A template is required because WhatsApp does not permit freeform messages to open a conversation; a missing template returns TEMPLATE_REQUIRED.

- Templates with media headers (image, video, document) are handled automatically: Zernio reads the approved template definition and fills the header at send time with the template's approved sample asset. To send a DIFFERENT asset per message (e.g. a distinct invoice PDF for each recipient), pass the headerMedia field with a public link (or a Meta media id); it overrides the sample for that send.
- A template whose approved header format is LOCATION has no header asset to reconstruct at all: Meta only accepts the location at send time, so pass headerLocation (latitude and longitude required) whenever such a template is sent; headerMedia and headerLocation cannot both be supplied.
- A button that carries its own value at send time (a copy-code button holding a Pix payment code or a coupon, a flow token) is sent with templateButtonParams, addressed by the button's index; templateParams covers text variables and dynamic URL buttons only.
- CAROUSEL templates take per-card overrides via templateCards, each addressed by the card's card_index, because card body variables restart at {{1}} per card and cannot be expressed in the flat templateParams order.
- Template fields are accepted on the JSON body only, not on multipart requests.

For a number you already have a thread with, this sends the template into that thread, which also makes it the way to re-engage a contact after the 24-hour customer-service window has closed. Once the recipient replies (opening the 24h window), send freeform messages with the send-message endpoint (POST /v1/inbox/conversations/{conversationId}/messages).

Alternatively, WhatsApp Business Accounts eligible for Meta Direct Send can open a conversation with a business-initiated utility text message and no template: pass category: 'utility' together with message (and no templateName). See the category field below.

**DM eligibility (X).** Before sending, the endpoint checks if the recipient accepts DMs from your account (via the receives_your_dm field). If not, a 422 error with code DM_NOT_ALLOWED is returned. You can skip this check with skipDmCheck: true if you have already verified eligibility.

**X API tier requirement.** DM write endpoints require X API Pro tier ($5,000/month) or Enterprise access. This applies to BYOK (Bring Your Own Key) users who provide their own X API credentials.

**Rate limits (X only).** X's DM API enforces 200 requests per 15 minutes, 1,000 per 24 hours per connected X account, and 15,000 per 24 hours per X developer app (shared across all DM endpoints). These limits do NOT apply to other platforms. WhatsApp sends are governed by Meta's per-number messaging tiers (unique business-initiated conversations per 24 hours) and per-number throughput instead.


## GET /v1/inbox/conversations

**List conversations**

Fetch conversations (DMs) from all connected messaging accounts in a single API call. Supports filtering by profile and platform. Results are aggregated and deduplicated.

Supported platforms: Facebook, Instagram, X, Bluesky, Reddit, Telegram.

**X limitation.** X has replaced traditional DMs with encrypted "X Chat" for many accounts. Messages sent or received through encrypted X Chat are not accessible via X's API (the /2/dm_events endpoint only returns legacy unencrypted DMs). This means some X conversations may show only outgoing messages or appear empty. This is an X platform limitation that affects all third-party applications. See X's docs on encrypted messaging for more details.

**Instagram and Facebook pre-connect history.** When one of these accounts is connected, Zernio replays the DM history the account already holds on Meta, so conversations that began before the account was connected appear here. Up to 500 conversations per account are replayed.

- The replay runs in the background and can finish after a listing you have already taken, and replayed conversations keep their original lastMessageAt, so they sort into date order rather than appearing at the top. If you mirror this endpoint into your own store, re-run the sweep rather than relying on a single pass at connect time.
- Replayed history emits no webhooks and is stored as already read, so it never affects unread counts.
- Threads that Meta refuses to serve are skipped, and an account whose Instagram "connected tools" message access is turned off is not replayed at all.


### Parameters

- **profileId** (optional) in query: Filter by profile ID
- **platform** (optional) in query: Filter by platform
- **status** (optional) in query: Filter by conversation status
- **sortOrder** (optional) in query: Sort order by updated time
- **limit** (optional) in query: Maximum number of conversations to return
- **cursor** (optional) in query: Pagination cursor for next page
- **accountId** (optional) in query: Filter by specific account ID

### Responses

#### 200: Aggregated conversations

**Response Body:**

- **data** `array[object]`: 
  - **id** `string`: Opaque conversation identifier. Pass it back verbatim to any /v1/inbox/conversations/{conversationId} route; do not assume a fixed format.
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **accountUsername** `string`: No description
  - **participantId** `string`: No description
  - **participantName** `string`: No description
  - **participantPicture** `string,null`: No description
  - **participantVerifiedType** `string,null`: X verified badge type. Only present for X conversations. - one of: blue, government, business, none
  - **lastMessage** `string`: No description
  - **updatedTime** `string` (date-time): No description
  - **status** `string`: No description - one of: active, archived
  - **unreadCount** `integer,null`: Number of unread messages
  - **threadControl** `string`: WhatsApp only, present once Meta Business Agent has touched the thread. ai_agent: the agent answers and new inbound arrive flagged metadata.standby; app: you hold control; other: another partner app does. Change it with POST /v1/inbox/conversations/{conversationId}/thread-control. - one of: app, ai_agent, other
  - **url** `string,null`: Direct link to open the conversation on the platform (if available)
  - **instagramProfile** `object,null`: Instagram profile data for the participant. Only present for Instagram conversations.
  - **metadata** `object,null`: Click attribution for a conversation that started from a Meta ad or
a ref-tagged ig.me / m.me link. Absent when the conversation did not
originate from an attributable click.

Captured from the referral Meta delivers for the click. If the same
person later arrives through a different ad or link, the original
values are kept, so the first referral wins; read the fresh referral
per click on the `message.received` / `referral.received` webhooks
instead. One exception on WhatsApp: when Meta omits `ctwa_clid`
from that referral, a later Meta automatic event can supply it and
refresh `ctwa_captured_at`, so treat `ctwa_captured_at` as the time
Zernio stored the value, not the time of the click.

Two families of keys, one per surface. They never appear together:

  - `ctwa_*` is WhatsApp Click-to-WhatsApp. The ad ID is
    `ctwa_source_id`. There is no `meta_ad_id` on WhatsApp.
  - `meta_ad_*` is Instagram Click-to-Direct, Facebook Messenger
    Click-to-Message, and ig.me / m.me ref links. The ad ID is
    `meta_ad_id` (ad clicks only; a link capture carries
    `meta_ad_ref` without it). `ctwa_clid` never appears on these
    platforms.

Every key is optional and only the keys Meta supplied are returned, so
read defensively. Meta does not send a campaign or ad set ID, so none
is exposed here. More keys may be added over time. Treat any key you
do not recognise as an opaque string.

Key names differ from the `message.received` webhook on purpose. The
webhook forwards Meta's referral verbatim (`ad_id`, `source`, `type`)
while the stored conversation record uses the prefixed names below.
Renaming either side would break existing integrations, so both
spellings are kept.

- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **nextCursor** `string,null`: No description
- **meta** `object`: 
  - **accountsQueried** `integer`: No description
  - **accountsFailed** `integer`: No description
  - **failedAccounts** `array[object]`: 
    - **accountId** `string`: No description
    - **accountUsername** `string,null`: No description
    - **platform** `string`: No description
    - **error** `string`: No description
    - **code** `string,null`: Error code if available
    - **retryAfter** `integer,null`: Seconds to wait before retry (rate limits)
  - **lastUpdated** `string` (date-time): No description
  - **accountsSkipped** `array[object]`: Connected accounts that were not queried: their platform does not support this feature, or the account is not enabled for it
    - **accountId** `string`: No description
    - **platform** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

## POST /v1/inbox/conversations

**Create conversation**

Start a direct message conversation with a user. If a conversation with that recipient already exists, the message is added to the existing thread.

Supported platforms: X, Bluesky, Reddit, WhatsApp, SMS, and Slack. Other platforms return PLATFORM_NOT_SUPPORTED.

**Slack.** Pass a workspace member id as participantId (list them with GET /v1/accounts/{accountId}/slack-members). Zernio opens the DM channel with that member and sends the message; the thread then behaves like any other Slack conversation in the inbox. The member must belong to the connected workspace.

**WhatsApp.** This is the endpoint for sending an approved template message to a phone number. Provide templateName, templateLanguage, and templateParams (variable values for the text header, body and dynamic URL buttons, in that order), with the recipient phone in participantId. A template is required because WhatsApp does not permit freeform messages to open a conversation; a missing template returns TEMPLATE_REQUIRED.

- Templates with media headers (image, video, document) are handled automatically: Zernio reads the approved template definition and fills the header at send time with the template's approved sample asset. To send a DIFFERENT asset per message (e.g. a distinct invoice PDF for each recipient), pass the headerMedia field with a public link (or a Meta media id); it overrides the sample for that send.
- A template whose approved header format is LOCATION has no header asset to reconstruct at all: Meta only accepts the location at send time, so pass headerLocation (latitude and longitude required) whenever such a template is sent; headerMedia and headerLocation cannot both be supplied.
- A button that carries its own value at send time (a copy-code button holding a Pix payment code or a coupon, a flow token) is sent with templateButtonParams, addressed by the button's index; templateParams covers text variables and dynamic URL buttons only.
- CAROUSEL templates take per-card overrides via templateCards, each addressed by the card's card_index, because card body variables restart at {{1}} per card and cannot be expressed in the flat templateParams order.
- Template fields are accepted on the JSON body only, not on multipart requests.

For a number you already have a thread with, this sends the template into that thread, which also makes it the way to re-engage a contact after the 24-hour customer-service window has closed. Once the recipient replies (opening the 24h window), send freeform messages with the send-message endpoint (POST /v1/inbox/conversations/{conversationId}/messages).

Alternatively, WhatsApp Business Accounts eligible for Meta Direct Send can open a conversation with a business-initiated utility text message and no template: pass category: 'utility' together with message (and no templateName). See the category field below.

**DM eligibility (X).** Before sending, the endpoint checks if the recipient accepts DMs from your account (via the receives_your_dm field). If not, a 422 error with code DM_NOT_ALLOWED is returned. You can skip this check with skipDmCheck: true if you have already verified eligibility.

**X API tier requirement.** DM write endpoints require X API Pro tier ($5,000/month) or Enterprise access. This applies to BYOK (Bring Your Own Key) users who provide their own X API credentials.

**Rate limits (X only).** X's DM API enforces 200 requests per 15 minutes, 1,000 per 24 hours per connected X account, and 15,000 per 24 hours per X developer app (shared across all DM endpoints). These limits do NOT apply to other platforms. WhatsApp sends are governed by Meta's per-number messaging tiers (unique business-initiated conversations per 24 hours) and per-number throughput instead.


### Request Body

- **accountId** (required) `string`: The account ID to send from
- **participantId** `string`: Recipient identifier. For X this is the numeric user ID; for WhatsApp and SMS, the recipient phone number in international format (digits, country code included); for Slack, the workspace member id (e.g. U01ABCDEF). Provide either this or participantUsername.
- **participantUsername** `string`: Recipient handle/username, an X or Bluesky handle (with or without @) or a Reddit username (with or without u/). Resolved via lookup. Provide either this or participantId.
- **message** `string`: Text content of the message. At least one of message, attachment, or (for WhatsApp) templateName is required. Required when category is set (a Direct Send utility message is a text message).
- **skipDmCheck** `boolean`: X only. Skip the receives_your_dm eligibility check before sending. Use if you have already verified the recipient accepts DMs.
- **templateName** `string`: WhatsApp only. Name of the approved template to start the conversation with. Required for WhatsApp unless category is used instead (Direct Send). Cannot be combined with category.
- **category** `string`: WhatsApp only (Meta Direct Send). Combined with message and without templateName, starts the conversation with a business-initiated UTILITY message and no pre-approved template; Meta matches or auto-creates a template asynchronously. The WhatsApp Business Account must be eligible for Direct Send, otherwise the send fails with an error telling you to use an approved message template instead. Cannot be combined with templateName (templates are already categorized at creation). Utility messages only; marketing content is not allowed under this category. Accepted on the JSON body only, not on multipart requests. - one of: utility
- **linkPreview** `boolean`: WhatsApp only. Set false to send the Direct Send (category: 'utility') text message without a link-preview thumbnail for the first URL in the text. Defaults to true, which is how every WhatsApp text has been sent to date. Does not apply to template sends. Accepted on the JSON body only, not on multipart requests.
- **templateLanguage** `string`: WhatsApp only. Template language code (e.g. en_US).
- **templateParams** `array`: WhatsApp only. Template variable values as one flat array, in the order the variables appear across the whole template: text-header variables first, then body variables, then one value per dynamic URL button (in button order). Works with positional placeholders ({{1}}, {{2}}, ...) and with named placeholders ({{name}}, {{company}} - how Meta Business Manager creates templates), where values fill the named slots in order of appearance. Example - a body with {{1}}, {{2}} plus a URL button https://example.com/{{1}} takes three values: [body1, body2, buttonSuffix]. For positional templates the list must cover every slot: supplying fewer values than the template's header + body + dynamic URL-button count is rejected with a 400 (code INVALID_TEMPLATE_PARAMS) naming the expected split, rather than delivering a template whose button URL was filled from the wrong value. A dynamic URL button covered by templateButtonParams needs no value here unless another uncovered dynamic URL button follows it, since the override applies after slot numbering. Media headers (image, video, document) are filled automatically from the approved template and take no value here (use headerMedia to override the header asset per send). Buttons that are not dynamic-URL buttons (copy-code, flow) take no value here either; use templateButtonParams.
- **templateButtonParams** `array`: WhatsApp only. Values for template buttons that carry one at send time, each addressed by the button's position in the approved template. This is the only way to send a copy-code button's payload (a Pix payment code, a coupon) or a flow token, because templateParams is a flat array of text variables and covers dynamic URL buttons only. Supplying a button here overrides whatever templateParams would have derived for that same index, so the send never carries one button twice; repeating an index within this array is rejected with 400. Each index must name a button of the matching kind on the approved template, which is also checked before the send and returns 400 (INVALID_TEMPLATE_BUTTON_PARAM) rather than a Meta rejection.
- **templateCards** `array`: WhatsApp only. Per-card overrides for a CAROUSEL template, each addressed by the card's card_index. Carousel card body variables restart at {{1}} per card, so they cannot be expressed in the flat templateParams slot order; use this instead. A cardIndex naming a card the approved template does not have, a duplicate cardIndex, or a params count that does not match the card body's token count is rejected with 400 (INVALID_TEMPLATE_CARD_PARAM).
- **headerMedia** `object`: WhatsApp only. Overrides a media-header template's header asset for THIS send, so a template with an image/video/document header can carry a different asset per message (e.g. each recipient their own invoice PDF). Without it, the template's approved sample asset is sent. Provide exactly one of link or id.
- **headerLocation** `object`: WhatsApp only. Required to send a template whose approved header format is LOCATION: Meta only accepts the location's lat/long at send time, never at template creation, so there is nothing to fill in automatically. Cannot be combined with headerMedia (a template has exactly one header).

### Responses

#### 201: Conversation created successfully

**Response Body:**

- **success** `boolean`: No description (example: true)
- **data** `object`: 
  - **messageId** `string`: Platform message ID (dm_event_id)
  - **conversationId** `string`: Platform conversation ID (dm_conversation_id). For WhatsApp, this is Zernio's internal conversation id (24-character hex) which matches the id returned by the list-conversations endpoint and the conversationId in the message.received and conversation.started webhooks; use it to correlate the created thread with inbound events.
  - **participantId** `string`: X numeric user ID of the recipient
  - **participantName** `string,null`: Display name of the recipient
  - **participantUsername** `string,null`: X username of the recipient

#### 400: Validation error, platform not supported, an attachment the platform does not accept (PLATFORM_LIMITATION), template required to start a WhatsApp conversation (TEMPLATE_REQUIRED), template variables that do not match the approved definition (INVALID_TEMPLATE_PARAMS, INVALID_TEMPLATE_BUTTON_PARAM), templateCards that do not match the approved carousel definition (INVALID_TEMPLATE_CARD_PARAM), category combined with templateName or used on a non-WhatsApp account, or the WhatsApp Business Account is not eligible for Direct Send: DIRECT_SEND_NOT_ELIGIBLE and DIRECT_SEND_BLOCKED require Meta to grant or restore Direct Send access, while DIRECT_SEND_LIMITED is temporary and lifts on its own

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description - one of: PLATFORM_NOT_SUPPORTED, PLATFORM_LIMITATION, TEMPLATE_REQUIRED, INVALID_TEMPLATE_PARAMS, INVALID_TEMPLATE_BUTTON_PARAM, INVALID_TEMPLATE_CARD_PARAM, DIRECT_SEND_NOT_ELIGIBLE, DIRECT_SEND_LIMITED, DIRECT_SEND_BLOCKED

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required or profile limit reached

#### 404: Account or recipient user not found (Reddit: PARTICIPANT_NOT_FOUND when the u/username does not exist)

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description - one of: account_not_found, PARTICIPANT_NOT_FOUND

#### 422: Recipient does not accept DMs from this account (X), or does not accept direct messages from you (Reddit)

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description - one of: DM_NOT_ALLOWED

#### 429: X API rate limit exceeded, or Reddit rate limit reached for this account

**Response Body:**

- **error** `string`: No description
- **code** `string`: No description - one of: rate_limited

---

---
