# Unfollow a user API Reference

Unfollow a user on X.


## POST /v1/twitter/follow

**Follow a user**

Follow a user on X.
Requires the follows.write OAuth scope.
For protected accounts, a follow request is sent instead (pending_follow will be true).


### Request Body

- **accountId** (required) `string`: The account ID
- **targetUserId** (required) `string`: The X ID of the user to follow

### Responses

#### 200: User followed or follow request sent

**Response Body:**

- **status** `string`: No description (example: "success")
- **targetUserId** `string`: No description
- **following** `boolean`: No description
- **pending_follow** `boolean`: True if the target account is protected and a follow request was sent
- **platform** `string`: No description (example: "twitter")

#### 400: Bad request or platform limitation

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: X rejected the request (e.g. suspended account, missing OAuth scope)

#### 404: Account not found

---

## DELETE /v1/twitter/follow

**Unfollow a user**

Unfollow a user on X.


### Parameters

- **accountId** (required) in query: No description
- **targetUserId** (required) in query: The X ID of the user to unfollow

### Responses

#### 200: User unfollowed

**Response Body:**

- **status** `string`: No description (example: "success")
- **targetUserId** `string`: No description
- **following** `boolean`: No description (example: false)
- **platform** `string`: No description (example: "twitter")

#### 400: Bad request

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

#### 403: X rejected the request (e.g. suspended account, missing OAuth scope)

#### 404: Account not found

---

---
