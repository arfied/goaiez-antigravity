# List reviews API Reference

Fetch reviews from all connected Facebook Pages and Google Business Profile accounts. Aggregates data with filtering and sorting options.
Supported platforms: Facebook, Google Business Profile.


## GET /v1/inbox/reviews

**List reviews**

Fetch reviews from all connected Facebook Pages and Google Business Profile accounts. Aggregates data with filtering and sorting options.
Supported platforms: Facebook, Google Business Profile.


### Parameters

- **profileId** (optional) in query: No description
- **platform** (optional) in query: No description
- **minRating** (optional) in query: No description
- **maxRating** (optional) in query: No description
- **hasReply** (optional) in query: Filter by reply status
- **sortBy** (optional) in query: No description
- **sortOrder** (optional) in query: No description
- **limit** (optional) in query: No description
- **cursor** (optional) in query: No description
- **accountId** (optional) in query: Filter by specific account ID

### Responses

#### 200: Aggregated reviews

**Response Body:**

- **status** `string`: No description
- **data** `array[object]`: 
  - **id** `string`: Review identifier. For Google Business Profile this is the full review resource name (accounts/{accountId}/locations/{locationId}/reviews/{reviewId}), so it also encodes the location.
  - **platform** `string`: No description
  - **accountId** `string`: No description
  - **accountUsername** `string`: No description
  - **locationId** `string`: Bare Google Business Profile location id the review belongs to. Google Business Profile only; absent for other platforms.
  - **locationName** `string,null`: Human-readable Google Business Profile location display name. Google Business Profile only; absent for other platforms.
  - **reviewer** `object`: 
    - **id** `string,null`: No description
    - **name** `string`: No description
    - **profileImage** `string,null`: No description
  - **rating** `integer`: No description
  - **text** `string`: No description
  - **created** `string` (date-time): No description
  - **hasReply** `boolean`: No description
  - **hasPhotos** `boolean`: Whether the review has at least one photo. Google Business Profile only; always false for other platforms.
  - **photoCount** `integer`: Number of photos attached to the review (photos only; videos are not counted). Google Business Profile only; 0 for other platforms.
  - **photos** `array[object]`: Photos attached to the review. Google Business Profile only; always an empty array for other platforms.
    - **url** `string` (uri): No description
  - **reply** `object,null`: No description
  - **reviewUrl** `string,null`: No description
- **pagination** `object`: 
  - **hasMore** `boolean`: No description
  - **nextCursor** `string,null`: No description
- **meta** `object`: 
  - **accountsQueried** `integer`: No description
  - **accountsFailed** `integer`: No description
  - **failedAccounts** `array[object]`: 
    - **accountId** `string`: No description
    - **accountUsername** `string,null`: No description
    - **platform** `string`: No description
    - **error** `string`: No description
    - **code** `string,null`: Error code if available
    - **retryAfter** `integer,null`: Seconds to wait before retry (rate limits)
  - **lastUpdated** `string` (date-time): No description
  - **accountsSkipped** `array[object]`: Connected accounts that were not queried: their platform does not support this feature, or the account is not enabled for it
    - **accountId** `string`: No description
    - **platform** `string`: No description
- **summary** `object`: 
  - **totalReviews** `integer`: No description
  - **averageRating** `number,null`: No description

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Inbox addon required

---

---
