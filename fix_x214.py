with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text += """
## X-214 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-214-01** | Debit Safety | fails | `none` | fails | debit is NEVER surcharged — BIN-asserted, unknown type treated as debit · ⛔ **REFUSES with SPEND_REJECTED** | ⑥⑦ inherit |
| **N-214-02** | Surcharge Limits | fails | `none` | fails | no apply without a preceding surcharge.disclosed on the same transaction · the ceiling is the LOWER of 3% and the merchant's own effective rate; above it is REFUSED, not clamped · ⛔ **REFUSES with SPEND_REJECTED** | ⑥⑦ inherit |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
