import re

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    '@agent_reachable `action.preview` ⭐',
    '@agent_reachable `action.preview · none` ⭐'
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
