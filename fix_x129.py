with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

replacement = """**TEST ANCHOR** *every URL in the source crawl has a redirect row; cutover is blocked while any old URL returns 404 on the new host*

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **N-129-01** | Safe Migration Engine | fails | `none` | fails | every URL in the source crawl has a redirect row; cutover is blocked while any old URL returns 404 on the new host · ⛔ **REFUSES with MIGRATION_REJECTED** |
"""

text = text.replace("**TEST ANCHOR** *every URL in the source crawl has a redirect row; cutover is blocked while any old URL returns 404 on the new host*", replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
