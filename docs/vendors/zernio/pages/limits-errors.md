# Limits & Errors

Every platformSpecificData field for X, what X's API does not expose, and the errors you will see with their fixes.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

Use this page to look up a `platformSpecificData` field for X (platform value `twitter`), check what X's API does not expose, and match an error message to its fix. The guides in this section show each field in a request; media limits are on [Media & Video](/platforms/twitter/media#media-requirements).

## Platform fields

All fields go in `platformSpecificData` on the X entry of `POST /v1/posts`.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `replyToTweetId` | string | | Id of the post to reply to. In a thread only the first post replies to the target. X accepts replies to your own posts or posts you are mentioned in. Cannot be combined with `replySettings`. See [Replies & Quotes](/platforms/twitter/replies-quotes). |
| `quoteTweetId` | string | | Id or full status URL of the post to quote. Cannot be combined with `mediaItems` or `poll`; in a thread it applies to the first post only. Billed at the standard create rate, unlike a URL pasted into `content`. |
| `replySettings` | `"following"` \| `"mentionedUsers"` \| `"subscribers"` \| `"verified"` | everyone | Who can reply. In a thread it applies to the first post only. Cannot be combined with `replyToTweetId`. |
| `threadItems` | Array\<\{content, mediaItems?\}\> | | The whole thread, root post first. When set, the top-level `content` is stored for display and search only and is not published. See [publish a thread](/platforms/twitter/posts#step-3-publish-a-thread). |
| `poll` | \{options, duration_minutes\} | | 2 to 4 options of up to 25 characters, open for 5 to 10080 minutes. Cannot be combined with `mediaItems`, `threadItems` or `quoteTweetId`. |
| `longVideo` | boolean | `false` | Upload the video with X's `amplify_video` category. Applied only on accounts with a paid X subscription and ignored elsewhere; some accounts also need X's allowlisting. See [Long video uploads](/platforms/twitter/media#long-video-uploads). |
| `geoRestriction` | \{countries\} | | Hide the attached media outside up to 25 uppercase ISO 3166-1 alpha-2 country codes. The text stays visible everywhere. Ignored on text-only posts. |
| `paidPartnership` | boolean | `false` | X labels the post as a paid partnership or paid promotion. Root post only in a thread. Availability depends on your X API access tier. |
| `madeWithAi` | boolean | `false` | X labels the post as containing AI-generated media (not AI-written text). Root post only in a thread. |
| `sensitiveMedia` | \{adultContent?, graphicViolence?, other?\} | | Sensitive-content warning on every attached media item. At least one flag must be `true`. Ignored on text-only posts. |
| `article` | \{title, content_state, mode?, cover?\} | `mode: "publish"` | Long-form X Article, on eligible X Premium+ accounts. Cannot be combined with media, `threadItems`, `poll`, `quoteTweetId`, `replyToTweetId`, `replySettings`, `sensitiveMedia`, `paidPartnership` or `madeWithAi`. See [Publish an Article](/platforms/twitter/posts#publish-an-article). |

`customContent` on the platform entry (outside `platformSpecificData`) replaces `content` for X only, which is how a post that also goes to platforms with higher limits stays under 280 characters.

## What you cannot do

X's API does not expose:

- Spaces
- Posting to Communities
- Pinning a post to the profile
- Cards (configure them with meta tags on the destination URL instead)
- Broadcast DMs

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Tweet text is too long (312 characters). Twitter's limit is 280 characters. Note: URLs count as 23 characters." | The text is over the 280-character limit of a free account. | Shorten the text or set `customContent` on the X entry. URLs count as 23 characters and emojis as 2. |
| "X (Twitter) does not allow duplicate tweets" | The same or very similar text was posted before. | Change the text, even slightly. |
| "Rate limit hit. Please wait 10 minutes before posting again." | X rate-limited the account, so Zernio put it in a cooldown. The cooldown starts at 10 minutes and doubles on each further hit within 24 hours. | Wait it out. Every post scheduled inside the window fails with the same message. |
| "Hourly limit reached (25/25 posts/hour for this account). Will retry automatically when the limit resets." | Zernio's own [velocity limit](/guides/rate-limits#posting-velocity-limits) of 25 posts per hour per account. | Nothing: the post is retried when the hour resets. |
| "Missing tweet.write scope" or "forbidden" | The OAuth token lacks a required scope. | Reconnect the account so the consent screen grants every [scope](/platforms/twitter#oauth-scopes). |
| Token expired | The OAuth access was revoked or expired. | Reconnect the account. Subscribe to the `account.disconnected` webhook to catch this early. |
| `402` with `reason: "twitter_passthrough"` on `GET /v1/connect/twitter` | The team has no card on file, and X bills every API call. | Add a payment method at the `dashboard_url` in the response. See [X API usage](/pricing#x-twitter-api-usage). |

A `publishNow: true` post that X rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`; [Posts & Editing](/platforms/twitter/posts#if-it-fails) shows the body. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Media & Video](/platforms/twitter/media#media-requirements): image, GIF and video limits.
- [Posts & Editing](/platforms/twitter/posts): the base request, threads, Articles and edits.
- [Fields, Geo & Polls](/platforms/twitter/fields-polls): each field above in a request.
- [Rate limits](/guides/rate-limits): velocity limits and how a platform `429` is handled.
- [Error handling](/guides/error-handling): the error envelope and stable codes.

---
