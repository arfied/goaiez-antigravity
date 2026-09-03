import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "``@consumes `capability.decided` ⭐⭐⭐ *(**`R235` `N-235-04` 2026-08-27 — THE GATE THAT MAKES SHIPPING-ON SAFE.** `R235` turned 102 autopilots ON; **`X-126`'s gate is what supervises them.** ⛔⛔ ***No grounding `Fact` → no skill*** — an agent action invoked without grounding is REFUSED with `NO_FACT`, so the autopilot **cannot invent a price, a time or a link.** ⭐ *This is what \"AI watching AI\" means mechanically — **autonomy without the gate is nothing watching anything.***)* · `cart.checkout`` ⭐ *(2026-08-27, owner-confirmed — a promotion is APPLIED at checkout. ⛔ NOT `campaign.sent` — campaigns DISTRIBUTE a promotion; they do not trigger one.)* · @owns_table promotions · promotion_redemptions · promotion_scopes`"

replacement = "`@consumes `capability.decided` ⭐⭐⭐ *(**`R235` `N-235-04` 2026-08-27 — THE GATE THAT MAKES SHIPPING-ON SAFE.** `R235` turned 102 autopilots ON; **`X-126`'s gate is what supervises them.** ⛔⛔ ***No grounding `Fact` → no skill*** — an agent action invoked without grounding is REFUSED with `NO_FACT`, so the autopilot **cannot invent a price, a time or a link.** ⭐ *This is what \"AI watching AI\" means mechanically — **autonomy without the gate is nothing watching anything.***)* · `cart.checkout` ⭐ *(2026-08-27, owner-confirmed — a promotion is APPLIED at checkout. ⛔ NOT `campaign.sent` — campaigns DISTRIBUTE a promotion; they do not trigger one.)*` · `@owns_table promotions · promotion_redemptions · promotion_scopes`"

text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
