import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "@agent_reachable `qa.score` ⭐ *(`P-209`'s BACKFILL GATE"
replacement = "@agent_reachable `qa.score` · `campaign.start` · `campaign.pause` · `dial.next` · `call.dispose` · `callback.schedule` · `seat.login` · `seat.logout` ⭐ *(`P-209`'s BACKFILL GATE"
text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
