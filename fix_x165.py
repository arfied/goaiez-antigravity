with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text += """
## X-165 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-165-01** | Never authors a price | fails | `none` | fails | a membership price taps through X-163's confirmation — ⛔ X-165 NEVER authors a price · ⛔ **REFUSES with SPEND_REJECTED** | ⑥⑦ inherit |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
