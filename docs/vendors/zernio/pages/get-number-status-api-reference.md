# Get number status API Reference

Live snapshot of a connected number straight from Meta: the phone-number node
(display number, display name + approval, quality rating, messaging-limit tier,
throughput, official-business badge, connection status, health_status) and its
owning WhatsApp Business Account (name, business verification, timezone,
health_status). Fetched live because Meta updates quality/tier/name/health over
time; the call also refreshes the cached values shown on the connection card.


## GET /v1/whatsapp/number-info

**Get number status**

Live snapshot of a connected number straight from Meta: the phone-number node
(display number, display name + approval, quality rating, messaging-limit tier,
throughput, official-business badge, connection status, health_status) and its
owning WhatsApp Business Account (name, business verification, timezone,
health_status). Fetched live because Meta updates quality/tier/name/health over
time; the call also refreshes the cached values shown on the connection card.


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Number + WABA status

**Response Body:**

- **phone** `object`: 
  - **display_phone_number** `string`: No description
  - **verified_name** `string`: No description
  - **name_status** `string`: APPROVED, AVAILABLE_WITHOUT_REVIEW, PENDING_REVIEW, DECLINED, EXPIRED, NONE
  - **quality_rating** `string`: GREEN, YELLOW, RED, UNKNOWN
  - **messaging_limit_tier** `string`: e.g. TIER_250, TIER_1K, TIER_UNLIMITED
  - **throughput** `object`: 
    - **level** `string`: STANDARD or HIGH
  - **status** `string`: e.g. CONNECTED
  - **is_official_business_account** `boolean`: No description
  - **platform_type** `string`: e.g. CLOUD_API
  - **health_status** `object`: Meta's can_send_message health object (messaging + calling signals)
- **waba** `object,null`: No description

#### 400: Phone number ID not found on account

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 404: WhatsApp account not found

---

---
