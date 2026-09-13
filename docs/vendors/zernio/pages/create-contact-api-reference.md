# Create contact API Reference

Create a new contact. Optionally create a platform channel in the same request by providing accountId, platform, and platformIdentifier.

## GET /v1/contacts

**List contacts**

List and search contacts for a profile. Supports filtering by tags, platform, subscription status, and text search on name, email and company.

### Parameters

- **profileId** (optional) in query: Filter by profile. Omit to list across all profiles. Matches the profile recorded on the contact itself, which is set when the contact is created and is independent of the profile its account currently belongs to. Filter by accountId to list a contact through its channel instead.
- **accountId** (optional) in query: Filter by the SocialAccount that owns the contact channel. Contacts are resolved through their channels, so the profileId contact filter is not applied while accountId is set. A profileId sent alongside is still access-checked and still scopes the returned filters.tags list.
- **search** (optional) in query: Case-insensitive substring match on the contact name, email and company. Phone numbers and other platform identifiers are not matched: they live on the contact channel, not on the contact. To reach a contact from an inbox webhook, use the conversation.contactId it already carries.
- **tag** (optional) in query: No description
- **tags** (optional) in query: Comma-separated tags, matches contacts carrying any of them
- **platform** (optional) in query: No description
- **isSubscribed** (optional) in query: No description
- **limit** (optional) in query: No description
- **skip** (optional) in query: No description

### Responses

#### 200: Contacts list with pagination and filter metadata

**Response Body:**

- **success** `boolean`: No description
- **contacts** `array[object]`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **email** `string`: No description
  - **company** `string`: No description
  - **avatarUrl** `string`: No description
  - **tags** `array[string]`: 
  - **isSubscribed** `boolean`: No description
  - **isBlocked** `boolean`: No description
  - **lastMessageSentAt** `string` (date-time): No description
  - **lastMessageReceivedAt** `string` (date-time): No description
  - **messagesSentCount** `integer`: No description
  - **messagesReceivedCount** `integer`: No description
  - **customFields** `object`: No description
  - **notes** `string`: No description
  - **createdAt** `string` (date-time): No description
  - **platform** `string`: No description
  - **platformIdentifier** `string`: No description
  - **displayIdentifier** `string`: No description
- **filters** `object`: 
  - **tags** `array[string]`: 
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

## POST /v1/contacts

**Create contact**

Create a new contact. Optionally create a platform channel in the same request by providing accountId, platform, and platformIdentifier.

### Request Body

- **profileId** (required) `string`: No description
- **name** (required) `string`: No description
- **email** `string`: No description
- **company** `string`: No description
- **tags** `array`: No description
- **isSubscribed** `boolean`: No description
- **notes** `string`: No description
- **accountId** `string`: Optional. Creates a channel if provided with platform + platformIdentifier
- **platform** `string`: Channel platform. Only the enum values support contact channels; any other platform is rejected with code platform_not_supported. - one of: instagram, facebook, telegram, twitter, bluesky, reddit, whatsapp, slack, sms
- **platformIdentifier** `string`: No description
- **displayIdentifier** `string`: No description

### Responses

#### 200: Contact created

**Response Body:**

- **success** `boolean`: No description
- **contact** `object`: 
  - **id** `string`: No description
  - **name** `string`: No description
  - **email** `string`: No description
  - **company** `string`: No description
  - **tags** `array[string]`: 
  - **isSubscribed** `boolean`: No description
  - **isBlocked** `boolean`: No description
  - **customFields** `object`: No description
  - **notes** `string`: No description
  - **createdAt** `string` (date-time): No description
- **channel** `object`: Created when accountId, platform, and platformIdentifier are provided
  - **id** `string`: No description
  - **platform** `string`: No description
  - **platformIdentifier** `string`: No description
  - **displayIdentifier** `string`: No description
- **warning** `string`: No description

#### 400: Invalid request. Channel fields are all-or-nothing: accountId, platform and platformIdentifier must be sent together (code: missing_required_field). A platform outside the enum does not support contact channels (code: platform_not_supported, details.supportedPlatforms lists the valid values).

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 409: Duplicate channel. The platformIdentifier is already bound to a channel on this accountId.

---

---
