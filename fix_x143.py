with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text += """
## X-143 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-143-01** | Declared surfaces only | fails | `none` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with SCOPE_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
