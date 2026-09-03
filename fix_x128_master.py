with open('app/GOAIEZ-MASTER-PLAN.md', 'a') as f:
    f.write("""
## 169.Z X-128 `IntegrationMatrix` — CAPABILITY TABLE

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-049** | Matrix failure | an event with no subscriber fails the build | `integration_matrix` | the build passes | an event with no subscriber fails the build | ⑥⑦ inherit · ⛔ **REFUSES with BUILD_FAILED** |
""")
