with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if line.startswith("`@provides invoice.draft"):
        lines[i] = "`@provides invoice.draft · invoice.issue · invoice.record_offline · terms.set` · `@emits invoice.issued · invoice.paid · invoice.due · invoice.overdue · limit.exceeded · overflow.charged · overflow.reversed` ⭐ *(2026-08-27 — was CONSUMED by `X-198` and emitted by NOBODY: a live `P-208` violation in the dangerous direction. `X-199` owns invoices and emits this.)* · `@consumes job.completed · payment.captured · subscription.renewed · ledger.period_closed` · `@owns_table invoices · invoice_lines · credit_terms · overflow_charges`\n"

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.writelines(lines)
