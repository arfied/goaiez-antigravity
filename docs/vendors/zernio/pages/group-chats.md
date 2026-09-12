# Group Chats

Create WhatsApp group chats from the API, add and remove participants, share invite links, approve join requests, and message the group from the inbox.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page your number owns a WhatsApp group, participants are in it, and messages to the group flow through the same inbox endpoints as 1:1 conversations. You need a connected WhatsApp account on a Cloud API-only number. These are real WhatsApp groups on the platform, not the contact segments broadcasts target.

<Callout type="warn">
The Groups API is not available on a number in [coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence) (Cloud API and the WhatsApp Business app on the same number): Meta disables group create, list and management endpoints there and for Multi-solution Conversations setups. Connect the number with `onboarding=api`, or with [credentials](/platforms/whatsapp/connection#connect-with-credentials-headless), to get a Cloud API-only number.
</Callout>

## Step 1: Create a group

Call `POST /v1/whatsapp/wa-groups` with `accountId` and `subject` (at most 128 characters). `joinApprovalMode: "approval_required"` makes people who use the invite link wait for approval; `auto_approve` lets them straight in.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const accountId = '66b2e19d8c3f5a7e9d0b1c2d';

const { data: created } = await zernio.whatsapp.createWhatsAppGroupChat({
  body: {
    accountId,
    subject: 'Customer Support Team',
    description: 'Internal support coordination',
    joinApprovalMode: 'approval_required'
  }
});

const groupId = created.group.groupId;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
account_id = "66b2e19d8c3f5a7e9d0b1c2d"

created = client.whatsapp.create_whats_app_group_chat(
    account_id=account_id,
    subject="Customer Support Team",
    description="Internal support coordination",
    join_approval_mode="approval_required",
)

group_id = created["group"]["groupId"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/whatsapp/wa-groups \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "subject": "Customer Support Team",
    "description": "Internal support coordination",
    "joinApprovalMode": "approval_required"
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "success": true,
  "group": {
    "groupId": "120363418596621542",
    "inviteLink": "https://chat.whatsapp.com/..."
  }
}
```

`group.groupId` is the `groupId` for every call below. List the number's active groups with `GET /v1/whatsapp/wa-groups?accountId=...`, which returns `groups[]` with `id`, `subject` and `createdAt`, paginated with `after`.

## Step 2: Add participants

Call `POST /v1/whatsapp/wa-groups/{groupId}/participants` with `accountId` in the query and up to 8 `phoneNumbers` in E.164. The same path with `DELETE` removes them.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: added } = await zernio.whatsapp.addWhatsAppGroupParticipants({
  path: { groupId },
  query: { accountId },
  body: { phoneNumbers: ['+13105551234', '+442071234567'] }
});

console.log(added.message);

await zernio.whatsapp.removeWhatsAppGroupParticipants({
  path: { groupId },
  query: { accountId },
  body: { phoneNumbers: ['+442071234567'] }
});
```
</Tab>
<Tab value="Python">
```python
added = client.whatsapp.add_whats_app_group_participants(
    group_id=group_id,
    account_id=account_id,
    phone_numbers=["+13105551234", "+442071234567"],
)

print(added["message"])

client.whatsapp.remove_whats_app_group_participants(
    group_id=group_id,
    account_id=account_id,
    phone_numbers=["+442071234567"],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/whatsapp/wa-groups/120363418596621542/participants?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phoneNumbers": ["+13105551234", "+442071234567"]}'

curl -X DELETE "https://zernio.com/api/v1/whatsapp/wa-groups/120363418596621542/participants?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phoneNumbers": ["+442071234567"]}'
```
</Tab>
</Tabs>

Response (`200`), the add:

```json
{
  "success": true,
  "message": "Participants added"
}
```

Response (`200`), the remove:

```json
{
  "success": true,
  "message": "Participants removed"
}
```

## Step 3: Invite links and join requests

`POST /v1/whatsapp/wa-groups/{groupId}/invite-link` creates a fresh invite link and revokes the previous one. For a group with `approval_required`, `GET /v1/whatsapp/wa-groups/{groupId}/join-requests` lists who is waiting and `POST` on the same path with `phoneNumbers` lets them in (`DELETE` rejects them).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: link } = await zernio.whatsapp.createWhatsAppGroupInviteLink({
  path: { groupId },
  query: { accountId }
});

