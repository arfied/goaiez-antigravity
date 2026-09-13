# List subreddit flairs API Reference

Returns available post flairs for a subreddit. Some subreddits require a flair when posting.

## GET /v1/accounts/{accountId}/reddit-flairs

**List subreddit flairs**

Returns available post flairs for a subreddit. Some subreddits require a flair when posting.

### Parameters

- **accountId** (required) in path: No description
- **subreddit** (required) in query: Subreddit name (without "r/" prefix) to fetch flairs for

### Responses

#### 200: Flairs list

**Response Body:**

- **flairs** `array[object]`: 
  - **id** `string`: Flair ID to pass as flairId in platformSpecificData
  - **text** `string`: Flair display text
  - **textColor** `string`: Text color: 'dark' or 'light'
  - **backgroundColor** `string`: Background hex color (e.g. '#ff4500')

#### 400: Not a Reddit account or missing subreddit parameter

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

## POST /v1/accounts/{accountId}/reddit-flairs

**Set Reddit post flair**

Applies a flair to a post the connected account already published. Use the GET on this
path to list the available `flairTemplateId` values for the subreddit.

Flair can also be set at submit time by passing `flairId` in `platformSpecificData`
when creating the post. This endpoint is for changing it afterwards.

The subreddit must allow users to select their own post flair. Setting flair on
another user's post requires moderator permissions, which Zernio does not request.


### Parameters

- **accountId** (required) in path: The ID of the Reddit account that owns the post

### Request Body

- **subreddit** (required) `string`: Subreddit name (without the "r/" prefix)
- **postId** (required) `string`: Reddit post id, with or without the t3_ prefix
- **flairTemplateId** (required) `string`: Flair template id from the GET on this path
- **text** `string`: Optional override text, only for editable flair templates

### Responses

#### 200: Flair applied

**Response Body:**

- **success** `boolean`: No description

#### 400: Not a Reddit account, or missing subreddit/postId/flairTemplateId

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

#### 502: Reddit was unreachable or returned an unclassified error. Reddit 4xx statuses (e.g. subreddit does not allow user flair selection) are forwarded as-is.

---

---
