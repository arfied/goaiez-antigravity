import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Fix X-82 Agent Reachable
text = re.sub(
    r'(@agent_reachable\s+`rate\.lookup`\s+·\s+`allowance\.lookup`)',
    r'\1 · `rate.set` · `none`',
    text
)

# Fix C-Billing Emits
text = re.sub(
    r'(`@emits `ledger\.period_closed · refund\.issued` ⭐ [^`]+` \· `)',
    r'`@emits ledger.period_closed · refund.issued · credit.debited · credit.exhausted · topup.charged · dunning.advanced · tenant.suspended · subscription.renewed` · `',
    text
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
