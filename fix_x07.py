import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G2-56** · **G2-59** · **G2-68** · **G17-19** | Gap · quota pacing · seasonality · roll-up — **one spec** | where the pipeline is thin, and when · **trigger:** render | closed history | ⛔ **a projection renders as a fact** | **X-194's law: an estimate is violet-dashed and labelled; a tile with no definition row cannot ship**, asserted |",
    "| **G2-56** · **G2-59** · **G2-68** · **G17-19** | Gap · quota pacing · seasonality · roll-up — **one spec** | where the pipeline is thin, and when · **trigger:** render | closed history | ⛔ **a projection renders as a fact** | **X-194's law: an estimate is violet-dashed and labelled; a tile with no definition row cannot ship**, asserted · ⛔ **REFUSES with FORECAST_REJECTED** |"
)

text = text.replace(
    "| ⚠️ **G2-66** | Sandbagging detection | a rep whose deals always close late | the pattern | ⛔⛔ **it becomes a per-person accusation** | ⛔ **§150.4: no per-person negative output exists in the schema** — **it surfaces as a FORECAST adjustment, never as a name**, asserted by schema |",
    "| ⚠️ **G2-66** | Sandbagging detection | a rep whose deals always close late | the pattern | ⛔⛔ **it becomes a per-person accusation** | ⛔ **§150.4: no per-person negative output exists in the schema** — **it surfaces as a FORECAST adjustment, never as a name**, asserted by schema · ⛔ **REFUSES with FORECAST_REJECTED** |"
)

text = text.replace(
    "| G2-56 | Pipeline Gap Analysis | ENH | X-07 | named in the header |",
    "| G2-56 | Pipeline Gap Analysis | ENH | X-07 | named in the header · ⛔ **REFUSES with FORECAST_REJECTED** |"
)
text = text.replace(
    "| G2-66 | Sandbagging Detection | ENH | X-07 | named in the header |",
    "| G2-66 | Sandbagging Detection | ENH | X-07 | named in the header · ⛔ **REFUSES with FORECAST_REJECTED** |"
)
text = text.replace(
    "| G2-68 | Seasonal Adjustment | ENH | X-07 | named in the header |",
    "| G2-68 | Seasonal Adjustment | ENH | X-07 | named in the header · ⛔ **REFUSES with FORECAST_REJECTED** |"
)
text = text.replace(
    "| G17-19 | Manager Roll-Up | ENH | X-07 | named in the header |",
    "| G17-19 | Manager Roll-Up | ENH | X-07 | named in the header · ⛔ **REFUSES with FORECAST_REJECTED** |"
)

# And add G9-42 explicitly
text += """
## X-07 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G9-42** | Monte Carlo Simulations | fails | `none` | fails | every output is labelled a SIMULATION; a simulated figure never renders like a measured one · ⛔ **REFUSES with FORECAST_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
