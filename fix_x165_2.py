import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **N-165-01** | Never authors a price | fails | `none` | fails | a membership price taps through X-163's confirmation — ⛔ X-165 NEVER authors a price · ⛔ **REFUSES with SPEND_REJECTED** | ⑥⑦ inherit |",
    "| **N-165-01** | Never authors a price | fails | `none` | fails | a membership price taps through X-163's confirmation — ⛔ X-165 NEVER authors a price · ⛔ **REFUSES with SPEND_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
