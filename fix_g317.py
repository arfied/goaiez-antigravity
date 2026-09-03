import re

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G3-17 | Competitor Pivot | ENH | X-105 | SPECCED | the battle card lands in the reply |",
    "| G3-17 | Competitor Pivot | ENH | X-105 | SPECCED | the battle card lands in the reply · ⛔ **REFUSES with NO_FACT** |"
)
text = text.replace(
    "| G19-19 | SMS Rebuttal Engine | ENH | X-105 | SPECCED | the battle card drafted into a reply the human sends |",
    "| G19-19 | SMS Rebuttal Engine | ENH | X-105 | SPECCED | the battle card drafted into a reply the human sends · ⛔ **REFUSES with NO_FACT** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G3-17 | Competitor Pivot | ENH | X-105 | the battle card lands in the reply |",
    "| G3-17 | Competitor Pivot | ENH | X-105 | the battle card lands in the reply · ⛔ **REFUSES with NO_FACT** |"
)
text = text.replace(
    "| G19-19 | SMS Rebuttal Engine | ENH | X-105 | the battle card drafted into a reply the human sends |",
    "| G19-19 | SMS Rebuttal Engine | ENH | X-105 | the battle card drafted into a reply the human sends · ⛔ **REFUSES with NO_FACT** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)

