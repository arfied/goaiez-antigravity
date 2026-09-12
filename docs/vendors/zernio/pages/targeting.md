# Targeting

Target Meta ads by country, city, region, ZIP, age, gender and interests, and resolve Meta's opaque location and interest ids with GET /v1/ads/targeting/search.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Target by country, city, region, ZIP, metro, age, gender, income, interests, behaviours and custom audiences, and exclude locations and audiences, with flat fields on `POST /v1/ads/create`. Cities, regions and interests are opaque Meta ids: resolve them with `GET /v1/ads/targeting/search` first. `POST /v1/ads/boost`, `POST /v1/ads/messaging` and reach estimates take the same fields inside a `targeting` object.

## Targeting fields

| Field | Type | Meaning |
|-------|------|-------------|
| `ageMin`, `ageMax` | number | 13 to 65. |
| `gender` | string | `all` (default), `male` or `female`. |
| `countries` | string[] | ISO 3166-1 alpha-2 codes. Defaults to `["US"]` when no other geo field is present. |
| `cities` | object[] | `{ key, radius?, distance_unit? }`. `key` comes from the search below; `radius` and `distance_unit` (`kilometer` or `mile`) go together. Meta enforces a minimum radius of about 17 km (10 miles); a smaller one resolves to an empty audience and the ad fails at launch. |
| `regions` | object[] | `{ key }` from a search with `geoType=region`. |
| `zips` | object[] | `{ key }` from a search with `geoType=zip`, for example `US:94304`. |
| `metros` | object[] | `{ key }` from a search with `geoType=metro_area`, for example `DMA:807`. |
| `customLocations` | object[] | `{ latitude, longitude, radius, distanceUnit }` for a point-radius catchment tighter than a city radius. |
| `excludedLocations` | object | Geo to leave out, mirroring the include shape: `countries`, `regions`, `cities` (with `radius` and `distance_unit`), `zips`, `places`, `neighborhoods` and `customLocations`. |
| `interests` | object[] | `{ id, name }` from a search with `dimension=interest`. |
| `behaviors` | object[] | `{ id, name }` from a search with `dimension=behavior`. |
| `workPositions`, `workEmployers`, `workIndustries` | object[] | `{ id, name }` from a search with `dimension=workPosition`, `workEmployer` or `workIndustry`. Meta only, and not interchangeable with LinkedIn's job title and industry URNs. |
| `userOs` | string[] | Meta only. Operating systems and version ranges, e.g. `iOS_ver_14.0_and_above` or `Android`. Emitted as `user_os`. |
| `userDevice` | string[] | Meta only. Device models, e.g. `iPhone`. Emitted as `user_device`. |
| `audienceInclude`, `audienceExclude` | string[] | The `platformAudienceId` of a [custom audience or lookalike](/platforms/meta-ads/audiences) to target, or to hold back from the ad. |
| `languages` | string[] | A bare code (`en`) targets every regional variant; a region-qualified code (`en_GB`) targets one. |
| `incomeTier` | string | `top_5`, `top_10`, `top_10_25` or `top_25_50`, a household-income band by ZIP percentile. Meta rejects it on a housing, employment or credit ad, which is what `specialAdCategories` declares. |
| `placements` | object | Manual placements; omit for Meta's automatic placements. Values are in the [reference](/platforms/meta-ads/reference#placements). |
| `savedTargetingId` | string | A `saved_targeting` [audience](/platforms/meta-ads/audiences#saved-targeting) whose spec is the base; inline fields merge on top. |
| `rawTargeting` | object | A Meta-native targeting spec (`geo_locations`, `flexible_spec`, `excluded_custom_audiences`), for fields the flat surface does not expose. |
| `advantageAudience` | number | Meta's Advantage+ audience expansion: `0` (default) keeps targeting strict, `1` lets Meta expand beyond the fields you sent. Meta requires the field on every ad set create, so Zernio sends `0` for you, except when `rawTargeting` is present: then send `advantageAudience` yourself or put `targeting_automation` in the raw spec. |

On `POST /v1/ads/create` these sit at the top level. On `POST /v1/ads/boost` they live inside `targeting`, and cities there spell the unit `distanceUnit`. Do not combine `cities` with the `countries` that contain them: Meta returns a "locations overlap" error because the city is already inside the country. Drop the country, or scope `countries` to a different one.

Portable fields (`countries`, `ageMin`/`ageMax`, `gender`, `incomeTier`, `languages`) carry across platforms unchanged; every other field is meaningful only for the platform it was built against. A field a platform cannot honour is rejected at create time with `INVALID_FIELD_VALUE` naming it, not silently dropped.

## Look up city and region keys

Call `GET /v1/ads/targeting/search` with `accountId`, `q`, `dimension=geo` and `geoType`, then pass the returned `id` as `key`. `countryCode` disambiguates names that exist in several countries. The older `GET /v1/ads/interests` is a deprecated alias of the interest search.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();

const { data: search } = await zernio.adtargeting.searchAdTargeting({
  query: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    q: 'Amsterdam',
    dimension: 'geo',
    geoType: 'city',
    countryCode: 'NL'
  }
});

