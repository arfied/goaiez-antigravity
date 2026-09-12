# Inbox

Send and read X direct messages and manage replies to your posts through the inbox API.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can open a DM conversation on X (platform value `twitter`) with `POST /v1/inbox/conversations`, read the conversations that follow and manage replies to your posts as comments. You need a connected X account (`accountId`) with the `dm.read` and `dm.write` scopes. X DMs are billed at [X's pass-through rate](/pricing#x-twitter-api-usage) and never on the outbound message meter.

## Step 1: send a DM

Call `POST /v1/inbox/conversations` with `accountId`, the recipient as `participantUsername` (an X handle, with or without the @) or `participantId` (the numeric X user id) and `message` ([Create conversation](/messages/create-inbox-conversation)). If a conversation with that person already exists, the message is appended to it.

Before sending, Zernio checks that the recipient accepts DMs from your account and returns `422` with code `DM_NOT_ALLOWED` when they do not. Pass `skipDmCheck: true` to skip the check when you have already verified eligibility.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: sent } = await zernio.messages.createInboxConversation({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    participantUsername: 'XDevelopers',
    message: 'Thanks for the report. Which endpoint returned the error?'
  }
});

console.log(sent.data.conversationId, sent.data.messageId);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

sent = client.messages.create_inbox_conversation(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    participant_username="XDevelopers",
    message="Thanks for the report. Which endpoint returned the error?"
)

print(sent["data"]["conversationId"], sent["data"]["messageId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/inbox/conversations \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "participantUsername": "XDevelopers",
    "message": "Thanks for the report. Which endpoint returned the error?"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "success": true,
  "data": {
    "messageId": "1852634789012345678",
    "conversationId": "2244994945-1234567890",
    "participantId": "2244994945",
    "participantName": "Developers",
    "participantUsername": "XDevelopers"
  }
}
```

The conversation then appears in [List conversations](/messages/list-inbox-conversations) with `platform: "twitter"`, and later messages go through [Send message](/messages/send-inbox-message).

## Direct messages

| Feature | Supported |
|---------|-----------|
| List conversations | <Yes /> |
| Fetch messages | <Yes /> |
| Send text messages | <Yes /> |
| Send attachments | <Yes /> (images and videos up to 25 MB) |
| Archive and unarchive | <No /> |

X's DM API allows 200 requests per 15 minutes and 1,000 per 24 hours per connected X account. Conversations are cached for 2 minutes, so a list read inside that window is served from Zernio's copy rather than X.

<Callout type="warn">
X has replaced traditional DMs with end-to-end encrypted "X Chat" for many accounts, and messages sent or received through X Chat are not returned by X's API. Some conversations therefore show only outgoing messages or appear empty. This is an [X platform limitation](https://help.x.com/en/using-x/about-chat) that affects every third-party application.
</Callout>

If you bring your own X API credentials, X requires the Pro tier ($5,000 per month) or Enterprise access for DM writes.

## Comments

Replies to your posts are comments in the inbox ([List commented posts](/comments/list-inbox-comments)). Reply lookups use cached conversation threads with a 2-minute TTL to stay within X's rate limits.

`POST /v1/inbox/comments/{postId}` with `accountId` and `message` replies under one of your posts, and `commentId` aims the reply at a specific comment instead of the post itself.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: reply } = await zernio.comments.replyToInboxPost({
  path: { postId: '65f1c0a9e2b5af0012ab34cd' },
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    commentId: '1852634789012345679',
    message: 'Fixed in the 2.4 release, thanks for flagging it.'
  }
});
```
</Tab>
<Tab value="Python">
```python
reply = client.comments.reply_to_inbox_post(
    post_id="65f1c0a9e2b5af0012ab34cd",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    comment_id="1852634789012345679",
    message="Fixed in the 2.4 release, thanks for flagging it."
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/inbox/comments/65f1c0a9e2b5af0012ab34cd \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "commentId": "1852634789012345679",
    "message": "Fixed in the 2.4 release, thanks for flagging it."
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "data": {
    "commentId": "1852634789012345680",
    "isReply": true
  }
}
```

`postId` takes the Zernio post id or the X post id, so a post published outside Zernio can be answered too. Send an `Idempotency-Key` header on retries; the same key with the same body replays the original response instead of posting twice ([idempotency](/guides/idempotency)).

| Feature | Supported |
|---------|-----------|
| List comments on posts | <Yes /> |
| Post a new comment | <Yes /> |
| Reply to comments | <Yes /> |
| Delete comments | <Yes /> |
| Like and unlike comments | <Yes /> |
| Hide and unhide comments | <Yes /> |

## If it fails

A `422` on `POST /v1/inbox/conversations` means the recipient does not accept DMs from your account:

```json
{
  "error": "Recipient does not accept DMs from this account",
  "code": "DM_NOT_ALLOWED"
}
```

Nothing was sent. The recipient has to open their DMs to your account first. `skipDmCheck: true` skips this check; use it only when you have verified eligibility yourself. A `429` with code `rate_limited` means X's DM limit for the account is exhausted; wait for the window to pass. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Create conversation](/messages/create-inbox-conversation): every field, including attachments.
- [List conversations](/messages/list-inbox-conversations) and [List commented posts](/comments/list-inbox-comments): the inbox API.
- [Inbox webhooks](/webhooks/inbox): `message.received` and `comment.received` payloads.
- [X API usage](/pricing#x-twitter-api-usage): what a DM read and send costs.

---
