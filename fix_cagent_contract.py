with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "`@emits `send.requested` ⭐⭐⭐ *(`R231` 2026-08-27 — **§235 group ④'s largest gap.** Every channel CONSUMES it; **no module declared that it ASKS.** §235: *\"the entry point of the ENTIRE outbound messaging system… asking felt like infrastructure rather than an event, so nobody wrote it down.\"* **A REQUIRED EMISSION on every send path — not one emitter.**)* · message.sent · message.delivered · message.failed · message.received · stop.received`",
    "`@emits `send.requested` · `message.sent` · `message.delivered` · `message.failed` · `message.received` · `stop.received` ⭐⭐⭐ *(`R231` 2026-08-27 — **§235 group ④'s largest gap.** Every channel CONSUMES it; **no module declared that it ASKS.** §235: *\"the entry point of the ENTIRE outbound messaging system… asking felt like infrastructure rather than an event, so nobody wrote it down.\"* **A REQUIRED EMISSION on every send path — not one emitter.**)*`"
)

text = text.replace(
    "@agent_reachable `agent.draft` · `agent.classify` ⭐",
    "@agent_reachable `agent.draft` · `agent.classify` · `none` ⭐"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
