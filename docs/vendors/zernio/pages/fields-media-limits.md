# Fields, Media & Limits

Every platformSpecificData field for a Google Business Profile post, the image requirements, what Google's API does not expose, and common errors with their fixes.

Use this page to look up a `platformSpecificData` field for a Google Business Profile (`googlebusiness`) post, check the image requirements, see what Google's API does not expose, and match an error to its fix. The guides in this section show each field in a request.

## Platform fields

All fields go in `platformSpecificData` on the `googlebusiness` entry of `POST /v1/posts`.

| Field | Type | Default | Description |
|-------|------|---------|-------------|
| `topicType` | `"STANDARD"` \| `"EVENT"` \| `"OFFER"` | `STANDARD` | Post type. `EVENT` requires `event`; `OFFER` requires `offer` and takes an optional `event` for the offer period. See [Event & Offer Posts](/platforms/google-business/events-offers). |
| `event` | \{title, schedule\} | | `title` and a `schedule` with `startDate`, `endDate` and optional `startTime`, `endTime`. Dates are `{ year, month, day }`, times `{ hours, minutes }`; ISO 8601 strings are accepted for all four. Required for `EVENT`. |
| `offer` | \{couponCode?, redeemOnlineUrl?, termsConditions?\} | | Offer details for `OFFER` posts. All three optional. |
| `callToAction` | \{type, url\} | | Button under the post. `type` is `LEARN_MORE`, `BOOK`, `ORDER`, `SHOP`, `SIGN_UP` or `CALL`; `url` is an HTTPS URL. See [add a call-to-action button](/platforms/google-business/posts#step-2-add-a-call-to-action-button). |
| `locationId` | string | selected location | Target location as `locations/{id}`, for accounts that manage several. Ids come from `GET /v1/accounts/{accountId}/gmb-locations`. See [Multi-Location Posting](/platforms/google-business/multi-location). |
| `languageCode` | string | auto-detected | BCP 47 code such as `en` or `de`. Sets metadata only and does not translate the content. |

## Media requirements

A post carries at most 1 image and no video. Files that are not JPEG or PNG are rejected, except WebP, which is converted. An image above the size limit is compressed before it is sent, not rejected.

| Property | Requirement |
|----------|-------------|
| Max images | 1 per post |
| Formats | JPEG, PNG (WebP auto-converted) |
| Max file size | 5 MB (auto-compressed) |
| Min dimensions | 400 x 300 px |
| Recommended | 1200 x 900 px (4:3) |

Google crops images, so a 4:3 image survives the crop best. The URL must be public HTTPS and return the file bytes with no login and no redirect to an HTML page; the [media uploads guide](/guides/media-uploads) has the rules and the upload flow.

## What you cannot do

Google Business Profile's API does not expose:

- Video posts
- Q&A (Google deprecated it in favor of "Ask Maps")
- Per-post analytics (deprecated by Google; use [location-level metrics](/platforms/google-business/analytics))
- DMs and comments

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| "Image not found" | The image URL is not reachable or needs a login. | Use a public HTTPS URL. Open it in an incognito window: the raw file must load. |
| "Invalid image format" | The file is not JPEG or PNG, or is corrupted. | Use JPEG or PNG. GIF is not supported. Re-export the image if it is corrupted. |
| "Image too small" | The image is under 400 x 300 px. | Use at least 400 x 300 px; 1200 x 900 px is the recommended size. |
| Post not appearing on Google | The post is pending review or the location is not verified. | Posts can take 24 to 48 hours to appear. Check the approval status in the Business Profile dashboard and the [verification state](/platforms/google-business/business-profile#verification). |
| Call-to-action button does not work | The `url` is invalid or unreachable. | Use a valid HTTPS URL and avoid shortened URLs. |
| `404` on `POST /v1/posts/{postId}/edit` | The post no longer exists on Google: deleted from the dashboard, or an event or offer past its end date. | Publish a new post. |
| `401` with code `token_invalid` | Google revoked or expired the account's token. | Reconnect the account through `GET /v1/connect/googlebusiness`. |

A `publishNow: true` post that Google rejects returns `207` with `post.status: "failed"` and the message in `platforms[].errorMessage`; [Event & Offer Posts](/platforms/google-business/events-offers#if-it-fails) shows the body. [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Posts & Content Types](/platforms/google-business/posts): the base request, call-to-action buttons and edits.
- [Event & Offer Posts](/platforms/google-business/events-offers): `topicType`, `event` and `offer` in a request.
- [Media uploads](/guides/media-uploads): presigned uploads and media URL rules.
- [Rate limits](/guides/rate-limits): velocity limits and how a platform `429` is handled.
- [Error handling](/guides/error-handling): the error envelope and stable codes.

---
