import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G7-38 | Spiff Campaigns | ENH | X-170 | a time-boxed bonus rule |",
    "| G7-38 | Spiff Campaigns | ENH | X-170 | a time-boxed bonus rule · ⛔ **REFUSES with SPEND_REJECTED** |"
)
text = text.replace(
    "| **G7-03** · **G7-38** · **G9-29** | **X-170 — what-if sliders · spiffs · the rep dashboard** | READ | **L3** | ⛔ a what-if renders as a promise | ⭐ **a projection is labelled and violet-dashed** *(X-194's law)*; **the dashboard is POSITIVE-ONLY, no per-person negative tile** *(§150.4)* |",
    "| **G7-03** · **G7-38** · **G9-29** | **X-170 — what-if sliders · spiffs · the rep dashboard** | READ | **L3** | ⛔ a what-if renders as a promise | ⭐ **a projection is labelled and violet-dashed** *(X-194's law)*; **the dashboard is POSITIVE-ONLY, no per-person negative tile** *(§150.4)* · ⛔ **REFUSES with SPEND_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
