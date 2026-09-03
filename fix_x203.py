with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G4-23** · **G13-07** | **Multi-region + cold-storage hashing** — **one spec** | — | **L3** | a cold archive is written and never verified | **the hash is asserted re-verified on a schedule**, not only at write |",
    "| **G4-23** · **G13-07** | **Multi-region + cold-storage hashing** — **one spec** | — | **L3** | a cold archive is written and never verified | **the hash is asserted re-verified on a schedule**, not only at write · ⛔ **REFUSES with VERIFICATION_REJECTED** |"
)
text = text.replace(
    "| ⭐⭐ **G21-03** | **Runbook automation** | — | **L2** | ⛔ a runbook automates a step nobody has ever done manually | ⭐ **A-7's rule: rollback is REHEARSED before it is automated** — asserted that each automated step has a recorded manual run |",
    "| ⭐⭐ **G21-03** | **Runbook automation** | — | **L2** | ⛔ a runbook automates a step nobody has ever done manually | ⭐ **A-7's rule: rollback is REHEARSED before it is automated** — asserted that each automated step has a recorded manual run · ⛔ **REFUSES with REHEARSAL_REJECTED** |"
)

text = text.replace(
    "| G13-07 | Cold Storage Hashing | ENH | X-203 | **the minted desk's first mechanism — a restore that cannot prove itself is not a backup** |",
    "| G13-07 | Cold Storage Hashing | ENH | X-203 | **the minted desk's first mechanism — a restore that cannot prove itself is not a backup** · ⛔ **REFUSES with VERIFICATION_REJECTED** |"
)
text = text.replace(
    "| G21-03 | Runbook Automation | ENH | X-203 | **the minted desk's second mechanism** — the scripted response to a failure, with its own state |",
    "| G21-03 | Runbook Automation | ENH | X-203 | **the minted desk's second mechanism** — the scripted response to a failure, with its own state · ⛔ **REFUSES with REHEARSAL_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
