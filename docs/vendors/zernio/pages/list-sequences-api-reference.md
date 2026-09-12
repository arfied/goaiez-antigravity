# List sequences API Reference

Returns sequences with enrollment stats. Filter by status, platform, or profile.

## GET /v1/sequences

**List sequences**

Returns sequences with enrollment stats. Filter by status, platform, or profile.

### Parameters

- **profileId** (optional) in query: Filter by profile. Omit to list across all profiles
- **status** (optional) in query: No description
- **limit** (optional) in query: No description
- **skip** (optional) in query: No description

### Responses

#### 200: Sequences list

**Response Body:**

- **success** `boolean`: No description
- **sequences** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **accountName** `string`: Display name of the sending account
  - **messagePreview** `string`: First step template name or message text snippet
  - **status** `string`: No description - one of: draft, active, paused
  - **stepsCount** `integer`: No description
  - **exitOnReply** `boolean`: No description
  - **exitOnUnsubscribe** `boolean`: No description
  - **totalEnrolled** `integer`: No description
  - **totalCompleted** `integer`: No description
  - **totalExited** `integer`: No description
  - **createdAt** `string` (date-time): No description
- **pagination** `object`: 
  - **total** `integer`: No description
  - **limit** `integer`: No description
  - **skip** `integer`: No description
  - **hasMore** `boolean`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

## POST /v1/sequences

**Create sequence**

Create a multi-step messaging sequence. Each step has a delay and a message or WhatsApp template.

### Request Body

- **profileId** (required) `string`: No description
- **accountId** (required) `string`: No description
- **platform** (required) `string`: No description - one of: instagram, facebook, telegram, twitter, bluesky, reddit, whatsapp, slack
- **name** (required) `string`: No description
- **description** `string`: No description
- **steps** `array`: No description
- **exitOnReply** `boolean`: No description
- **exitOnUnsubscribe** `boolean`: No description

### Responses

#### 200: Sequence created

**Response Body:**

- **success** `boolean`: No description
- **sequence** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **status** `string`: No description
  - **stepsCount** `integer`: No description
  - **createdAt** `string` (date-time): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
