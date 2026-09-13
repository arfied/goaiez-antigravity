# Get Embedded Signup SDK config API Reference

The Meta app id and Embedded Signup configuration id the Zernio-hosted signup page uses to open Meta's
popup. Integrators do not need this endpoint: start the hosted flow with
`GET /v1/connect/whatsapp?signup=hosted` and send the user to the returned `authUrl`. Authenticates with
an API key or with the connect token the hosted flow issues (`X-Connect-Token` header).


## GET /v1/connect/whatsapp/sdk-config

**Get Embedded Signup SDK config**

The Meta app id and Embedded Signup configuration id the Zernio-hosted signup page uses to open Meta's
popup. Integrators do not need this endpoint: start the hosted flow with
`GET /v1/connect/whatsapp?signup=hosted` and send the user to the returned `authUrl`. Authenticates with
an API key or with the connect token the hosted flow issues (`X-Connect-Token` header).


### Parameters

- **X-Connect-Token** (optional) in header: Connect token issued by the hosted signup flow, accepted instead of an API key.

### Responses

#### 200: Meta app configuration for Embedded Signup

**Response Body:**

- **appId** (required) `string`: Meta app id
- **configId** (required) `string`: Embedded Signup configuration id
- **branding** (required) `object,null`: Skin chosen when the hosted signup session was issued (`brandName`, `primaryColor`, `language` on `GET /v1/connect/whatsapp?signup=hosted`). Null for API-key callers and for sessions issued without one.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---

---
