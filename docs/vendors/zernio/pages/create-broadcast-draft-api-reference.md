# Create broadcast draft API Reference

Create a broadcast in draft status. Add recipients and then send or schedule it.

## GET /v1/broadcasts

**List broadcasts**

Returns broadcasts with delivery stats. Filter by status, platform, or profile.

### Parameters

- **profileId** (optional) in query: Filter by profile. Omit to list across all profiles
- **status** (optional) in query: No description
- **platform** (optional) in query: No description
- **limit** (optional) in query: No description
- **skip** (optional) in query: No description

### Responses

#### 200: Broadcasts list

**Response Body:**

- **success** `boolean`: No description
- **broadcasts** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **accountName** `string`: Display name of the sending account
  - **status** `string`: No description - one of: draft, scheduled, sending, completed, failed, cancelled
  - **messagePreview** `string`: Template name or message text snippet
  - **scheduledAt** `string` (date-time): No description
  - **startedAt** `string` (date-time): No description
  - **completedAt** `string` (date-time): No description
  - **recipientCount** `integer`: No description
  - **sentCount** `integer`: No description
  - **deliveredCount** `integer`: No description
  - **readCount** `integer`: No description
  - **failedCount** `integer`: No description
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

## POST /v1/broadcasts

**Create broadcast draft**

Create a broadcast in draft status. Add recipients and then send or schedule it.

### Request Body

- **profileId** (required) `string`: No description
- **accountId** (required) `string`: No description
- **platform** (required) `string`: No description - one of: instagram, facebook, telegram, twitter, bluesky, reddit, whatsapp, sms, slack
- **name** (required) `string`: No description
- **description** `string`: No description
- **message** `object`: No description
- **template** `object`: WhatsApp template (required when platform is whatsapp)
- **segmentFilters** `object`: No description

### Responses

#### 200: Broadcast created

**Response Body:**

- **success** `boolean`: No description
- **broadcast** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **description** `string`: No description
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **status** `string`: No description
  - **createdAt** `string` (date-time): No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
