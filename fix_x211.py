with open('app/GOAIEZ-MASTER-PLAN.md', 'a') as f:
    f.write("""
## X-211 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-033** | a fee with no matching TERM is refused | fails | `receivable_states` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with NO_TERM** |
| **G1-70** | Dynamic Payment Plans | fails | `payment_plans` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with PLAN_REJECTED** |
| **G1-71** | Late Fee Automation | fails | `receivable_states` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with FEE_REJECTED** |
| **G1-74** | Offline Payment Logging | fails | `offline_payments` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with OFFLINE_REJECTED** |
""")
