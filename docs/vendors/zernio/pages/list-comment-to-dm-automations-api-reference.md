# List comment-to-DM automations API Reference

List all comment-to-DM automations for a profile. Returns automations with their stats.

## GET /v1/comment-automations

**List comment-to-DM automations**

List all comment-to-DM automations for a profile. Returns automations with their stats.

### Parameters

- **profileId** (optional) in query: Filter by profile. Omit to list across all profiles

### Responses

#### 200: Automations list

**Response Body:**

- **success** `boolean`: No description
- **automations** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **platform** `string`: No description - one of: instagram, facebook
  - **trigger** `string`: No description - one of: comment, story_reply
  - **accountId** `string`: No description
  - **platformPostId** `string`: No description
  - **postTitle** `string`: No description
  - **keywords** `array[string]`: 
  - **matchMode** `string`: How a keyword is compared with the comment. 'contains' (default) matches anywhere, even inside another word (keyword 'app' fires on 'happy'). 'word' matches the keyword only as a standalone word. 'exact' requires the whole comment to be exactly the keyword. - one of: exact, contains, word
  - **excludeKeywords** `array[string]`: Comments containing one of these never trigger the automation, even when a trigger keyword also matches. Compared using the same matchMode.
  - **typoTolerance** `boolean`: Only with matchMode=word: also fire on close misspellings of a keyword (one edit for 4-7 character keywords, two from 8 up). Keywords shorter than 4 characters are never fuzzy-matched.
  - **dmMessage** `string`: No description
  - **buttons** `array[DmButton]`: Inline DM buttons (up to 3). Omitted when none are set.
  - **template**: `CommentAutomationTemplate` - See schema definition
  - **commentReply** `string`: No description
  - **dmMessageVariations** `array[string]`: Alternate DM texts rotated at random with dmMessage. Omitted when none.
  - **commentReplyVariations** `array[string]`: Alternate public replies rotated at random with commentReply. Omitted when none.
  - **linkTracking** `boolean`: Whether link buttons in the DM are wrapped in a tracked redirect to count clicks.
  - **clickTag** `string`: Tag applied to a contact when they click a tracked link.
  - **dmDelaySeconds** `integer`: Seconds waited after the trigger before the DM is sent. Absent when the DM goes out immediately.
  - **commentReplyDelaySeconds** `integer`: Seconds waited before the public reply is posted. Absent when it follows the DM immediately.
  - **alsoMatchInDms** `boolean`: Whether these keywords also fire on a plain inbound DM.
  - **isActive** `boolean`: No description
  - **stats** `object`: 
    - **triggered** `integer`: No description
    - **dmsSent** `integer`: No description
    - **dmsFailed** `integer`: No description
    - **uniqueContacts** `integer`: No description
    - **trackedSends** `integer`: DMs sent with a trackable (wrapped) link. CTR denominator: divide clicks by this, not dmsSent. Lags dmsSent for campaigns that predate click tracking.
    - **linkClicks** `integer`: Total clicks on tracked links (bots/prefetch excluded).
    - **uniqueClicks** `integer`: Distinct people who clicked a tracked link.
    - **delivered** `integer`: DMs confirmed delivered (Messenger; IG emits no delivery receipt).
    - **read** `integer`: DMs confirmed read (IG messaging_seen / Messenger message_reads).
  - **createdAt** `string` (date-time): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 503: An upstream service or database is temporarily unavailable. Retry after the indicated delay. A timed-out write may have completed upstream; check its outcome before resubmitting.

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

---

## POST /v1/comment-automations

**Create comment-to-DM automation**

Create a keyword-triggered DM automation on an Instagram or Facebook account.
When someone comments a matching keyword (or, with `trigger: story_reply`, replies
to your Instagram story with one), they automatically receive a DM.

