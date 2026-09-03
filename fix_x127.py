with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text += """
## X-127 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-127-01** | Claims match the live query | fails | `published_metrics` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CLAIM_REJECTED** |
| **N-127-02** | Tenant zero gates the ship | fails | `published_metrics` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with DEPLOY_REJECTED** |
| **N-127-03** | GOAIEZ scope is not a superuser | fails | `published_metrics` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with SCOPE_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
