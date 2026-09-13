# Porting

Move a number you own at another carrier onto Zernio: check portability, upload the LOA and invoice, submit the port-in and track it to ported.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a number you already own is transferring to Zernio and you know how to track it to `ported`. You need the number, a signed Letter of Authorization (LOA), a recent invoice from the losing carrier and the account details on that carrier.

## Step 1: Check portability

Call `POST /v1/phone-numbers/port-in/check` with `phoneNumbers` before collecting anything from the customer. It is read-only and creates no order.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: check } = await zernio.phonenumbers.checkPhoneNumberPortability({
  body: { phoneNumbers: ['+34911234567'] }
});
for (const r of check.results) {
  console.log(r.phoneNumber, r.portable, r.countryCode, r.phoneNumberType, r.notPortableReason);
}
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

check = client.phone_numbers.check_phone_number_portability(
    phone_numbers=["+34911234567"]
)
for r in check["results"]:
    print(r["phoneNumber"], r["portable"], r["countryCode"], r["phoneNumberType"], r["notPortableReason"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/port-in/check" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"phoneNumbers": ["+34911234567"]}'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "results": [
    {
      "phoneNumber": "+34911234567",
      "portable": true,
      "fastPortable": false,
      "lineType": "landline",
      "countryCode": "ES",
      "phoneNumberType": "local",
      "notPortableReason": null
    }
  ]
}
```

`countryCode` and `phoneNumberType` (`local`, `mobile`, `national`, `toll_free`) are the `country` and `numberType` for Step 2.

The check answers per number, because coverage varies by number type inside a country. Porting runs in the United States, Canada, United Kingdom, Spain, Germany, France, Netherlands, Australia and Brazil; landline and national numbers are portable in all of them, UK mobiles port with a PAC code, and Spanish, German, French and Dutch mobiles cannot be ported yet. Porting itself is free, and a ported number arrives voice-ready, SMS-ready where the order supports messaging, and starts billing its regular monthly price once it activates.

## Step 2: Get the country requirements (international ports only)

US and Canadian ports need nothing beyond the LOA, invoice and account details. For every other country, call `GET /v1/phone-numbers/port-in/requirements` with `country` and `numberType` from the check.

```bash
curl "https://zernio.com/api/v1/phone-numbers/port-in/requirements?country=ES&numberType=local" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "country": "ES",
  "numberType": "local",
  "supported": true,
  "fields": [
    { "requirementId": "a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d", "label": "CIF", "kind": "text", "example": "B12345674" },
    { "requirementId": "b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e", "label": "ID copy", "kind": "file" },
    { "requirementId": "c3d4e5f6-a7b8-4c9d-8e0f-2a3b4c5d6e7f", "label": "Service address", "kind": "address" }
  ]
}
```

| `kind` | How to satisfy it |
|---|---|
| `text` | A string value (a Spanish CIF, a UK PAC code). |
| `date` | An ISO date string. |
| `file` | Upload the document (Step 3) and use the returned `documentId` as the value. |
| `address` | Nothing to send: the `endUser` service address from your submit is applied automatically. |
| `action` | Cannot be completed through the API (the response also carries `supported: false`); contact support to port this number type. |

You pass the collected values as `requirements` in Step 4. The LOA and invoice also appear as requirements in some countries; `loaDocumentId` and `invoiceDocumentId` satisfy them, so never send them again here.

## Step 3: Upload the documents

Call `POST /v1/phone-numbers/port-in/documents` once per document as multipart form data: a `file` part (PDF, JPEG or PNG, up to 10 MB) and a `kind` part (`loa`, `invoice`, or any short slug for requirement documents). Requirement documents are converted to PDF automatically, since regulators reject raw images. Upload shortly before submitting: unattached documents are deleted after 30 minutes.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/port-in/documents" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -F file=@loa.pdf \
  -F kind=loa
```

Response (`200`):

```json
{
  "documentId": "3f2a9c1e-7b4d-4e8a-9c6f-1d2e3f4a5b6c"
}
```

