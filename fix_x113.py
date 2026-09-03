import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G15-02 | Employee Scoring | ENH | X-113 | T677: coaching and positive capability tracking only (§150.4) |",
    "| G15-02 | Employee Scoring | ENH | X-113 | T677: coaching and positive capability tracking only (§150.4) · ⛔ **REFUSES with HR_REJECTED** |"
)
text = text.replace(
    "| G15-05 | 9-Box Grid Automation | ENH | X-113 | T677 — coaching framing; the quarterly nag is a reminder, not a ranking |",
    "| G15-05 | 9-Box Grid Automation | ENH | X-113 | T677 — coaching framing; the quarterly nag is a reminder, not a ranking · ⛔ **REFUSES with HR_REJECTED** |"
)
text = text.replace(
    "| G9-36 | Candidate Scorecards | ENH | X-113 | interview scorecards — hiring, not the platform |",
    "| G9-36 | Candidate Scorecards | ENH | X-113 | interview scorecards — hiring, not the platform · ⛔ **REFUSES with HR_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
