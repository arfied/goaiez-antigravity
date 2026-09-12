# Get ad tracking tags API Reference

Unified read of the platform's native click-URL tracking params.
- Meta (facebook/instagram): the creative's `url_tags` (and template_url_spec).
- Google (googleads): the campaign's `trackingUrlTemplate` + `finalUrlSuffix`.
  Subject to the Google Ads API access-tier daily quota; bulk audits need Standard access.
- LinkedIn (linkedinads): the campaign's Dynamic UTM `dynamicValueParameters` + `customValueParameters`.
Returns 405 for platforms without a click-URL tracking surface (TikTok, X, Pinterest).

**Not pixels.** Despite the shared path segment, this endpoint has nothing to do with
measurement tags. For an ad account's pixels use
`GET /v1/accounts/{accountId}/tracking-tags?adAccountId=act_...` (Meta Pixels, with `kind`
and `ownerAdAccountId`) or `GET /v1/accounts/{accountId}/conversion-destinations`.


## GET /v1/ads/{adId}/tracking-tags

**Get ad tracking tags**

Unified read of the platform's native click-URL tracking params.
- Meta (facebook/instagram): the creative's `url_tags` (and template_url_spec).
- Google (googleads): the campaign's `trackingUrlTemplate` + `finalUrlSuffix`.
  Subject to the Google Ads API access-tier daily quota; bulk audits need Standard access.
- LinkedIn (linkedinads): the campaign's Dynamic UTM `dynamicValueParameters` + `customValueParameters`.
Returns 405 for platforms without a click-URL tracking surface (TikTok, X, Pinterest).

**Not pixels.** Despite the shared path segment, this endpoint has nothing to do with
measurement tags. For an ad account's pixels use
`GET /v1/accounts/{accountId}/tracking-tags?adAccountId=act_...` (Meta Pixels, with `kind`
and `ownerAdAccountId`) or `GET /v1/accounts/{accountId}/conversion-destinations`.


### Parameters

- **adId** (required) in path: Ad id (hex _id, platformAdId, or effective story/media id).

### Responses

#### 200: Tracking tags for the ad's platform (shape varies by platform).

**Response Body:**

- **platform** `string`: No description
- **level** `string`: No description - one of: creative, campaign
- **urlTags** `string,null`: Meta: &-joined click-URL params.
- **templateUrlSpec** `object,null`: Meta: third-party click-tracking template (Dynamic Ads).
- **trackingUrlTemplate** `string,null`: Google.
- **finalUrlSuffix** `string,null`: Google.
- **dynamicValueParameters** `object,null`: LinkedIn.
- **customValueParameters** `object,null`: LinkedIn.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Ad not found

#### 405: Platform has no click-URL tracking surface

---

## PATCH /v1/ads/{adId}/tracking-tags

**Set ad tracking tags**

Unified update. Send only the fields for the ad's platform:
- Meta: `urlTags` (array of {key,value}). Meta creatives are immutable, so this rebuilds the
  creative and repoints the ad. By DEFAULT we PRESERVE the existing creative verbatim
  (re-post its object_story_spec + the new url_tags, reusing the image), so you send `urlTags`
  ALONE, with no need to read back headline/body/CTA. `creative` (headline, body, callToAction,
  linkUrl, imageUrl) is OPTIONAL and only needed to rebuild explicitly, or for SHARE / page-post
  / dark / asset_feed creatives whose object_story_spec Meta strips (those return 422 asking for
  `creative`).
- Google: `trackingUrlTemplate` and/or `finalUrlSuffix` (full template strings; account quota applies).
- LinkedIn: `dynamicValueParameters` and/or `customValueParameters` (campaign-level Dynamic UTM).


### Parameters

- **adId** (required) in path: No description

### Request Body

- **urlTags** `array`: Meta only. Click-URL params appended to a freshly-rebuilt creative. Meta dynamic macros ({{ad.id}}, {{campaign.id}}, {{placement}}, ...) are sent through unescaped so Meta expands them; every other character is percent-encoded.
- **creative** `object`: Meta only. OPTIONAL: omit to preserve the existing creative verbatim (default). Provide it only to rebuild the creative explicitly, or for creatives whose object_story_spec Meta strips.
- **trackingUrlTemplate** `string`: Google only. Full tracking template (must contain {lpurl}).
- **finalUrlSuffix** `string`: Google only. Parse-only key=value params.
- **dynamicValueParameters** `object`: LinkedIn only. key -> dynamic value enum (CAMPAIGN_ID, CAMPAIGN_NAME, CREATIVE_ID, ...).
- **customValueParameters** `object`: LinkedIn only. key -> static value.

### Responses

#### 200: The tags as they now stand, in the same shape the GET on this path returns:
`platform` plus the fields that platform supports. Meta returns `level`,
`urlTags` and `templateUrlSpec`; Google returns `trackingUrlTemplate` and
`finalUrlSuffix`. A field the platform does not support is absent.


**Response Body:**

- **platform** `string`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Ad not found

#### 405: Platform has no click-URL tracking surface

#### 422: Meta creative cannot be rebuilt (e.g. placement-customized/asset-feed/dark creative)

#### 502: Meta accepted the request then failed to produce the media (upload session, chunk transfer, processing timeout, or a response with no image hash). Inspect `platformError.reason`.

---

---
