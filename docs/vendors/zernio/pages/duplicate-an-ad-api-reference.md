# Duplicate an ad API Reference

Duplicates a single ad via Meta's native `POST /{ad-id}/copies`. The copy is created
paused. `adSetId` retargets the copy into another ad set; omitted = the source's own ad
set. Accepts the Zernio ad id or the platform ad id. Sync discovery is triggered
automatically (`syncAfter: false` to skip). Creative settings returned by Meta,
including explicit promotion metadata and creativeFeatures, are preserved when the
native copy requires a creative rebuild. Metadata Meta does not return cannot be recovered.

## POST /v1/ads/{adId}/duplicate

**Duplicate an ad**

Duplicates a single ad via Meta's native `POST /{ad-id}/copies`. The copy is created
paused. `adSetId` retargets the copy into another ad set; omitted = the source's own ad
set. Accepts the Zernio ad id or the platform ad id. Sync discovery is triggered
automatically (`syncAfter: false` to skip). Creative settings returned by Meta,
including explicit promotion metadata and creativeFeatures, are preserved when the
native copy requires a creative rebuild. Metadata Meta does not return cannot be recovered.

### Parameters

- **Idempotency-Key** (optional) in header: Optional client-generated unique key (e.g. a UUID) that makes retries safe. Same key + same body replays the original response; same key + different body → 422; key still processing → 409. Only 2xx responses are stored, so a request that failed with a 4xx can be retried with a corrected body under the SAME key.
- **adId** (required) in path: Zernio ad ID or platform ad ID

### Request Body

- **adSetId** `string`: Destination platform ad set id (defaults to the source's ad set)
- **statusOption** `string`: No description - one of: ACTIVE, PAUSED, INHERITED_FROM_SOURCE
- **renameStrategy** `string`: No description - one of: DEEP_RENAME, ONLY_TOP_LEVEL_RENAME, NO_RENAME
- **renamePrefix** `string`: No description
- **renameSuffix** `string`: No description
- **syncAfter** `boolean`: No description

### Responses

#### 200: Ad duplicated

**Response Body:**

- **copiedAdId** `string`: Platform ID of the new ad
- **discovery** `string`: No description - one of: triggered, skipped, failed
- **raw** `object`: No description

#### 400: Invalid input

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Ad not found

#### 501: Only supported on Meta (facebook/instagram)

---

---
