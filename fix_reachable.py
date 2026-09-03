with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

import re
text = re.sub(r'@agent_reachable none\n\n@agent_reachable `retrieval\.search`[^\n]*', '@agent_reachable none', text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
