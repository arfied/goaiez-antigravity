with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| ⭐⭐⭐ **G1-32** · **G1-20** · **G1-18** | **The credit ledger** — **one spec** | ⛔ **MONEY** | ⛔ **L1** | ⛔⛔ **float arithmetic drifts and the ledger stops balancing** | ⛔ **INTEGER hundredths of a cent throughout** *(§18)*, asserted by summing a million operations and diffing to zero · **the ledger is the source; the gateway receives period totals** |",
    "| ⭐⭐⭐ **G1-32** · **G1-20** · **G1-18** | **The credit ledger** — **one spec** | ⛔ **MONEY** | ⛔ **L1** | ⛔⛔ **float arithmetic drifts and the ledger stops balancing** | ⛔ **INTEGER hundredths of a cent throughout** *(§18)*, asserted by summing a million operations and diffing to zero · **the ledger is the source; the gateway receives period totals** · ⛔ **REFUSES with CALCULATION_ERROR** |"
)

text = text.replace(
    "| ⭐ **G1-10** | **Automated reconciliation** *(payout vs invoices)* | — | **L3** | a mismatch is silently absorbed | **an unreconciled cent RAISES**, asserted by injecting a one-cent difference |",
    "| ⭐ **G1-10** | **Automated reconciliation** *(payout vs invoices)* | — | **L3** | a mismatch is silently absorbed | **an unreconciled cent RAISES**, asserted by injecting a one-cent difference · ⛔ **REFUSES with RECONCILIATION_FAILED** |"
)

text = text.replace(
    "| **G18-22** · **G19-17** · **G1-13** · **G1-14** | **Auto top-up + monitor** — **one spec** | ⛔ MONEY | ⛔ **L1 to enable, L3 to execute** | ⛔ **a top-up loop drains a card overnight** | **a daily top-up ceiling**, asserted by forcing repeated depletion |",
    "| **G18-22** · **G19-17** · **G1-13** · **G1-14** | **Auto top-up + monitor** — **one spec** | ⛔ MONEY | ⛔ **L1 to enable, L3 to execute** | ⛔ **a top-up loop drains a card overnight** | **a daily top-up ceiling**, asserted by forcing repeated depletion · ⛔ **REFUSES with DAILY_LIMIT_REACHED** |"
)

text = text.replace(
    "| **G1-01** · **G1-56** | **Demo sandbox + trial** — **one spec** | — | **L3** | ⛔ a mock charge reaches the live gateway | ⛔ **`X-198`'s MOCK gateway is asserted unreachable from a live tenant** *(G1-34)* |",
    "| **G1-01** · **G1-56** | **Demo sandbox + trial** — **one spec** | — | **L3** | ⛔ a mock charge reaches the live gateway | ⛔ **`X-198`'s MOCK gateway is asserted unreachable from a live tenant** *(G1-34)* · ⛔ **REFUSES with MOCK_UNREACHABLE** |"
)

text = text.replace(
    "| G19-17 | SMS Auto-Top-Up Matrix | ENH | C-Billing | ⚠️ the $50/5,000 figures are dead — **auto top-up is universal and the amounts live in X-82** (T469–T474) |",
    "| G19-17 | SMS Auto-Top-Up Matrix | ENH | C-Billing | ⚠️ the $50/5,000 figures are dead — **auto top-up is universal and the amounts live in X-82** (T469–T474) · ⛔ **REFUSES with DAILY_LIMIT_REACHED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
