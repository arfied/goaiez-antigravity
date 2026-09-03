import re

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G12-05 | Auto-Publishing | ENH | X-183 | SPECCED | to the builder or the plugin |",
    "| G12-05 | Auto-Publishing | ENH | X-183 | SPECCED | to the builder or the plugin · ⛔ **REFUSES with UNATTENDED_LOCKED** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G12-05 | Auto-Publishing | ENH | X-183 | to the builder or the plugin |",
    "| G12-05 | Auto-Publishing | ENH | X-183 | to the builder or the plugin · ⛔ **REFUSES with UNATTENDED_LOCKED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)

