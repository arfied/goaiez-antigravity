# Duplicate a campaign API Reference

Duplicates a campaign, including its ad sets, ads, creatives, and
targeting by default (`deepCopy: true`). The copy is created paused
so callers can review before launching.

Per-platform implementation:
- **Meta** uses the native `POST /{campaign-id}/copies` endpoint.
- **TikTok** has no native copy primitive; Zernio walks the source
  graph (`/v2/campaign/get/`, `/v2/adgroup/get/`, `/v2/ad/get/`) and
  recreates each entity via the corresponding `/create/` endpoints,
  carrying over budget / targeting / bid_type / bid_price /
  deep_bid_type / creative fields. Spark Ad linkage (`tiktok_item_id`)
  is preserved.
- **LinkedIn** has no native copy primitive; Zernio walks the source
  CampaignGroup → Campaigns → Creatives and recreates each entity,
  carrying over `type` / `costType` / `unitCost` /
  `optimizationTargetType` / `creativeSelection` / `objectiveType` /
  `format` / `dailyBudget` / `totalBudget` / `targetingCriteria` /
  `runSchedule` and every Creative's `content` object verbatim.
  `statusOption: INHERITED_FROM_SOURCE` is evaluated **per entity**:
  any Group / Campaign / Creative whose source is `ACTIVE` gets its
  clone activated too. Duplicating an ACTIVE campaign with
  `INHERITED_FROM_SOURCE` starts a second front of spend the moment
  the clone activates. The safe default is `PAUSED`.

The new hierarchy is asynchronous to materialize in our DB, and we
trigger sync discovery automatically. Set `syncAfter: false` to
skip and poll `/v1/ads/tree` on your own cadence.

Other platforms return 501 Not Implemented.


## POST /v1/ads/campaigns/{campaignId}/duplicate

**Duplicate a campaign**

Duplicates a campaign, including its ad sets, ads, creatives, and
targeting by default (`deepCopy: true`). The copy is created paused
so callers can review before launching.

Per-platform implementation:
- **Meta** uses the native `POST /{campaign-id}/copies` endpoint.
- **TikTok** has no native copy primitive; Zernio walks the source
  graph (`/v2/campaign/get/`, `/v2/adgroup/get/`, `/v2/ad/get/`) and
  recreates each entity via the corresponding `/create/` endpoints,
  carrying over budget / targeting / bid_type / bid_price /
  deep_bid_type / creative fields. Spark Ad linkage (`tiktok_item_id`)
  is preserved.
- **LinkedIn** has no native copy primitive; Zernio walks the source
  CampaignGroup → Campaigns → Creatives and recreates each entity,
  carrying over `type` / `costType` / `unitCost` /
  `optimizationTargetType` / `creativeSelection` / `objectiveType` /
  `format` / `dailyBudget` / `totalBudget` / `targetingCriteria` /
  `runSchedule` and every Creative's `content` object verbatim.
  `statusOption: INHERITED_FROM_SOURCE` is evaluated **per entity**:
  any Group / Campaign / Creative whose source is `ACTIVE` gets its
  clone activated too. Duplicating an ACTIVE campaign with
  `INHERITED_FROM_SOURCE` starts a second front of spend the moment
  the clone activates. The safe default is `PAUSED`.

The new hierarchy is asynchronous to materialize in our DB, and we
trigger sync discovery automatically. Set `syncAfter: false` to
skip and poll `/v1/ads/tree` on your own cadence.

Other platforms return 501 Not Implemented.


### Parameters

- **Idempotency-Key** (optional) in header: Optional client-generated unique key (e.g. a UUID) that makes retries safe. Same key + same body replays the original response; same key + different body → 422; key still processing → 409. Only 2xx responses are stored, so a request that failed with a 4xx can be retried with a corrected body under the SAME key.
- **campaignId** (required) in path: Source platform campaign ID

### Request Body

- **platform** (required) `string`: No description - one of: facebook, instagram, tiktok, linkedin
- **deepCopy** `boolean`: Copy child ad sets + ads + creatives + targeting
- **statusOption** `string`: ACTIVE = launch the clone immediately (spends the moment LinkedIn approves it). PAUSED = clone stays DRAFT, safe default. INHERITED_FROM_SOURCE = mirror each entity's source status per-entity. Duplicating an ACTIVE campaign this way starts a second front of spend.
 - one of: ACTIVE, PAUSED, INHERITED_FROM_SOURCE
- **startTime** `string`: Reschedule the copied hierarchy's start time
- **endTime** `string`: No description
- **renameStrategy** `string`: No description - one of: DEEP_RENAME, ONLY_TOP_LEVEL_RENAME, NO_RENAME
- **renamePrefix** `string`: No description
- **renameSuffix** `string`: No description
- **syncAfter** `boolean`: Trigger ads discovery on the owning account after the copy succeeds

### Responses

#### 200: Campaign duplicated

**Response Body:**

- **copiedCampaignId** `string`: Platform ID of the new campaign
- **discovery** `string`: No description - one of: triggered, skipped, failed
- **raw** `object`: Platform-native response from the copy endpoint (Meta includes ad_object_ids for child copies)

#### 400: Invalid input

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Source campaign not found

#### 501: Operation not supported on this platform

---

---
