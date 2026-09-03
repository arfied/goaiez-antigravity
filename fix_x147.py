with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text += """
## X-147 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-147-01** | Degrade is recorded | fails | `rcs_capabilities` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with DEGRADE_SILENT** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
