# Carrier Registration

Register a US number for SMS with 10DLC or toll-free verification through POST /v1/sms/registrations, reuse an approval on more numbers, and appeal a rejection.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page a US number has a carrier registration in review, and you know how to add more numbers to it without paying again. You need an [SMS-capable number](/platforms/phone-numbers/provisioning) and the legal details of the business sending the messages. US carriers require a number to be registered before it can send SMS; `POST /v1/sms/registrations` handles both paths:

- [10DLC](#start-a-10dlc-registration), for local (10-digit long code) numbers. Standard (company) or sole proprietor. Needs a `brand` and a `campaign`.
- [Toll-free verification](#start-a-toll-free-verification), for toll-free numbers. Needs the `tollFree` details.

Approval is asynchronous: poll `GET /v1/sms/registrations/{id}` and do not assume delivery until `status` is `approved`. Registration is a US requirement only; a number in any other country sends as soon as [SMS is enabled](#enable-sms-on-the-number-first) on it.

## What registration costs

Registration bills 2 fees to your usage invoice: a $9 one-time brand fee, and a recurring campaign fee of $20 per month for standard 10DLC and toll-free, or $4 per month for sole proprietors. The fees show before you submit; per-destination message rates are in the [SMS price list](/pricing/sms). [Reusing an approval](#reuse-an-approval-skip-the-fee) on more numbers costs nothing.

## Enable SMS on the number first

Call `POST /v1/phone-numbers/{id}/sms`. Zernio checks the number's real carrier capability, and the response says which registration path applies.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b4/sms" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "enabled": true,
  "id": "66b2e19d8c3f5a7e9d0b1c2d",
  "phoneNumber": "+14155550100",
  "isActive": false,
  "country": "US",
  "smsCapable": true,
  "mmsCapable": true,
  "needsRegistration": true,
  "alreadyRegistered": false,
  "registrationStatus": null,
  "reusable": null
}
```

`id` is the account id the number sends from. `isActive` stays `false` on a US number until its registration is approved. `enabled: false` with `smsCapable: false` means the number cannot text; `notReady: true` means it is still provisioning at the carrier, so retry shortly. When `reusable` is set, skip to [reuse](#reuse-an-approval-skip-the-fee).

## Start a 10DLC registration

Call `POST /v1/sms/registrations` with `registrationType`, `brand` and `campaign`. `registrationType` is the only field the endpoint requires; `brand` and `campaign` are required for 10DLC, and `phoneNumbers` is optional. Send `phoneNumbers` anyway: omitting it enrols every active SMS-enabled US local number on the team that no other registration already covers, which is rarely what one campaign should cover.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: registration } = await zernio.sms.startSmsRegistration({
  body: {
    phoneNumbers: ['+14155550100'],
    registrationType: 'standard_10dlc',
    brand: {
      entityType: 'PRIVATE_PROFIT',
      displayName: 'Acme',
      companyName: 'Acme Inc.',
      ein: '12-3456789',
      phone: '+14155550199',
      country: 'US',
      vertical: 'TECHNOLOGY',
      website: 'https://acme.example.com',
      street: '123 Market St',
      city: 'San Francisco',
      state: 'CA',
      postalCode: '94105',
    },
    campaign: {
      usecase: 'CUSTOMER_CARE',
      description: 'Order updates and support replies for Acme customers.',
      messageFlow: 'Customers opt in at checkout on https://acme.example.com/checkout by ticking the SMS updates box.',
      sample1: 'Acme: your order #1234 has shipped. Reply STOP to opt out.',
      sample2: 'Acme: a support agent replied to your ticket. Reply HELP for help.',
      optinKeywords: 'START',
      optoutKeywords: 'STOP',
      helpKeywords: 'HELP',
    },
  }
});
const registrationId = registration.registrationId;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

registration = client.sms.start_sms_registration(
    phone_numbers=["+14155550100"],
    registration_type="standard_10dlc",
    brand={
        "entityType": "PRIVATE_PROFIT",
        "displayName": "Acme",
        "companyName": "Acme Inc.",
        "ein": "12-3456789",
        "phone": "+14155550199",
        "country": "US",
        "vertical": "TECHNOLOGY",
        "website": "https://acme.example.com",
        "street": "123 Market St",
        "city": "San Francisco",
        "state": "CA",
        "postalCode": "94105",
    },
    campaign={
        "usecase": "CUSTOMER_CARE",
        "description": "Order updates and support replies for Acme customers.",
        "messageFlow": "Customers opt in at checkout on https://acme.example.com/checkout by ticking the SMS updates box.",
        "sample1": "Acme: your order #1234 has shipped. Reply STOP to opt out.",
        "sample2": "Acme: a support agent replied to your ticket. Reply HELP for help.",
        "optinKeywords": "START",
        "optoutKeywords": "STOP",
        "helpKeywords": "HELP",
    },
)
registration_id = registration["registrationId"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/sms/registrations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "phoneNumbers": ["+14155550100"],
    "registrationType": "standard_10dlc",
    "brand": {
      "entityType": "PRIVATE_PROFIT",
      "displayName": "Acme",
      "companyName": "Acme Inc.",
      "ein": "12-3456789",
      "phone": "+14155550199",
      "country": "US",
      "vertical": "TECHNOLOGY",
      "website": "https://acme.example.com",
      "street": "123 Market St",
      "city": "San Francisco",
      "state": "CA",
      "postalCode": "94105"
    },
    "campaign": {
      "usecase": "CUSTOMER_CARE",
      "description": "Order updates and support replies for Acme customers.",
      "messageFlow": "Customers opt in at checkout on https://acme.example.com/checkout by ticking the SMS updates box.",
      "sample1": "Acme: your order #1234 has shipped. Reply STOP to opt out.",
      "sample2": "Acme: a support agent replied to your ticket. Reply HELP for help.",
      "optinKeywords": "START",
      "optoutKeywords": "STOP",
      "helpKeywords": "HELP"
    }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "registrationId": "67a7b8c9d0e1f2a3b4c5d6e7",
  "status": "pending",
  "awaitingOtp": false
}
```

Carriers reject sparse filings, so several `brand` and `campaign` fields are hard requirements: the brand needs a `website` (sole proprietors may use a social profile URL instead), a full street address (`street`, `city`, `state`, `postalCode`) and, for company, non-profit and government brands, a business `phone`. The campaign needs a use case, a description, a `messageFlow` with a link to the page where people opt in, opt-in, opt-out and help keywords, and 2 distinct sample messages (`sample1` and `sample2`, 20+ characters each). The auto-response messages (`optinMessage`, `optoutMessage`, `helpMessage`) are optional; Zernio generates a compliant template when they are omitted. `POST /v1/sms/registrations/preflight` runs the same checks plus a compliance review without creating anything.

Sole-proprietor registrations (`sole_prop_10dlc`) return `awaitingOtp: true`: a code is texted to the brand's `mobilePhone`, and the registration waits until you submit it with `POST /v1/sms/registrations/{id}/verify-otp` and `otpPin`. When that code expired or never arrived, `POST /v1/sms/registrations/{id}/resend-otp` sends a new one, at most once a minute. Standard company registrations skip this.

## Start a toll-free verification

A toll-free number is verified by the toll-free aggregator instead of the 10DLC registry, so it takes no `brand` and no `campaign`. Call `POST /v1/sms/registrations` with `registrationType: "toll_free"` and a `tollFree` object.

```bash
curl -X POST "https://zernio.com/api/v1/sms/registrations" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "registrationType": "toll_free",
    "phoneNumbers": ["+18445550100"],
    "tollFree": {
      "businessName": "Acme Inc.",
      "corporateWebsite": "https://acme.example.com",
      "phoneNumbers": ["+18445550100"],
      "useCase": "Customer care",
      "useCaseSummary": "Order updates and support replies for Acme customers.",
      "productionMessageContent": "Acme: your order #1234 has shipped. Reply STOP to opt out.",
      "optInWorkflow": "Customers tick the SMS updates box at checkout on https://acme.example.com/checkout.",
      "optInWorkflowImageUrls": ["https://zernio.com/opt-in-proof/..."],
      "messageVolume": "10,000",
      "additionalInformation": "Transactional only, no promotions.",
      "businessAddr1": "123 Market St",
      "businessCity": "San Francisco",
      "businessState": "CA",
      "businessZip": "94105",
      "businessContactFirstName": "Dana",
      "businessContactLastName": "Reyes",
      "businessContactEmail": "dana@acme.example.com",
      "businessContactPhone": "+14155550199",
      "businessRegistrationNumber": "12-3456789",
      "businessRegistrationType": "EIN",
      "businessRegistrationCountry": "US"
    }
  }'
```

Response (`200`):

```json
{
  "registrationId": "67a7b8c9d0e1f2a3b4c5d6e8",
  "status": "pending",
  "awaitingOtp": false
}
```

Every field in that body is required; `businessAddr2` is the only optional one. Two of them decide the outcome. `optInWorkflowImageUrls` needs at least one screenshot of the opt-in form itself, so upload the image with `POST /v1/sms/opt-in-proof` (PNG, JPG, WebP, GIF or PDF, up to 4 MB) and pass the URL it returns. `messageVolume` is a monthly tier, one of `10`, `100`, `1,000`, `10,000`, `100,000`, `250,000`, `500,000`, `750,000`, `1,000,000`, `5,000,000` or `10,000,000+`.

Toll-free verification has no OTP step, so `awaitingOtp` is always `false`. Track it with the same `GET /v1/sms/registrations/{id}` as 10DLC. `POST /v1/sms/registrations/{id}/appeal` covers 10DLC campaigns only.

## Reuse an approval (skip the fee)

Carriers charge a brand fee per registration. When you already hold an approved 10DLC campaign, attach the new number to it with `POST /v1/phone-numbers/{id}/sms/reuse-registration` instead of registering again. The number inherits the campaign's approval, with no new brand or campaign and no extra carrier fee. Enable SMS on the number first; its response carries the `reusable` registration.

```bash
curl -X POST "https://zernio.com/api/v1/phone-numbers/66d4e5f6a7b8c9d0e1f2a3b5/sms/reuse-registration" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "registrationId": "67a7b8c9d0e1f2a3b4c5d6e7",
  "status": "approved"
}
```

## Hand the form off (white-label)

When your customer holds the legal business details, call `POST /v1/sms/registrations/share` with the number's `numberId`. It returns a single-use link (valid 7 days) where they fill in the registration form with no Zernio login; the registration is created under your team once they submit.

```bash
curl -X POST "https://zernio.com/api/v1/sms/registrations/share" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"numberId": "66d4e5f6a7b8c9d0e1f2a3b4"}'
```

Response (`200`):

```json
{
  "url": "https://zernio.com/sms/register/share/...",
  "expiresAt": "2027-01-08T12:00:00Z"
}
```

## Track and appeal

Call `GET /v1/sms/registrations` for every registration and `GET /v1/sms/registrations/{id}` for one.

```bash
curl "https://zernio.com/api/v1/sms/registrations/67a7b8c9d0e1f2a3b4c5d6e7" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```

Response (`200`):

```json
{
  "id": "67a7b8c9d0e1f2a3b4c5d6e7",
  "registrationType": "standard_10dlc",
  "status": "pending",
  "brandStatus": "VERIFIED",
  "campaignStatus": "PENDING",
  "declineReason": null,
  "phoneNumbers": ["+14155550100"],
  "awaitingOtp": false
}
```

Branch on `status`:

| `status` | Meaning | What to do |
|---|---|---|
| `pending` | Submitted and under carrier review. On a sole-proprietor registration, `awaitingOtp: true` means it is paused on the [OTP step](#start-a-10dlc-registration). | Poll, or wait. Messages do not deliver yet. |
| `approved` | The registration is live; every number attached to it can send. | Start [sending](/platforms/sms/sending), or [attach more numbers](#reuse-an-approval-skip-the-fee). |
| `rejected` | The carrier declined it; `declineReason` says why. | Fix and appeal (below). |

You may also see `requested` and `changes_requested` (pre-submission review states; answer a change request with `POST /v1/sms/registrations/{id}/respond`) and `deactivated` for terminated registrations, which the list hides unless you pass `includeDeactivated=true`. `brandStatus`, `campaignStatus` and `trustScore` are the raw carrier-registry fields, useful when referencing the filing with carrier support, but `status` is the field to branch on. Statuses are not one-way: an approved registration can drop back to `pending` when the carrier registry suspends the campaign, so treat `status` as the current state rather than a completed milestone.

Appeal a rejected campaign with `POST /v1/sms/registrations/{id}/appeal`: pass an `appealReason` that addresses `declineReason` directly, and fix the flagged content in the same call (`messageFlow`, `sample1`, `sample2`; the content on file is returned as `campaignContent` on a rejected registration). Carrier reviewers reject campaigns whose consent they cannot verify, so host a screenshot of your opt-in form with `POST /v1/sms/opt-in-proof` and put the returned URL inside `messageFlow`.

```bash
curl -X POST "https://zernio.com/api/v1/sms/registrations/67a7b8c9d0e1f2a3b4c5d6e7/appeal" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "appealReason": "The opt-in checkbox is on the checkout page linked below; screenshot attached in the message flow.",
    "messageFlow": "Customers opt in at checkout on https://acme.example.com/checkout by ticking the SMS updates box. Screenshot: https://zernio.com/opt-in-proof/..."
  }'
```

Response (`200`):

```json
{
  "status": "pending"
}
```

A brand-level rejection cannot be appealed: correct the brand details and re-verify instead.

## If it fails

A `400` on `POST /v1/sms/registrations` names the missing field:

```json
{
  "error": "campaign.sample2 is required",
  "type": "invalid_request_error",
  "code": "missing_required_field",
  "param": "campaign.sample2"
}
```

Add the field and resubmit; run `POST /v1/sms/registrations/preflight` first to catch every gap at once. A `422` means the carrier registry rejected a field; `param` names it when known. Every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Sending](/platforms/sms/sending): send once the number is approved.
- [Start a carrier registration](/sms/start-sms-registration): every `brand`, `campaign` and `tollFree` field.
- [Add a number to an existing registration](/sms/reuse-sms-registration-for-number) and [Appeal a rejected campaign](/sms/appeal-sms-registration): every field.
- [SMS rates](/pricing/sms): registration fees and per-segment prices.

---
