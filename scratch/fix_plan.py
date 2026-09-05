import re

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    content = f.read()

# Restore ships:
content = content.replace('`@ships advice`', '`ships: advice`')
content = content.replace('`@ships n/a`', '`ships: n/a`')
content = content.replace('`@ships seed mail only`', '`ships: seed mail only`')

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(content)
