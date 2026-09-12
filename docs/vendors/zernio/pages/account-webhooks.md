# Account webhooks

Receive an event when an account is connected to a profile or stops working, and know how fast each disconnect path reaches you.

Account events tell you when an account joins a profile and when it can no longer be used. Subscribe with `POST /v1/webhooks/settings` and the event names below ([first event](/webhooks#first-event)); delivery, retries and signatures are the same for every event ([how webhooks behave](/webhooks#how-it-behaves)).

## Events

| Event | Description |
| --- | --- |
| [`account.connected`](#accountconnected) | An account was connected to a profile. |
| [`account.disconnected`](#accountdisconnected) | A connected account stopped working or was removed. |

## How it behaves

### How disconnect detection works

Zernio sends `account.disconnected` through 4 paths, and the path decides how quickly you hear about it.

- An API removal, for example `DELETE /v1/accounts/{id}`, emits immediately with `disconnectionType: "intentional"`.
- A phone-side WhatsApp disconnect (WhatsApp Business app, Settings, Account, Business Platform, Disconnect) emits within seconds of Meta's notification, with `disconnectionType: "unintentional"`. `reason` carries Meta's own explanation where Meta gives one, which separates a deliberate disconnect (`BUSINESS_DOWNGRADE`, `CHANGE_NUMBER`, `USER_RE_REGISTERED`) from Meta dropping an idle number (`PRIMARY_INACTIVITY` after about 14 days, `COMPANION_INACTIVITY` after about 30), and says whether the user or Meta initiated it.
- An expired or revoked grant on any platform surfaces the next time Zernio calls it: a publish or an analytics sync that gets a definitive auth error deactivates the account and emits with `disconnectionType: "unintentional"`. The publish path tries a token refresh first, so the event means the refresh failed too. `reason` carries the platform's own message on that path, and `Your linkedin access token is no longer valid. Please reconnect your account.` when the sync path found it. Reconnect the account to clear it ([connecting accounts](/guides/connecting-accounts)).
- Everything else (a number deleted in WhatsApp Manager, a revoked grant, a channel Meta silently stops serving) is caught by a periodic reconciliation against Meta, not a notification. That path confirms across more than one probe before acting, so it lags by anywhere from minutes to about a day.

### Poll liveness for WhatsApp

Meta's notifications are best-effort, so a disconnect can still fall through to the slower reconciliation path. Do not read the absence of `account.disconnected` as proof a channel is alive. For WhatsApp, poll the [liveness check](/platforms/whatsapp/phone-numbers#liveness-check) (`GET /v1/whatsapp/number-info`) or [account health](/platforms/whatsapp/phone-numbers#other-status-endpoints-and-what-they-actually-tell-you), which runs a live Meta link probe for WhatsApp accounts.

---

## `account.connected`

An account was connected to a profile, through OAuth or credentials. Reconnecting an account you already hold fires it again: the connect flow upserts the row for that profile and platform, so the repeat carries the same `accountId` rather than a new one. Key your own upsert on `accountId` and expect the event more than once per account.

<br />

**Payload for `account.connected`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: account.connected
- **account** (required) `object`: 
  - **accountId** (required) `string`: The account's unique identifier (same as used in /v1/accounts/{accountId})
  - **profileId** (required) `string`: The profile's unique identifier this account belongs to
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

---

## `account.disconnected`

A connected account stopped working or was removed. `disconnectionType` is `intentional` for removals you made and `unintentional` for the rest; see [how disconnect detection works](#how-disconnect-detection-works) for what fills `reason`.

<br />

**Payload for `account.disconnected`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: account.disconnected
- **account** (required) `object`: 
  - **accountId** (required) `string`: The account's unique identifier (same as used in /v1/accounts/{accountId})
  - **profileId** (required) `string`: The profile's unique identifier this account belongs to
  - **platform** (required) `string`: No description
  - **username** (required) `string`: No description
  - **displayName** `string`: No description
  - **disconnectionType** (required) `string`: Whether the disconnection was intentional (user action) or unintentional (token expired/revoked) - one of: intentional, unintentional
  - **reason** (required) `string`: Human-readable reason for the disconnection
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued). Retries and redeliveries keep the original value, so it reflects the event, not the delivery attempt.

## Related

- [Webhooks](/webhooks): create an endpoint, retries, signatures.
- [Connecting accounts](/guides/connecting-accounts): what fires `account.connected`.
- [Account health](/accounts/get-all-accounts-health): `canPost` and `canFetchAnalytics` per account, on demand.
- [Disconnect an account](/accounts/delete-account): the intentional path.
- [WhatsApp phone numbers](/platforms/whatsapp/phone-numbers): the liveness check.

---
