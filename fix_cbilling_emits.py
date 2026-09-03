with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if line.startswith("`@provides ledger.debit"):
        lines[i] = "`@provides ledger.debit · ledger.grant · topup.charge · dunning.advance · ledger.explain` · `@emits ledger.period_closed · refund.issued · credit.debited · credit.exhausted · topup.charged · dunning.advanced · tenant.suspended · subscription.renewed` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · `@consumes payment.captured · payment.failed · subscription.renewed` · `@owns_table credit_ledger_entries · meters · trial_limits · dunning_states`\n"

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.writelines(lines)
