# Related Schema Definitions

## WebhookLog

A single webhook delivery attempt recorded by Zernio (30-day retention).

### Properties

- **userId** `string`: ID of the account owner the webhook belongs to
- **webhookId** `string`: ID of the webhook configuration that produced this delivery
- **webhookName** `string`: Name of the webhook configuration at delivery time
- **eventId** `string`: Stable webhook event ID: the payload `id`, also sent as the X-Zernio-Event-Id header. Shared by every attempt and redelivery of the same event.
- **event** `string`: Event type that triggered the delivery (e.g. post.published)
- **url** `string`: Destination URL the webhook was delivered to
- **status** `string`: Delivery outcome - one of: success, failed
- **statusCode** `integer`: HTTP status code returned by the destination endpoint
- **requestPayload** `object`: The JSON payload sent to the destination endpoint
- **responseBody** `string`: Response body returned by the destination endpoint
- **errorMessage** `string`: Error message when delivery failed
- **attemptNumber** `integer`: Delivery attempt number (increments on retries)
- **responseTime** `integer`: Time taken by the destination endpoint to respond, in milliseconds
- **createdAt** `string`: Timestamp the delivery was attempted

---
