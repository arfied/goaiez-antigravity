# Enums, Limits & Error Codes

Look up the Meta ads enums, media limits, unsupported operations and Meta error subcodes that the rest of this section links to.

Look up the values the other pages point at: media limits, the `callToAction` and `placements` enums, what Meta's API does not let you do, and the Meta error subcodes worth recognizing on sight. Nothing here needs a call of its own.

## Media requirements

| Type | Format | Max size | Notes |
|------|--------|----------|-------|
| Feed image | JPEG, PNG | 30 MB | 1080 x 1080 px or 1200 x 628 px recommended |
| Reels video | MP4, MOV | 4 GB | 9:16 vertical, at most 90 seconds |
| Story | JPEG, PNG, MP4 | 30 MB image, 4 GB video | 9:16 vertical |
| Carousel | JPEG, PNG, MP4 | 30 MB per card | 2 to 10 cards |
| Base64 image upload | JPEG, PNG | 30 MB decoded | Through [`POST /v1/ads/images`](/platforms/meta-ads/creative-library#image-library) |

A media URL must be publicly reachable with no authentication: Meta fetches it from its own infrastructure, so a hotlink-protected CDN comes back as Meta error code `324`.

## Call to action values

`callToAction` on `POST /v1/ads/create`, `POST /v1/ads/boost` and `POST /v1/ads/creatives` takes one of:

| | | | |
|---|---|---|---|
| `LEARN_MORE` | `SHOP_NOW` | `SIGN_UP` | `BOOK_TRAVEL` |
| `CONTACT_US` | `DOWNLOAD` | `GET_OFFER` | `GET_QUOTE` |
| `SUBSCRIBE` | `WATCH_MORE` | `ADD_TO_CART` | `APPLY_NOW` |
| `BOOK_NOW` | `BUY_TICKETS` | `DONATE` | `DONATE_NOW` |
| `GET_DIRECTIONS` | `GET_SHOWTIMES` | `LISTEN_NOW` | `ORDER_NOW` |
| `PLAY_GAME` | `REQUEST_TIME` | `SEE_MENU` | `START_ORDER` |
| `INSTALL_MOBILE_APP` | `USE_APP` | `BUY_NOW` | |

`POST /v1/ads/boost` adds `VIEW_INSTAGRAM_PROFILE`, which no other endpoint accepts; pair it with the Instagram profile URL as `linkUrl`. It also accepts the 3 messaging CTAs, `WHATSAPP_MESSAGE`, `MESSAGE_PAGE` and `INSTAGRAM_MESSAGE` ([messaging boosts](/platforms/meta-ads/boost#messaging-boosts-open-a-chat-instead-of-a-link)), the same values `POST /v1/ads/messaging` uses. `POST /v1/ads/creatives` defaults to `LEARN_MORE`. `CALL_NOW` is not here: [`POST /v1/ads/call`](/platforms/meta-ads/messaging-ads#call-ads) builds the ad set that button requires. The enum also carries `REGISTER`, `JOIN`, `ATTEND`, `REQUEST_DEMO`, `VIEW_QUOTE`, `APPLY` and `SEE_MORE` for [LinkedIn Ads](/platforms/linkedin-ads).

`dynamicCreative.callToActionTypes` takes the same values as an array.

## Placements

The `placements` object restricts delivery to chosen surfaces. Omit it for Meta's automatic placements. It appears as a top-level field on `POST /v1/ads/create` and `POST /v1/ads/rf-predictions`, inside `targeting` on `POST /v1/ads/boost`, and as `rules[].placements` on [`placementAssets`](/platforms/meta-ads/creatives#placement-asset-customization).

| Field | Values |
|-------|--------|
| `publisherPlatforms` | `facebook`, `instagram`, `threads`, `messenger`, `audience_network`, `whatsapp` |
| `facebookPositions` | `feed`, `right_hand_column`, `marketplace`, `video_feeds`, `story`, `search`, `instream_video`, `facebook_reels`, `facebook_reels_overlay`, `profile_feed`, `notification` |
| `instagramPositions` | `stream`, `story`, `explore`, `explore_home`, `reels`, `profile_feed`, `ig_search`, `profile_reels` |
| `messengerPositions` | `messenger_home`, `sponsored_messages`, `story` |
| `audienceNetworkPositions` | `classic`, `rewarded_video` |
| `threadsPositions` | `threads_stream` |
| `whatsappPositions` | `status` |
| `devicePlatforms` | `mobile`, `desktop`. Omit to deliver on both. |

A position field counts only when its parent platform is in `publisherPlatforms`. Zernio validates enum membership; Meta enforces its own co-selection rules on top (`profile_feed` needs `feed`) and its message surfaces verbatim.

## Special ad categories

`specialAdCategories` declares a regulated ad and makes Meta restrict its targeting: `HOUSING`, `EMPLOYMENT`, `CREDIT`, `FINANCIAL_PRODUCTS_SERVICES`, `ISSUES_ELECTIONS_POLITICS`, `ONLINE_GAMBLING_AND_GAMING`. Setting one disables income and ZIP targeting on Meta.

## What you cannot do

Meta's API does not expose:

- Editing a creative's content after create. Creatives are immutable beyond their name, so [swap a new creative onto the ad](/platforms/meta-ads/creatives#swap-the-creative-on-a-live-ad).
- Duplicating more than about 2 objects in one synchronous copy. Meta's `/copies` cap; [duplicate the campaign](/platforms/meta-ads/lifecycle#duplicate-a-campaign) for bigger hierarchies.
- Copying a campaign or ad set whose creative still carries Meta's deprecated `standard_enhancements` bundle. Rebuild it with [`creativeFeatures`](/platforms/meta-ads/creatives#advantage-creative-enhancements); an ad copy drops the field and retries by itself.
- Creating a paused first ad in a Reach and Frequency reservation. Meta requires it `ACTIVE`; delivery stays gated by status and the reserved window.
- Meta's automatic placements on a Reach and Frequency reservation. Send explicit [`placements`](#placements), or omit them and Zernio [falls back to the Facebook feed](/platforms/meta-ads/reach-and-frequency).
- A campaign spend cap of exactly 0. Pass [`spendCap: null`](/platforms/meta-ads/campaigns#campaign-spend-cap) to remove a cap.
- Server-side filtering of the activity log per object. Meta has no per-object filter on that edge, so `objectId` filters the returned page.
- Reading high demand periods at the account level. The Graph edge exists on campaign and ad set nodes only.
- `CALL_NOW` and the messaging buttons through `POST /v1/ads/create`. Use [`POST /v1/ads/call` and `POST /v1/ads/messaging`](/platforms/meta-ads/messaging-ads).
- App and offline Event Match Quality. [Meta exposes web EMQ only](/platforms/meta-ads/capi#event-match-quality).
- Deleting a pixel. There is no Meta API for it; [unshare it](/platforms/meta-ads/pixels#share-a-pixel-with-another-ad-account) or disable it in Events Manager.

## Common errors

Meta's validation errors surface inside Zernio's `platform_error` envelope with the subcode preserved ([error handling](/guides/error-handling)). The ones worth recognizing:

| Meta subcode | Where it appears | Meaning and fix |
|---|---|---|
| `324` | Any create with `imageUrl` | Meta cannot fetch the image (auth wall, hotlink protection). Host it publicly or upload [base64](/platforms/meta-ads/creative-library#image-library). |
| `1815430` | Conversion-goal creates | Missing `promotedObject`. Zernio pre-empts it with a clean `400`; see [conversion campaigns](/platforms/meta-ads/conversion-campaigns). |
| `1815089` | Lead ad creates | The Page has not accepted the Lead Generation terms. Accept them once at `facebook.com/ads/leadgen/tos`. |
| `2446886` | Messaging ads with `destination: "whatsapp"` | The Page is not paired with a verified WhatsApp Business number. Pair it in Meta Business Manager. |
| `2061006` | Call ads | The display link must be a real website; `tel:` is rejected. Pass a website `linkUrl`. |
| `1885099` | Campaign spend cap | A spend cap cannot be zero. Remove it with `spendCap: null`. |
| `1885696` | Reach and Frequency predictions | Automatic placements are rejected on reservations. Pass explicit `placements`. |
| `1885237` | Reach and Frequency predictions | Instagram placements need a linked Instagram professional account. |
| `1487716` | Reach and Frequency creates | The first ad in a reservation cannot be paused. Zernio creates it `ACTIVE` by design. |
| `4834012` and `1815830` | Reach and Frequency creates | Auction-only fields on a reserved campaign (budget sharing, ad set bids). Zernio strips them. |
| `1713151` | Engagement audiences | The source Page, Instagram account or video is not eligible. An eligibility problem, not a wrong `event`. |
| `1870049` | Engagement audiences | Video sources need Meta's legacy rule dialect. Zernio sends it. |
| `1713098` and `1870029` | Website audiences | A raw `rule` in the wrong shape. Use Meta's flexible rule shape. |
| `3858504` | Creatives | `standard_enhancements` is deprecated. Use individual [`creativeFeatures`](/platforms/meta-ads/creatives#advantage-creative-enhancements). |
| `100` | [Flexible insights](/platforms/meta-ads/insights#flexible-queries) | Unknown field, or an invalid field and breakdown combination. Meta's message lists the valid values. |

Two review states look like errors and are not. A successful create returns an ad in `pending_review`: `reviewStatus` (`in_review`, `approved`, `rejected`, `with_issues`) is separate from the delivery `status`, and both sit on every tree node. And a creative rename has read-after-write lag on Meta's side, so a read right after it can return the old name for a few seconds.

## Related

- [Campaigns](/platforms/meta-ads/campaigns): the create request most of these values go on.
- [Creatives](/platforms/meta-ads/creatives): the creative shapes and their media fields.
- [Targeting](/platforms/meta-ads/targeting): where `placements` sits on each endpoint.
- [Error handling](/guides/error-handling): the envelope every `4xx` uses.

---
