import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@emits `call.requested` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · call.dialed · call.connected · call.disposed · callback.due · seat.state_changed · abandon.ceiling_near` · `"
replacement = "`@emits `call.requested` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · call.dialed · call.connected · call.disposed · callback.due · seat.state_changed · abandon.ceiling_near` · `@consumes capability.decided` · `@owns_table callcenter_campaigns · callcenter_seats · qa_scorecards`"
text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
