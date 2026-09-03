import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| ⭐⭐⭐ **G2-65** · **G2-77** · **G2-71** | Route optimisation · weather re-route · smart staff routing — **one spec** | the day is re-sequenced · **trigger:** a delay, weather, a new urgent job | the board · travel · skills | ⛔⛔ **a blind cascade pushes the last three jobs into the evening and nobody is told until a customer calls** | ⭐⭐ **THE DOMINO GATE: a cascade that pushes any job past its committed window DROPS TO L1 and surfaces a recommendation** — *\"Mike is free and 8 minutes away\"* — asserted by forcing a collision · ⛔ **a silent cascade past a committed time FAILS THE BUILD** · ⭐ **and the gate is on the ladder: twenty identical choices and it stops asking** | ⑥ none · ⑦ **L3, except at a collision** |",
    "| ⭐⭐⭐ **G2-65** · **G2-77** · **G2-71** | Route optimisation · weather re-route · smart staff routing — **one spec** | the day is re-sequenced · **trigger:** a delay, weather, a new urgent job | the board · travel · skills | ⛔⛔ **a blind cascade pushes the last three jobs into the evening and nobody is told until a customer calls** | ⭐⭐ **THE DOMINO GATE: a cascade that pushes any job past its committed window DROPS TO L1 and surfaces a recommendation** — *\"Mike is free and 8 minutes away\"* — asserted by forcing a collision · ⛔ **a silent cascade past a committed time FAILS THE BUILD** · ⭐ **and the gate is on the ladder: twenty identical choices and it stops asking** · ⛔ **REFUSES with DOMINO_REJECTED** | ⑥ none · ⑦ **L3, except at a collision** |"
)
text = text.replace(
    "| G2-65 | Route Optimization | ENH | X-162 | named in the header |",
    "| G2-65 | Route Optimization | ENH | X-162 | named in the header · ⛔ **REFUSES with DOMINO_REJECTED** |"
)
text = text.replace(
    "| G2-77 | Weather Re-Routing | ENH | X-162 | named in the header; ⚠️ the multi-warehouse framing is out of scope — *\"a van and a storage unit, not a warehouse\"* (X-167) |",
    "| G2-77 | Weather Re-Routing | ENH | X-162 | named in the header; ⚠️ the multi-warehouse framing is out of scope — *\"a van and a storage unit, not a warehouse\"* (X-167) · ⛔ **REFUSES with DOMINO_REJECTED** |"
)
text = text.replace(
    "| G2-71 | Smart Staff Routing | ENH | X-162 | nearest tech — the third state (EN ROUTE) is what makes the answer true |",
    "| G2-71 | Smart Staff Routing | ENH | X-162 | nearest tech — the third state (EN ROUTE) is what makes the answer true · ⛔ **REFUSES with DOMINO_REJECTED** |"
)
text = text.replace(
    "| G2-54 | Pipeline Automation | ENH | X-162 | named in the header; the sequence fires through X-186 |",
    "| G2-54 | Pipeline Automation | ENH | X-162 | named in the header; the sequence fires through X-186 · ⛔ **REFUSES with PIPELINE_REJECTED** |"
)
text = text.replace(
    "| G2-55 | Pipeline Automations | ENH | X-162 | = G2-54; one spec. The Zapier hop is native here (X-123) |",
    "| G2-55 | Pipeline Automations | ENH | X-162 | = G2-54; one spec. The Zapier hop is native here (X-123) · ⛔ **REFUSES with PIPELINE_REJECTED** |"
)
text = text.replace(
    "| G2-41 | Kanban & Predictability | ENH | X-162 | named in the header |",
    "| G2-41 | Kanban & Predictability | ENH | X-162 | named in the header · ⛔ **REFUSES with PIPELINE_REJECTED** |"
)
text = text.replace(
    "| G4-11 | Dependency Locking | ENH | X-162 | ⚠️ **NAME COLLISION** — X-195's header claims the term for manifests; this row is task dependencies |",
    "| G4-11 | Dependency Locking | ENH | X-162 | ⚠️ **NAME COLLISION** — X-195's header claims the term for manifests; this row is task dependencies · ⛔ **REFUSES with PIPELINE_REJECTED** |"
)
text = text.replace(
    "| G2-29 | Deal Slip Warnings | ENH | X-162 | named in the header |",
    "| G2-29 | Deal Slip Warnings | ENH | X-162 | named in the header · ⛔ **REFUSES with PIPELINE_REJECTED** |"
)
text = text.replace(
    "| G2-73 | Stale Deal Alerts | ENH | X-162 | named in the header |",
    "| G2-73 | Stale Deal Alerts | ENH | X-162 | named in the header · ⛔ **REFUSES with PIPELINE_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
