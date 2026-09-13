# Phone Numbers

Read a WhatsApp number's live status from Meta, tell a dead channel from a live one, and register a number whose two-step PIN blocked it.

import { Tab, Tabs } from 'fumadocs-ui/components/tabs';

When you finish this page you can tell from the API whether a connected WhatsApp number is alive, read its live state from Meta, and re-register a number whose two-step PIN blocked it. A WhatsApp number is a [phone number](/platforms/phone-numbers) with WhatsApp enabled on it, so availability, pricing, buying, porting and KYC live on [Phone numbers](/platforms/phone-numbers) and getting one is covered there and in [Connection & Setup](/platforms/whatsapp/connection); `/v1/phone-numbers` is the current API and the `/v1/whatsapp/phone-numbers/*` routes are deprecated aliases with the same contracts.

## Number status

Call `GET /v1/whatsapp/number-info` with `accountId` for the number's live state from Meta: display name and its approval, quality rating, messaging-limit tier, throughput, the official-business badge, connection status, and the owning WABA's verification status.

<Tabs items={['Node.js', 'Python', 'curl']}>
<Tab value="Node.js">
```typescript
import Zernio from '@zernio/node';

const zernio = new Zernio();
const accountId = '66b2e19d8c3f5a7e9d0b1c2d';

const { data: info } = await zernio.whatsapp.getWhatsAppNumberInfo({
  query: { accountId }
});

console.log(info.phone.quality_rating, info.phone.messaging_limit_tier);
```
</Tab>
<Tab value="Python">
```python
from zernio import Zernio

client = Zernio()
account_id = "66b2e19d8c3f5a7e9d0b1c2d"

info = client.whatsapp.get_whats_app_number_info(account_id=account_id)

print(info["phone"]["quality_rating"], info["phone"]["messaging_limit_tier"])
```
</Tab>
<Tab value="curl">
```bash
curl "https://zernio.com/api/v1/whatsapp/number-info?accountId=66b2e19d8c3f5a7e9d0b1c2d" \
  -H "Authorization: Bearer $ZERNIO_API_KEY"
```
</Tab>
</Tabs>

Response (`200`):

```json
{
  "phone": {
    "display_phone_number": "+1 310-555-1234",
    "verified_name": "Acme Corp",
    "name_status": "APPROVED",
    "quality_rating": "GREEN",
    "messaging_limit_tier": "TIER_1K",
    "throughput": { "level": "STANDARD" },
    "status": "CONNECTED",
    "is_official_business_account": false,
    "platform_type": "CLOUD_API"
  },
  "waba": {
    "name": "Acme WABA",
    "business_verification_status": "verified",
    "timezone_id": "1"
  }
}
```

`messaging_limit_tier` is the daily unique-contact cap Meta has granted (`TIER_250` for a new number, up to `TIER_UNLIMITED`); `quality_rating` is `GREEN`, `YELLOW`, `RED` or `UNKNOWN`. The call also refreshes the cached values shown on the connection card.

<Callout type="warn">
A number you bring has no Zernio line behind it, so [Calls](/platforms/voice) and [SMS](/platforms/sms) cannot be enabled on it; `GET /v1/phone-numbers` lists it under `connected`, not `numbers`. To run Calls or SMS alongside WhatsApp, [buy the number through Zernio](/platforms/phone-numbers/provisioning).
</Callout>

## Liveness check

