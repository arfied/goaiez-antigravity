# Lead Gen Forms

Create a Meta instant form with POST /v1/ads/lead-forms, attach it to an ad, and receive the leads by webhook or by polling.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Collect leads inside Meta instead of on your site: create an instant form, attach it to an ad with `goal: "lead_generation"`, and receive submissions on the [`lead.received`](/webhooks/ads#leadreceived) webhook. Forms are Page-scoped, so use a connected Facebook account or a `metaads` business-login connection with a selected Page as `accountId`; Instagram lead ads use the Page's form.

<Callout type="warn">
The Page must accept Facebook's Lead Generation terms once, at `https://www.facebook.com/ads/leadgen/tos/`, before lead ads can run. Until it does, ad creation returns Meta subcode `1815089`. Creating forms and reading leads work without it.
</Callout>

## Create a form

Call `POST /v1/ads/lead-forms` with `accountId`, `name`, `privacyPolicyUrl` and the form content in `platformSpecificData`. Prefilled question types (`EMAIL`, `PHONE`, `FULL_NAME`, `FIRST_NAME`, `LAST_NAME`) generate their own label and key, so send only `{ "type": "..." }`. A `CUSTOM` question needs `key`, `label` and, for a choice question, `options`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.leadgen.createLeadForm({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    name: 'Spring promo',
    privacyPolicyUrl: 'https://example.com/privacy',
    platformSpecificData: {
      questions: [
        { type: 'EMAIL' },
        { type: 'FULL_NAME' },
        {
          type: 'CUSTOM',
          key: 'budget',
          label: 'Monthly budget?',
          options: [
            { key: 'low', value: 'Under $1k' },
            { key: 'high', value: '$1k and above' }
          ]
        }
      ]
    }
  }
});

const formId = created.form.id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.lead_gen.create_lead_form(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    name="Spring promo",
    privacy_policy_url="https://example.com/privacy",
    platform_specific_data={
        "questions": [
            {"type": "EMAIL"},
            {"type": "FULL_NAME"},
            {
                "type": "CUSTOM",
                "key": "budget",
                "label": "Monthly budget?",
                "options": [
                    {"key": "low", "value": "Under $1k"},
                    {"key": "high", "value": "$1k and above"},
                ],
            },
        ]
    },
)

