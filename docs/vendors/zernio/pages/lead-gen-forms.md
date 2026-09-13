# Lead Gen Forms

Create LinkedIn Lead Gen Forms with POST /v1/ads/lead-forms and pull their responses with GET /v1/ads/leads.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you have a LinkedIn Lead Gen Form and a way to pull its responses. LinkedIn forms share the `/v1/ads/lead-forms` endpoints with Meta: `accountId` selects the platform and the LinkedIn-shaped content goes inside `platformSpecificData`. LinkedIn forms are owned by the ad account's Company Page, so the calls also take `adAccountId`.

## Create a form

Call `POST /v1/ads/lead-forms` with `accountId`, `name`, `privacyPolicyUrl` and a `platformSpecificData` carrying `adAccountId`, `headline`, `description` and at least one question. Every question needs `kind`, `name` and `question`; a `multipleChoice` question also needs `choices`.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.leadgen.createLeadForm({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    name: 'Q3 Whitepaper form',
    privacyPolicyUrl: 'https://example.com/privacy',
    platformSpecificData: {
      adAccountId: '517258773',
      headline: 'Get the whitepaper',
      description: 'Fill in your details and we will send it over.',
      state: 'PUBLISHED',
      questions: [
        { kind: 'text', name: 'company', question: 'Company name', maxResponseLength: 120 },
        { kind: 'multipleChoice', name: 'size', question: 'Team size',
          choices: [{ id: 1, text: '1 to 10' }, { id: 2, text: '11 to 50' }] }
      ],
      consents: [{ description: 'I agree to be contacted', required: true }]
    }
  }
});

console.log(created.form.id);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.lead_gen.create_lead_form(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    name="Q3 Whitepaper form",
    privacy_policy_url="https://example.com/privacy",
    platform_specific_data={
        "adAccountId": "517258773",
        "headline": "Get the whitepaper",
        "description": "Fill in your details and we will send it over.",
        "state": "PUBLISHED",
        "questions": [
            {"kind": "text", "name": "company", "question": "Company name", "maxResponseLength": 120},
            {"kind": "multipleChoice", "name": "size", "question": "Team size",
             "choices": [{"id": 1, "text": "1 to 10"}, {"id": 2, "text": "11 to 50"}]},
        ],
        "consents": [{"description": "I agree to be contacted", "required": True}],
    },
)

print(created["form"]["id"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/lead-forms" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "name": "Q3 Whitepaper form",
    "privacyPolicyUrl": "https://example.com/privacy",
    "platformSpecificData": {
      "adAccountId": "517258773",
      "headline": "Get the whitepaper",
      "description": "Fill in your details and we will send it over.",
      "state": "PUBLISHED",
      "questions": [
        { "kind": "text", "name": "company", "question": "Company name", "maxResponseLength": 120 },
        { "kind": "multipleChoice", "name": "size", "question": "Team size",
          "choices": [ { "id": 1, "text": "1 to 10" }, { "id": 2, "text": "11 to 50" } ] }
      ],
      "consents": [ { "description": "I agree to be contacted", "required": true } ]
    }
  }'
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "status": "success",
  "form": { "id": "4210986", "name": "Q3 Whitepaper form" }
}
```

Pass `form.id` as `leadGenFormId` on a `goal: "lead_generation"` ad ([Create ads](/platforms/linkedin-ads/create-ads)). The create is not idempotent: a retry creates a second form.

- Question kinds are `text` and `multipleChoice` only. LinkedIn's API does not expose profile-prefilled fields (EMAIL, FIRST_NAME, ...); those exist in Campaign Manager's UI only.
- `state` is `DRAFT` (default) or `PUBLISHED`.
- `consents[]` entries are `{ description, required? }`; Zernio maps them to LinkedIn's wire shape and assigns ids.
- `maxResponseLength` defaults to 300 on LinkedIn's side.

## List, get and archive

- `GET /v1/ads/lead-forms?accountId=&adAccountId=` lists the forms owned by the Company Page: `{ id, name, state, headline, createdTime }`.
- `GET /v1/ads/lead-forms/{formId}?accountId=` returns the full form.
- `DELETE /v1/ads/lead-forms/{formId}?accountId=` archives the form (`state: ARCHIVED`); LinkedIn has no hard delete.

## Retrieve leads

Call `GET /v1/ads/leads` with `accountId` and `adAccountId`, and `formId` to narrow to one form. This is the only lead read LinkedIn has: the per-form `GET /v1/ads/lead-forms/{formId}/leads` and `test-leads` endpoints on the [Meta lead-forms page](/platforms/meta-ads/lead-forms) are Meta-only, because LinkedIn exposes no per-form finder.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: page } = await zernio.leadgen.listLeads({
  query: { accountId: '66b2e19d8c3f5a7e9d0b1c2d', adAccountId: '517258773', formId: created.form.id, limit: 50 }
});

console.log(page.leads[0].fields);
```
</Tab>
<Tab value="Python">
```python
page = client.lead_gen.list_leads(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="517258773",
    form_id=created["form"]["id"],
    limit=50,
)

print(page["leads"][0]["fields"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/leads?accountId=66b2e19d8c3f5a7e9d0b1c2d&adAccountId=517258773&formId=4210986&limit=50" \
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
      "id": "66f1a2b3c4d5e6f7a8b9c0d1",
      "leadgenId": "9021734",
      "formId": "4210986",
      "formName": "Q3 Whitepaper form",
      "campaignId": "810456563",
      "createdTime": "2027-01-06T09:12:44.000Z",
      "fields": { "company": "Acme", "size": "11 to 50" }
    }
  ],
  "pagination": { "hasMore": false, "cursor": null }
}
```

`fields` maps each question `name` to its answer; multiple-choice values are the option labels. `campaignId` is the LinkedIn Campaign, which is `platformAdSetId` on `GET /v1/ads`.

- Leads are fetched live from LinkedIn; there is no LinkedIn lead webhook. Poll on your own schedule.
- LinkedIn retains responses for 90 days. Pull them out regularly; anything older is gone on LinkedIn's side.
- `since` is Unix seconds; `cursor` is the numeric offset from `pagination.cursor`.

## If it fails

A `400` with code `ads_connection_required` on `GET /v1/ads/leads` means the connection predates the `r_marketing_leadgen_automation` scope: LinkedIn's `403` is turned into an actionable reconnect prompt. Form create, list and archive work without the scope; reading responses does not.

```json
{
  "error": "Reading LinkedIn lead responses needs the r_marketing_leadgen_automation permission. Reconnect this LinkedIn account to grant it.",
  "type": "invalid_request_error",
  "code": "ads_connection_required",
  "param": "accountId"
}
```

One pass through the LinkedIn connect flow fixes it ([scopes](/guides/connecting-accounts#scopes)).

## Related

- [Create ads](/platforms/linkedin-ads/create-ads): attach the form with `goal: "lead_generation"` and `leadGenFormId`.
- [Meta Ads lead forms](/platforms/meta-ads/lead-forms): the Meta shape of the same endpoints.
- [Create a lead form](/lead-gen/create-lead-form) and [List submitted leads](/lead-gen/list-leads): every field.

---
