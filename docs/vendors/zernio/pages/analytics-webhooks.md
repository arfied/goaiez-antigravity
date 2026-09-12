# Analytics webhooks

Receive a cursor each time an account's analytics sync completes, then read every changed post in one call to the delta feed.

`analytics.synced` tells you that an account's analytics changed and hands you the cursor to read what changed from `GET /v1/analytics/delta`. Subscribe with `POST /v1/webhooks/settings` and `events: ["analytics.synced"]` ([first event](/webhooks#first-event)); delivery, retries and signatures are the same for every event ([how webhooks behave](/webhooks#how-it-behaves)).

## Events

| Event | Description |
| --- | --- |
| [`analytics.synced`](#analyticssynced) | One connected account's analytics sync cycle completed successfully. Carries a cursor for the delta feed, not metrics. |

## How it behaves

### The payload is a cursor for the delta feed

Zernio sends no metrics in this event. It says an account's analytics changed; the delta feed says what changed. `sync.cursor` is an opaque [`GET /v1/analytics/delta`](/analytics/get-analytics-delta) cursor positioned immediately before this cycle's rows. Pass it straight to the delta feed and one call returns every post whose analytics changed, across every account you can see. That replaces polling `GET /v1/analytics` once per connected account, where a fleet of 1,000 accounts costs 1,000 requests an hour.

The two are designed to be used together:

1. Bootstrap your baseline once from [`GET /v1/analytics`](/analytics/get-analytics).
2. Receive `analytics.synced`.
3. Call [`GET /v1/analytics/delta`](/analytics/get-analytics-delta) with the cursor you were given, then keep sending back each `nextCursor`.

If you would rather not run a webhook consumer, skip this event and poll the delta feed on a timer. The feed is the source of truth either way.

### An empty page does not mean nothing changed

Zernio serves the delta feed from a materialized view, so a read issued the instant this event lands can return an empty `data` array while the rows are still landing. Re-poll with the same cursor rather than advancing. Advancing on an empty page is the one way to lose changes permanently.

### Volume scales with your fleet

Zernio fires this event per account, per successful sync cycle, and sync runs roughly hourly per account. An integration with 1,000 connected accounts should expect on the order of 1,000 events an hour. Two consequences:

- Subscribe on a dedicated webhook endpoint. An endpoint's consecutive-failure count is shared by every event on it, so an outage on this busy event can [disable](/webhooks#delivery-retries) the whole endpoint and silence low-volume events like `post.published` with it.
- Return `2xx` first, then do the delta read out of band.

### When it does not fire

Zernio skips the event when the sync cycle fails or is skipped because the account was synced recently, when the account is inactive or disconnected, and when the platform does not support analytics sync. [`GET /v1/analytics`](/analytics/get-analytics) lists the supported set.

---

## `analytics.synced`

One connected account's analytics sync cycle completed successfully. `sync.cursor` is the delta-feed cursor for this cycle.

<br />

**Payload for `analytics.synced`:**

- **id** (required) `string`: Stable webhook event ID
- **event** (required) `string`: No description - one of: analytics.synced
- **account** (required) `object`: 
  - **accountId** (required) `string`: The account's unique identifier (same as used in /v1/accounts/{accountId})
  - **profileId** (required) `string`: The profile this account belongs to
  - **platform** (required) `string`: No description (example: "youtube")
  - **username** (required) `string`: No description
- **sync** (required) `object`: Summary of the analytics sync cycle that completed.
  - **syncedAt** (required) `string` (date-time): When the cycle COMPLETED. Not a join key for the delta feed: the rows a
cycle produces carry a `syncedAt` stamped when the cycle STARTED, which
is measured at around one second earlier at the median and up to a
couple of minutes earlier in the tail. Correlate on `account.accountId`.

  - **postsUpdated** (required) `integer`: Post records created or modified by this cycle. Not the number of delta
feed rows the cycle produced, which the syncer does not report, so a
cycle with a non-zero `postsUpdated` can still yield an empty delta page.
 (example: 42)
- **timestamp** (required) `string` (date-time): UTC time at which Zernio generated this event (set once when the event payload is built, before delivery is queued).

## Related

- [Webhooks](/webhooks): create an endpoint, retries, signatures.
- [Analytics delta feed](/analytics/get-analytics-delta): the endpoint the cursor points at.
- [Get analytics](/analytics/get-analytics): the baseline read.
- [Multi-tenant analytics](/multi-tenant/analytics): per-customer dashboards on top of the feed.

---
