with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@emits invoice.issued · invoice.paid · `invoice.due` ⭐ *(2026-08-27 — was CONSUMED by `X-198` and emitted by **NOBODY**: a live `P-208` violation in the dangerous direction, found while verifying a different claim. `X-199` owns `invoices` and already emits `invoice.overdue`, so it owns this too.)* · invoice.overdue · limit.exceeded · overflow.charged · overflow.reversed`"
replacement = "`@emits invoice.issued · invoice.paid · invoice.due · invoice.overdue · limit.exceeded · overflow.charged · overflow.reversed`"

text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
