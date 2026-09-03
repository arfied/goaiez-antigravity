import re

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G12-10 | Best Time to Post | ENH | X-182 | SPECCED | from the tenant's own engagement history |",
    "| G12-10 | Best Time to Post | ENH | X-182 | SPECCED | from the tenant's own engagement history · ⛔ **REFUSES with GLOBAL_AVERAGE_FALLBACK** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G12-10 | Best Time to Post | ENH | X-182 | from the tenant's own engagement history |",
    "| G12-10 | Best Time to Post | ENH | X-182 | from the tenant's own engagement history · ⛔ **REFUSES with GLOBAL_AVERAGE_FALLBACK** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)