Triggers (`trigger`):
  * `comment` (default): fires on keyword comments on a post or reel.
  * `story_reply`: fires when someone replies to your Instagram story with a keyword,
    and answers them with a DM. Set `platformPostId` to a story media id to scope to
    one story, or omit it to match replies to any story.

Targeting (comment trigger):
  * Per-post: set `platformPostId` to scope to one specific post (only one active
    per-post automation is allowed per post).
  * Account-wide ("any post"): omit `platformPostId` (and `postId`). The automation
    evaluates every comment on every post on the account. You can stack unlimited
    account-wide automations, each with its own keyword set, and they all run
    independently. Per-post automations take priority on their post.

Audience (`audience`, Instagram only): restrict the automation to followers or
non-followers, and/or to accounts above a follower count. Instagram only reveals the
follow relationship for people who have messaged the account, so `audience.whenUnknown`
decides what happens for everyone else - including `verify`, which sends a one-tap
confirmation DM (`followGate`) and then delivers the real DM automatically. People we
already know follow you skip the tap entirely.

Set `alsoMatchInDms: true` on a `comment` automation to also answer people who send
a keyword as a direct message instead of commenting it. One automation then covers
both doors, and each door is deduplicated separately (someone who already got the DM
from their comment still gets it if they later DM the keyword). Requires at least one
keyword.

Links in the DM's buttons can be click-tracked (`linkTracking`, on by default) and
clickers optionally tagged (`clickTag`) for segmentation. Stats returned include
delivered, read, and link clicks.


### Request Body

- **profileId** (required) `string`: No description
- **accountId** (required) `string`: Instagram or Facebook account ID
- **trigger** `string`: What fires the automation. 'comment' (keyword comment on a post) or 'story_reply' (keyword reply to an Instagram story). For 'story_reply', platformPostId is the story media id (omit for any story). - one of: comment, story_reply
- **platformPostId** `string`: Platform media/post ID (or story media id when trigger=story_reply). Omit for an account-wide (any-post / any-story) automation.
- **postId** `string`: Zernio post ID (24 hexadecimal characters); platform IDs return 400. Optional and never required. Use it INSTEAD of platformPostId to bind a per-post automation to a not-yet-published Zernio post: the automation stays pending and arms itself when that post publishes. For a post already live on the platform, pass platformPostId alone and omit this.
- **postTitle** `string`: Post content snippet for display
- **name** (required) `string`: Automation label
- **keywords** `array`: Trigger keywords (empty = any comment triggers)
- **matchMode** `string`: How a keyword is compared with the comment. 'contains' (default) matches anywhere, even inside another word (keyword 'app' fires on 'happy'). 'word' matches the keyword only as a standalone word. 'exact' requires the whole comment to be exactly the keyword. - one of: exact, contains, word
- **excludeKeywords** `array`: Comments containing one of these never trigger the automation, even when a trigger keyword also matches. Compared using the same matchMode.
- **typoTolerance** `boolean`: Only with matchMode=word: also fire on close misspellings of a keyword (one edit for 4-7 character keywords, two from 8 up). Keywords shorter than 4 characters are never fuzzy-matched.
- **dmMessage** (required) `string`: DM text to send to commenter. Max 640 chars when buttons are set, otherwise ~1000.
- **buttons** `array`: Optional inline DM buttons (1-3). Phone buttons are Facebook-only. Omit or pass [] for a plain-text DM.
- **template**: Optional product card sent INSTEAD of the plain dmMessage bubble. Mutually exclusive with buttons. dmMessage stays required: it is what gets sent the moment the card is cleared.
- **commentReply** `string`: Optional public reply to the comment
- **dmMessageVariations** `array`: Optional alternate DM texts for random rotation. When set, each triggered comment sends one picked at random from [dmMessage, ...dmMessageVariations], so repeat commenters get slightly different DMs (helps avoid identical-message patterns). Up to 5. Buttons are attached to whichever text is picked, not varied.
- **commentReplyVariations** `array`: Optional alternate public replies, rotated at random alongside commentReply (picked independently of the DM). Up to 5.
- **linkTracking** `boolean`: Wrap link buttons in the DM in a tracked redirect so clicks are counted (Link Clicks / CTR). Pass false to send links exactly as written. Defaults to on.
- **clickTag** `string`: Optional tag applied to a contact when they click a tracked link (requires linkTracking). Lets you segment clickers for broadcasts/sequences.
- **dmDelaySeconds** `integer`: Seconds to wait after the trigger before sending the DM. Omit or send 0 to reply immediately (the default). Max 86400 (24h). The trigger is still matched and deduplicated the moment the comment arrives, so a delay only moves when the response is sent.
- **commentReplyDelaySeconds** `integer`: Seconds to wait before posting the public comment reply. Omit or send 0 to post it right after the DM (the default). The reply never goes out before the DM, so a value below dmDelaySeconds is raised to it. Ignored when trigger=story_reply, which has no public reply.
- **alsoMatchInDms** `boolean`: Also fire these keywords on a plain inbound DM, so the automation answers people who message the keyword instead of commenting it. Requires at least one keyword (an empty keyword list means 'match anything', which would answer every inbound message) and is rejected on story_reply automations, which already trigger on DMs. Dedup is per door: a contact who already received the DM from their comment can still receive it from a DM.
- **audience**: No description
- **followGate**: No description

