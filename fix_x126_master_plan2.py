import re

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# I will replace the previously appended block.
# Wait, I can just replace `⑥⑦ inherit |` with `⑥⑦ inherit ⛔ **REFUSES with NO_FACT** |` for N-126-01.

text = text.replace(
    '| **N-126-01** | No Fact, no skill | an agent skill invoked with NO grounding Fact is REFUSED with reason NO_FACT | `capability_decisions` | the skill executes anyway | an agent skill invoked with no grounding Fact is refused with reason NO_FACT · ⛔ **REFUSES with NO_FACT** | ⑥⑦ inherit |',
    '| **N-126-01** | No Fact, no skill | an agent skill invoked with NO grounding Fact is REFUSED with reason NO_FACT | `capability_decisions` | the skill executes anyway | an agent skill invoked with no grounding Fact is refused with reason NO_FACT | ⑥⑦ inherit · ⛔ **REFUSES with NO_FACT** |'
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
