with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

replacement = """**TEST ANCHOR** *a terminal action never appears in a proposal set; every proposal row carries a non-empty explanation built from named entity fields; a proposal is graded by its outcome event within the window, either way*

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **N-145-01** | Safe Decisioning Engine | fails | `none` | fails | a terminal action never appears in a proposal set; every proposal row carries a non-empty explanation built from named entity fields; a proposal is graded by its outcome event within the window, either way · ⛔ **REFUSES with DECISION_REJECTED** |
"""

text = text.replace("**TEST ANCHOR** *a terminal action never appears in a proposal set; every proposal row carries a non-empty explanation built from named entity fields; a proposal is graded by its outcome event within the window, either way*", replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
