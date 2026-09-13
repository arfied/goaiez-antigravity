# Contacts

Create and import contacts with a WhatsApp channel so broadcasts can target them by id, phone or tag.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have contacts with a WhatsApp channel that broadcasts can target by id, phone or tag. You need a connected WhatsApp account, its `accountId` and its `profileId`. A contact is a person in your CRM; the channel on it is the phone number Zernio messages through your WhatsApp account.

## Step 1: Create a contact

Call `POST /v1/contacts` with `profileId` and `name`. Add `accountId`, `platform: "whatsapp"` and `platformIdentifier` (the phone in E.164) together to create the WhatsApp channel in the same request; the three are all-or-nothing.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const profileId = '66a1f0c2a4b9d3e8f1a2b3c4';
const accountId = '66b2e19d8c3f5a7e9d0b1c2d';

const { data: created } = await zernio.contacts.createContact({
  body: {
    profileId,
    name: 'Ana Costa',
    email: 'ana@example.com',
    tags: ['vip', 'newsletter'],
    accountId,
    platform: 'whatsapp',
    platformIdentifier: '+13105551234'
  }
});

const contactId = created.contact.id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
profile_id = "66a1f0c2a4b9d3e8f1a2b3c4"
account_id = "66b2e19d8c3f5a7e9d0b1c2d"

created = client.contacts.create_contact(
    profile_id=profile_id,
    name="Ana Costa",
    email="ana@example.com",
    tags=["vip", "newsletter"],
    account_id=account_id,
    platform="whatsapp",
    platform_identifier="+13105551234",
)

contact_id = created["contact"]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/contacts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "name": "Ana Costa",
    "email": "ana@example.com",
    "tags": ["vip", "newsletter"],
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "platform": "whatsapp",
    "platformIdentifier": "+13105551234"
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "contact": {
    "id": "66e5b2c3d4f5a6b7c8d9e0f1",
    "name": "Ana Costa",
    "email": "ana@example.com",
    "tags": ["vip", "newsletter"],
    "isSubscribed": true,
    "isBlocked": false,
    "createdAt": "2027-01-01T09:00:00Z"
  },
  "channel": {
    "id": "66e5b2c3d4f5a6b7c8d9e0f9",
    "platform": "whatsapp",
    "platformIdentifier": "13105551234"
  }
}
```

`contact.id` is the id broadcasts take in `contactIds`.

## Step 2: Import contacts in bulk

Call `POST /v1/contacts/bulk` with up to 1,000 `contacts`. With `accountId` set, every row needs a `platformIdentifier`; on WhatsApp it is normalized to digits, and a value that is not phone-shaped is rejected per row in `errors[]` rather than failing the import. Duplicates are skipped and their new tags merged onto the existing contact.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: imported } = await zernio.contacts.bulkCreateContacts({
  body: {
    profileId,
    accountId,
    contacts: [
      { name: 'Ana Costa', platformIdentifier: '+13105551234', tags: ['vip'] },
      { name: 'Ben Okafor', platformIdentifier: '+442071234567', tags: ['newsletter'] }
    ]
  }
});

console.log(imported.created, imported.skipped, imported.errors);
```
</Tab>
<Tab value="Python">
```python
imported = client.contacts.bulk_create_contacts(
    profile_id=profile_id,
    account_id=account_id,
    contacts=[
        {"name": "Ana Costa", "platformIdentifier": "+13105551234", "tags": ["vip"]},
        {"name": "Ben Okafor", "platformIdentifier": "+442071234567", "tags": ["newsletter"]},
    ],
)

print(imported["created"], imported["skipped"], imported["errors"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST https://zernio.com/api/v1/contacts/bulk \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "contacts": [
      {"name": "Ana Costa", "platformIdentifier": "+13105551234", "tags": ["vip"]},
      {"name": "Ben Okafor", "platformIdentifier": "+442071234567", "tags": ["newsletter"]}
    ]
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "created": 1,
  "skipped": 1,
  "errors": [],
  "total": 2
}
```

Ana was skipped because Step 1 already created her; her `vip` tag was already there.

## Step 3: Update a contact

Call `PATCH /v1/contacts/{contactId}` with the fields to change. Only the fields you send are written. `isSubscribed: false` drops the contact from segment-targeted broadcasts.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: updated } = await zernio.contacts.updateContact({
  path: { contactId },
  body: { tags: ['vip', 'promo-2027'], isSubscribed: true }
});

console.log(updated.contact.tags);
```
</Tab>
<Tab value="Python">
```python
updated = client.contacts.update_contact(
    contact_id=contact_id,
    tags=["vip", "promo-2027"],
    is_subscribed=True,
)

print(updated["contact"]["tags"])
```
</Tab>
<Tab value="curl">
```bash
curl -X PATCH "https://zernio.com/api/v1/contacts/66e5b2c3d4f5a6b7c8d9e0f1" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"tags": ["vip", "promo-2027"], "isSubscribed": true}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "success": true,
  "contact": {
    "id": "66e5b2c3d4f5a6b7c8d9e0f1",
    "name": "Ana Costa",
    "tags": ["vip", "promo-2027"],
    "isSubscribed": true,
    "isBlocked": false,
    "updatedAt": "2027-01-01T09:05:00Z"
  }
}
```

## If it fails

A `409` from Step 1 means the phone number is already a channel on this account:

```json
{
  "error": "Duplicate channel. The platformIdentifier is already bound to a channel on this accountId."
}
```

Look the contact up with [List contacts](/contacts/list-contacts) and update it instead, or use the bulk import, which skips duplicates rather than rejecting them. A `400` with code `missing_required_field` means `accountId`, `platform` and `platformIdentifier` were not sent together. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Broadcasts](/platforms/whatsapp/broadcasts): target these contacts by phone, id or tag.
- [Contacts API](/contacts/list-contacts): list, get, delete, channels and custom fields.
- [WhatsApp inbox](/platforms/whatsapp/inbox): the conversations these contacts appear in.
- [Connection and setup](/platforms/whatsapp/connection#business-profile): the profile customers see when they open your number.

---
