# List SMS opt-outs API Reference

The recipients who opted out of SMS (replied STOP) across your numbers,
most recent first. Compliance surface: you must be able to see and
export your opt-out list. Read-only: a recipient is re-subscribed only
by replying START. Pass `format=csv` to download a CSV instead of JSON.


## GET /v1/sms/opt-outs

**List SMS opt-outs**

The recipients who opted out of SMS (replied STOP) across your numbers,
most recent first. Compliance surface: you must be able to see and
export your opt-out list. Read-only: a recipient is re-subscribed only
by replying START. Pass `format=csv` to download a CSV instead of JSON.


### Parameters

- **format** (optional) in query: No description
- **limit** (optional) in query: No description

### Responses

#### 200: Opt-out list

**Response Body:**

- **optOuts** `array[object]`: 
  - **phoneNumber** `string`: No description
  - **optedOutAt** `string,null` (date-time): No description
  - **keyword** `string,null`: The keyword they sent (e.g. STOP), when the carrier recorded one.
  - **from** `string,null`: Which of your numbers the recipient opted out from.
- **count** `integer`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