Repeat for the invoice (`kind=invoice`) and for every `file`-kind requirement (for example `kind=id-copy` for the passport). Keep each `documentId`.

## Step 4: Submit the port-in

Call `POST /v1/phone-numbers/port-in` with `phoneNumbers`, `loaDocumentId`, `invoiceDocumentId`, the losing-carrier account details in `endUser` and, for international ports, `requirements`. The transfer PIN for US/CA mobile numbers goes in `endUser.pinPasscode`; international porting codes (the UK PAC) travel as requirement values. Both are forwarded to the carrier and never stored by Zernio.

`endUser.countryCode` is the service-address country and must be a supported porting country; `administrativeArea` is required (and validated) for US/CA only; EU business ports can pass `taxIdentifier` and `businessIdentifier`. Numbers from different countries go in separate requests when any of them is outside the US/CA.

`focDatetimeRequested` asks for the cutover date, the moment the number starts ringing on Zernio; the carrier confirms the actual FOC (firm order commitment) date later. Omit it and a US/CA order defaults to one week out, shifted off weekends, while an international order is scheduled into the carrier's next allowed porting window at or after the date you send. `portType` is `full` when the losing account ports every number it holds and `partial` when it keeps some; it defaults to `full`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: port } = await zernio.phonenumbers.createPhoneNumberPortIn({
  body: {
    phoneNumbers: ['+34911234567'],
    loaDocumentId: '3f2a9c1e-7b4d-4e8a-9c6f-1d2e3f4a5b6c',
    invoiceDocumentId: '8a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d',
    endUser: {
      entityName: 'Acme SL',
      authPersonName: 'Jane Doe',
      accountNumber: 'A-778812',
      streetAddress: 'Calle Gran Via 1',
      locality: 'Madrid',
      postalCode: '28013',
      countryCode: 'ES',
      taxIdentifier: 'B12345674',
    },
    requirements: [
      { requirementTypeId: 'a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d', fieldValue: 'B12345674' },
      { requirementTypeId: 'b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e', fieldValue: 'c5d6e7f8-a9b0-4c1d-8e2f-3a4b5c6d7e8f' },
    ],
  }
});
for (const o of port.orders) {
  console.log(o.id, o.status, o.error ?? 'ok');
}
```
</Tab>
<Tab value="Python">
```python
port = client.phone_numbers.create_phone_number_port_in(
    phone_numbers=["+34911234567"],
    loa_document_id="3f2a9c1e-7b4d-4e8a-9c6f-1d2e3f4a5b6c",
    invoice_document_id="8a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d",
    end_user={
        "entityName": "Acme SL",
        "authPersonName": "Jane Doe",
        "accountNumber": "A-778812",
        "streetAddress": "Calle Gran Via 1",
        "locality": "Madrid",
        "postalCode": "28013",
        "countryCode": "ES",
        "taxIdentifier": "B12345674",
    },
    requirements=[
        {"requirementTypeId": "a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d", "fieldValue": "B12345674"},
        {"requirementTypeId": "b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e", "fieldValue": "c5d6e7f8-a9b0-4c1d-8e2f-3a4b5c6d7e8f"},
    ],
)
for o in port["orders"]:
    print(o["id"], o["status"], o.get("error", "ok"))
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/port-in" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "phoneNumbers": ["+34911234567"],
    "loaDocumentId": "3f2a9c1e-7b4d-4e8a-9c6f-1d2e3f4a5b6c",
    "invoiceDocumentId": "8a1b2c3d-4e5f-4a6b-8c7d-9e0f1a2b3c4d",
    "endUser": {
      "entityName": "Acme SL",
      "authPersonName": "Jane Doe",
      "accountNumber": "A-778812",
      "streetAddress": "Calle Gran Via 1",
      "locality": "Madrid",
      "postalCode": "28013",
      "countryCode": "ES",
      "taxIdentifier": "B12345674"
    },
    "requirements": [
      {"requirementTypeId": "a1b2c3d4-e5f6-4a7b-8c9d-0e1f2a3b4c5d", "fieldValue": "B12345674"},
      {"requirementTypeId": "b2c3d4e5-f6a7-4b8c-9d0e-1f2a3b4c5d6e", "fieldValue": "c5d6e7f8-a9b0-4c1d-8e2f-3a4b5c6d7e8f"}
    ]
  }'
