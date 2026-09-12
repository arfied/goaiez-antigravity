# Make

Create and schedule posts from a Make scenario with the Zernio app's modules, cross-post through a Router, and call any endpoint with Make an API Call.

import { Step, Steps } from 'fumadocs-ui/components/steps';

When you finish this page a Make scenario creates a post on a connected account through a Zernio module. You need an [API key](https://zernio.com/dashboard/api-keys), a [connected account](/guides/connecting-accounts) and a Make account.

| Detail | Value |
|--------|-------|
| Make app name | Zernio (Make Nodes Zernio) |
| App directory | [make.com/en/integrations/make-nodes-late](https://www.make.com/en/integrations/make-nodes-late), whose URL still carries `late`, the product's former name |
| Modules | 20+ |
| Auth | API key connection |
| API base URL | `https://zernio.com/api` |

The app has these modules:

| Module | Description |
|--------|-------------|
| Add Post to Twitter/X | Publish or schedule a post on X (platform value `twitter`) |
| Add Post to LinkedIn | Publish or schedule a LinkedIn post |
| Add Post to Facebook | Publish or schedule a Facebook post |
| Add Post to Instagram | Publish or schedule an Instagram post |
| Add Instagram Stories | Create an Instagram Story |
| Add Instagram Reels | Create an Instagram Reel |
| Add Post to TikTok | Publish or schedule a TikTok video |
| Add Post to Threads | Publish or schedule a Threads post |
| Add Post to Bluesky | Publish or schedule a Bluesky post |
| Upload YouTube Video | Upload and publish a YouTube video |
| Upload Pinterest Image Pin | Create an image pin |
| Upload Pinterest Video Pin | Create a video pin |
| Get Social Accounts | List connected accounts |
| Get Post List | List posts, filtered by status |
| Get Post by ID | Get one post |
| List Profiles | List profiles |
| List Pinterest Boards | List boards for a Pinterest account |
| Update a Post | Edit a draft or scheduled post |
| Delete a Post | Delete a draft, scheduled or failed post |
| Delete a Social Account | Disconnect an account |
| Retry Adding a Post | Retry a failed post |
| Make an API Call | Send a request to any Zernio endpoint |

## Step 1: Connect the app

<Steps>

<Step>
### Find the app

In the scenario editor, click **+** to add a module and search for **Zernio**. Pick any module, for example **Add Post to Twitter/X**.
</Step>

<Step>
### Create a connection

Click **Add**, name the connection, paste your API key and click **Save**. The connection is reused by every Zernio module in your scenarios. Treat the key like a password: if it leaks, revoke it and create a new one under [API keys](https://zernio.com/dashboard/api-keys).
</Step>

<Step>
### Check the connection

Add **Get Social Accounts**, choose the connection and click **Run once**. The output lists your connected accounts.
</Step>

</Steps>

## Step 2: Create a post

1. Add the module for the target platform, for example **Add Post to Twitter/X**.
2. Select the **Connection**.
3. Select a **Profile**, then a **Social Account**.
4. Enter the **Text**.
5. Add **Media URLs** (image or video links) if the post has media.
6. Set the scheduled time to publish later, or leave it empty to publish now.

Click **Run once**. To map data from earlier modules, click into a field and pick a value from the mapping panel, for example `{{1.title}}` from a Google Sheets trigger into **Text** or `{{2.imageUrl}}` into **Media URLs**.

## Step 3: Schedule a post

The scheduled time takes an ISO 8601 timestamp. Make's date functions compute one at run time:

| Function | Result |
|----------|--------|
| `{{addDays(now; 1)}}` | Tomorrow, same time |
| `{{formatDate(addDays(now; 1); "YYYY-MM-DD")}}T09:00:00Z` | Tomorrow at 09:00 UTC |
| `{{addHours(now; 3)}}` | 3 hours from now |

## Step 4: Cross-post with a Router

A **Router** splits the scenario into one path per platform, each with its own module and its own text:

1. Trigger (Google Sheets, Webhook, Schedule)
2. **Router**
3. Path A: **Add Post to Twitter/X**
4. Path B: **Add Post to LinkedIn**
5. Path C: **Add Post to Bluesky**

## Step 5: Call any other endpoint

**Make an API Call** sends an authenticated request to an endpoint the modules do not cover. Set **URL** to the path under `https://zernio.com/api`, for example `/v1/analytics`, and **Method** to `GET`. The connection adds the `Authorization` header. Every endpoint is in the [API reference](/).

## Scenario examples

Content calendar: **Google Sheets: Watch Rows** triggers on a new row, **Add Post to Twitter/X** maps the row's text and date.

Posting from your app: **Webhooks: Custom Webhook** receives a POST from your app, **Add Post to LinkedIn** publishes it. Your app sends JSON to the URL the webhook module shows:

```json
{ "text": "Hello from my app", "platform": "linkedin" }
```

RSS to social: **RSS: Watch RSS Feed Items**, **Text Parser: Match Pattern** extracts title and link, a **Router** sends a short post to X and a longer one to LinkedIn.

Retry failed posts: **Schedule: Every Hour**, **Get Post List** filtered by `failed`, an **Iterator**, then **Retry Adding a Post** for each.

## If it fails

A `429` means your team hit its request limit ([rate limits](/guides/rate-limits)). Right-click the module, choose **Add error handler** and use **Break** so the bundle is stored for a manual retry; space requests with a **Sleep** module and a longer scenario interval. The other handlers are **Ignore** (skip and continue), **Rollback** (stop the scenario) and **Resume** (continue with a fallback value).

## Related

- [Zernio on Make](https://www.make.com/en/integrations/make-nodes-late) and the [app documentation](https://apps.make.com/make-nodes-late)
- [API keys](https://zernio.com/dashboard/api-keys)
- [API reference](/)
- [Media uploads](/guides/media-uploads)

---
