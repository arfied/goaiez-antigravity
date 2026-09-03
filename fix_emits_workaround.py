with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Fix X-82
t1 = "`@emits rate.changed · allowance.granted`"
r1 = "`@emits rate.changed · allowance.granted · country.detected`"
text = text.replace(t1, r1)

# Fix C-Billing
t2 = "`@emits ledger.period_closed · refund.issued`"
r2 = "`@emits ledger.period_closed · refund.issued · subscription.renewed`"
text = text.replace(t2, r2)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
