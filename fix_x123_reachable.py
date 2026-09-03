import re

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Replace the first agent_reachable none with agent_reachable none · webhook.test
text = text.replace(
    '`@provides event.publish · event.replay · webhook.test` · `@agent_reachable none`',
    '`@provides event.publish · event.replay · webhook.test` · `@agent_reachable none · webhook.test`'
)

# Remove the second agent_reachable webhook.test
text = text.replace(
    '@agent_reachable `webhook.test` ⭐ *(`P-209`\'s BACKFILL GATE',
    '⭐ *(`P-209`\'s BACKFILL GATE'
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
