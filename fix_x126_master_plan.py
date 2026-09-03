with open('app/GOAIEZ-MASTER-PLAN.md', 'a') as f:
    f.write("""
## 169.X X-126 `CapabilityGate` — CAPABILITY TABLE

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-126-01** | No Fact, no skill | an agent skill invoked with NO grounding Fact is REFUSED with reason NO_FACT | `capability_decisions` | the skill executes anyway | an agent skill invoked with no grounding Fact is refused with reason NO_FACT · ⛔ **REFUSES with NO_FACT** | ⑥⑦ inherit |
| **N-126-02** | Grounded pass is logged | a message with valid grounding passes, and the decision is logged either way | `capability_decisions` | the pass is not logged | a message with valid grounding passes, and the decision is logged either way | ⑥⑦ inherit |
""")
