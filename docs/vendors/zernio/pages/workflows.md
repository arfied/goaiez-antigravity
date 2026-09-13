# Workflows

Create and activate a workflow with POST /v1/workflows so inbound DMs on one account run a graph of messages, waits, conditions and handoffs.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

A workflow runs a graph of actions on one connected messaging account each time its trigger fires: it sends messages, waits for replies, branches, calls your webhooks and hands off to a human. Create one with `POST /v1/workflows` and activate it with `POST /v1/workflows/{workflowId}/activate`. Workflows are not [WhatsApp Flows](/platforms/whatsapp/flows) (Meta's in-chat forms); a workflow can send a flow, but they are separate products.

## First workflow

This workflow greets a WhatsApp contact who writes "hi" or "hello", waits for their name and replies with it. You need an API key, a profile id and the `accountId` of a connected WhatsApp account ([connecting accounts](/guides/connecting-accounts)). A workflow is created in `draft` status, so the second call activates it.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.workflows.createWorkflow({
  body: {
    profileId: '66a1f0c2a4b9d3e8f1a2b3c4',
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    platform: 'whatsapp',
    name: 'Welcome',
    nodes: [
      { id: 'n_trigger', type: 'trigger', config: { triggerType: 'inbound_message', keywords: ['hi', 'hello'], matchType: 'contains', onlyFirstMessage: true } },
      { id: 'n_ask', type: 'send_message', config: { messageType: 'text', text: 'Hi. What is your name?' } },
      { id: 'n_wait', type: 'wait_for_reply', config: { saveAs: 'name', timeoutMinutes: 10 } },
      { id: 'n_reply', type: 'send_message', config: { messageType: 'text', text: 'Thanks {{name}}, a teammate will be with you shortly.' } },
      { id: 'n_end', type: 'end' },
    ],
    edges: [
      { id: 'e1', source: 'n_trigger', target: 'n_ask' },
      { id: 'e2', source: 'n_ask', target: 'n_wait' },
      { id: 'e3', source: 'n_wait', target: 'n_reply' },
      { id: 'e4', source: 'n_reply', target: 'n_end' },
    ],
  },
});
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.workflows.create_workflow(
    profile_id="66a1f0c2a4b9d3e8f1a2b3c4",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    platform="whatsapp",
    name="Welcome",
    nodes=[
        {"id": "n_trigger", "type": "trigger", "config": {"triggerType": "inbound_message", "keywords": ["hi", "hello"], "matchType": "contains", "onlyFirstMessage": True}},
        {"id": "n_ask", "type": "send_message", "config": {"messageType": "text", "text": "Hi. What is your name?"}},
        {"id": "n_wait", "type": "wait_for_reply", "config": {"saveAs": "name", "timeoutMinutes": 10}},
        {"id": "n_reply", "type": "send_message", "config": {"messageType": "text", "text": "Thanks {{name}}, a teammate will be with you shortly."}},
        {"id": "n_end", "type": "end"},
    ],
    edges=[
        {"id": "e1", "source": "n_trigger", "target": "n_ask"},
        {"id": "e2", "source": "n_ask", "target": "n_wait"},
        {"id": "e3", "source": "n_wait", "target": "n_reply"},
        {"id": "e4", "source": "n_reply", "target": "n_end"},
    ],
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/workflows" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "whatsapp",
    "name": "Welcome",
    "nodes": [
      { "id": "n_trigger", "type": "trigger", "config": { "triggerType": "inbound_message", "keywords": ["hi", "hello"], "matchType": "contains", "onlyFirstMessage": true } },
      { "id": "n_ask", "type": "send_message", "config": { "messageType": "text", "text": "Hi. What is your name?" } },
      { "id": "n_wait", "type": "wait_for_reply", "config": { "saveAs": "name", "timeoutMinutes": 10 } },
      { "id": "n_reply", "type": "send_message", "config": { "messageType": "text", "text": "Thanks {{name}}, a teammate will be with you shortly." } },
      { "id": "n_end", "type": "end" }
    ],
    "edges": [
      { "id": "e1", "source": "n_trigger", "target": "n_ask" },
      { "id": "e2", "source": "n_ask", "target": "n_wait" },
      { "id": "e3", "source": "n_wait", "target": "n_reply" },
      { "id": "e4", "source": "n_reply", "target": "n_end" }
    ]
  }'
```
</Tab>
</Tabs>

Response (`200`), the draft workflow:

```json
{
  "success": true,
  "workflow": {
    "id": "66d4a1b2c3e4f5a6b7c8d9e1",
    "name": "Welcome",
    "platform": "whatsapp",
    "status": "draft",
    "nodeCount": 5,
    "entryNodeId": "n_trigger",
    "createdAt": "2026-09-08T10:00:00Z"
  }
}
```

`workflow.id` is the `workflowId` for the activate call:

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: activated } = await zernio.workflows.activateWorkflow({
  path: { workflowId: created.workflow.id },
});

console.log(activated.workflow.status);
```
</Tab>
<Tab value="Python">
```python
activated = client.workflows.activate_workflow(workflow_id=created["workflow"]["id"])

print(activated["workflow"]["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/workflows/66d4a1b2c3e4f5a6b7c8d9e1/activate" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), the same workflow with `status: "active"`:

```json
{
  "success": true,
  "workflow": {
    "id": "66d4a1b2c3e4f5a6b7c8d9e1",
    "status": "active",
    "entryNodeId": "n_trigger"
  }
}
```

From now on a DM containing "hi" or "hello" on that account starts a run.

## Triggers and nodes

Every workflow has exactly one `trigger` node. Its `config.triggerType` decides what starts a run:

| `triggerType` | Starts when |
|---|---|
| `inbound_message` (default) | The account receives a DM. Optional `keywords` plus `matchType` (`any`, `contains`, `exact`, `regex`) filter which messages match; `onlyFirstMessage: true` fires once per contact. |
| `api_call` | Your backend starts a run with [Manually start a workflow run](/workflows/trigger-workflow). |
| `whatsapp_event` | A WhatsApp status event arrives (`eventType`: `message_sent`, `message_delivered`, `message_read`, `message_failed`, `reaction`). WhatsApp only. |

`platform` is one of `whatsapp`, `instagram`, `facebook`, `telegram`, `twitter` (X), `bluesky`, `reddit`.

The graph is `nodes[]` plus `edges[]`. Each node has a stable `id`, a `type` and a type-specific `config`; each edge links a `source` node to a `target` node. A node with more than one outcome takes the edge whose `sourceHandle` matches: a `condition` takes the matched rule's `id` or `default`, `wait_for_reply` takes `reply` or `timeout`, `webhook` takes `success` or `error`. An edge without `sourceHandle` is the node's single or default output.

The 16 node types:

- Messaging: `send_message` (text, media, and on WhatsApp also template and interactive messages).
- Control flow: `trigger`, `condition`, `delay`, `wait_for_reply`, `a_b_split`, `end`.
- Data: `set_variable` (run-scoped), `set_field` (persisted on the contact), `add_tag`, `remove_tag`, `enroll_sequence`.
- Integrations: `webhook`, `ai` (your own LLM provider key), `handoff`, `start_call` (WhatsApp only).

Every string in `config` accepts `{{variable}}` interpolation against the run's variables: `lastMessage`, anything captured with `saveAs`, and `set_variable` assignments. The full `config` shape per node type is on [Create workflow](/workflows/create-workflow).

## How it behaves

### A workflow starts as a draft

Creation validates the graph's structure (unique node ids, edges that reference existing nodes, no WhatsApp-only node on another platform) and returns `400` when it is invalid. Activation additionally requires a complete graph, one trigger node with a reachable entry, and only an `active` workflow starts runs. A workflow that "does nothing" is usually `draft` or `paused`: check `status` first.

### Pause stops new runs, in-flight runs finish

A workflow moves `draft` to `active` to `paused`. [Pause](/workflows/pause-workflow) stops matching new messages while runs already started finish; [activate](/workflows/activate-workflow) again to resume.

### Editing the graph needs a draft or paused workflow

[Update workflow](/workflows/update-workflow) changes the name, the description or the account at any time, but the graph can only be modified while the workflow is `draft` or `paused`; a graph edit on an `active` workflow answers `400`. [Pause](/workflows/pause-workflow) it, send the new `nodes` and `edges`, then [activate](/workflows/activate-workflow) again. Each change to the graph records a [version](/workflows/list-workflow-versions) you can [restore](/workflows/restore-workflow-version).

### Runs carry their status and variables

[List workflow runs](/workflows/list-workflow-executions) returns each execution with its `status` (`running`, `waiting`, `completed`, `exited`, `failed`), current node and accumulated variables; [the run timeline](/workflows/list-workflow-execution-events) shows every node visited. To test a graph without a real inbound message, use [Manually start a workflow run](/workflows/trigger-workflow) with `to` (WhatsApp) or `conversationId` (other platforms) plus an optional `text` to seed `lastMessage`.

### Workflows need inbox access

Every `/v1/workflows/*` route runs the same guard, and on an account without inbox access it answers `403`:

```json
{
  "error": "Inbox addon required. Start a free 7-day trial to get started.",
  "code": "INBOX_REQUIRED",
  "trialAvailable": true
}
```

Branch on `code`, not on the message: `trialAvailable` is `false` once the trial is spent, and the message then reads `Inbox addon required. Upgrade to access inbox features.` Usage-based billing includes the inbox on every account ([pricing](/pricing)), so this answer belongs to legacy plans; a team member inherits the access of whoever invited them.

## If it fails

A `400` on the activate call means the graph is incomplete or invalid; the `error` message names the problem (no trigger node, unreachable nodes, a WhatsApp-only node such as `start_call` on another platform). Fix the graph with [Update workflow](/workflows/update-workflow) and activate again. A `401` with `{ "error": "Unauthorized" }` means the key is wrong. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Create workflow](/workflows/create-workflow): every node type's `config`.
- [List workflows](/workflows/list-workflows), [Get workflow](/workflows/get-workflow), [Delete workflow](/workflows/delete-workflow), [Duplicate workflow](/workflows/duplicate-workflow).
- [WhatsApp inbox](/platforms/whatsapp/inbox): the messages a `send_message` node can send.
- [Inbox webhooks](/webhooks/inbox): the `message.received` events that trigger runs.
- [CLI](/cli/inbox#workflows): `zernio workflows:create` and friends.

---
