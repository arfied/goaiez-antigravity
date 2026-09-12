# Delete automation API Reference

Permanently delete an automation and all its trigger logs.

## GET /v1/comment-automations/{automationId}

**Get automation details**

Returns an automation with its configuration, stats, and recent trigger logs.

### Parameters

- **automationId** (required) in path: No description

### Responses

#### 200: Automation details with stats and recent trigger logs

**Response Body:**

- **success** `boolean`: No description
- **automation** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **platform** `string`: No description
  - **trigger** `string`: No description - one of: comment, story_reply
  - **accountId** `string`: No description
  - **platformPostId** `string`: No description
  - **postId** `string`: No description
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
  - **updatedAt** `string` (date-time): No description
- **logs** `array[object]`: 
  - **id** `string`: No description
  - **commentId** `string`: No description
  - **commenterId** `string`: No description
  - **commenterName** `string`: No description
  - **commentText** `string`: No description
  - **source** `string`: Which door triggered this send. Absent on rows written before this field existed (all of those are comment-triggered). - one of: comment, story_reply, dm
  - **status** `string`: DM outcome. 'pending' = the automation has a dmDelaySeconds and the response is queued but not sent yet. 'gated' = the follow-gate confirmation DM went out and we are waiting for the tap; it flips to 'sent' or 'skipped' when they tap. - one of: pending, sent, failed, skipped, gated
  - **audienceOutcome** `string`: How the audience rule resolved. Absent on automations without one. - one of: passed, blocked, gate_sent, gate_passed, gate_failed
  - **commenterIsFollower** `boolean`: Follow relationship at decision time. Absent when Instagram would not tell us (the commenter never messaged the account).
  - **commenterFollowerCount** `integer`: No description
  - **error** `string`: DM error message if status is failed
  - **commentReplyStatus** `string`: Outcome of the optional public reply on the triggering comment. 'skipped' if no commentReply was configured or if the DM failed (the public reply is not attempted in that case). - one of: sent, failed, skipped
  - **commentReplyError** `string`: Public-reply error message if commentReplyStatus is failed
  - **nextDueAt** `string` (date-time): When the next queued send fires. Present only while something is still pending.
  - **createdAt** `string` (date-time): No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## PATCH /v1/comment-automations/{automationId}

**Update automation settings**

Update an automation's keywords, DM message, inline buttons, comment reply, or active status.
Pass `buttons: []` to clear all buttons. When `buttons` is non-empty, `dmMessage` (the new
one if you're changing it, otherwise the stored one) must be 640 characters or less.


### Parameters

- **automationId** (required) in path: No description

### Request Body

- **name** `string`: No description
- **trigger** `string`: What fires the automation. Changing it detaches the automation from its bound post or story (a post id and a story id are different objects), unless this same request sets a new binding. 'story_reply' is Instagram only. - one of: comment, story_reply
- **keywords** `array`: No description
- **matchMode** `string`: How a keyword is compared with the comment. 'contains' (default) matches anywhere, even inside another word (keyword 'app' fires on 'happy'). 'word' matches the keyword only as a standalone word. 'exact' requires the whole comment to be exactly the keyword. - one of: exact, contains, word
- **excludeKeywords** `array`: Comments containing one of these never trigger the automation, even when a trigger keyword also matches. Compared using the same matchMode.
- **typoTolerance** `boolean`: Only with matchMode=word: also fire on close misspellings of a keyword (one edit for 4-7 character keywords, two from 8 up). Keywords shorter than 4 characters are never fuzzy-matched.
- **dmMessage** `string`: No description
- **buttons** `array`: Inline DM buttons (1-3). Pass [] to clear all buttons.
- **template**: Platform-specific settings (see schema definitions below)
- **commentReply** `string`: No description
- **dmMessageVariations** `array`: Alternate DM texts for random rotation (see create). Pass [] to clear.
- **commentReplyVariations** `array`: Alternate public replies for random rotation. Pass [] to clear.
- **linkTracking** `boolean`: Wrap link buttons in a tracked redirect to count clicks. Pass false to send links untouched.
- **clickTag** `string`: Tag applied to a contact when they click a tracked link (requires linkTracking). Empty string clears it.
- **alsoMatchInDms** `boolean`: Also fire these keywords on a plain inbound DM. Enabling it requires the automation to end up with at least one keyword (this request's keywords if you send them, otherwise the stored ones) and is rejected on story_reply automations.
- **dmDelaySeconds** `integer`: Seconds to wait after the trigger before sending the DM. Send 0 to clear the delay and reply immediately.
- **commentReplyDelaySeconds** `integer`: Seconds to wait before posting the public comment reply. Send 0 to clear it. The reply never goes out before the DM.
- **audience**: No description
- **followGate**: No description
- **isActive** `boolean`: No description

### Responses

#### 200: Automation updated

**Response Body:**

- **success** `boolean`: No description
- **automation** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
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
  - **audience**: `CommentAutomationAudience` - See schema definition
  - **followGate**: `CommentAutomationFollowGate` - See schema definition
  - **alsoMatchInDms** `boolean`: Whether these keywords also fire on a plain inbound DM.
  - **isActive** `boolean`: No description
  - **updatedAt** `string` (date-time): No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---

## DELETE /v1/comment-automations/{automationId}

**Delete automation**

Permanently delete an automation and all its trigger logs.

### Parameters

- **automationId** (required) in path: No description

### Responses

#### 200: Automation deleted

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

---
