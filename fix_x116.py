import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = re.sub(
    r'(@agent_reachable\s+`template\.score`)\s*⭐',
    r'\1 · `none` ⭐',
    text
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
