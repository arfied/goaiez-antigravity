# Pause or resume a single ad API Reference

Ad-scoped pause/resume: touches ONLY this ad, never its parent ad set or
campaign (so sibling ads keep running). Thin wrapper over the `status`
field of PUT /v1/ads/{adId}, for callers that want a URL symmetric to
/v1/ads/campaigns/{campaignId}/status and /v1/ads/ad-sets/{adSetId}/status.

`{adId}` accepts the same identifier dialects as GET/PUT /v1/ads/{adId}
(Zernio hex `_id`, Meta numeric `platformAdId`, or the creative's
effective story/media IDs). `platform` is inferred from the ad, so it's
not required in the body. Ads in terminal statuses (rejected, completed,
cancelled) and no-op flips (already in the target state) are skipped.


## PUT /v1/ads/{adId}/status

**Pause or resume a single ad**

Ad-scoped pause/resume: touches ONLY this ad, never its parent ad set or
campaign (so sibling ads keep running). Thin wrapper over the `status`
field of PUT /v1/ads/{adId}, for callers that want a URL symmetric to
/v1/ads/campaigns/{campaignId}/status and /v1/ads/ad-sets/{adSetId}/status.

`{adId}` accepts the same identifier dialects as GET/PUT /v1/ads/{adId}
(Zernio hex `_id`, Meta numeric `platformAdId`, or the creative's
effective story/media IDs). `platform` is inferred from the ad, so it's
not required in the body. Ads in terminal statuses (rejected, completed,
cancelled) and no-op flips (already in the target state) are skipped.


### Parameters

- **adId** (required) in path: Zernio `_id` (hex), Meta `platformAdId` (numeric), or one of the creative's effective story/media IDs.

### Request Body

- **status** (required) `string`: No description - one of: active, paused

### Responses

#### 200: Ad status updated (or skipped when no change was needed)

**Response Body:**

- **updated** `integer`: 1 when the status changed, 0 when skipped
- **skipped** `integer`: 1 when skipped (terminal status or already in target state), else 0
- **message** `string`: Human-readable summary (present only when skipped)

#### 400: Invalid input

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Ad not found

---

---
