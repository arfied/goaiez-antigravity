# Related Schema Definitions

## Webhook

Individual webhook configuration for receiving real-time notifications

### Properties

- **_id** `string`: Unique webhook identifier
- **name** `string`: Webhook name (for identification) (max: 50)
- **url** `string`: Webhook endpoint URL
- **secret** `string`: Secret key for HMAC-SHA256 signature verification.
- **events** `array`: Events subscribed to
- **isActive** `boolean`: Whether webhook delivery is enabled
- **lastFiredAt** `string`: Timestamp of last successful webhook delivery
- **failureCount** `integer`: Consecutive terminal delivery failures (resets to 0 on any successful delivery). Auto-disable only triggers when the endpoint has had no successful delivery within a 3-day window AND either reaches 20 consecutive terminal failures or has been failing continuously for 3 days; any success within that window keeps the endpoint enabled regardless of the count.
- **customHeaders** `object`: Custom headers included in webhook requests
- **disabledResourceGroups** `array`: Resource groups this subscription does not receive (opt-out denylist, same vocabulary and same semantics as the field on API keys). Absent or empty means the subscription receives every event listed in `events`, which is how every subscription created before this field existed behaves. An event whose group is listed here is dropped before delivery even when it is still present in `events`, and the same check runs on every replay path (test fire, redelivery, dead-letter requeue). Editing the denylist applies to every event emitted afterwards; events already queued when the edit landed can still be delivered for up to five minutes after they were enqueued.

---
