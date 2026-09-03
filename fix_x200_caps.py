import re

# Fix Tracker
with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = re.sub(r'(\| G5-09 \|[^|]+\|[^|]+\| X-200 \| SPECCED \|[^|]+)(\|)', r'\1 · ⛔ **REFUSES with BAD_STATE** \2', text)
text = re.sub(r'(\| G11-25 \|[^|]+\|[^|]+\| X-200 \| SPECCED \|[^|]+)(\|)', r'\1 · ⛔ **REFUSES with BAD_STATE** \2', text)
text = re.sub(r'(\| G18-08 \|[^|]+\|[^|]+\| X-200 \| SPECCED \|[^|]+)(\|)', r'\1 · ⛔ **REFUSES with BAD_STATE** \2', text)
text = re.sub(r'(\| G21-13 \|[^|]+\|[^|]+\| X-200 \| SPECCED \|[^|]+)(\|)', r'\1 · ⛔ **REFUSES with BAD_STATE** \2', text)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)

# Fix Master Plan
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = re.sub(r'(\| G5-09 \|[^|]+\|[^|]+\| X-200 \|[^|]+)(\|)', r'\1 · ⛔ **REFUSES with BAD_STATE** \2', text)
text = re.sub(r'(\| G11-25 \|[^|]+\|[^|]+\| X-200 \|[^|]+)(\|)', r'\1 · ⛔ **REFUSES with BAD_STATE** \2', text)
text = re.sub(r'(\| G18-08 \|[^|]+\|[^|]+\| X-200 \|[^|]+)(\|)', r'\1 · ⛔ **REFUSES with BAD_STATE** \2', text)
text = re.sub(r'(\| G21-13 \|[^|]+\|[^|]+\| X-200 \|[^|]+)(\|)', r'\1 · ⛔ **REFUSES with BAD_STATE** \2', text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)

