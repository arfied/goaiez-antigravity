# Media & Limits

LinkedIn Ads media requirements per format, what the API cannot do, and the errors LinkedIn returns most often.

Use this page to look up a LinkedIn Ads media limit, check what LinkedIn's Marketing API does not expose, and match a recurring LinkedIn error to its cause. Files outside the limits below are rejected by LinkedIn, not compressed. Media URLs must be publicly accessible; LinkedIn (via Zernio) fetches and re-hosts them under the Company Page or ad account, and the [media uploads guide](/guides/media-uploads) covers hosting.

## Media requirements

| Type | Format | Max size | Notes |
|------|--------|----------|-------|
| Single image | JPEG, PNG, GIF | 5 MB | 1200 x 627 (1.91:1) recommended |
| Video | MP4 H.264/AAC | 500 MB | 3 seconds to 30 minutes, 75 KB minimum |
| Carousel | JPEG, PNG | 10 MB per card | 2 to 10 cards, 1080 x 1080 |
| Document | PDF, PPT, DOC | 100 MB | rendered as a swipeable document |
| Text ad logo | JPEG, PNG | 2 MB | 100 x 100 |

## What you cannot do

LinkedIn's API does not expose:

- Profile-prefilled Lead Gen Form fields (EMAIL, FIRST_NAME, ...); Campaign Manager's UI only. API forms take `text` and `multipleChoice` questions.
- Lead webhooks. LinkedIn has no lead push, so poll [`GET /v1/ads/leads`](/platforms/linkedin-ads/lead-forms#retrieve-leads) (90-day retention).
- A hard delete of a Lead Gen Form (archive only) or of a conversion rule (soft disable only).
- Appending to a contact-list audience: every upload is a full replace, and one upload carries at most 10,000 users.
- Daily time series on firmographic pivots: LinkedIn aggregates over the whole range (`timeGranularity: ALL`).
- Dynamic UTM parameters on Conversation, Message or Lead Gen Form ads.
- City, ZIP, metro, radius or income targeting: geo stops at country and region, and the professional facets carry the rest ([targeting](/platforms/linkedin-ads/create-ads#target-the-audience)).

LinkedIn accepts the call and refuses the request when:

- The budget is below $10/day, or $100 lifetime on an inactive campaign.
- A conversion event is older than 90 days.
- A personal profile authored the post (Thought Leader Ads proper): the public Marketing API sponsors organization-authored posts only, and member posts return `422`.

## Common errors

LinkedIn's REST errors surface verbatim inside Zernio's `platform_error` envelope ([error handling](/guides/error-handling)). The recurring ones:

| Error | Where | Cause and fix |
|---|---|---|
| `403` on create | Ads from scratch | The member lacks the Direct Sponsored Content Poster or Administrator role on the Page, or the Page is not associated with the ad account. |
| `403` `linkedin_reconnect_required` | Conversions API | The connection predates the `rw_conversions` scope. Reconnect the account. |
| `400` `ads_connection_required` | Lead responses | The connection lacks `r_marketing_leadgen_automation`. Reconnect the account. |
| `422` on reshare or sponsor | Thought-leader, boost | The post URN is an `activity` URN. Use the `share` or `ugcPost` id from "Copy link to post". |
| `SINMAIL_SENDER_NOT_APPROVED` | Conversation ads | The sender is not approved for the ad account in Campaign Manager ("Manage message ad senders"), organization senders included. |
| `422` "The objective type is not supported" | Supply forecast | `objectiveType` is required in practice despite being documented optional. Pass any valid objective. |
| `404` on a dynamic-format creative | Follower, jobs, event ads | LinkedIn could not resolve the Page-side state (follow target, active job postings, a real Event). Fix the Page, not the payload. |
| Campaign quality score rejections, or stuck in review | Any | LinkedIn reviews every ad; a `DRAFT` or review state is normal for the first minutes to hours. |

Two behaviours that look like bugs and are not:

- A segment read right after an upload can come back empty. LinkedIn's audience builds are eventually consistent; re-read after a minute.
- `LinkedIn-Version` deprecates monthly on LinkedIn's side. Zernio pins and rolls the version for you, so you never send it, and version churn is absorbed server-side.

## Related

- [Create ads](/platforms/linkedin-ads/create-ads) and [Creative formats](/platforms/linkedin-ads/creative-formats): where each limit applies.
- [Media uploads](/guides/media-uploads): host images and videos on Zernio.
- [Error handling](/guides/error-handling): the envelope every error uses.

---