```
</Tab>
</Tabs>

Response (`201`):

```json
{
  "id": "67d0e1f2a3b4c5d6e7f8a9b0",
  "status": "pending",
  "phoneNumbers": ["+34911234567"],
  "orders": [
    { "id": "67d0e1f2a3b4c5d6e7f8a9b0", "status": "pending", "phoneNumbers": ["+34911234567"] }
  ]
}
```

The carrier may split your numbers into several orders (by country, number type or losing carrier); `orders[]` carries the per-order result. A partial failure still returns `201`: the failed orders come back with `error` set and stay as cancellable drafts, so you fix and resubmit only those. When a required country value is missing, the order is kept as a draft and `error` names what is needed (for example `Additional information required: Spanish CIF`); `GET /v1/phone-numbers/port-in/{id}/requirements` lists an order's live gaps at any time.

## Track and cancel

Call `GET /v1/phone-numbers/port-in` for your orders, newest first. US/CA ports typically complete in a few business days (same day for FastPort-eligible numbers); international ports can take several weeks while the carrier and local regulator review the documents.

```bash
curl "https://zernio.com/api/v1/phone-numbers/port-in" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "orders": [
    {
      "id": "67d0e1f2a3b4c5d6e7f8a9b0",
      "status": "foc_confirmed",
      "phoneNumbers": ["+34911234567"],
      "fastPortEligible": false,
      "focDatetimeRequested": "2027-01-08T00:00:00Z",
      "focDatetimeActual": "2027-01-12T00:00:00Z",
      "declineReason": null,
      "submittedAt": "2027-01-01T12:00:00Z",
      "portedAt": null
    }
  ]
}
```

| `status` | Meaning |
|---|---|
| `draft` | Created but not accepted by the carrier, including split orders that failed at submit (their `error` says why). Fix and resubmit, or cancel; drafts abandoned for 14 days are cancelled automatically. |
| `pending` | Submitted; the losing carrier is processing the transfer. |
| `foc_confirmed` | The carrier confirmed the transfer date (`focDatetimeActual`, the Firm Order Commitment). The number moves on that date. |
| `ported` | Transfer complete. The number is on your team, voice-ready. |
| `exception` | The losing carrier flagged a problem; `declineReason` says what. Not terminal: the order stays open and returns to `pending` once the flagged details are corrected and resubmitted. |
| `cancelled` | The order was cancelled and will not proceed. |

Cancel an order that has not reached `ported` with `DELETE /v1/phone-numbers/port-in/{id}`; it returns `200` with `status: "cancelled"` (the carrier may report `cancel-pending` briefly). Once an order reaches `ported`, the number appears in your [number list](/platforms/phone-numbers/provisioning#manage-numbers), voice-ready, and starts billing at its country's regular monthly price.

## If it fails

A `422` on the submit means a number is not portable, the numbers span several non-US/CA countries, or every split order failed:

```json
{
  "error": "+34611234567 is not portable: mobile numbers in ES cannot be ported yet",
  "type": "invalid_request_error",
  "param": "phoneNumbers"
}
```

Run Step 1 on each number and submit one country per request. A `409` means a number is already provisioned on Zernio or already in an in-flight port. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Buying a number](/platforms/phone-numbers/provisioning): buy from inventory instead.
- [Availability and pricing](/platforms/phone-numbers/availability): what the ported number bills per month.
- [Port numbers in](/phone-numbers/create-phone-number-port-in) and [Country porting requirements](/phone-numbers/get-phone-number-port-in-requirements): every field.
- [SMS](/platforms/sms): enable texting on the ported number.

---
