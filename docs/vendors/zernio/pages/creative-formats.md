# Creative Formats

Select carousel, document, event, text, spotlight, follower, jobs, conversation and thought-leader ads with one key inside platformSpecificData.

Every LinkedIn format goes through the same `POST /v1/ads/create` described on [Create ads](/platforms/linkedin-ads/create-ads#create-a-single-image-or-video-ad), selected by one key inside `platformSpecificData`. Formats are mutually exclusive with each other and with the top-level `imageUrl` and `video`. Each sample below replaces only `platformSpecificData` (and `goal` where noted) in that request.

`POST /v1/ads/create` takes 6 goals on LinkedIn. The 4 base goals in the table are `engagement`, `traffic`, `awareness` and `lead_generation` (which needs `leadGenFormId`). The other 2 are format-bound: `video_views` needs the top-level `video`, which every format below replaces, and `job_applicants` needs the `jobs` creative. For `conversions`, or to promote a post that already exists, use [boost](/platforms/linkedin-ads/boost); any other `goal` returns a `422` naming the supported set.

| Format | `platformSpecificData` key | Goals | Notes |
|---|---|---|---|
| [Carousel](#carousel) | `carousel` | the 4 base goals | 2 to 10 cards |
| [Document](#document) | `document` | the 4 base goals | PDF, PPT or DOC up to 100 MB |
| [Event](#event) | `event` | the 4 base goals | promotes an existing LinkedIn Event |
| [Text ad](#text-ad) | `textAd` | the 4 base goals | right-rail classic, no post |
| [Spotlight](#spotlight-dynamic) | `spotlight` | `traffic`, `awareness` | dynamic, personalized per viewer |
| [Follower](#follower-dynamic) | `follower` | `engagement`, `awareness` | dynamic, drives Page follows |
| [Jobs](#jobs-dynamic) | `jobs` | `job_applicants` only | pulls the Page's active job postings |
| [Conversation](#conversation) | `conversation` | the 4 base goals | message tree in LinkedIn messaging |
| [Thought-leader](#thought-leader) | `thoughtLeader` | the 4 base goals | sponsors an existing post under your campaign |
| [Article and newsletter](#article-and-newsletter) | none (top-level `imageUrl` + `linkUrl`) | the 4 base goals | the default link-ad shape |

## Carousel

2 to 10 cards. Every card needs a `landingUrl`; the ad-level `linkUrl` backfills cards that omit it, and a card with neither is a `400`.

```json
{ "platformSpecificData": { "carousel": { "cards": [
  { "imageUrl": "https://cdn.example.com/card-1.jpg", "headline": "Card 1", "landingUrl": "https://zernio.com" },
  { "imageUrl": "https://cdn.example.com/card-2.jpg", "landingUrl": "https://zernio.com/pricing" }
]}}}
```

## Document

PDF, PPT or DOC up to 100 MB, uploaded to LinkedIn under the Company Page and rendered as a swipeable document in the feed.

```json
{ "platformSpecificData": { "document": { "url": "https://cdn.example.com/whitepaper.pdf", "title": "Q3 Whitepaper" } } }
```

## Event

Promotes an existing LinkedIn Event. No headline is needed; the Event carries its own presentation.

```json
{ "platformSpecificData": { "event": { "urn": "urn:li:event:7654321" } } }
```

## Text ad

The right-rail classic, with no post behind it. `headline` is at most 25 characters and `description` at most 75. `imageUrl` is an optional 100 x 100 logo. Manual bidding is not required: LinkedIn accepts CPC or CPM without `unitCost` here.

```json
{ "platformSpecificData": { "textAd": {
  "headline": "Try Zernio",
  "description": "Schedule everywhere from one API",
  "landingUrl": "https://zernio.com",
  "imageUrl": "https://cdn.example.com/logo-100x100.png"
}}}
```

## Spotlight (dynamic)

A dynamic ad personalized with the viewer's own photo and name. Goals: `traffic`, `awareness`.

```json
{
  "goal": "traffic",
  "platformSpecificData": { "spotlight": {
    "headline": "See our platform",
    "description": "One API for every network",
    "callToAction": "Visit us",
    "landingUrl": "https://zernio.com",
    "logoUrl": "https://cdn.example.com/logo.png",
    "organizationName": "Zernio"
  }}
}
```

`logoUrl` and `organizationName` default from the Company Page, but some Pages fail LinkedIn's identity fallback and the create returns a `404`. Passing both explicitly always works.

## Follower (dynamic)

Drives Page follows. Goals: `engagement`, `awareness`. `headline` and `description` each take exactly one of `preApproved` (a LinkedIn preset id, not reviewed) or `custom` (free text, reviewed by LinkedIn). `callToAction` uses LinkedIn's `VISIT_ORGANIZATION_*` enum.

```json
{
  "goal": "engagement",
  "platformSpecificData": { "follower": {
    "headline": { "custom": "Follow us for updates" },
    "description": { "preApproved": "GET_LATEST_JOBS_AND_INDUSTRY_NEWS" },
    "callToAction": "VISIT_ORGANIZATION_COMPANY_PAGE"
  }}
}
```

## Jobs (dynamic)

Uses the LinkedIn-only goal `job_applicants`. The ad renders the Company Page's active job postings, so the Page must have some; a Page without live postings fails creative creation on LinkedIn's side.

```json
{
  "goal": "job_applicants",
  "platformSpecificData": { "jobs": {
    "headline": { "custom": "Ready for your next role" },
    "buttonLabel": { "preApproved": "CAREERS_AT_COMPANY" }
  }}
}
```

## Conversation

A clickable message tree delivered in LinkedIn messaging: up to 25 message nodes, 5 buttons per node, 500 characters of message text and 35 characters of button text. Each button takes exactly one of `nextMessageId` or `url`. Zernio validates the graph (unknown ids, cycles, an unreachable `firstMessageId`) before anything reaches LinkedIn.

```json
{ "platformSpecificData": { "conversation": {
  "subject": "Chat with us",
  "firstMessageId": "intro",
  "messages": [
    { "id": "intro", "text": "Hi there.", "buttons": [
      { "text": "Tell me more", "nextMessageId": "more" },
      { "text": "Visit site", "url": "https://zernio.com" }
    ]},
    { "id": "more", "text": "Details here." }
  ]
}}}
```

<Callout type="warn">
The sender must be approved for the ad account in Campaign Manager ("Manage message ad senders" in account settings), organization senders included. An unapproved sender fails with `SINMAIL_SENDER_NOT_APPROVED`.
</Callout>

## Thought-leader

Attaches an existing LinkedIn post (a share or ugcPost authored by the Company Page running the ads) as a sponsored creative under a campaign you control.

```json
{ "platformSpecificData": { "thoughtLeader": {
  "postUrn": "urn:li:ugcPost:7482739454772752385"
}}}
```

- `postUrn` is `urn:li:share:N` or `urn:li:ugcPost:N`. Get it from "Copy link to post" on the target post: the copied URL contains `-share-` or `-ugcPost-` plus the numeric id. The address-bar `/feed/update/urn:li:activity:N` URL is not usable, because activity URNs are rejected.
- The post must be authored by an organization (Company Page). LinkedIn's public Marketing API rejects personal-profile posts (Thought Leader Ads proper) with a `422` regardless of sponsorship approval; Campaign Manager creates those through a private API.
- No `headline`, `body`, `imageUrl` or organization is needed; the referenced post carries its own commentary and author.
- [Boost](/platforms/linkedin-ads/boost) provisions its own Campaign Group and Campaign around the post. Thought-leader slots into the campaign `/v1/ads/create` builds, so bidding, targeting and schedule are yours, or attaches as a new creative under an existing Campaign via [`adSetId`](/platforms/linkedin-ads/create-ads#reuse-a-campaign-or-campaign-group-you-already-have), inheriting that campaign's settings.

## Article and newsletter

The default `imageUrl` plus `linkUrl` create publishes LinkedIn's article link ad (`content.article` on the wire), so no extra shape is needed. The same request works for LinkedIn Article and Newsletter URLs; `callToAction` sets the button label.

```json
{
  "imageUrl": "https://cdn.example.com/hero.jpg",
  "linkUrl": "https://blog.example.com/post",
  "headline": "Read the post",
  "body": "Our take on platform engineering."
}
```

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `400` on a carousel | A card carries neither its own `landingUrl` nor an ad-level `linkUrl` | Give every card a destination, or set `linkUrl` on the ad. |
| `400` on `platformSpecificData` | An unknown key, or 2 format keys in one request | Send exactly one format key; the formats are mutually exclusive. |
| `404` on a spotlight or follower create | `logoUrl` and `organizationName` were left to LinkedIn's identity fallback and the Page failed it | Pass both explicitly. |
| `404` or `422` on a follower, jobs or event create | The Page lacks the state LinkedIn resolves at create time: a follow target, active job postings, a real Event | Fix the Page state. The payload is well-formed, and LinkedIn's own message comes back verbatim. |

Follower, jobs and event ads depend on Page state, so their failures come from LinkedIn rather than from the request. The [LinkedIn Ads reference](/platforms/linkedin-ads/reference#common-errors) carries the errors that span formats, including the thought-leader URN `422` and `SINMAIL_SENDER_NOT_APPROVED`. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Create ads](/platforms/linkedin-ads/create-ads): the request every format extends, with its response.
- [Boost a post](/platforms/linkedin-ads/boost): the other way to sponsor an existing post.
- [Media and limits](/platforms/linkedin-ads/reference): file sizes per format.
- [Create standalone ad](/ad-campaigns/create-standalone-ad): every field of `platformSpecificData`.

---
