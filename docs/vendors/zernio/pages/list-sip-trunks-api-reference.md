# List SIP trunks API Reference



## POST /v1/phone-numbers/sip-trunks

**Create a SIP trunk**

Creates a SIP trunk an external voice platform (Retell, ElevenLabs,
Vapi, or any SIP endpoint) can import your Zernio numbers into. The
trunk carries both directions: inbound calls on attached numbers are
delivered to `sipHost`, and the platform originates outbound calls
through `termination.uri` with the digest credentials.

The `digestPassword` is returned only by this call (and by
rotate-credentials); store it immediately. Attach any number of numbers
to a trunk. Several trunks may point at the same host. Each carries its
own credentials and spend cap, so separate destinations (e.g.
an agency's clients) stay isolated.


### Request Body

- **label** (required) `string`: Display name for the trunk.
- **sipHost** (required) `string`: Fully-qualified hostname inbound calls are delivered to (e.g. sip.rtc.elevenlabs.io, sip.retellai.com).
- **sipPort** `integer`: Defaults to 5061 for tls, 5060 otherwise.
- **transport** `string`: Signaling transport toward sipHost. Default tls (with SRTP media). - one of: tls, tcp, udp

### Responses

#### 201: Trunk created. The digest password is shown only here and on rotate.

**Response Body:**

- **id** `string`: No description
- **label** `string`: No description
- **sipHost** `string`: No description
- **sipPort** `integer`: No description
- **transport** `string`: No description - one of: tls, tcp, udp
- **termination** `object`: 
  - **uri** `string`: Telnyx termination host the platform dials for outbound (sip.telnyx.com).
  - **username** `string`: SIP digest username.
- **numbersAttached** `integer`: No description
- **createdAt** `string,null` (date-time): No description
- **digestPassword** `string`: SIP digest password, shown only in this response.

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

#### 403: SIP trunking is not enabled for this team, or the team is on legacy (non-usage-based) billing, which cannot invoice trunk call costs (code feature_not_available).

#### 409: The team trunk limit was reached (code invalid_resource_state).

#### 422: The host cannot be used as a trunk destination (e.g. a Zernio or carrier host).

---

## GET /v1/phone-numbers/sip-trunks

**List SIP trunks**

### Responses

#### 200: The team's trunks. Passwords are never included.

**Response Body:**

- **trunks** `array[object]`: 
  - **id** `string`: No description
  - **label** `string`: No description
  - **sipHost** `string`: No description
  - **sipPort** `integer`: No description
  - **transport** `string`: No description - one of: tls, tcp, udp
  - **termination** `object`: 
    - **uri** `string`: No description
    - **username** `string`: No description
  - **numbersAttached** `integer`: No description
  - **createdAt** `string,null` (date-time): No description
- **enabled** `boolean`: Whether this team can create SIP trunks. Managing existing trunks always works.

#### 401: Unauthorized

**Response Body:**

- **error** `string`: No description (example: "Unauthorized")

---
