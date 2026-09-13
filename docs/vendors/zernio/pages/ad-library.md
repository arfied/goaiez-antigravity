# Ad Library

Search LinkedIn's public ad archive by keyword or advertiser with GET /v1/ads/library, using a connected LinkedIn account's token.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can search LinkedIn's public [Ad Library](https://www.linkedin.com/ad-library/) by keyword or advertiser with `GET /v1/ads/library`. The call runs on the token of a connected `linkedin` or `linkedinads` account, passed as `accountId`; the member token you already hold is enough, because access is granted to the Zernio app, not per scope. Meta is the other way round: `platform=meta` needs no account at all ([Meta Ad Library](/platforms/meta-ads/ad-library)).

<Callout type="info">
**Needs a payment method on file.** Searches are not charged, but the archive quota is shared across every Zernio customer, so the billing owner must have a card on file or a legacy paid plan (AppSumo counts as free). Otherwise the call returns `403 payment_required`; add a card on the [billing page](https://zernio.com/dashboard/billing) and retry.
</Callout>

## Search the archive

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: page } = await zernio.adlibrary.searchAdLibrary({
  query: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    advertiser: 'Microsoft',
    countries: 'DE',
    since: '2027-01-01',
    limit: 25
  }
});

for (const ad of page.data) {
  console.log(ad.details.advertiser.advertiserName, ad.details.type, ad.adUrl);
}
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

page = client.ad_library.search_ad_library(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    advertiser="Microsoft",
    countries="DE",
    since="2027-01-01",
    limit=25,
)

for ad in page["data"]:
    print(ad["details"]["advertiser"]["advertiserName"], ad["details"]["type"], ad["adUrl"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/library?accountId=66b2e19d8c3f5a7e9d0b1c2d&advertiser=Microsoft&countries=DE&since=2027-01-01&limit=25" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`), rows in LinkedIn's raw shape under `data`:

```json
{
  "platform": "linkedin",
  "data": [
    {
      "isRestricted": false,
      "adUrl": "https://www.linkedin.com/ad-library/detail/1521996423",
      "details": {
        "type": "SPONSORED_STATUS_UPDATE",
        "advertiser": {
          "advertiserName": "Microsoft",
          "advertiserUrl": "https://www.linkedin.com/company/1035"
        },
        "adStatistics": {
          "firstImpressionAt": 1798848000000,
          "latestImpressionAt": 1799452800000,
          "totalImpressions": { "from": 5000, "to": 10000 },
          "impressionsDistributionByCountry": []
        },
        "adTargeting": [
          { "facetName": "Location", "includedSegments": ["Deutschland"], "excludedSegments": [] }
        ]
      }
    }
  ],
  "paging": { "after": "25", "total": 27147 }
}
```

`paging.after` is the next offset (`null` on the last page) and `paging.total` the number of matching ads.

## What is in the archive

Every ad served on LinkedIn after June 1 2023, worldwide, kept for one year after its last impression. New ads show up 24 to 48 hours after their first impression.

Each row carries `adUrl` (the public detail page with the creative), `isRestricted`, and `details` with the ad `type` (`SPONSORED_STATUS_UPDATE`, `SPONSORED_VIDEO`, ...) and the `advertiser` (`advertiserName`, `advertiserUrl`, and the disclosed `adPayer` when it differs).

Ads delivered to the EU also carry the DSA transparency data: `adStatistics` (`firstImpressionAt`, `latestImpressionAt`, `totalImpressions` as a `from` and `to` range, `impressionsDistributionByCountry`) and `adTargeting`, one entry per facet (`Language`, `Location`, `Company`, `Job`, ...) with the included and excluded segments. Ads that never reached the EU have an empty `adTargeting` and no `adStatistics`.

## Search parameters

Every search needs `q`, `advertiser` or both; sending neither returns a `400` on `q`.

| Param | Notes |
|---|---|
| `q` | Keyword search over the ad text. |
| `advertiser` | Advertiser (Page) name. Combine with `q` to narrow one advertiser's ads. |
| `countries` | ISO alpha-2 codes the ads reached, comma-separated. Omit to search every market (`ALL` is Meta-only and rejected here). |
| `since`, `until` | Delivery window, `YYYY-MM-DD`. Either bound alone is fine; Zernio fills the other one. |
| `limit`, `after` | At most 25 rows per page (LinkedIn's cap; more is a `400`). Pass `paging.after` back for the next page. |

The Meta-only filters (`pageIds`, `adType`, `status`, `platforms`, `mediaType`, `languages`, `searchType`, `fields`) return a `400` naming the param on a LinkedIn account.

## If it fails

A `403` with code `feature_not_available` is the one a first-time caller hits: LinkedIn refused Ad Library access for that token, because the archive is granted to the app, not to a scope. The `error` carries LinkedIn's own wording when it sends one:

```json
{
  "error": "LinkedIn refused Ad Library access for this token.",
  "type": "permission_error",
  "code": "feature_not_available",
  "platform": "linkedin"
}
```

A `403` with code `linkedin_reconnect_required` is the other one: LinkedIn rejected the token itself, so reconnect the account and retry. Zernio checks `limit`, `countries`, `after` and the `q`-or-`advertiser` requirement before it calls LinkedIn, so those `400`s carry Zernio's own message and name the param that failed, for example "LinkedIn Ad Library returns at most 25 ads per page." on `limit`. Only a `400` LinkedIn itself raises, such as an unrecognised country code, carries LinkedIn's wording.

## Related

- [Meta Ad Library](/platforms/meta-ads/ad-library): the same endpoint with `platform=meta`.
- [Search the public Ad Library](/ad-library/search-ad-library): every parameter.
- [Connecting accounts](/guides/connecting-accounts): connect the LinkedIn account whose token searches.

---
