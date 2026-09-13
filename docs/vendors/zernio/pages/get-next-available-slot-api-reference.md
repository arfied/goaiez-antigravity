# Get next available slot API Reference

Returns the next available queue slot for preview purposes. To create a queue post, use POST /v1/posts with queuedFromProfile instead of scheduledFor.

## GET /v1/queue/next-slot

**Get next available slot**

Returns the next available queue slot for preview purposes. To create a queue post, use POST /v1/posts with queuedFromProfile instead of scheduledFor.

### Parameters

- **profileId** (required) in query: No description
- **queueId** (optional) in query: Specific queue ID (optional, defaults to profile's default queue)

### Responses

#### 200: Next available slot

**Response Body:**

- **profileId** `string`: No description
- **nextSlot** `string` (date-time): No description
- **timezone** `string`: No description
- **queueId** `string`: Queue ID this slot belongs to
- **queueName** `string`: Queue name

#### 400: Invalid parameters or inactive queue

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Profile or queue schedule not found, or no available slots

---
