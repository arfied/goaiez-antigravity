with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Fix X-82 @agent_reachable
t_agent = "`@agent_reachable `rate.lookup` · `allowance.lookup` ⭐"
r_agent = "`@agent_reachable rate.lookup · allowance.lookup · none` ⭐"
text = text.replace(t_agent, r_agent)

# Fix @ingress country.detected in X-82
import re
text = re.sub(
    r'(`@ingress country\.detected <api>`)\s*⭐\s*\*\([^)]*\)\*',
    r'\1',
    text
)

# Fix @scheduled subscription.renewed in C-Billing
text = re.sub(
    r'(`@scheduled subscription\.renewed <daily> @owner C-Billing`)\s*⭐\s*\*\([^)]*\)\*',
    r'\1',
    text
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