console.log(link.inviteLink);

const { data: pending } = await zernio.whatsapp.listWhatsAppGroupJoinRequests({
  path: { groupId },
  query: { accountId }
});

await zernio.whatsapp.approveWhatsAppGroupJoinRequests({
  path: { groupId },
  query: { accountId },
  body: { phoneNumbers: pending.joinRequests.map(r => r.user) }
});
```
</Tab>
<Tab value="Python">
```python
link = client.whatsapp.create_whats_app_group_invite_link(
    group_id=group_id,
    account_id=account_id,
)

print(link["inviteLink"])

pending = client.whatsapp.list_whats_app_group_join_requests(
    group_id=group_id,
    account_id=account_id,
)

client.whatsapp.approve_whats_app_group_join_requests(
    group_id=group_id,
    account_id=account_id,
    phone_numbers=[r["user"] for r in pending["joinRequests"]],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/whatsapp/wa-groups/120363418596621542/invite-link?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl "https://zernio.com/api/v1/whatsapp/wa-groups/120363418596621542/join-requests?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"

curl -X POST "https://zernio.com/api/v1/whatsapp/wa-groups/120363418596621542/join-requests?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phoneNumbers": ["+13105551234"]}'
```
</Tab>
</Tabs>

Response (`200`), the new invite link, which revokes the previous one:

```json
{
  "success": true,
  "inviteLink": "https://chat.whatsapp.com/..."
}
```

Response (`200`), the waiting phones with the Unix timestamp of each request:

```json
{
  "success": true,
  "joinRequests": [
    { "user": "+13105551234", "timestamp": 1788084000 }
  ]
}
```

Response (`200`), the approval:

```json
{
  "success": true,
  "message": "Requests approved"
}
```

## Step 4: Message the group

A group becomes a conversation in the inbox when the first message arrives in it, and every participant's messages land there afterwards. Nothing you have called so far creates that conversation: a participant has to send the first message, since there is no endpoint that opens a group conversation from your side. When it arrives, [`message.received`](/webhooks/inbox) carries the `conversationId`, and [List conversations](/messages/list-inbox-conversations) shows it from then on.

With that id, send with `POST /v1/inbox/conversations/{conversationId}/messages` and `accountId` plus `message`, the same call as a 1:1 conversation.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sent } = await zernio.messages.sendInboxMessage({
  path: { conversationId: '66c3d2ae7b4f6c8d0e1f2a3b' },
  body: { accountId, message: 'Welcome to the support team group.' }
});

console.log(sent.data.messageId);
```
</Tab>
<Tab value="Python">
```python
sent = client.messages.send_inbox_message(
    conversation_id="66c3d2ae7b4f6c8d0e1f2a3b",
    account_id=account_id,
    message="Welcome to the support team group.",
)

print(sent["data"]["messageId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/inbox/conversations/66c3d2ae7b4f6c8d0e1f2a3b/messages" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "message": "Welcome to the support team group."
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "data": {
    "messageId": "wamid.HBgLMTMxMDU1NTEyMzQVAgARGBI5QTNEMEY3RjQ4RjE2QjA3QzYA",
    "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b"
  }
}
```

## If it fails

A `400` from any group endpoint on a coexistence number is Meta refusing the call: `type` is `platform_error` and `platformError` carries Meta's own code and message verbatim. Check the number in Meta Business Suite; if it is still registered in the WhatsApp Business app, take it out of coexistence and reconnect it with `onboarding=api` ([coexistence](/platforms/whatsapp/connection#whatsapp-business-app-coexistence)). A `400` on Step 2 with more than 8 numbers means the batch is too large; split it. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Connection & Setup](/platforms/whatsapp/connection): how to end up with a Cloud API-only number.
- [WhatsApp inbox](/platforms/whatsapp/inbox): everything you can send into a group conversation.
- [Groups API](/whatsapp/list-whatsapp-group-chats): get, update, delete and reject join requests.
- [Broadcasts](/platforms/whatsapp/broadcasts): reach many people 1:1 instead of in a group.

---
