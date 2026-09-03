with open('app/GOAIEZ-MASTER-PLAN.md', 'a') as f:
    f.write("""
## C-Billing explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G1-52** | Stripe Metered Billing Sync | the ledger is the source | `meters` | a transaction fails | the ledger is the source | ⑥⑦ inherit · ⛔ **REFUSES with SYNC_FAILED** |
| **G1-78** | Pre-Paid Credit Ledger | Flow B's credit block | `credit_ledger_entries` | a transaction fails | Flow B's credit block | ⑥⑦ inherit · ⛔ **REFUSES with BALANCE_LOW** |
| **G1-83** | Trial Expiration Logic | §197.3 NOTICE BEFORE CHARGE | `trial_limits` | a transaction fails | §197.3 NOTICE BEFORE CHARGE | ⑥⑦ inherit · ⛔ **REFUSES with NO_NOTICE** |
| **G1-01** | 100-Credit Demo Sandbox | Twilio/tokens | `credit_ledger_entries` | a transaction fails | Twilio/tokens | ⑥⑦ inherit · ⛔ **REFUSES with MOCK_LIVE** |
| **G1-10** | Automated Reconciliation | nightly reconciler | `credit_ledger_entries` | a transaction fails | nightly reconciler | ⑥⑦ inherit · ⛔ **REFUSES with CENT_MISMATCH** |
| **G1-18** | Credit Deduction Sync | every AI call writes cent-precision cost | `credit_ledger_entries` | a transaction fails | every AI call writes cent-precision cost | ⑥⑦ inherit · ⛔ **REFUSES with DEDUCTION_FAILED** |
| **G1-20** | Credit Ledger Check | pre-spend estimate | `credit_ledger_entries` | a transaction fails | pre-spend estimate | ⑥⑦ inherit · ⛔ **REFUSES with CHECK_FAILED** |
| **G19-17** | SMS Auto-Top-Up Matrix | amounts live in X-82 | `credit_ledger_entries` | a transaction fails | amounts live in X-82 | ⑥⑦ inherit · ⛔ **REFUSES with TOPUP_FAILED** |
""")
