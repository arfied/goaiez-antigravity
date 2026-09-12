# Get a phone call API Reference

Full call detail, including the transcript segments when transcription was on.

## GET /v1/voice/calls/{id}

**Get a phone call**

Full call detail, including the transcript segments when transcription was on.

### Parameters

- **id** (required) in path: No description

### Responses

#### 200: Call

**Response Body:**

- **call**: `CallRecord` - See schema definition

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: Call not found

---
