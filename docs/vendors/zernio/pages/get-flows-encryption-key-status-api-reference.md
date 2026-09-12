# Get Flows encryption key status API Reference

Read the RSA business public key registered on the phone number for WhatsApp Flows
endpoint encryption. Only one key is active per phone number at a time. Flows that
use flow_action: data_exchange (an endpoint-backed flow) stop working at runtime
until the endpoint serves the matching private key, and Meta rejects publish with
error code 139002 ("Missing Flows Signed Public Key") when no key is registered.
`registered` reflects whether a key is present, never `signatureStatus` alone:
Meta reports an unregistered key as MISMATCH rather than a null/absent value.


## GET /v1/whatsapp/flows/encryption-key

**Get Flows encryption key status**

Read the RSA business public key registered on the phone number for WhatsApp Flows
endpoint encryption. Only one key is active per phone number at a time. Flows that
use flow_action: data_exchange (an endpoint-backed flow) stop working at runtime
until the endpoint serves the matching private key, and Meta rejects publish with
error code 139002 ("Missing Flows Signed Public Key") when no key is registered.
`registered` reflects whether a key is present, never `signatureStatus` alone:
Meta reports an unregistered key as MISMATCH rather than a null/absent value.


### Parameters

- **accountId** (required) in query: WhatsApp account ID

### Responses

#### 200: Encryption key status retrieved

**Response Body:**

- **publicKey** `string,null`: The registered RSA public key in PEM format, or null when none is registered.
- **signatureStatus** `string,null`: VALID (key matches Meta's records) or MISMATCH (no key registered, or the key does not match); null when unknown. - one of: VALID, MISMATCH
- **registered** `boolean`: Whether a key is currently registered. Derived from publicKey, not signatureStatus.

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

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

#### 404: WhatsApp account not found

#### 502: Meta rejected the request

---

## POST /v1/whatsapp/flows/encryption-key

**Register a Flows encryption key**

Register (or replace) the RSA business public key for WhatsApp Flows endpoint
encryption on the phone number. Uploading a new key replaces the previous one:
only one key is active per phone number. The corresponding private key must be
served by the flow's endpoint, or endpoint-backed flows (flow_action:
data_exchange) will fail at runtime even though the key is registered.


### Request Body

- **accountId** (required) `string`: WhatsApp account ID
- **businessPublicKey** (required) `string`: RSA public key in PEM format. Rejected if it is a private key or not a valid RSA public key PEM.

### Responses

#### 200: Encryption key registered

**Response Body:**

- **success** `boolean`: No description

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

- **error** `string`: No description (example: "Unauthorized")

#### 403: The API key is a restricted key (zrk_ prefix) and may not perform this operation. Three cases. (1) The operation's resource group (see the operation's x-resource-group) is disabled on the key: fix it by creating a key with the group enabled in the dashboard API keys tab and revoking the old one. (2) The operation is admin-plane (x-resource-group admin-plane: API keys, invites, connected apps, member identity), which is never grantable to restricted keys; the error reads "Restricted API keys cannot manage API keys, invites, or member identity." and the fix is a full-access key or the dashboard, never a new restricted key. (3) On webhook subscription writes, delivery-log reads and replays, a named event maps to a resource group the key does not hold, so a restricted key can never create or edit a subscription broader than itself (a no-messages key cannot subscribe to, test-fire, redeliver or read logs for message.* events).

**Response Body:**

- **error** `string`: No description (example: "This API key has the 'messages' resource group disabled. GET /api/v1/inbox/conversations requires it. Create a key with 'messages' enabled in the dashboard API keys tab.")
- **code** `string`: No description - one of: insufficient_permissions, unclassified_resource
- **required_group** `string`: The resource group the key needs for this operation. Absent on admin-plane and unclassified-path denials. - one of: publishing, engagement, messages, contacts, analytics, ads, telephony, accounts, billing, webhooks

#### 404: WhatsApp account not found

#### 502: Meta rejected the request

---
