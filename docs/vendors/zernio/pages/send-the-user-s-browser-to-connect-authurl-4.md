# Send the user's browser to connect["authUrl"]
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/connect/googlebusiness?profileId=66a1f0c2a4b9d3e8f1a2b3c4&redirect_url=https://myapp.com/callback" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "authUrl": "https://accounts.google.com/o/oauth2/v2/auth?client_id=...",
  "state": "..."
}
```

After the location is selected the user lands on `redirect_url` with `connected=googlebusiness&profileId=...&accountId=...` appended. One account is one location; an account that manages several locations posts to the others with `locationId`, see [Multi-Location Posting](/platforms/google-business/multi-location).

### OAuth scopes

| Scope | What it enables |
|-------|-----------------|
| `https://www.googleapis.com/auth/business.manage` | Manage locations: local posts, reviews and performance metrics |
| `https://www.googleapis.com/auth/userinfo.profile` | Account identity during connection |
| `https://www.googleapis.com/auth/userinfo.email` | Account email during connection |

## Publish

A plain post becomes a `STANDARD` update on the connected location. Call `POST /v1/posts` with `content`, at most one image in `mediaItems`, a `platforms` entry with `platform: "googlebusiness"` and `publishNow: true`:

```bash
curl -X POST https://zernio.com/api/v1/posts \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "content": "Open all holiday weekend. Stop by for the seasonal menu.",
    "mediaItems": [
      {"type": "image", "url": "https://cdn.example.com/holiday-special.jpg"}
    ],
    "platforms": [
      {"platform": "googlebusiness", "accountId": "66b2e19d8c3f5a7e9d0b1c2d"}
    ],
    "publishNow": true
  }'
```

Response (`201`):

```json
{
  "post": {
    "_id": "65f1c0a9e2b5af0012ab34cd",
    "status": "published",
    "platforms": [
      {
        "platform": "googlebusiness",
        "status": "published",
        "platformPostId": "1234567890123456789",
        "platformPostUrl": "https://business.google.com/..."
      }
    ]
  }
}
```

Replace `publishNow` with `scheduledFor` and `timezone` to schedule it. Call-to-action buttons, text-only posts and edits are on [Posts & Content Types](/platforms/google-business/posts); `topicType: "EVENT"` and `"OFFER"` are on [Event & Offer Posts](/platforms/google-business/events-offers).

## In this section

<Cards>
  <Card icon={<PenLine />} title="Posts & Content Types" href="/platforms/google-business/posts" description="Create a post with an image, add a call-to-action button and edit a published post" />
  <Card icon={<CalendarClock />} title="Event & Offer Posts" href="/platforms/google-business/events-offers" description="Event and offer posts with topicType, event and offer" />
  <Card icon={<MapPin />} title="Multi-Location Posting" href="/platforms/google-business/multi-location" description="List the locations an account manages and post to several at once" />
  <Card icon={<Store />} title="Business Profile Management" href="/platforms/google-business/business-profile" description="Verification, hours, photos, attributes and action links" />
  <Card icon={<ClipboardList />} title="Services & Food Menus" href="/platforms/google-business/services-menus" description="The service list, and the menus of a restaurant or cafe" />
  <Card icon={<ChartLine />} title="Analytics" href="/platforms/google-business/analytics" description="Daily performance metrics and the search keywords that triggered impressions" />
  <Card icon={<Inbox />} title="Inbox" href="/platforms/google-business/inbox" description="List and reply to reviews" />
  <Card icon={<FileImage />} title="Fields, Media & Limits" href="/platforms/google-business/reference" description="Media requirements, every platformSpecificData field and common errors" />
</Cards>

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `402` with `code: "PAYMENT_REQUIRED"` on `GET /v1/connect/googlebusiness` | A billing gate stopped the connection before OAuth started, usually no payment method on file. | Send the user to the `dashboard_url` in the response, then call connect again. |
| `500` on `GET /v1/connect/googlebusiness/locations` | Google refused the listing: the token is invalid, or the user lacks permission on the Google Business account. | Restart the connect flow so the user re-authorizes with `business.manage`. |
| `404` on `POST /v1/connect/googlebusiness/select-location` | `locationId` is not one of the locations this connection manages. | Pass an `id` from the [List locations](/connect/list-google-business-locations) response of the same flow. |

The publish-time errors are in [Fields, Media & Limits](/platforms/google-business/reference#common-errors), and [Error handling](/guides/error-handling) covers the envelope.

## Related

- [Connecting accounts](/guides/connecting-accounts): the OAuth flow and the location selection step.
- [Create post](/posts/create-post): every field of the request.
- [Media uploads](/guides/media-uploads): upload images instead of hosting them.
- [Reviews](/reviews/list-inbox-reviews): the inbox API for reviews.
- [Performance metrics](/analytics/get-google-business-performance): daily impressions, clicks, calls, directions and bookings.

---
