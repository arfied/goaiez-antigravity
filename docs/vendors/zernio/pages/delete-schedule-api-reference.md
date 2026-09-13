# Delete schedule API Reference

Delete a queue from a profile. Pass queueId to delete a specific queue;
omit it to delete all queues for the profile.
If deleting the default queue, another queue will be promoted to default.


## GET /v1/queue/slots

**List schedules**

Returns queue schedules for a profile. Use all=true for all queues, or queueId for a specific one. Defaults to the default queue.

### Parameters

- **profileId** (required) in query: Profile ID to get queues for
- **queueId** (optional) in query: Specific queue ID to retrieve (optional)
- **all** (optional) in query: Set to 'true' to list all queues for the profile

### Responses

#### 200: Queue schedule(s) retrieved

**Response Body:**

*One of the following:*
- `QueueSlotsResponse`
  - **queues** `array[QueueSchedule]`: 
  - **count** `integer`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Profile not found

---

## POST /v1/queue/slots

**Create schedule**

Create an additional queue for a profile. The first queue created becomes the default.
Subsequent queues are non-default unless explicitly set.


### Request Body

- **profileId** (required) `string`: Profile ID
- **name** (required) `string`: Queue name (e.g., Evening Posts)
- **timezone** (required) `string`: IANA timezone
- **slots** (required) `array`: No description
- **active** `boolean`: No description

### Responses

#### 201: Queue created

**Response Body:**

- **success** `boolean`: No description
- **schedule**: `QueueSchedule` - See schema definition
- **nextSlots** `array[string]`: 

#### 400: Invalid request or validation error

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Profile not found

---

## PUT /v1/queue/slots

**Update schedule**

Create a new queue or update an existing one. Without queueId, creates/updates the default queue. With queueId, updates a specific queue. With setAsDefault=true, makes this queue the default for the profile.


### Request Body

- **profileId** (required) `string`: No description
- **queueId** `string`: Queue ID to update (optional)
- **name** `string`: Queue name
- **timezone** (required) `string`: No description
- **slots** (required) `array`: No description
- **active** `boolean`: No description
- **setAsDefault** `boolean`: Make this queue the default
- **reshuffleExisting** `boolean`: Whether to reschedule existing queued posts to match new slots

### Responses

#### 200: Queue schedule updated

**Response Body:**

- **success** `boolean`: No description
- **schedule**: `QueueSchedule` - See schema definition
- **nextSlots** `array[string]`: 
- **reshuffledCount** `integer`: No description
- **skippedDailyLimit** `integer`: No description
- **isNewQueue** `boolean`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Profile not found

---

## DELETE /v1/queue/slots

**Delete schedule**

Delete a queue from a profile. Pass queueId to delete a specific queue;
omit it to delete all queues for the profile.
If deleting the default queue, another queue will be promoted to default.


### Parameters

- **profileId** (required) in query: No description
- **queueId** (optional) in query: Queue ID to delete. Omit to delete all queues for the profile

### Responses

#### 200: Queue schedule deleted

**Response Body:**

- **success** `boolean`: No description
- **deleted** `boolean`: No description
- **deletedCount** `integer`: No description
- **message** `string`: No description

#### 400: Invalid request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Profile or queue not found

---
