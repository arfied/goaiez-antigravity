with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

replacement = """**TEST ANCHOR** *a phone field of "N/A" from tier 1 is rejected and tier 2 is called; the expensive tier's call count over a month is under 5% of resolutions*

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **N-150-01** | Shape Validation | fails | `none` | fails | a phone field of "N/A" from tier 1 is rejected and tier 2 is called · ⛔ **REFUSES with BAD_SHAPE** |
| **N-150-02** | Cost Control | fails | `none` | fails | the expensive tier's call count over a month is under 5% of resolutions · ⛔ **REFUSES with RATE_LIMIT** |
"""

text = text.replace("**TEST ANCHOR** *a phone field of \"N/A\" from tier 1 is rejected and tier 2 is called; the expensive tier's call count over a month is under 5% of resolutions*", replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
