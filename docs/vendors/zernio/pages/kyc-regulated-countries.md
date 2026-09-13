# KYC (Regulated Countries)

Collect the identity details a regulated country requires, submit them with POST /v1/phone-numbers/kyc or a hosted link, and fix a declined number.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a number in a regulated country is in regulatory review under your team, and you know how to fix it if the reviewer declines it. You need a profile id and a [purchase](/platforms/phone-numbers/provisioning#step-2-purchase) that returned `status: "kyc_required"`. The US provisions numbers instantly; other countries regulate who can hold a number and need the registrant's identity before the number is ordered. That `202` also carries a `kycUrl`, a ready-made link to the [hosted form](#hand-the-form-off-white-label) for the country, so you can hand the step off instead of building the form with the endpoints below.

## Step 1: Check availability and the address constraint

Call `GET /v1/phone-numbers/availability` with `country`. It tells you whether deliverable inventory exists and which address the registrant needs:

- `geo`: the address must be in one of the returned `areas` (some countries stock numbers tied to a city).
- `country`: any in-country address works.
- `none`: no address is required.

[Availability and pricing](/platforms/phone-numbers/availability#check-one-country) has the call and its response. Checking first avoids the worst case: a submission that passes review but can never be assigned a number because the address is in the wrong area.

### Out of stock? Pre-order it

When `available` is `false` but `preOrderable` is `true`, you can still order the number. Run the same KYC flow below: the submission places a pre-order. We then get the number the fastest way available: from regular stock the moment it comes back, otherwise sourced by the carrier for this registration. That usually takes 2 to 4 weeks and is not guaranteed.

- The submit response carries `preOrder: true`, and the number stays `pending_regulatory` until it is sourced.
- Nothing is billed until the number is active. If the carrier cannot source it, the number is declined with a reason (the usual [`whatsapp.number.declined`](/webhooks/phone-numbers#whatsappnumberdeclined) webhook) and nothing was charged.
- A pre-order is one number per submission, so `quantity` must be 1.
- To cancel, release the number (`DELETE /v1/phone-numbers/{id}`) while it is still pending. We withdraw the carrier request too, so nothing is charged.
- Only countries and types that require KYC can be pre-ordered. That includes types marked `fulfilment: "request"` by `listPhoneNumberCountries` (`GET /v1/phone-numbers/countries`), which the carrier stocks nowhere and only sources to order. `preOrderable` on each type tells you which ones qualify.

## Step 2: Get the form for the country

Call `GET /v1/phone-numbers/kyc` with `country`. It returns the fields the country requires and whether your team already holds a reusable, approved verification for that country. Requirements and reuse eligibility are per country and number type, so add `numberType` when you want something other than the country's default, and send the same value on the submit. A field's `kind` is `text`, `date`, `address`, `file` (an ID or a business registration) or `action`, an out-of-band identity verification run by Onfido. An `action` field is not filled in on this form: it is fulfilled after the order, through a link, in [step 4](#step-4-forward-the-id-verification-link).

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const profileId = '66a1f0c2a4b9d3e8f1a2b3c4';

const { data: form } = await zernio.phonenumbers.getPhoneNumberKycForm({
  query: { country: 'GB' }
});
for (const f of form.fields) {
  console.log(f.requirementId, f.kind, f.label);
}
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
profile_id = "66a1f0c2a4b9d3e8f1a2b3c4"

form = client.phone_numbers.get_phone_number_kyc_form(country="GB")
for f in form["fields"]:
    print(f["requirementId"], f["kind"], f["label"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/phone-numbers/kyc?country=GB" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "country": "GB",
  "numberType": "local",
  "fields": [
    { "requirementId": "d7e8f9a0-b1c2-4d3e-9f4a-5b6c7d8e9f0a", "label": "Business name", "kind": "text", "example": "Acme Ltd" },
    { "requirementId": "e8f9a0b1-c2d3-4e4f-8a5b-6c7d8e9f0a1b", "label": "Proof of identity", "kind": "file", "description": "Passport or ID card, both sides" },
    { "requirementId": "f9a0b1c2-d3e4-4f5a-9b6c-7d8e9f0a1b2c", "label": "Business address", "kind": "address", "localTo": "GB" }
  ],
  "reusable": null,
  "pendingReview": false
}
```

When `reusable` is set, submit with `reuse: true` and the option's `id` as `reuseOptionId` to skip the documents; the order is placed at once.

## Step 3: Submit the details

Call `POST /v1/phone-numbers/kyc` with `profileId`, `country`, the `values`, `documents` and `address` keyed by `requirementId`. Repeat the `numberType` from step 2 to order that type, and add `wantsSms: true` to have SMS enabled on activation, which draws the order from SMS-capable stock. Send each document inline as base64, or upload it first with `POST /v1/phone-numbers/kyc/upload-document` (raw bytes, filename in `X-Filename`) and pass the returned `documentId`. For an ID-card requirement, carriers need both sides: combine front and back into one file before uploading, because a one-sided ID is a common decline reason. Add `endUserFirstName` and `endUserLastName` when the form returned a field with `kind: "action"`: the country's ID-verification step needs the end user's legal name and the submit is rejected without it.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/kyc" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "country": "GB",
    "values": { "d7e8f9a0-b1c2-4d3e-9f4a-5b6c7d8e9f0a": "Acme Ltd" },
    "documents": [{ "requirementId": "e8f9a0b1-c2d3-4e4f-8a5b-6c7d8e9f0a1b", "filename": "id.pdf", "base64": "JVBERi0xLjQK..." }],
    "address": {
      "requirementId": "f9a0b1c2-d3e4-4f5a-9b6c-7d8e9f0a1b2c", "country_code": "GB",
      "street_address": "10 Downing Street", "locality": "London",
      "administrative_area": "London", "postal_code": "SW1A 2AA"
    }
  }'
```

Response (`200`):

```json
{
  "status": "kyc_submitted",
  "phoneNumber": {
    "id": "66d4e5f6a7b8c9d0e1f2a3b4",
    "status": "pending_regulatory",
    "country": "GB"
  },
  "numbers": [
    { "id": "66d4e5f6a7b8c9d0e1f2a3b4", "status": "pending_regulatory", "phoneNumber": null, "country": "GB" }
  ]
}
```

The order goes to regulatory review and the number activates within 1 to 3 business days. Poll `GET /v1/phone-numbers/{id}`, or listen for [`whatsapp.number.activated`](/webhooks/phone-numbers#whatsappnumberactivated) and [`whatsapp.number.declined`](/webhooks/phone-numbers#whatsappnumberdeclined); they fire for every number, and the names are legacy. Send a `submissionId` so a retry of the same attempt returns the same number instead of ordering another; `quantity` (1 to 5) provisions several same-country numbers from one verification.

ID documents and the address stream straight to the number provider inside the request; Zernio never stores the document bytes. `POST /v1/phone-numbers/kyc/validate-address` checks an address for deliverability before you upload anything, and `POST /v1/phone-numbers/kyc/review-packet` reviews a whole packet for likely decline reasons; both are optional.

## Step 4: Forward the ID-verification link

Only for a country whose form carried an `action` field. Once the order is placed, `onfidoVerificationUrl` appears on the number and holds the link the end user has to complete; it is `null` on every other number and until the order exists, so poll `GET /v1/phone-numbers/{id}` after submitting.

```bash
curl "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`), trimmed:

```json
{
  "phoneNumber": {
    "id": "66d4e5f6a7b8c9d0e1f2a3b4",
    "status": "pending_regulatory",
    "country": "GB",
    "onfidoVerificationUrl": "https://id.onfido.app/..."
  }
}
```

Send that link to the end user. The requirement stays unmet until they finish the check, so a number whose link nobody opened sits in `pending_regulatory` past the usual 1 to 3 business days.

## Hand the form off (white-label)

Call `POST /v1/phone-numbers/kyc/share` with `profileId` and `country` when you would rather not build the document-collection UI. It returns a single-use hosted page (valid 7 days, no Zernio login) running the same guided wizard. Pass `branding` (company name, logo, brand color) so the page carries your brand, and `redirect_url` to send the customer back to your app afterward; Zernio appends `kyc=submitted` and `country=<ISO-2>` to it. The number provisions under your team once they submit, and [`whatsapp.number.kyc_submitted`](/webhooks/phone-numbers#whatsappnumberkyc_submitted) fires.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/kyc/share" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "profileId": "66a1f0c2a4b9d3e8f1a2b3c4",
    "country": "GB",
    "branding": { "companyName": "Acme", "logoUrl": "https://acme.example.com/logo.png", "brandColor": "#1a73e8" },
    "redirect_url": "https://acme.example.com/numbers/done"
  }'
```

Response (`200`):

```json
{
  "url": "https://zernio.com/kyc/share/...",
  "token": "...",
  "expiresAt": "2027-01-08T12:00:00Z"
}
```

## Fix a declined number

You do not start over. A declined number stays in your list with status `regulatory_declined` and `regulatoryDeclineReason`; the `status=pending_regulatory` filter on `GET /v1/phone-numbers` also surfaces numbers declined in the last 30 days. Call `GET /v1/phone-numbers/{id}/remediate` for only the requirements the reviewer flagged, then `POST /v1/phone-numbers/{id}/remediate` with the corrected `values`, `documents` or `address`. The number goes back to review on the same record, keeps its place in the queue and is not billed again.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/remediate" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"documents": [{ "requirementId": "e8f9a0b1-c2d3-4e4f-8a5b-6c7d8e9f0a1b", "filename": "id-both-sides.pdf", "base64": "JVBERi0xLjQK..." }]}'
```

Response (`200`):

```json
{
  "status": "pending_regulatory",
  "phoneNumber": { "id": "66d4e5f6a7b8c9d0e1f2a3b4", "status": "pending_regulatory" }
}
```

## Answer the reviewer

Not every ask fits the form. A reviewer who writes "is this a personal or a business line?" wants a sentence, and the structured requirements have nowhere to put it. `POST /v1/phone-numbers/{id}/remediate/respond` sends one response covering both: a `message` to the reviewer, corrected `documents` or `address` when you have them, and up to 5 loose `attachments` (PDF, JPG, PNG or WebP, 10 MB each) whose links are added to the message. At least one of the three is required.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/remediate/respond" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"message": "This is a business line for Acme Ltd; the utility bill is in the company name."}'
```

Response (`200`):

```json
{
  "status": "replied",
  "posted": true,
  "phoneNumber": { "id": "66d4e5f6a7b8c9d0e1f2a3b4", "status": "pending_regulatory" },
  "siblingsResubmitted": 0
}
```

`status` is `replied` for a message-only response and `resubmitted` when corrections went with it, which is also what moves the number back into review; `siblingsResubmitted` counts the other numbers on the same registration the correction fanned out to. `POST /v1/phone-numbers/{id}/remediate/reply` is the message-only half of the same job, taking `text` and `attachments`. Answering a comment-style ask puts the number back in review on its own; a reply on a formal decline is supplementary, so still send the fix through `POST /v1/phone-numbers/{id}/remediate`. A `409` means the number sits under Zernio's own carrier registration, so there is nothing for you to correct.

## If it fails

A `400` on the submit means a value failed validation, most often an address outside the country or a file over the size limit:

```json
{
  "error": "Address must be in GB",
  "type": "invalid_request_error",
  "param": "address"
}
```

Run `POST /v1/phone-numbers/kyc/validate-address` first to catch it before uploading documents. A `409` means `reuse: true` was sent with no approved verification on file, or the requested `areaCode` has no inventory (`code: "area_code_unavailable"`). Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Buying a number](/platforms/phone-numbers/provisioning): the purchase that starts this flow.
- [Availability and pricing](/platforms/phone-numbers/availability): which countries need KYC.
- [Phone number webhooks](/webhooks/phone-numbers): how events map to number status.
- [Submit KYC](/phone-numbers/submit-phone-number-kyc) and [Create a hosted KYC link](/phone-numbers/create-phone-number-kyc-link): every field.

---
