import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@emits `fetch.requested` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · fetch.completed · fetch.blocked · fetch.stale · prospect.discovered`"
replacement = "`@emits fetch.requested · fetch.completed · fetch.blocked · fetch.stale · prospect.discovered`"

text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
