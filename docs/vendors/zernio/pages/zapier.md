# Zapier

Create and schedule posts from a Zap with the Zernio app, triggered by Google Sheets, Notion, RSS, Ghost or any of 7,000+ apps.

import { Step, Steps } from 'fumadocs-ui/components/steps';

When you finish this page a Zap creates a post on a connected account through the Zernio action. You need a Zernio account with a [connected account](/guides/connecting-accounts) and a Zapier account; the app signs in with OAuth, so no API key is pasted.

| Detail | Value |
|--------|-------|
| Zapier app | [Zernio](https://zapier.com/apps/late/integrations), whose URL still carries `late`, the product's former name |
| Auth | OAuth (sign in with your Zernio account) |
| Role | Action app: it receives content from a trigger and creates the post |
| Common triggers | Schedule, Notion, Google Sheets, Ghost, RSS, Gmail |

The app has these actions:

| Action | Required fields | Optional fields |
|--------|----------------|-----------------|
| Create Post | Platform, Account ID, Content | Scheduled For, Media URLs |
| Create Instagram Reel | Account ID, Video URL | Content, Thumbnail, Scheduled For |
| Create Instagram Story | Account ID, Image URL | Content, Scheduled For |
| Create YouTube Video | Account ID, Content, Video URL | Thumbnail, Scheduled For |
| Add Pinterest Image Pin | Account ID, Board ID, Link, Content, Image | None |
| Add Pinterest Video Pin | Account ID, Board ID, Content, Link, Cover Image, Video | None |

The two Pinterest actions have no **Scheduled For** field, so a pin publishes as soon as the Zap runs. To schedule one, put the delay on the Zap, or create the pin with `POST /v1/posts` and `scheduledFor` ([Pinterest](/platforms/pinterest)).

| Action | Description |
|--------|-------------|
| Update Post | Edit a draft or scheduled post |
| Delete Post | Delete a draft, scheduled or failed post |
| Retry Add Post | Retry a failed post |
| Get Post by ID | Get one post |
| Get Posts | List posts, filtered by status, profile and date, with pagination |
| Get Profiles | List profiles |
| Get Social Accounts | List the connected accounts of a profile |
| Get Pinterest Board List | List boards for a Pinterest account |
| Delete Social Account | Disconnect an account |

## Step 1: Connect the app

<Steps>

<Step>
### Find the app

Create a Zap on [zapier.com](https://zapier.com). When adding an action step, search for **Zernio**.

<Callout type="warn">
Pick **Zernio** (zernio.com), not "Later". They are different products, and a Zap built on the wrong one fails at the sign-in step. Zernio's own listing URL still reads `zapier.com/apps/late/`, a leftover from the old name, so trust the app name and not the slug.
</Callout>
</Step>

<Step>
### Sign in

Click **Sign in**. Zapier sends you to Zernio's OAuth page: log in, authorize Zapier, and you land back in Zapier. The connection is reused by every Zap.
</Step>

<Step>
### Pick a profile and an account

In the action, select a **Profile**, then a **Social Account** (the list is filtered to that profile), then fill in the content fields. Click **Test step**.
</Step>

</Steps>

## Step 2: Create a post

1. Add a trigger (see [triggers](#triggers)).
2. Add **Zernio** as the action app and choose **Create Post**.
3. Select the **Profile** and the **Social Account**.
4. Enter the **Content**, or map it from the trigger step.
5. Set **Scheduled For** to publish later, or leave it empty to publish now.

Map trigger values by clicking into a field: a Google Sheets column, a Notion property or an RSS title into **Content**; a file URL from Google Drive, Dropbox or Airtable into **Media URLs**; a date column into **Scheduled For**.

## Step 3: Schedule a post

**Scheduled For** takes an ISO 8601 timestamp. To compute one, add a **Code by Zapier** step (JavaScript) before the action and map its `scheduledFor` output:

```javascript
const tomorrow = new Date();
tomorrow.setDate(tomorrow.getDate() + 1);
tomorrow.setHours(9, 0, 0, 0);

output = [{ scheduledFor: tomorrow.toISOString() }];
```

## Step 4: Cross-post

Add one **Create Post** action per platform in the same Zap (X, then LinkedIn, then Bluesky), or use **Paths by Zapier** on a paid plan to send different content down each path: Path A with short content for X (platform value `twitter`), Path B with long-form content for LinkedIn, and a fallback path for the rest.

## Triggers

Zernio is an action-only app, so a trigger from another app starts the Zap:

- **Schedule by Zapier**: every day at 09:00 for a spreadsheet calendar, every Monday for a weekly roundup, every hour for frequent updates, the first of the month for a recap. Schedule triggers do not count toward task usage.
- **Google Sheets: New Spreadsheet Row**, mapping the Content, Platform, Image URL and Publish Date columns into Content, Platform, Media URLs and Scheduled For.
- **Notion: Updated Database Item**, with a **Filter by Zapier** step that continues only when the status is "Ready".
- **RSS by Zapier: New Item in Feed**, mapping the title and link into Content.
- **Ghost: Published Post**, sharing each new article.

A retry loop is a Zap too: **Schedule by Zapier** every hour, **Get Posts** filtered by status `failed`, a **Filter** that continues only when results exist, then **Retry Add Post**.

## If it fails

"Account not found" means the profile and account in the action no longer match; reselect both from the dropdowns. Other errors the action returns:

| Error | Cause | Fix |
|-------|-------|-----|
| "Content too long" | The text exceeds the platform's character limit | Shorten it, or truncate it in a Code step |
| "Rate limit exceeded" | Too many requests ([rate limits](/guides/rate-limits)) | Space out Zap runs |
| "Cannot delete published post" | The post is already published | Only draft, scheduled and failed posts can be deleted |

Zapier retries some failures on its own. For the rest, open **Zap History**, find the run, fix the input and click **Replay**. A **Filter by Zapier** step before the action (content not empty, platform valid, publish date in the future) prevents most bad calls.

## Related

- [Zernio on Zapier](https://zapier.com/apps/late/integrations)
- [API keys](https://zernio.com/dashboard/api-keys)
- [API reference](/)
- [Media uploads](/guides/media-uploads)

---
