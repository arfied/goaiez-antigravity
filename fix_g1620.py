import re

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G16-20 | Rich Media Hub | ENH | X-114 | SPECCED | transcoding to each channel's limits |",
    "| G16-20 | Rich Media Hub | ENH | X-114 | SPECCED | transcoding to each channel's limits · ⛔ **REFUSES with OVER_LIMIT** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G16-20 | Rich Media Hub | ENH | X-114 | transcoding to each channel's limits |",
    "| G16-20 | Rich Media Hub | ENH | X-114 | transcoding to each channel's limits · ⛔ **REFUSES with OVER_LIMIT** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
