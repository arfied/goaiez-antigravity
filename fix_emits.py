with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Fix X-103
t1 = "`@emits `approval.requested` ⭐⭐⭐ *(2026-08-27 — **the `send.requested` shape, applied to approvals.** `X-202` is the ONE queue every L1 decision lands in, from all 122 modules. It cannot learn five `*.proposed` names one at a time — **that would mean a code change in `X-202` every time a module gains a proposal.** So every PROPOSING module emits this, exactly as every SENDING module emits `send.requested`. ⛔ This module was identified as a proposer because it already emits a `*.proposed` event — measured, not assumed.)* · page.published · site.published · funnel.completed · optimiser.proposed`"
r1 = "`@emits approval.requested · page.published · site.published · funnel.completed · optimiser.proposed` ⭐⭐⭐ *(2026-08-27 — **the `send.requested` shape, applied to approvals.** `X-202` is the ONE queue every L1 decision lands in, from all 122 modules. It cannot learn five `*.proposed` names one at a time — **that would mean a code change in `X-202` every time a module gains a proposal.** So every PROPOSING module emits this, exactly as every SENDING module emits `send.requested`. ⛔ This module was identified as a proposer because it already emits a `*.proposed` event — measured, not assumed.)*"

text = text.replace(t1, r1)

# Fix X-125
t2 = "`@emits `flow.changed` · `rule.changed` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · flow.triggered · flow.completed · flow.branched · flow.paused` · `"
r2 = "`@emits flow.changed · rule.changed · flow.triggered · flow.completed · flow.branched · flow.paused · rule.fired` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · `"

text = text.replace(t2, r2)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
