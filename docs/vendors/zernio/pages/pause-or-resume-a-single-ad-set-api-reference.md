# Pause or resume a single ad set API Reference

Ad-set-scoped pause/resume (doesn't touch sibling ad sets). Thin wrapper
over PUT /v1/ads/ad-sets/{adSetId} for callers that only want the
status toggle and prefer a symmetric URL to
/v1/ads/campaigns/{campaignId}/status.

On Meta and LinkedIn this writes the ad set's own on/off switch
(Meta: `configured_status`), whatever delivery status its ads report:
an ad still in review does not block resuming its ad set. The echoed
`status` is the confirmation that it landed. Where the platform has no
ad-set switch (TikTok and others) the toggle is emulated by flipping the
child ads; a call with no actionable ad then writes nothing and returns a
`message` with no `status`.

`updated` / `skipped` describe only the ads whose own stored status
CHANGED alongside the switch, so `updated: 0` is a normal successful
response. See `skippedReasons` for which of the three cases applies
(terminal, already in the target state, or switched on but not yet
delivering).

A campaign created paused needs its campaign resumed as well: pair this
with PUT /v1/ads/campaigns/{campaignId}/status.


## PUT /v1/ads/ad-sets/{adSetId}/status

**Pause or resume a single ad set**

Ad-set-scoped pause/resume (doesn't touch sibling ad sets). Thin wrapper
over PUT /v1/ads/ad-sets/{adSetId} for callers that only want the
status toggle and prefer a symmetric URL to
/v1/ads/campaigns/{campaignId}/status.

On Meta and LinkedIn this writes the ad set's own on/off switch
(Meta: `configured_status`), whatever delivery status its ads report:
an ad still in review does not block resuming its ad set. The echoed
`status` is the confirmation that it landed. Where the platform has no
ad-set switch (TikTok and others) the toggle is emulated by flipping the
child ads; a call with no actionable ad then writes nothing and returns a
`message` with no `status`.

`updated` / `skipped` describe only the ads whose own stored status
CHANGED alongside the switch, so `updated: 0` is a normal successful
response. See `skippedReasons` for which of the three cases applies
(terminal, already in the target state, or switched on but not yet
delivering).

A campaign created paused needs its campaign resumed as well: pair this
with PUT /v1/ads/campaigns/{campaignId}/status.


### Parameters

- **adSetId** (required) in path: Platform ad set ID

### Request Body

- **status** (required) `string`: No description - one of: active, paused
- **platform** (required) `string`: No description - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai

### Responses

#### 200: Ad set status updated

**Response Body:**

- **status** `string`: The status written to the ad set. Absent when nothing was written (see message). - one of: active, paused
- **updated** `integer`: Number of ads whose own stored status changed too. 0 is normal on a resume whose ads are all awaiting the platform.
- **skipped** `integer`: Number of ads whose own status was left as it was
- **skippedReasons** `array[string]`: Why each group of ads was skipped
- **message** `string`: Present only where the platform has no ad-set switch and no child ad was actionable

#### 400: Invalid input

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: Ad set not found

---

---
