with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

import re
text = re.sub(r'(`@emits document\.ingested · document\.reviewed · )`', r'\1upload.received`', text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
