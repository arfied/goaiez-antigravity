# Get a single call API Reference



## GET /v1/whatsapp/calls/{id}

**Get a single call**

### Parameters

- **id** (required) in path: No description
- **accountId** (required) in query: No description

### Responses

#### 200: Call

**Response Body:**

- **call** `object`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Call not found

---

---
