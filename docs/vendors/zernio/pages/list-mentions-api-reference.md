# List mentions API Reference

Returns mentions of your connected organization accounts, delivered via platform webhooks.
Currently supports LinkedIn organization mentions.

Requires Inbox addon.


## GET /v1/inbox/mentions

**List mentions**

Returns mentions of your connected organization accounts, delivered via platform webhooks.
Currently supports LinkedIn organization mentions.

Requires Inbox addon.


### Parameters

- **accountId** (optional) in query: Filter by account ID
- **profileId** (optional) in query: Filter by profile ID
- **sortOrder** (optional) in query: Sort order by publishedAt
- **limit** (optional) in query: No description
- **cursor** (optional) in query: Cursor for pagination (ID of the last item from the previous page)

### Responses

#### 200: Paginated list of mentions

**Response Body:**

- **data** `array[object]`: 
  - **id** `string`: Mention document ID
  - **platform** `string`: No description - one of: linkedin
  - **accountId** `string`: No description
  - **accountUsername** `string`: No description
  - **content** `string`: Text of the post that mentioned you
  - **permalink** `string,null`: URL to the source post on LinkedIn
  - **authorUrn** `string,null`: LinkedIn URN of the person who mentioned you
  - **authorName** `string,null`: Display name of the author, resolved from authorUrn. Null when LinkedIn does not allow resolving the profile.
  - **authorUsername** `string,null`: LinkedIn vanity name of the author (the slug in their profile URL)
  - **authorPicture** `string,null`: Profile picture URL of the author. LinkedIn CDN URLs expire after some time, so fetch promptly rather than storing long-term.
  - **organizationalEntity** `string`: URN of the organization that was mentioned
  - **publishedAt** `string` (date-time): No description
  - **createdAt** `string` (date-time): No description
- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **cursor** `string,null`: No description
- **meta** `object`: 
  - **total** `integer`: No description
  - **sortOrder** `string`: No description - one of: asc, desc

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

---
