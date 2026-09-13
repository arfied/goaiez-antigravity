# Replace audience companies API Reference

Upload the company rows of a LinkedIn `company_list` audience (account-based marketing).
LinkedIn-only, every other platform returns 422.

A LinkedIn audience segment holds exactly one uploaded list, so the list you send here
REPLACES the segment's list instead of being appended to it: always send the full set of
companies. LinkedIn returns only the identifier of the uploaded file, never its rows, so the
merge cannot be done for you, keep the source list on your side.

How the matching behaves:

- Rows are plain text (not hashed), matched against LinkedIn's own company graph.
- Matching is asynchronous: LinkedIn takes up to 48h for a new audience and up to 24h for a
  later update, and the audience stays `processing` meanwhile.
- LinkedIn does not document how quickly companies dropped from the list stop being targeted,
  so treat removals as eventual rather than immediate.
- LinkedIn recommends at least 1,000 companies for a usable match rate, and caps a list at
  300,000.

The initial list is sent with `companies` on `POST /v1/ads/audiences`; this endpoint is for
every change after that.


## POST /v1/ads/audiences/{audienceId}/companies

**Replace audience companies**

Upload the company rows of a LinkedIn `company_list` audience (account-based marketing).
LinkedIn-only, every other platform returns 422.

A LinkedIn audience segment holds exactly one uploaded list, so the list you send here
REPLACES the segment's list instead of being appended to it: always send the full set of
companies. LinkedIn returns only the identifier of the uploaded file, never its rows, so the
merge cannot be done for you, keep the source list on your side.

How the matching behaves:

- Rows are plain text (not hashed), matched against LinkedIn's own company graph.
- Matching is asynchronous: LinkedIn takes up to 48h for a new audience and up to 24h for a
  later update, and the audience stays `processing` meanwhile.
- LinkedIn does not document how quickly companies dropped from the list stop being targeted,
  so treat removals as eventual rather than immediate.
- LinkedIn recommends at least 1,000 companies for a usable match rate, and caps a list at
  300,000.

The initial list is sent with `companies` on `POST /v1/ads/audiences`; this endpoint is for
every change after that.


### Parameters

- **audienceId** (required) in path: The Zernio audience id (the id field of GET /v1/ads/audiences), not the platform segment id.

### Request Body

- **companies** (required) `array`: The complete company list. Each row needs at least one of name, domain, website or linkedinPageUrl.

### Responses

#### 200: Companies uploaded

**Response Body:**

- **message** `string`: No description
- **numReceived** `integer`: Rows sent to LinkedIn. Matching happens asynchronously, so this is not the matched company count.

#### 400: Invalid input (malformed audienceId, empty companies array, a row with no identifier)

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: Ads access required. Legacy plans need the Ads add-on; included by default on usage-based plans.

#### 404: Resource not found

**Response Body:**

- **error** `string`: No description (example: "Not found")

#### 422: Audience is not a company_list type, is not on LinkedIn, or has no platform ID yet

---

---
