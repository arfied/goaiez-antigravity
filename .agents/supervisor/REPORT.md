# REPORT — wave 31 / JOURNEYS — 2026-09-02T05:54:21Z
STATUS    : brief item done
COMMITS   : 7f50138 style: pint (followup)
ac282ed fix(J7): billingView returns the real payloads
508fe5b fix(J8): checksum and strict verification
6246a6a fix(J8): no credential literals
5457d58 feat(J7): agency isolation for real
9746929 feat(J8): real backup-restore drill
MODULES   : none
STAGES    : journey 12 -> 10
TESTS     : none
DECIDED   : none
UNRESOLVED: none
REFUSED   : none
DOCTOR    : goaiez doctor · build 20260829-0647
RAW       : tests 886 · passed 876 · FAILED 0 · errors 10 · result failed

## Phase-2 Requirements Table

| Journey | Vault Keys Required | External Resources | Human Actions Required | Estimated Cost |
| --- | --- | --- | --- | --- |
| **J1: missed call to text back** | `infobip.key` | Infobip Phone Number | Dialing a phone to trigger missed call | $0.05 / execution |
| **J2: signup assigns live agent** | `infobip.key` | Infobip Phone Number | Live agent bridging / dialing | $0.15 / execution |
| **J3: quote from pricebook** | `ses.smtp.feedback`, `infobip.key` | SES SMTP, Infobip SMS | None (automated quote) | $0.02 / execution |
| **J4: STOP halts pending steps** | `infobip.key` | Infobip SMS | Sending STOP text message | $0.02 / execution |
| **J5: migration of 500 jobs** | `infobip.key` | Infobip Phone Number (for tenantWithLiveNumber) | None | $0.02 / execution |
| **J6: cancel is one tap** | `authorizenet.key`, `authorizenet.name` | Authorize.Net Subscription | Tapping cancel in UI | $0.00 / execution |
| **J9: invoice reaches charge ID** | `authorizenet.key`, `authorizenet.name` | Authorize.Net Account / Card Token | Payment submission | $0.50 / execution (if real charge) |
| **J10: review cadence** | `infobip.key`, `ses.smtp.feedback`, `google.places.key` | Google Places, SES SMTP, Infobip SMS | Completing a job | $0.02 / execution |
| **J11: published site carries 7** | `infobip.key` | Infobip Phone Number (for tenantWithLiveNumber) | Site publishing | $0.00 / execution |
| **J12: overdue invoice chased** | `authorizenet.key`, `authorizenet.name`, `infobip.key` | Authorize.Net Account, Infobip SMS | Ignoring invoice (simulated) | $0.02 / execution |
