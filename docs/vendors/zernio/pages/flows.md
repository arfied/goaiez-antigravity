# Flows

Build WhatsApp Flows, the native forms, surveys and booking screens inside WhatsApp, publish them, send them, and read what customers submitted.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a flow is published, a customer has opened it from a message, and their submission is in your hands. You need a connected WhatsApp account and its `accountId`. A flow starts as a `DRAFT`, receives a Flow JSON definition, and is published, which makes it sendable and immutable; iterate by creating a new version.

## Step 1: Create the flow

Call `POST /v1/whatsapp/flows` with `accountId`, `name` and `categories` (one or more of `SIGN_UP`, `SIGN_IN`, `APPOINTMENT_BOOKING`, `LEAD_GENERATION`, `CONTACT_US`, `CUSTOMER_SUPPORT`, `SURVEY`, `OTHER`).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const accountId = '66b2e19d8c3f5a7e9d0b1c2d';

const { data: created } = await zernio.whatsappflows.createWhatsAppFlow({
  body: {
    accountId,
    name: 'lead_capture_form',
    categories: ['LEAD_GENERATION']
  }
});

const flowId = created.flow.id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
account_id = "66b2e19d8c3f5a7e9d0b1c2d"

created = client.whatsapp_flows.create_whats_app_flow(
    account_id=account_id,
    name="lead_capture_form",
    categories=["LEAD_GENERATION"],
)

flow_id = created["flow"]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/whatsapp/flows \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "name": "lead_capture_form",
    "categories": ["LEAD_GENERATION"]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "flow": {
    "id": "1178094743614213",
    "name": "lead_capture_form",
    "status": "DRAFT",
    "categories": ["LEAD_GENERATION"],
    "version": 1,
    "lineageId": "1178094743614213"
  }
}
```

`flow.id` is the `flowId` for every call below.

## Step 2: Upload the Flow JSON

Call `PUT /v1/whatsapp/flows/{flowId}/json` with `accountId` and `flow_json`, the screens, components and navigation in [Meta's Flow JSON format](https://developers.facebook.com/docs/whatsapp/flows/reference/flowjson). Meta validates it on upload.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: uploaded } = await zernio.whatsappflows.uploadWhatsAppFlowJson({
  path: { flowId },
  body: {
    accountId,
    flow_json: {
      version: '6.0',
      screens: [{
        id: 'LEAD_FORM',
        title: 'Get a Quote',
        terminal: true,
        success: true,
        layout: {
          type: 'SingleColumnLayout',
          children: [
            { type: 'TextInput', name: 'full_name', label: 'Full Name', required: true, 'input-type': 'text' },
            { type: 'TextInput', name: 'email', label: 'Email', required: true, 'input-type': 'email' },
            { type: 'Footer', label: 'Submit', 'on-click-action': { name: 'complete', payload: { full_name: '${form.full_name}', email: '${form.email}' } } }
          ]
        }
      }]
    }
  }
});

console.log(uploaded.validation_errors);
```
</Tab>
<Tab value="Python">
```python
uploaded = client.whatsapp_flows.upload_whats_app_flow_json(
    flow_id=flow_id,
    account_id=account_id,
    flow_json={
        "version": "6.0",
        "screens": [{
            "id": "LEAD_FORM",
            "title": "Get a Quote",
            "terminal": True,
            "success": True,
            "layout": {
                "type": "SingleColumnLayout",
                "children": [
                    {"type": "TextInput", "name": "full_name", "label": "Full Name", "required": True, "input-type": "text"},
                    {"type": "TextInput", "name": "email", "label": "Email", "required": True, "input-type": "email"},
                    {"type": "Footer", "label": "Submit", "on-click-action": {"name": "complete", "payload": {"full_name": "${form.full_name}", "email": "${form.email}"}}}
                ]
            }
        }]
    },
)

print(uploaded["validation_errors"])
```
</Tab>
<Tab value="curl">
```bash
curl -X PUT "https://zernio.com/api/v1/whatsapp/flows/1178094743614213/json" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "flow_json": {
      "version": "6.0",
      "screens": [{
        "id": "LEAD_FORM",
        "title": "Get a Quote",
        "terminal": true,
        "success": true,
        "layout": {
          "type": "SingleColumnLayout",
          "children": [
            {"type": "TextInput", "name": "full_name", "label": "Full Name", "required": true, "input-type": "text"},
            {"type": "TextInput", "name": "email", "label": "Email", "required": true, "input-type": "email"},
            {"type": "Footer", "label": "Submit", "on-click-action": {"name": "complete", "payload": {"full_name": "${form.full_name}", "email": "${form.email}"}}}
          ]
        }
      }]
    }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "validation_errors": []
}
```

Before publishing, `GET /v1/whatsapp/flows/{flowId}/preview?accountId=...` returns Meta's public web preview of the flow, drafts included, as `preview_url`. The link needs no login, embeds as an iframe, and is reused across calls for about 30 days; pass `invalidate=true` to mint a fresh one, which stops the previous link working.

## Step 3: Publish

