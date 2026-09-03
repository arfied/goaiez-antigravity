with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "`@emits `agent.turn.answer · send.requested` ⭐⭐⭐ *(`R231` 2026-08-27 — **§235 group ④'s largest gap.** Every channel CONSUMES it; **no module declared that it ASKS.** §235: *\"the entry point of the ENTIRE outbound messaging system… asking felt like infrastructure rather than an event, so nobody wrote it down.\"* **A REQUIRED EMISSION on every send path — not one emitter.**)* · `agent.teach` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · `agent.turn.started` ⭐ *(2026-08-27 — `C-Ai` and `X-148` both CONSUME it and NOTHING emitted it; `P-208`: a subscriber to nothing is dead code. My phase-3 \"fix\" replaced prose with a token no one emitted.)* · agent.turn.answer · agent.refused · agent.escalated · objection.detected`",
    "`@emits `agent.turn.answer` · `send.requested` · `agent.teach` · `agent.turn.started` · `agent.refused` · `agent.escalated` · `objection.detected` ⭐⭐⭐ *(`R231` note, `agent.teach` missing, `agent.turn.started` missing fix applied)*`"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
