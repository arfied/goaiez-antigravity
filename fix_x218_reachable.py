import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "@agent_reachable `influencer.discover` ⭐ *(`P-209`'s BACKFILL GATE"
replacement = "@agent_reachable `influencer.discover` · `influencer.outreach` · `influencer.deal` · `influencer.deliverable` ⭐ *(`P-209`'s BACKFILL GATE"
text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
