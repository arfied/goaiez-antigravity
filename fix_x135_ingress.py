import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@emits research.completed · icebreaker.generated · signal.found` · `"
replacement = "`@emits research.completed · icebreaker.generated · signal.found`"
text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
