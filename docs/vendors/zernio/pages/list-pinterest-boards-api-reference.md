# List Pinterest boards API Reference

Returns the boards available for a connected Pinterest account. Use this to get a board ID when creating a Pinterest post.

## GET /v1/accounts/{accountId}/pinterest-boards

**List Pinterest boards**

Returns the boards available for a connected Pinterest account. Use this to get a board ID when creating a Pinterest post.

### Parameters

- **accountId** (required) in path: No description

### Responses

#### 200: Boards list

**Response Body:**

- **boards** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **privacy** `string`: No description

#### 400: Not a Pinterest account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

## PUT /v1/accounts/{accountId}/pinterest-boards

**Set default Pinterest board**

Sets the default board used when publishing pins for this account.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **defaultBoardId** (required) `string`: No description
- **defaultBoardName** `string`: No description

### Responses

#### 200: Default board set

**Response Body:**

- **message** `string`: No description
- **account**: `SocialAccount` - See schema definition

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

---

## POST /v1/accounts/{accountId}/pinterest-boards

**Create Pinterest board**

Creates a new board on the connected Pinterest account. The returned board ID can be used immediately as `platformSpecificData.boardId` when creating a Pinterest post.

### Parameters

- **accountId** (required) in path: No description

### Request Body

- **name** (required) `string`: Name of the board
- **description** `string`: Board description
- **privacy** `string`: Board privacy setting - one of: PUBLIC, PROTECTED, SECRET

### Responses

#### 201: Board created

**Response Body:**

- **board** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **privacy** `string`: No description
  - **url** `string`: No description

#### 400: Invalid request or not a Pinterest account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Account not found

#### 502: Pinterest rejected the request (e.g. duplicate board name)

---
