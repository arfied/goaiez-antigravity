with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "**SWARM** `SQ-11` · **W2**. Owns `app/Modules/Growth/MigrationIn/**`.",
    "**SWARM** `SQ-11` · **W2**. Owns `app/Modules/Growth/MigrationIn/**` · runtime proof = an import writes rows and emits nothing."
)

text += """
## X-212 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G4-54** | Integration Marketplace | fails | `migration_runs` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with IMPORT_REJECTED** |
| **N-004** | imported person unpermitted | fails | `migration_runs` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with IMPORT_REJECTED** |
| **N-038** | zero outbound | fails | `migration_runs` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with IMPORT_REJECTED** |
| **N-040** | weak id rejected | fails | `migration_runs` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with IMPORT_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
