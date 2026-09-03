with open('app/GOAIEZ-MASTER-PLAN.md', 'a') as f:
    f.write("""
## 169.A X-204 `ConsentService` — CAPABILITY TABLE

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-013** | A push is still a SEND | a push is still a SEND | `send_permits` | the build passes | it passes X-204 and the cadence ceiling - asserted | ⑥⑦ inherit · ⛔ **REFUSES with CADENCE_EXCEEDED** |
""")
