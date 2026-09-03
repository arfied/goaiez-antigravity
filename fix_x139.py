with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G13-22** | **X-139** — offline conversion push | ⭐ **the one-way push, explicitly NOT fenced** *(P-128)* · **trigger:** a job closes | completed jobs | it pushes device geo or store visits we do not have | only **completed-job conversions** are uploaded, asserted by payload schema |",
    "| **G13-22** | **X-139** — offline conversion push | ⭐ **the one-way push, explicitly NOT fenced** *(P-128)* · **trigger:** a job closes | completed jobs | it pushes device geo or store visits we do not have | only **completed-job conversions** are uploaded, asserted by payload schema · ⛔ **REFUSES with ADS_REJECTED** |"
)
text = text.replace(
    "| G13-22 | Offline Event Uploads | ENH | X-139 | named in the header — ⭐ the one-way push is explicitly NOT fenced (P-128) |",
    "| G13-22 | Offline Event Uploads | ENH | X-139 | named in the header — ⭐ the one-way push is explicitly NOT fenced (P-128) · ⛔ **REFUSES with ADS_REJECTED** |"
)

text += """
## X-139 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **G1-62** | Automated Refund Requests | fails | `none` | fails | DETECT AND REPORT ONLY — doctor asserts no outbound call to any ad platform (R200) · refuses: any outbound call to an ad platform (R200) · ⛔ **REFUSES with ADS_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
