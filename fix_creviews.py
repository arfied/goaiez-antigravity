with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G18-14 | Post-Call CSAT Survey | ENH | C-Reviews | CSAT on resolve is named in the header |",
    "| G18-14 | Post-Call CSAT Survey | ENH | C-Reviews | CSAT on resolve is named in the header · ⛔ **REFUSES with CSAT_SUPPRESSED** |"
)

text = text.replace(
    "| **G18-14** · **G20-05** · **G20-07** | **CSAT · NPS · post-call survey** — **one spec** | — | **L3** | a survey fires after a bad call and reads as taunting | **suppressed on any conversation with an open escalation**, asserted |",
    "| **G18-14** · **G20-05** · **G20-07** | **CSAT · NPS · post-call survey** — **one spec** | — | **L3** | a survey fires after a bad call and reads as taunting | **suppressed on any conversation with an open escalation**, asserted · ⛔ **REFUSES with CSAT_SUPPRESSED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
