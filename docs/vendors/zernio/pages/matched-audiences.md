# Matched Audiences

Create LinkedIn contact-list, company-list, engagement and website retargeting audiences with POST /v1/ads/audiences.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can create the 4 LinkedIn audience types with `POST /v1/ads/audiences` and fill them. You need a `linkedinads` [account](/platforms/linkedin-ads#connect) and the numeric ad account id. `type` selects the audience:

| `type` | Built from |
|---|---|
| [`customer_list`](#contact-list) | Emails you upload (SHA-256 hashed server-side) |
| [`company_list`](#company-list) | Company names, domains or LinkedIn Page URLs |
| [`engagement`](#engagement-retargeting) | People who engaged with your ads, Pages or events |
| [`website_retargeting`](#website-retargeting) | Insight Tag visitors matching URL rules |

## Contact list

Create the empty `customer_list` audience, then upload members with `POST /v1/ads/audiences/{audienceId}/users`. Email only (any `phone` is ignored); values are SHA-256 hashed server-side.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: created } = await zernio.adaudiences.createAdAudience({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: '517258773',
    type: 'customer_list',
    name: 'Webinar attendees'
  }
});

const { data: upload } = await zernio.adaudiences.addUsersToAdAudience({
  path: { audienceId: created.audience.id },
  body: { users: [{ email: 'jane@example.com' }, { email: 'sam@example.com' }] }
});

console.log(upload.numReceived);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

created = client.ad_audiences.create_ad_audience(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="517258773",
    type="customer_list",
    name="Webinar attendees",
)

upload = client.ad_audiences.add_users_to_ad_audience(
    audience_id=created["audience"]["id"],
    users=[{"email": "jane@example.com"}, {"email": "sam@example.com"}],
)

print(upload["numReceived"])
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/audiences" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "accountId": "66b2e19d8c3f5a7e9d0b1c2d", "adAccountId": "517258773", "type": "customer_list", "name": "Webinar attendees" }'

curl -X POST "https://zernio.com/api/v1/ads/audiences/66e5b2c3d4f5a6b7c8d9e0f1/users" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{ "users": [{ "email": "jane@example.com" }, { "email": "sam@example.com" }] }'
```
</Tab>
</Tabs>

Response (`201`) from `POST /v1/ads/audiences`:

```json
{
  "audience": {
    "id": "66e5b2c3d4f5a6b7c8d9e0f1",
    "name": "Webinar attendees",
    "type": "customer_list",
    "platform": "linkedin"
  },
  "message": "Custom audience created"
}
```

Response (`200`) from `POST /v1/ads/audiences/{audienceId}/users`:

```json
{ "message": "Users added to audience", "numReceived": 2 }
```

<Callout type="warn">
Each upload is a full replace, not an append: send the complete member list every time. One request carries at most 10,000 users and a longer array returns `400`, so a LinkedIn contact list tops out at 10,000 members: a second call replaces the first rather than extending it.
</Callout>

## Company list

Targets (or excludes) specific companies, up to 300,000 rows. `companies` is required at creation, and each row needs at least one of `name`, `domain`, `website` or `linkedinPageUrl`. Rows are plain text: LinkedIn matches them against its own company graph, takes up to 48 hours to process the list, and recommends 1,000 or more companies for a usable match rate.

```json
{
  "type": "company_list",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "517258773",
  "name": "Target ICP Q3",
  "companies": [
    { "name": "Microsoft", "domain": "microsoft.com" },
    { "linkedinPageUrl": "https://www.linkedin.com/company/linkedin" }
  ]
}
```

To change the list later, send the complete new list to `POST /v1/ads/audiences/{audienceId}/companies` (a replace, not a merge). The `/users` endpoint rejects company lists with a `422`.

## Engagement retargeting

People who interacted with your ads, Pages or events:

```json
{
  "type": "engagement",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "517258773",
  "name": "Video Q1 viewers",
  "sourceType": "VIDEO_ADS",
  "trigger": "FIRST_QUARTILE",
  "lookbackDays": 90,
  "engagementSources": ["urn:li:sponsoredCampaign:777301293"]
}
```

| Field | Notes |
|---|---|
| `sourceType` | `VIDEO_ADS`, `LEAD_GEN_FORMS`, `ORGANIZATION_PAGES`, `EVENT_PAGES`, `SINGLE_IMAGE_ADS` |
| `trigger` | The action, for example `FIRST_QUARTILE`, `VIEW`, `CTA_CLICK`, `LEAD_FORM_SUBMIT`. LinkedIn validates the pairing with `sourceType`. |
| `lookbackDays` | 30, 60, 90, 180 or 365 |
| `engagementSources[]` | Campaign URNs for ad sources, organization URNs for Pages and events. Max 50. |

This is LinkedIn's `engagement` type. Meta's engagement audiences are a different type (`meta_engagement`) with different fields, documented on [Meta Ads](/platforms/meta-ads/audiences#engagement-audiences). Using either type on the wrong platform's account returns a `422`.

## Website retargeting

Visitors matching URL rules, fed by the LinkedIn Insight Tag:

```json
{
  "type": "website_retargeting",
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "adAccountId": "517258773",
  "name": "Pricing page visitors",
  "matchRules": [
    { "matchType": "STARTS_WITH", "matchValue": "https://zernio.com/pricing" },
    { "matchType": "EXACT", "matchValue": "zernio.com" }
  ]
}
```

`matchType` is `EXACT`, `STARTS_WITH`, `CONTAINS` or `ENDS_WITH`, up to 50 rules. The segment only starts filling once the customer's site has the Insight Tag installed and reporting visits; creating the audience does not require the tag, delivery does. The returned `platformAudienceId` is a real LinkedIn adSegment id, usable downstream (attach to a campaign, seed a lookalike). Retargeting segments appear in `GET /v1/ads/audiences` once LinkedIn finishes building them.

## Manage audiences

`GET /v1/ads/audiences?accountId=&adAccountId=` lists them and `DELETE /v1/ads/audiences/{audienceId}` deletes one. Audience builds are eventually consistent on LinkedIn's side: a members read issued right after an upload can come back empty. Re-read after a minute rather than retrying the upload.

## If it fails

A `422` on `POST /v1/ads/audiences/{audienceId}/users` means the audience is a `company_list`, whose rows go to `/companies`:

```json
{
  "error": "Users can only be added to customer_list audiences. To upload company rows to a company_list audience, use POST /v1/ads/audiences/{audienceId}/companies.",
  "type": "invalid_request_error",
  "code": "invalid_field_value"
}
```

The same status with "Audience has no platform ID. It may not have been created on the platform yet." means LinkedIn has not accepted the audience yet: re-read it and retry once `platformAudienceId` is set.

## Related

- [Create ads](/platforms/linkedin-ads/create-ads): target an audience from a campaign.
- [Create custom audience](/ad-audiences/create-ad-audience): every field per type.
- [Add users to audience](/ad-audiences/add-users-to-ad-audience) and [Replace audience companies](/ad-audiences/replace-ad-audience-companies).

---
