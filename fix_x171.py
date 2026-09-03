import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| ⭐⭐⭐ **G4-26** | **X-171 — offline, local-first** — ⭐ **§197.4 is this row** | OWN-DAY | **L2** | ⛔⛔ **last-write-wins discards a technician's real work** | ⭐⭐ **THE FIELD wins on OBSERVATIONS, THE OFFICE on ASSIGNMENTS** — asserted with a fixture where both changed · **client-generated ids make replay idempotent** *(sync twice → one row)* · **device clock ORDERS, server time RECORDS** · ⛔ **`doctor` asserts NO model call in the reconciliation path** |",
    "| ⭐⭐⭐ **G4-26** | **X-171 — offline, local-first** — ⭐ **§197.4 is this row** | OWN-DAY | **L2** | ⛔⛔ **last-write-wins discards a technician's real work** | ⭐⭐ **THE FIELD wins on OBSERVATIONS, THE OFFICE on ASSIGNMENTS** — asserted with a fixture where both changed · **client-generated ids make replay idempotent** *(sync twice → one row)* · **device clock ORDERS, server time RECORDS** · ⛔ **`doctor` asserts NO model call in the reconciliation path** · ⛔ **REFUSES with SYNC_REJECTED** |"
)
text = text.replace(
    "| G4-26 | Offline Mode - Local-First | ENH | X-171 | offline-first is the premise |",
    "| G4-26 | Offline Mode - Local-First | ENH | X-171 | offline-first is the premise · ⛔ **REFUSES with SYNC_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
