with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

import re
text = re.sub(r'(`@emits gbp\.posted · gbp\.question_answered · gbp\.suspension_risk · gbp\.suspended · gbp\.reinstated)` · `', r'\1 · zernio.webhook`', text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
