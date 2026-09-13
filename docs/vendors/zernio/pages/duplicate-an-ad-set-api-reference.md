# Duplicate an ad set API Reference

Duplicates an ad set, including its ads and creatives by default (`deepCopy: true`),
via Meta's native `POST /{adset-id}/copies`. The copy is created paused so callers can
review before launching. `campaignId` retargets the copy into another campaign; omitted
= the source's own campaign. The new hierarchy materializes asynchronously, and sync
discovery is triggered automatically (`syncAfter: false` to skip).

## POST /v1/ads/ad-sets/{adSetId}/duplicate

**Duplicate an ad set**

Duplicates an ad set, including its ads and creatives by default (`deepCopy: true`),
via Meta's native `POST /{adset-id}/copies`. The copy is created paused so callers can
review before launching. `campaignId` retargets the copy into another campaign; omitted
= the source's own campaign. The new hierarchy materializes asynchronously, and sync
discovery is triggered automatically (`syncAfter: false` to skip).

### Parameters

- **Idempotency-Key** (optional) in header: Optional client-generated unique key (e.g. a UUID) that makes retries safe. Same key + same body replays the original response; same key + different body → 422; key still processing → 409. Only 2xx responses are stored, so a request that failed with a 4xx can be retried with a corrected body under the SAME key.
- **adSetId** (required) in path: Source platform ad set ID

### Request Body

- **platform** (required) `string`: No description - one of: facebook, instagram
- **campaignId** `string`: Destination platform campaign id (defaults to the source's campaign)
- **deepCopy** `boolean`: Copy child ads + creatives
- **statusOption** `string`: No description - one of: ACTIVE, PAUSED, INHERITED_FROM_SOURCE
- **startTime** `string`: Reschedule the copy's start time
- **endTime** `string`: No description
- **renameStrategy** `string`: No description - one of: DEEP_RENAME, ONLY_TOP_LEVEL_RENAME, NO_RENAME
- **renamePrefix** `string`: No description
- **renameSuffix** `string`: No description
- **syncAfter** `boolean`: No description

### Responses

#### 200: Ad set duplicated

**Response Body:**

- **copiedAdSetId** `string`: Platform ID of the new ad set
- **discovery** `string`: No description - one of: triggered, skipped, failed
- **raw** `object`: Meta's native copy response (includes ad_object_ids for child copies)

#### 400: Invalid input

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Source ad set not found

#### 501: Only supported on Meta (facebook/instagram)

---

---
