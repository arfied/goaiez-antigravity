# Batch get reviews API Reference

Fetches reviews across multiple locations in a single request.
More efficient than calling GET /gmb-reviews per location for multi-location businesses.
Returns a flat locationReviews array (not grouped by location): each item carries
the location resource name it belongs to (`name`) plus the review object (`review`),
whose identity is `review.reviewId`.
Reviews are requested from Google ordered by `orderBy` (default `updateTime desc`,
newest first), so callers polling for recent reviews can stop paginating once they
cross their date window.
Note: this endpoint does not return aggregate metrics (averageRating / totalReviewCount).
For those, use the single-location GET /gmb-reviews endpoint.


## POST /v1/accounts/{accountId}/gmb-reviews/batch

**Batch get reviews**

Fetches reviews across multiple locations in a single request.
More efficient than calling GET /gmb-reviews per location for multi-location businesses.
Returns a flat locationReviews array (not grouped by location): each item carries
the location resource name it belongs to (`name`) plus the review object (`review`),
whose identity is `review.reviewId`.
Reviews are requested from Google ordered by `orderBy` (default `updateTime desc`,
newest first), so callers polling for recent reviews can stop paginating once they
cross their date window.
Note: this endpoint does not return aggregate metrics (averageRating / totalReviewCount).
For those, use the single-location GET /gmb-reviews endpoint.


### Parameters

- **accountId** (required) in path: No description

### Request Body

- **locationNames** (required) `array`: Array of full location resource names (e.g. ['accounts/123/locations/456']). Max 50 per request (Google's batchGetReviews cap); chunk larger sets into multiple requests.
- **pageSize** `integer`: Number of reviews per page (max 50)
- **pageToken** `string`: Pagination token from previous response
- **orderBy** `string`: Sort order requested from Google. Defaults to 'updateTime desc' (newest first), which allows early-stopping pagination once results cross your date window. - one of: updateTime desc, rating, rating desc

### Responses

#### 200: Batch reviews fetched successfully

**Response Body:**

- **success** `boolean`: No description
- **accountId** `string`: No description
- **locationReviews** `array[object]`: 
  - **name** `string`: LOCATION resource name the review belongs to (accounts/{accountId}/locations/{locationId}) - NOT the review resource name. Use it to attribute the review to a location; the review identity is review.reviewId (full review resource name at review.name).
  - **review** `object`: The review object: reviewId (the review's identity), name (full review resource name, accounts/*/locations/*/reviews/*), starRating, comment, reviewer, createTime, updateTime, reviewReply, and reviewMediaItems (review photos/videos; photo items carry thumbnailUrl, video items carry videoUrl)
- **nextPageToken** `string`: No description

#### 400: Invalid request

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: Human-readable error message.
- **type** `string`: Error class for programmatic handling. - one of: invalid_request_error, authentication_error, permission_error, not_found, rate_limit_error, platform_error, api_error
- **code** `string`: Stable machine-readable error code.
- **param** `string`: The request field that caused the error, when applicable.
- **platform** `string`: Upstream platform (e.g. meta, google, tiktok), present when type is platform_error.
- **platformError** `object`: Raw error payload from the upstream platform, passed through verbatim so
integrators can read provider-specific codes. For Meta this includes
error_subcode, error_user_title, and error_user_msg.

- **details** `object`: Additional structured context (e.g. field-level validation errors), for example `privateReplyConsumed` on the private-reply endpoint's 400 when the comment's single reply is already spent.

---