Call `POST /v1/whatsapp/flows/{flowId}/publish` with `accountId`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: published } = await zernio.whatsappflows.publishWhatsAppFlow({
  path: { flowId },
  body: { accountId }
});

console.log(published.success);
```
</Tab>
<Tab value="Python">
```python
published = client.whatsapp_flows.publish_whats_app_flow(
    flow_id=flow_id,
    account_id=account_id,
)

print(published["success"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/whatsapp/flows/1178094743614213/publish" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"accountId": "66b2e19d8c3f5a7e9d0b1c2d"}'
```
</Tab>
</Tabs>

Response (`200`): `{ "success": true }`.

<Callout type="warn">
Publishing is irreversible. A published flow and its JSON cannot be changed; to change anything, create a [new version](#version-a-flow) with `cloneFlowId` and `asVersion: true`, which copies the published flow into a fresh draft.
</Callout>

## Step 4: Send the flow

Call `POST /v1/whatsapp/flows/send` with `accountId`, `to`, `flow_id`, `flow_cta` (the button label) and `body`. The customer gets a message with a button that opens the flow natively in WhatsApp. `draft: true` sends an unpublished flow for testing.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: sent } = await zernio.whatsappflows.sendWhatsAppFlowMessage({
  body: {
    accountId,
    to: '+13105551234',
    flow_id: flowId,
    flow_cta: 'Get a quote',
    flow_action: 'navigate',
    flow_action_payload: { screen: 'LEAD_FORM' },
    body: 'Fill out this quick form to get a personalized quote.'
  }
});

console.log(sent.messageId);
```
</Tab>
<Tab value="Python">
```python
sent = client.whatsapp_flows.send_whats_app_flow_message(
    account_id=account_id,
    to="+13105551234",
    flow_id=flow_id,
    flow_cta="Get a quote",
    flow_action="navigate",
    flow_action_payload={"screen": "LEAD_FORM"},
    body="Fill out this quick form to get a personalized quote.",
)

print(sent["messageId"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/whatsapp/flows/send \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "to": "+13105551234",
    "flow_id": "1178094743614213",
    "flow_cta": "Get a quote",
    "flow_action": "navigate",
    "flow_action_payload": { "screen": "LEAD_FORM" },
    "body": "Fill out this quick form to get a personalized quote."
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "messageId": "wamid.HBgLMTMxMDU1NTEyMzQVAgARGBI5QTNEMEY3RjQ4RjE2QjA3QzYA"
}
```

Omit `flow_token` and Zernio generates one as `<flowId>:<uuid>`, which is what lets Step 5 attribute the response to this flow. A flow can also go out inside a conversation as an `interactive` message of type `flow` ([WhatsApp inbox](/platforms/whatsapp/inbox)).

## The two flow modes

`flow_action` on the send picks how the flow gets its screens:

| `flow_action` | How it runs | What you have to host |
|---|---|---|
| `navigate` (default) | WhatsApp renders the screens straight from the published Flow JSON. The steps above build one of these. | Nothing |
| `data_exchange` | WhatsApp posts the current screen's data to an endpoint you host and renders whatever screen the endpoint returns, so screens can depend on your data. | An HTTPS endpoint, plus an RSA key pair |

Point Meta at that endpoint with `endpointUri`, on `POST /v1/whatsapp/flows` at creation or on `PATCH /v1/whatsapp/flows/{flowId}` while the flow is still a `DRAFT`. The URL must be HTTPS, and the uploaded Flow JSON has to declare `data_api_version` `"3.0"` before WhatsApp calls it.

A `data_exchange` flow encrypts every exchange with your endpoint. Register the public half against the number with `POST /v1/whatsapp/flows/encryption-key` (`accountId` and `businessPublicKey`, an RSA public key in PEM), and serve the private half from the endpoint. Only one key is active per number, and uploading a new one replaces it. `GET /v1/whatsapp/flows/encryption-key` reads back `publicKey`, `signatureStatus` and `registered`; read `registered`, since Meta reports an unregistered key as `MISMATCH` rather than as absent.

Publishing a `data_exchange` flow with no key registered fails with Meta error `139002`, "Missing Flows Signed Public Key". A key that is registered but whose private half the endpoint does not serve passes publish and then fails at runtime, when the customer opens the flow.

## Step 5: Read the responses

A completed flow arrives as an `nfm_reply` on the `message.received` webhook. To read submissions per flow without touching the inbox, call `GET /v1/whatsapp/flow-responses` with `accountId` and `flowId`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: responses } = await zernio.whatsappflows.listWhatsAppFlowResponses({
  query: { accountId, flowId }
});

for (const r of responses.responses) {
  console.log(r.from, r.receivedAt, r.data);
}
```
</Tab>
<Tab value="Python">
```python
responses = client.whatsapp_flows.list_whats_app_flow_responses(
    account_id=account_id,
    flow_id=flow_id,
)

for r in responses["responses"]:
    print(r["from"], r["receivedAt"], r["data"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/flow-responses?accountId=66b2e19d8c3f5a7e9d0b1c2d&flowId=1178094743614213" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), newest first:

```json
{
  "responses": [
    {
      "id": "wamid.HBgLMTMxMDU1NTEyMzQVAgASGBQzQTNEMEY3RjQ4RjE2QjA3QzYyRQA=",
      "receivedAt": "2027-01-01T09:12:44Z",
      "from": "13105551234",
      "senderName": "Ana Costa",
      "conversationId": "66c3d2ae7b4f6c8d0e1f2a3b",
      "flowToken": "1178094743614213:2c1a7f0e-5b3d-4c9e-8a2f-6d1e0b9c7a54",
      "data": { "full_name": "Ana Costa", "email": "ana@example.com" }
    }
  ]
}
```

Scoping by `flowId` works because the auto-generated `flow_token` carries the flow id and round-trips through Meta inside the response. A flow sent with your own `flow_token` still delivers its responses on the webhook, but they are not attributed to a flow here, and only flows sent since this token convention shipped are attributable.

## Version a flow

A published flow is immutable, so you iterate by cloning it into a new draft. Pass `cloneFlowId` with `asVersion: true` to `POST /v1/whatsapp/flows` for a new version in the same lineage, auto-numbered; pass `cloneFlowId` alone for an independent copy with its own lineage at version 1. `GET /v1/whatsapp/flows/{flowId}/versions` lists the lineage, newest first, with each version's live name and status from Meta.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: next } = await zernio.whatsappflows.createWhatsAppFlow({
  body: {
    accountId,
    name: 'lead_capture_form',
    categories: ['LEAD_GENERATION'],
    cloneFlowId: flowId,
    asVersion: true
  }
});

const { data: history } = await zernio.whatsappflows.listWhatsAppFlowVersions({
  path: { flowId: next.flow.id },
  query: { accountId }
});

for (const v of history.versions) {
  console.log(v.version, v.status);
}
```
</Tab>
<Tab value="Python">
```python
next_version = client.whatsapp_flows.create_whats_app_flow(
    account_id=account_id,
    name="lead_capture_form",
    categories=["LEAD_GENERATION"],
    clone_flow_id=flow_id,
    as_version=True,
)

history = client.whatsapp_flows.list_whats_app_flow_versions(
    flow_id=next_version["flow"]["id"],
    account_id=account_id,
)

for v in history["versions"]:
    print(v["version"], v["status"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/whatsapp/flows \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "name": "lead_capture_form",
    "categories": ["LEAD_GENERATION"],
    "cloneFlowId": "1178094743614213",
    "asVersion": true
  }'

curl "https://zernio.com/api/v1/whatsapp/flows/1178094743614213/versions?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), the clone, a fresh draft at version 2 of the same lineage:

```json
{
  "success": true,
  "flow": {
    "id": "1178094743614299",
    "name": "lead_capture_form",
    "status": "DRAFT",
    "categories": ["LEAD_GENERATION"],
    "version": 2,
    "lineageId": "1178094743614213"
  }
}
```

Response (`200`), the version list:

```json
{
  "versions": [
    { "flowId": "1178094743614299", "version": 2, "parentFlowId": "1178094743614213", "name": "lead_capture_form", "status": "DRAFT", "missing": false },
    { "flowId": "1178094743614213", "version": 1, "parentFlowId": null, "name": "lead_capture_form", "status": "PUBLISHED", "missing": false }
  ]
}
```

Zernio tracks the lineage, because Meta has no native flow versioning; a flow that was never cloned reports as `version: 1` of its own lineage, and `missing: true` marks a version Meta no longer has.

## Flow lifecycle

| Status | Description |
|--------|-------------|
| `DRAFT` | Editable. Upload or update the JSON, change the name and categories. |
| `PUBLISHED` | Immutable and sendable. To change it, create a new version with `cloneFlowId` and `asVersion`. |
| `DEPRECATED` | No longer sendable. |
| `BLOCKED` | Blocked by Meta for policy violations. |
| `THROTTLED` | Temporarily rate-limited by Meta. |

## If it fails

Step 2 returns `200` even when Meta rejects the definition; the problems are in `validation_errors`:

```json
{
  "success": true,
  "validation_errors": [
    {
      "error": "INVALID_PROPERTY",
      "error_type": "JSON_SCHEMA_ERROR",
      "message": "Property 'input-type' is invalid",
      "line_start": 12,
      "line_end": 12,
      "column_start": 9,
      "column_end": 20
    }
  ]
}
```

Fix the JSON at the line and column named and upload again; a flow with validation errors cannot be published, and Step 3 returns `400` until they are gone. A `400` on the upload itself means the body is not valid JSON or the flow is no longer a `DRAFT`. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Flows API](/whatsapp/list-whatsapp-flows): get, update, delete and deprecate a flow.
- [WhatsApp inbox](/platforms/whatsapp/inbox): send a flow inside a conversation.
- [Inbox webhooks](/webhooks/inbox): the `message.received` payload that carries `nfm_reply`.
- [Templates](/platforms/whatsapp/templates): open the conversation before the flow when the window is closed.

---