const cityKey = search.results[0].id;
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()

search = client.ad_targeting.search_ad_targeting(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    q="Amsterdam",
    dimension="geo",
    geo_type="city",
    country_code="NL",
)

city_key = search["results"][0]["id"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/ads/targeting/search?accountId=66b2e19d8c3f5a7e9d0b1c2d&q=Amsterdam&dimension=geo&geoType=city&countryCode=NL" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "results": [
    {
      "id": "2759794",
      "name": "Amsterdam",
      "type": "city",
      "path": ["Netherlands", "North Holland", "Amsterdam"],
      "audienceSize": null
    }
  ]
}
```

Then use the key on the [create request](/platforms/meta-ads/campaigns#create-the-full-tree-in-one-call), replacing `countries`. EU targeting also needs the DSA beneficiary and payer, unless the ad account has defaults set:

```json
{
  "budgetType": "lifetime",
  "endDate": "2027-03-31T23:59:00Z",
  "cities": [{ "key": "2759794", "radius": 25, "distance_unit": "kilometer" }],
  "dsaBeneficiary": "Acme BV",
  "dsaPayor": "Acme BV"
}
```

`geoType` accepts `all`, `country`, `region`, `city` (the default), `subcity`, `neighborhood`, `place`, `zip`, `metro_area` and `geo_market`. `dimension` also resolves `interest`, `behavior`, `income`, the Meta-only `workPosition`, `workEmployer` and `workIndustry`, and `language`, which reads Google's language table and returns nothing on Meta.

## Estimate reach

`POST /v1/ads/targeting/reach-estimate` returns Meta's `delivery_estimate` before you create anything. `accountId`, `adAccountId` and `spec` are all required, and `spec` carries the fields above, nested.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
const { data: estimate } = await zernio.adtargeting.estimateAdReach({
  body: {
    accountId: '66b2e19d8c3f5a7e9d0b1c2d',
    adAccountId: 'act_1234567890',
    spec: {
      countries: ['NL'],
      ageMin: 25,
      ageMax: 54,
      interests: [{ id: '6003107902433', name: 'Interior design' }]
    }
  }
});
```
</Tab>
<Tab value="Python">
```python
estimate = client.ad_targeting.estimate_ad_reach(
    account_id="66b2e19d8c3f5a7e9d0b1c2d",
    ad_account_id="act_1234567890",
    spec={
        "countries": ["NL"],
        "ageMin": 25,
        "ageMax": 54,
        "interests": [{"id": "6003107902433", "name": "Interior design"}],
    },
)
```
</Tab>
<Tab value="curl">
```bash
curl -X POST "https://zernio.com/api/v1/ads/targeting/reach-estimate" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
    "adAccountId": "act_1234567890",
    "spec": {
      "countries": ["NL"],
      "ageMin": 25,
      "ageMax": 54,
      "interests": [{ "id": "6003107902433", "name": "Interior design" }]
    }
  }'
```
</Tab>
</Tabs>

Response (`200`), trimmed:

```json
{
  "available": true,
  "lower": 412000,
  "upper": 585000,
  "estimateReady": true
}
```

`estimateReady: false` means Meta is still computing the estimate, which happens on a brand-new audience; retry shortly. An empty `spec` estimates the platform's broadest audience, and `optimizationGoal` (Meta's own vocabulary, such as `REACH` or `LINK_CLICKS`) narrows the estimate to the goal the ad set will use.

## If it fails

A `400` with `type: "platform_error"` on create is Meta rejecting the geo combination:

```json
{
  "error": "Invalid parameter: Your cities and countries overlap",
  "type": "platform_error",
  "platform": "meta",
  "platformError": { "code": 100 }
}
```

Remove the country that already contains the city. A `404` on the search means the account's platform does not support the requested `dimension`.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the create request these fields go on.
- [Audiences](/platforms/meta-ads/audiences): customer lists, lookalikes and saved targeting.
- [Reference](/platforms/meta-ads/reference#placements): the `placements` values and special ad categories.
- [Search targeting options](/ad-targeting/search-ad-targeting) and [Estimate audience reach](/ad-targeting/estimate-ad-reach): every parameter.

---
