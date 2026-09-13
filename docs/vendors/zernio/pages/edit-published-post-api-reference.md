# Edit published post API Reference

Edit the text of an already-published post. Supported on X, Discord,
Facebook, Reddit, LinkedIn, Telegram, Pinterest, Google Business Profile, YouTube,
and Slack. When a post was published to several accounts on the same platform,
pass `accountId` to pick which account's copy to edit (the first entry is edited
otherwise). Each platform enforces its own rules:

**X**
- Connected X account must have an active X Premium subscription
- Must be within 1 hour of original publish time
- Maximum 5 edits per tweet (enforced by X)
- Threads cannot be edited, only single tweets
- X assigns a NEW post ID on edit, returned as `id`

**Discord**
- No time limit and no premium requirement
- The message ID is unchanged after the edit

**Facebook**
- Graph only permits editing a post that the same app created, so this works on
  posts published through Zernio and is rejected for posts created in Meta
  Business Suite / Composer or by another tool
- Media cannot be swapped, only the message text
- Reactions, comments, and shares are preserved. The post ID is unchanged

**Reddit**
- Self-posts only. A link post has no editable body and is rejected before the write
- Body only. Reddit exposes no API to edit a post title, ever
- The post ID is unchanged

**LinkedIn**
- Text only, no time limit. Media, polls, articles, and reshare targets cannot be
  changed
- Works for member and organization posts published through this API. The post
  keeps its ID and LinkedIn shows an "edited" marker
- Text is limited to 3,000 characters; mentions and hashtags are preserved

**Telegram**
- No time limit; messages published through Zernio are editable indefinitely
- Text posts: edits the message text (up to 4096 characters)
- Media posts: edits the caption only (up to 1024 characters). The media itself
  cannot be swapped
- For albums, the caption shown on the album (its first message) is edited
- The message ID is unchanged

**Pinterest**
- Description only, maximum 800 characters. Media, link, and board cannot be
  changed, and a pin title derived from the old content's first line at publish
  stays as-is
- Pinterest's pin-update endpoint is currently in closed beta; until the app is
  allowlisted by Pinterest, edits are rejected with a "beta feature not yet
  enabled" error
- The pin ID is unchanged

**Google Business Profile**
- Post body (summary) text only. Call-to-action, event/offer fields, and media are
  untouched
- No time limit and no edit limit. The post ID is unchanged
- The post must still exist on Google: a post deleted from the Business Profile
  dashboard, or an event/offer post past its end date, returns a 404

**YouTube**
- `content` replaces the video description only. The title is unchanged, even if
  it was originally derived from the content's first line at publish time
- Title, tags, thumbnail, and privacy edits belong to `POST /v1/posts/{postId}/update-metadata`
- No time window and no edit cap. The video ID is unchanged

**Slack**
- Text only, up to 4,000 characters. Media cannot be swapped, and media posts
  whose share message reference never resolved cannot be edited
- No time limit unless workspace admins restrict message editing
- The message ID is unchanged

Media edits are not supported on any platform. The post record in Zernio is updated
with the new content and an edit-history entry.


## POST /v1/posts/{postId}/edit

**Edit published post**

Edit the text of an already-published post. Supported on X, Discord,
Facebook, Reddit, LinkedIn, Telegram, Pinterest, Google Business Profile, YouTube,
and Slack. When a post was published to several accounts on the same platform,
pass `accountId` to pick which account's copy to edit (the first entry is edited
otherwise). Each platform enforces its own rules:

**X**
- Connected X account must have an active X Premium subscription
- Must be within 1 hour of original publish time
- Maximum 5 edits per tweet (enforced by X)
- Threads cannot be edited, only single tweets
- X assigns a NEW post ID on edit, returned as `id`

**Discord**
- No time limit and no premium requirement
- The message ID is unchanged after the edit

**Facebook**
- Graph only permits editing a post that the same app created, so this works on
  posts published through Zernio and is rejected for posts created in Meta
  Business Suite / Composer or by another tool
- Media cannot be swapped, only the message text
- Reactions, comments, and shares are preserved. The post ID is unchanged

**Reddit**
- Self-posts only. A link post has no editable body and is rejected before the write
- Body only. Reddit exposes no API to edit a post title, ever
- The post ID is unchanged

**LinkedIn**
- Text only, no time limit. Media, polls, articles, and reshare targets cannot be
  changed
- Works for member and organization posts published through this API. The post
  keeps its ID and LinkedIn shows an "edited" marker
- Text is limited to 3,000 characters; mentions and hashtags are preserved

**Telegram**
- No time limit; messages published through Zernio are editable indefinitely
- Text posts: edits the message text (up to 4096 characters)
- Media posts: edits the caption only (up to 1024 characters). The media itself
  cannot be swapped
- For albums, the caption shown on the album (its first message) is edited
- The message ID is unchanged

**Pinterest**
- Description only, maximum 800 characters. Media, link, and board cannot be
  changed, and a pin title derived from the old content's first line at publish
  stays as-is
- Pinterest's pin-update endpoint is currently in closed beta; until the app is
  allowlisted by Pinterest, edits are rejected with a "beta feature not yet
  enabled" error
- The pin ID is unchanged

**Google Business Profile**
- Post body (summary) text only. Call-to-action, event/offer fields, and media are
  untouched
- No time limit and no edit limit. The post ID is unchanged
- The post must still exist on Google: a post deleted from the Business Profile
  dashboard, or an event/offer post past its end date, returns a 404

**YouTube**
- `content` replaces the video description only. The title is unchanged, even if
  it was originally derived from the content's first line at publish time
- Title, tags, thumbnail, and privacy edits belong to `POST /v1/posts/{postId}/update-metadata`
- No time window and no edit cap. The video ID is unchanged

**Slack**
- Text only, up to 4,000 characters. Media cannot be swapped, and media posts
  whose share message reference never resolved cannot be edited
- No time limit unless workspace admins restrict message editing
- The message ID is unchanged

Media edits are not supported on any platform. The post record in Zernio is updated
with the new content and an edit-history entry.


### Parameters

- **postId** (required) in path: No description

### Request Body

- **platform** (required) `string`: The platform to edit the post on. - one of: twitter, discord, facebook, reddit, linkedin, telegram, pinterest, googlebusiness, youtube, slack
- **content** (required) `string`: The new post text content
- **accountId** `string`: Which account's copy of the post to edit when the post was published to several accounts on the same platform; defaults to the first.


### Responses

#### 200: Post edited successfully

**Response Body:**

- **success** `boolean`: No description
- **id** `string`: The platform post ID after the edit. X assigns a new ID; every other platform returns the original ID unchanged.

- **url** `string` (uri): URL of the edited post
- **message** `string`: No description

#### 400: Invalid request: platform not supported, post not published, edit window expired, not X Premium, or missing content. Also returned when the platform rejects the edit with a 4xx, which covers a Reddit link post (no editable body), a Facebook post that was not created by this app, content over a platform's length limit (LinkedIn 3,000, Telegram 4096 text / 1024 caption, Pinterest 800, Slack 4,000), a Telegram edit with unchanged content, and a Pinterest app not yet allowlisted for the pin-update beta.


#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Forbidden

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 500: Platform API edit failed for a reason the platform did not classify.

---

---