### Responses

#### 200: Automation created

**Response Body:**

- **success** `boolean`: No description
- **automation** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **platform** `string`: No description
  - **trigger** `string`: No description - one of: comment, story_reply
  - **platformPostId** `string`: No description
  - **keywords** `array[string]`: 
  - **matchMode** `string`: How a keyword is compared with the comment. 'contains' (default) matches anywhere, even inside another word (keyword 'app' fires on 'happy'). 'word' matches the keyword only as a standalone word. 'exact' requires the whole comment to be exactly the keyword. - one of: exact, contains, word
  - **excludeKeywords** `array[string]`: Comments containing one of these never trigger the automation, even when a trigger keyword also matches. Compared using the same matchMode.
  - **typoTolerance** `boolean`: Only with matchMode=word: also fire on close misspellings of a keyword (one edit for 4-7 character keywords, two from 8 up). Keywords shorter than 4 characters are never fuzzy-matched.
  - **dmMessage** `string`: No description
  - **buttons** `array[DmButton]`: Inline DM buttons (up to 3). Omitted when none are set.
  - **template**: `CommentAutomationTemplate` - See schema definition
  - **commentReply** `string`: No description
  - **dmMessageVariations** `array[string]`: Alternate DM texts rotated at random with dmMessage. Omitted when none.
  - **commentReplyVariations** `array[string]`: Alternate public replies rotated at random with commentReply. Omitted when none.
  - **linkTracking** `boolean`: No description
  - **clickTag** `string`: No description
  - **dmDelaySeconds** `integer`: Seconds waited after the trigger before the DM is sent. Absent when the DM goes out immediately.
  - **commentReplyDelaySeconds** `integer`: Seconds waited before the public reply is posted. Absent when it follows the DM immediately.
  - **audience**: `CommentAutomationAudience` - See schema definition
  - **followGate**: `CommentAutomationFollowGate` - See schema definition
  - **alsoMatchInDms** `boolean`: Whether these keywords also fire on a plain inbound DM.
  - **isActive** `boolean`: No description
  - **stats** `object`: 
    - **totalTriggered** `integer`: No description
    - **totalSent** `integer`: No description
    - **totalFailed** `integer`: No description
  - **createdAt** `string` (date-time): No description

#### 400: Validation error

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 409: Active per-post automation already exists for this platformPostId. Does not apply to account-wide automations.

#### 503: An upstream service or database is temporarily unavailable. Retry after the indicated delay. A timed-out write may have completed upstream; check its outcome before resubmitting.

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

---
