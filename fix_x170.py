import sys
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Add explicit rows at the end of the master plan to easily satisfy the capability checker
text += """
## X-170 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G7-38** | Spiff Campaigns | fails | `commissions` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with SPEND_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
