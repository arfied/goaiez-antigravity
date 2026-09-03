with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "| **G12-38** · **G10-10** | **Viral social proof + competitor inspiration** — **one spec** | READ | **L0** | ⛔ it reposts a competitor's content | ⭐ **inspiration produces a TOPIC signal, never copy** — asserted by absence of any text carried across |"

replacement = "| **G12-38** · **G10-10** | **Viral social proof + competitor inspiration** — **one spec** | READ | **L0** | ⛔ it reposts a competitor's content | ⭐ **inspiration produces a TOPIC signal, never copy** — asserted by absence of any text carried across · ⛔ **REFUSES with CONTENT_REJECTED** |"

text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
