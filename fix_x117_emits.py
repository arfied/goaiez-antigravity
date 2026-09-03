with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if line.startswith("**DECLARATIONS** `@provides cart.build"):
        lines[i] = "**DECLARATIONS** `@provides cart.build · cart.checkout · order.cancel` · `@emits inventory.updated · cart.checkout · deposit.captured · order.paid · order.cancelled · shipping.requested` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · `@consumes capability.decided · pricebook.updated · appointment.booked · payment.captured · inventory.updated` ⭐⭐⭐ *(R235 N-235-04)* · `@owns_table sellables · orders · order_lines · carts`\n"

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.writelines(lines)
