# Preview upcoming slots API Reference

Returns the next N upcoming queue slot times for a profile as ISO datetime strings.

## GET /v1/queue/preview

**Preview upcoming slots**

Returns the next N upcoming queue slot times for a profile as ISO datetime strings.

### Parameters

- **profileId** (required) in query: No description
- **queueId** (optional) in query: Filter by specific queue ID. Omit to use the default queue.
- **count** (optional) in query: No description

### Responses

#### 200: Queue slots preview

**Response Body:**

- **profileId** `string`: No description
- **queueId** `string`: No description
- **queueName** `string`: No description
- **count** `integer`: No description
- **slots** `array[string]`: 

#### 400: Invalid parameters

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Profile or queue schedule not found

---
