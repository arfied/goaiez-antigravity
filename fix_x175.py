with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

import re
text = re.sub(r'(`@emits assistant\.suggested · upsell\.prompted · )`', r'\1note.voice`', text)

replacement = """**TEST ANCHOR** *no output of this module is ever routed to a customer channel — asserted by the absence of any sender import; a SAMPLE price asked on site writes `price.refusal_flagged` and returns the "I'd need to confirm that price" string*

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **N-075** | FieldAssistant Core | fails | `none` | fails | a price on site comes from X-163 or is refused (P-092) · it is C-Agent's third deployment — no second agent exists · it never quotes a customer directly · ⛔ **REFUSES with UNVERIFIED_PRICE** |
| **N-077** | FieldAssistant Core | fails | `none` | fails | a price on site comes from X-163 or is refused (P-092) · it is C-Agent's third deployment — no second agent exists · it never quotes a customer directly · ⛔ **REFUSES with UNVERIFIED_PRICE** |
"""

text = text.replace("**TEST ANCHOR** *no output of this module is ever routed to a customer channel — asserted by the absence of any sender import; a SAMPLE price asked on site writes `price.refusal_flagged` and returns the \"I'd need to confirm that price\" string*", replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
