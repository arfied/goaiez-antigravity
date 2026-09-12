# Ad Library

Search Meta's public ad archive with GET /v1/ads/library, and know which ads it actually covers before you build a competitor screen.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Search the ads other advertisers are running with `GET /v1/ads/library?platform=meta`, which queries Meta's public [Ad Library](https://www.facebook.com/ads/library) (`/ads_archive`). Zernio searches it under its own Meta developer access, so there is nothing to connect: any API key with ads access works. Passing a connected `facebook`, `instagram` or `metaads` account as `accountId` selects Meta the same way. The [LinkedIn archive](/platforms/linkedin-ads/ad-library) runs on the same endpoint with a connected LinkedIn account.

<Callout type="info">
**Needs a payment method on file.** Searches are not charged, but the archive quota is shared across every Zernio customer, so the billing owner must have a card on file or a legacy paid plan (AppSumo counts as free). Otherwise the call returns `403 payment_required`; add a card on the [billing page](https://zernio.com/dashboard/billing) and retry.
</Callout>

## Search the archive

Send `q`, or `pageIds` to list everything one advertiser runs. Rows come back in Meta's raw `ArchivedAd` shape under `data`, and `paging.after` is Meta's cursor.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: archive } = await zernio.adlibrary.searchAdLibrary({
  query: {
    platform: 'meta',
    q: 'espresso machine',
    countries: 'ES,FR',
    status: 'ACTIVE',
    platforms: 'INSTAGRAM',
    limit: 25
  }
});

for (const ad of archive.data) console.log(ad.page_name, ad.ad_snapshot_url);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

archive = client.ad_library.search_ad_library(
    platform="meta",
    q="espresso machine",
    countries="ES,FR",
    status="ACTIVE",
    platforms="INSTAGRAM",
    limit=25,
)

for ad in archive["data"]:
    print(ad["page_name"], ad["ad_snapshot_url"])
```
</Tab>
<Tab value="curl">
```bash
curl -G "https://zernio.com/api/v1/ads/library" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  --data-urlencode "platform=meta" \
  --data-urlencode "q=espresso machine" \
  --data-urlencode "countries=ES,FR" \
  --data-urlencode "status=ACTIVE" \
  --data-urlencode "platforms=INSTAGRAM" \
  --data-urlencode "limit=25"
```
</Tab>
</Tabs>

Response (`200`), one row:

```json
{
  "platform": "meta",
  "data": [
    {
      "id": "1287654321098765",
      "page_name": "Caffe Nord",
      "ad_creative_bodies": ["Pull a better shot at home."],
      "ad_delivery_start_time": "2027-01-18",
      "ad_snapshot_url": "https://www.facebook.com/ads/archive/render_ad/?id=1287654321098765&access_token=..."
    }
  ],
  "paging": { "after": "QVFIUm..." }
}
```

`ad_snapshot_url` renders the full creative at its original quality. Meta's terms allow downloading an individual ad's creative for analysis only.

## What the archive covers

Set expectations here before you build a competitor screen:

| Ads | Searchable |
|---|---|
| Political and social-issue ads | Worldwide, 7 years back |
| Every other ad | Only if it was delivered to the EU or UK, for 1 year after it ran |

A US-only commercial advertiser is invisible whatever you pass in `countries`. EU and UK ads carry the DSA transparency fields: `eu_total_reach`, `beneficiary_payers`, `target_ages`, `target_gender`, `target_locations`, `age_country_gender_reach_breakdown` and `total_reach_by_location`.

`spend`, `impressions`, `demographic_distribution`, `delivery_by_region`, `estimated_audience_size`, `bylines` and `currency` exist on political ads only. They are outside the default projection, so add them with `fields` when you search with `adType=POLITICAL_AND_ISSUE_ADS`.

## Parameters

| Parameter | Meaning |
|---|---|
| `q` | Keyword. Meta does not translate it, so write it in the language of the ads you want. Required unless `pageIds` is given. |
| `pageIds` | Up to 10 Facebook Page ids, comma-separated. |
| `countries` | ISO 3166-1 alpha-2 codes the ads reached, comma-separated. Defaults to `ALL`. |
| `adType` | `ALL` (default), `POLITICAL_AND_ISSUE_ADS`, `HOUSING_ADS`, `EMPLOYMENT_ADS`, `FINANCIAL_PRODUCTS_AND_SERVICES_ADS`. |
| `status` | `ACTIVE` (default, eligible for delivery right now), `INACTIVE`, `ALL`. |
| `platforms` | `FACEBOOK`, `INSTAGRAM`, `AUDIENCE_NETWORK`, `MESSENGER`, `WHATSAPP`, `OCULUS`, `THREADS`, `STREAMING_SERVICES`. |
| `mediaType` | `ALL`, `IMAGE`, `VIDEO`, `MEME`, `NONE`. |
| `languages` | ISO 639-1 codes of the ad text. |
| `searchType` | `KEYWORD_UNORDERED` (default) or `KEYWORD_EXACT_PHRASE`, which matches a phrase instead of the words in any order. Comma-separate phrases to match all of them. |
| `since`, `until` | Delivery date window, `YYYY-MM-DD`. |
| `fields` | Raw Graph projection override. |
| `limit`, `after` | Rows per page, and `paging.after` from the previous page. |

## Common errors

Every customer's Meta searches run under one Zernio quota, so a `429` means Meta asked Zernio to slow down: wait a minute and retry. If Meta withdraws Zernio's archive access altogether:

```json
{
  "error": "Meta's Ad Library is unavailable",
  "type": "platform_error",
  "code": "PLATFORM_DISABLED"
}
```

A `503` with that code is not something your side can fix; retry later. LinkedIn searches keep working through it, because they run on your own connected account. A `400` names a parameter the chosen archive does not support.

## Related

- [LinkedIn Ad Library](/platforms/linkedin-ads/ad-library): the other archive this endpoint searches.
- [Creatives](/platforms/meta-ads/creatives): building the ads you found equivalents of.
- [Reference](/platforms/meta-ads/reference#common-errors): the Meta codes worth recognizing.
- [Search ad library](/ad-library/search-ad-library): every parameter.

---