`GET /v1/whatsapp/number-info` is the supported way to check whether a WhatsApp channel is alive, because it reads the channel object from Meta at request time. While the channel works, the read returns the snapshot above. When Meta stops serving the object, for example after a [phone-side coexistence disconnect](/platforms/whatsapp/connection#whatsapp-business-app-coexistence), the read fails with Meta Graph error 100, subcode 33, and the endpoint returns `400`:

```json
{
  "error": "Meta will not let Zernio read this WhatsApp object (error 100, subcode 33). Most often the number or its Business Account is no longer shared with Zernio, so reconnect your WhatsApp Business account from Connections."
}
```

In measured disconnects this flips within seconds of the channel dying and does not flap: treat a `400` with error 100/33 as "dead until reconnected" and a `200` as "the channel object is being served". A live read also refreshes Zernio's cached view of the account, so polling it keeps `platformStatus` in `GET /v1/accounts` current.

### Other status endpoints, and what they actually tell you

| Endpoint | What it reflects | Liveness check? |
|---|---|---|
| `GET /v1/whatsapp/number-info` | Live read of the channel object from Meta | Yes. Fails with error 100/33 when the channel is dead |
| `GET /v1/whatsapp/business-profile` | Live read of profile metadata (about, address, websites) | No. It returns `200` with a full profile even on a dead channel; a `200` says Meta served the profile, not that the number can send or receive |
| `GET /v1/accounts/{accountId}/health` | OAuth token validity and granted scopes, plus, for WhatsApp accounts, a live `platformConnection` probe of the Meta link | Yes, through `platformConnection` |
| `GET /v1/accounts` | Last stored state (`platformStatus`, `metadata.metaStatus`), refreshed by a periodic probe | No. It is a cache and can be hours or days stale |

For WhatsApp accounts, `GET /v1/accounts/{accountId}/health` runs the same live Meta read as `number-info` and reports it under `platformConnection`:

- `status`: `connected` (Meta served the channel object), `disconnected` (Meta refused it with error 100/33, and the overall health `status` becomes `error`), or `unknown` (the live read failed for another reason, such as a timeout, which is not evidence either way).
- `checkedAt`: when the probe ran, always the current request.
- `phoneStatus`: Meta's own `status` field from the phone-number node (for example `CONNECTED`) when it was readable.
- `metaError`: the Meta error detail (`code`, `subcode`, `message`) when `status` is `disconnected`.

The token can be valid while the Meta link is dead, so `tokenStatus` alone is never a liveness signal.

The cached `platformStatus` in `GET /v1/accounts` is sticky: a periodic probe and live reads refresh it, and it can hold a stale value such as `restricted` days after the problem was resolved. Before alarming on it, compare it with `metadata.metaStatusCheckedAt`, which records when the cached state was last verified against Meta. A `metaStatusCheckedAt` far in the past means "re-verify with a live read", not "the channel is down".

## Verification with Meta

WhatsApp requires the number to be verified with Meta before it can send, and there is no manual OTP endpoint: numbers bought through Zernio are pre-verified or verified during the [connection flow](/platforms/whatsapp/connection), and numbers you bring are verified inside Meta's own flow.

One case after connecting needs an API call. If the number has its own two-step verification PIN, the connect flows register it with a default PIN, Meta rejects that (error 133005), and every send then fails with a misleading `(#200) You do not have the necessary permission to send messages` while the account still shows as connected. Re-run the Cloud API registration with the right PIN through `POST /v1/accounts/{accountId}/whatsapp/register`; the PIN is used for that call only and is not stored.

```bash
curl -X POST "https://zernio.com/api/v1/accounts/66b2e19d8c3f5a7e9d0b1c2d/whatsapp/register" \
  -H "Authorization: Bearer $ZERNIO_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"pin": "481902"}'
```

Response (`200`):

```json
{
  "registered": true,
  "accountId": "66b2e19d8c3f5a7e9d0b1c2d",
  "phoneNumberId": "1875844705851813"
}
```

## Common errors

| Error | Cause | Fix |
|-------|-------|-----|
| `400` from `GET /v1/whatsapp/number-info`, Meta error 100 subcode 33 | Meta no longer serves the channel object, so the number or its WABA is not shared with Zernio any more | Reconnect from Connections, then re-run the [liveness check](#liveness-check) |
| `422` from `POST /v1/accounts/{accountId}/whatsapp/register` | Meta rejected the registration, most often a PIN mismatch | Check the PIN in the WhatsApp Business app or WhatsApp Manager and call again |
| `(#200) You do not have the necessary permission` on every send, account still shown as connected | The connect flow registered a default PIN and Meta rejected it (error 133005) | Re-register with the right PIN ([Verification with Meta](#verification-with-meta)) |
| `platformStatus` reads `restricted` long after the problem was fixed | The cached value is sticky and only a probe refreshes it | Compare `metadata.metaStatusCheckedAt`, then re-verify with a live `number-info` read |

Send-time codes are on [Limits and errors](/platforms/whatsapp/reference#common-errors), and every error uses the envelope in [error handling](/guides/error-handling).

## Related

- [Phone numbers](/platforms/phone-numbers): buy, port, price and KYC.
- [Connection & Setup](/platforms/whatsapp/connection): Embedded Signup, coexistence, credentials.
- [Calling](/platforms/whatsapp/calling): WhatsApp Business voice calling on the number.
- [Account webhooks](/webhooks/accounts): `account.disconnected` and how detection works.
- [Phone number webhooks](/webhooks/phone-numbers): activation, decline and suspension events.

---
