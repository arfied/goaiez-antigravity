import sys
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text += """
## X-201 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G1-08** | Automated Chargebacks | fails | `disputes` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with SPEND_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
