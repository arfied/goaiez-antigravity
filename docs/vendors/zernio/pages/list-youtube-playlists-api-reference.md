# List YouTube playlists API Reference

Returns the playlists available for a connected YouTube account. Use this to get a playlist ID when creating a YouTube post with the playlistId field.

## GET /v1/accounts/{accountId}/youtube-playlists

**List YouTube playlists**

Returns the playlists available for a connected YouTube account. Use this to get a playlist ID when creating a YouTube post with the playlistId field.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Playlists list

**Response Body:**

- **playlists** `array[object]`: 
  - **id** `string`: No description
  - **title** `string`: No description
  - **description** `string`: No description
  - **privacy** `string`: No description - one of: public, private, unlisted
  - **itemCount** `integer`: No description
  - **thumbnailUrl** `string`: No description
- **defaultPlaylistId** `string,null`: No description

#### 400: Not a YouTube account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

## PUT /v1/accounts/{accountId}/youtube-playlists

**Set default YouTube playlist**

Sets the default playlist used when publishing videos for this account. When a post does not specify a playlistId, the default playlist is not automatically used (it is stored for client-side convenience).

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **defaultPlaylistId** (required) `string`: No description
- **defaultPlaylistName** `string`: No description

### Responses

#### 200: Default playlist set

**Response Body:**

- **success** `boolean`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

---
