with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G12-04 | Auto-Publish Sync | ENH | X-202 | approval granted → the publish action fires |",
    "| G12-04 | Auto-Publish Sync | ENH | X-202 | approval granted → the publish action fires · ⛔ **REFUSES with EARLY_PUBLISH** |"
)

text = text.replace(
    "| **G12-04** · **G16-24** | **Auto-publish sync + frame annotation** — **one spec** | — | **L3** | an approval publishes before the ladder allows | the item's own floor governs, asserted |",
    "| **G12-04** · **G16-24** | **Auto-publish sync + frame annotation** — **one spec** | — | **L3** | an approval publishes before the ladder allows | the item's own floor governs, asserted · ⛔ **REFUSES with EARLY_PUBLISH** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
