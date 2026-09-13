# Connect Bluesky account API Reference

Connect a Bluesky account using identifier (handle or email) and an app password.
To get your userId for the state parameter, call GET /v1/users which includes a currentUserId field.


## POST /v1/connect/bluesky/credentials

**Connect Bluesky account**

Connect a Bluesky account using identifier (handle or email) and an app password.
To get your userId for the state parameter, call GET /v1/users which includes a currentUserId field.


### Request Body

- **identifier** (required) `string`: Your Bluesky handle (e.g. user.bsky.social) or email address
- **appPassword** (required) `string`: App password generated from Bluesky Settings > App Passwords
- **state** (required) `string`: Required state formatted as {userId}-{profileId}. Get userId from GET /v1/users and profileId from GET /v1/profiles.
- **redirectUri** `string`: Optional URL to redirect to after successful connection

### Responses

#### 200: Bluesky connected successfully

**Response Body:**

- **message** `string`: No description
- **account**: `SocialAccount` - See schema definition

#### 400: Invalid request - missing fields or invalid state format

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 500: Internal error

---