form_id = created["form"]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/lead-forms" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "name": "Spring promo",
    "privacyPolicyUrl": "https://example.com/privacy",
    "platformSpecificData": {
      "questions": [
        { "type": "EMAIL" },
        { "type": "FULL_NAME" },
        {
          "type": "CUSTOM",
          "key": "budget",
          "label": "Monthly budget?",
          "options": [
            { "key": "low", "value": "Under $1k" },
            { "key": "high", "value": "$1k and above" }
          ]
        }
      ]
    }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "status": "success",
  "form": { "id": "1029384756102938", "name": "Spring promo" }
}
```

`platformSpecificData` also carries the thank-you screen (`thankYouTitle`, `thankYouBody`, `thankYouButtonText`, `thankYouButtonType`, `thankYouWebsiteUrl`), the `contextCard`, `locale`, `followUpActionUrl` and `isOptimizedForQuality`, which adds a review step before submit. It is strict-parsed: an unknown key returns a `400`. The same endpoint serves [LinkedIn Ads](/platforms/linkedin-ads) with a LinkedIn-shaped `platformSpecificData`, selected by `accountId`. The older top-level Meta fields still work while `platformSpecificData` is absent; mixing the two shapes in one request is a `400`. Creates are not idempotent, so never auto-retry one.

To add a cover, set `platformSpecificData.contextCard.coverPhoto` to a direct public JPEG or PNG URL, up to 5 MB. Zernio uploads it as an unpublished Page photo and attaches it to the form. Redirects, Ad Image hashes and image IDs are unsupported.

## Attach the form to an ad

Send `goal: "lead_generation"` and `leadGenFormId` on the [create request](/platforms/meta-ads/campaigns#create-the-full-tree-in-one-call). This also works with [carousel creatives](/platforms/meta-ads/creatives#carousel-ads). No `linkUrl` is needed: the form is the destination, and the ad set's lead optimization and promoted Page are derived for you.

```json
{
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "act_1234567890",
  "name": "Spring promo lead ad",
  "goal": "lead_generation",
  "leadGenFormId": "1029384756102938",
  "budgetAmount": 10,
  "budgetType": "daily",
  "headline": "Get a quote",
  "body": "Tell us about your project",
  "callToAction": "SIGN_UP",
  "imageUrl": "https://cdn.example.com/creative.jpg"
}
```

`POST /v1/ads/boost` takes the same 2 fields to put a form behind an existing post, and every ad attached to a lead ad set needs its own `leadGenFormId` ([creatives](/platforms/meta-ads/creatives#attach-a-creative-to-an-existing-ad-set)).

## Receive leads

The [`lead.received`](/webhooks/ads#leadreceived) webhook fires the moment a lead is submitted, and its payload carries `lead.fields` (the question key to answer map) plus the `formId` and `adId` provenance. Poll `GET /v1/ads/lead-forms/{formId}/leads` when you would rather pull.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: page } = await zernio.leadgen.listFormLeads({
  path: { formId: '1029384756102938' },
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', limit: 50 }
});

for (const lead of page.leads) console.log(lead.fields);
```
</Tab>
<Tab value="Python">
```python
page = client.lead_gen.list_form_leads(
    form_id="1029384756102938",
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    limit=50,
)

for lead in page["leads"]:
    print(lead["fields"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/lead-forms/1029384756102938/leads?accountId=66b2e19d8c3f5a7e9d0b1c2d&limit=50" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "status": "success",
  "leads": [
    {
      "id": "5566778899001122",
      "createdTime": "2027-02-14T10:31:05+0000",
      "adId": "120260000000000000",
      "formId": "1029384756102938",
      "fields": { "email": "lead@example.com", "full_name": "Jamie Rivera", "budget": "high" }
    }
  ],
  "pagination": { "hasMore": false, "cursor": null }
}
```

`GET /v1/ads/leads` is the cross-form view of the same data: every lead the connected account has captured, filterable by `formId` and `since`, which is the shape to feed a CRM without iterating forms. `since` is Unix seconds, between `1` and `253402300799`; millisecond timestamps return `400` with instructions to divide by 1,000.

## Test a lead before you spend

`POST /v1/ads/lead-forms/{formId}/test-leads` puts a synthetic lead on the form so you can exercise your webhook handler and field mapping first. It takes `accountId` and `fieldData`, an array of at least one `{ name, values[] }` entry matching the form's questions.

```bash
curl -X POST "https://zernio.com/api/v1/ads/lead-forms/1029384756102938/test-leads" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "fieldData": [{ "name": "email", "values": ["test@example.com"] }]
  }'
```

Response (`200`):

```json
{ "status": "success", "testLead": { "id": "5566778899001123" } }
```

Meta allows one pending test lead per form, so consume or delete it before creating another. To retire a form, call `DELETE /v1/ads/lead-forms/{formId}`; Meta archives it, and there is no hard delete.

## Common errors

A `422` on the form create is Meta rejecting the form:

```json
{
  "error": "Meta rejected the lead form",
  "type": "platform_error",
  "platform": "meta",
  "platformError": { "code": 3 }
}
```

Code `3` is Meta's generic app-capability error and does not name a field. When the request set `isPhoneSmsVerifyEnabled`, the response names that field as the one to drop first; the toggle then has to be set in Meta's form builder. On the ad create, subcode `1815089` is the Lead Generation terms the Page has not accepted yet.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the create request `leadGenFormId` goes on.
- [Conversion campaigns](/platforms/meta-ads/conversion-campaigns): how `lead_generation` differs from `lead_conversion`.
- [Ads webhooks](/webhooks/ads): the `lead.received` event and its payload.
- [Create lead form](/lead-gen/create-lead-form) and [List form leads](/lead-gen/list-form-leads): every field.

---
