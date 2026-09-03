import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@emits extension.triggered · prospect.injected · extension.aborted` · `"
replacement = "`@emits extension.triggered · prospect.injected · extension.aborted`"
text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
