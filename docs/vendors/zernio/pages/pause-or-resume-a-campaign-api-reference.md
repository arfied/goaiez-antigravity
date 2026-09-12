# Pause or resume a campaign API Reference

Writes the campaign's own on/off switch, then lets the platform cascade delivery to its ad sets and ads.
Makes one platform API call, not one per ad.

The switch is always written, whatever delivery status the ads underneath report: an ad still in review
does not block resuming its campaign. The echoed `status` is the confirmation that it landed.

`updated` / `skipped` describe only the ads whose own stored status CHANGED alongside it, so
`updated: 0` is a normal successful response, not a no-op. Ads are skipped when they are in a terminal
status (rejected, completed, cancelled), already in the target state, or switched on but not yet
delivering. The last group keeps its `pending_review` / `error` status until the platform reports what
it became. `skippedReasons` names which case applies.

On Meta this flips the campaign only. An ad set paused in its own right stays paused, so pair this with
PUT /v1/ads/ad-sets/{adSetId}/status when you also need the ad set switched back on.

Google keeps an independent on/off switch at campaign, ad group and ad level and the most restrictive
one wins, so `active` switches the campaign on TOGETHER with the ad groups and ads Zernio tracks under
it, in one mutate. Without that the campaign reads ENABLED while a paused ad group or ad keeps it from
serving. `paused` writes the campaign alone, which already stops delivery and leaves each ad's own
switch as you set it.


## PUT /v1/ads/campaigns/{campaignId}/status

**Pause or resume a campaign**

Writes the campaign's own on/off switch, then lets the platform cascade delivery to its ad sets and ads.
Makes one platform API call, not one per ad.

The switch is always written, whatever delivery status the ads underneath report: an ad still in review
does not block resuming its campaign. The echoed `status` is the confirmation that it landed.

`updated` / `skipped` describe only the ads whose own stored status CHANGED alongside it, so
`updated: 0` is a normal successful response, not a no-op. Ads are skipped when they are in a terminal
status (rejected, completed, cancelled), already in the target state, or switched on but not yet
delivering. The last group keeps its `pending_review` / `error` status until the platform reports what
it became. `skippedReasons` names which case applies.

On Meta this flips the campaign only. An ad set paused in its own right stays paused, so pair this with
PUT /v1/ads/ad-sets/{adSetId}/status when you also need the ad set switched back on.

Google keeps an independent on/off switch at campaign, ad group and ad level and the most restrictive
one wins, so `active` switches the campaign on TOGETHER with the ad groups and ads Zernio tracks under
it, in one mutate. Without that the campaign reads ENABLED while a paused ad group or ad keeps it from
serving. `paused` writes the campaign alone, which already stops delivery and leaves each ad's own
switch as you set it.


### Parameters

- **campaignId** (required) in path: Platform campaign ID

### Request Body

- **status** (required) `string`: No description - one of: active, paused
- **platform** (required) `string`: No description - one of: facebook, instagram, tiktok, linkedin, pinterest, google, twitter, openai

### Responses

#### 200: Campaign status updated

**Response Body:**

- **status** `string`: The status written to the campaign - one of: active, paused
- **updated** `integer`: Number of ads whose own stored status changed too. 0 is normal on a resume whose ads are all awaiting the platform.
- **skipped** `integer`: Number of ads whose own status was left as it was
- **skippedReasons** `array[string]`: Why each group of ads was skipped

#### 400: Invalid input or campaign spans multiple accounts

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Returned with code `ads_allowance_exceeded` when the team has no payment method on file and has reached the 500 free live ads: add a card to resume.

#### 404: No ads found for this campaign

---

---
