with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G13-15** | **X-102** — exit-intent | the pixel triggers, the chat answers **grounded** · **trigger:** exit intent | X-119's facts | it offers a price it invented | an exit-intent answer containing an ungrounded price **refuses instead** *(P-092)* — asserted with the pricebook empty | ⑥⑦ inherit |",
    "| **G13-15** | **X-102** — exit-intent | the pixel triggers, the chat answers **grounded** · **trigger:** exit intent | X-119's facts | it offers a price it invented | an exit-intent answer containing an ungrounded price **refuses instead** *(P-092)* — asserted with the pricebook empty | ⑥⑦ inherit · ⛔ **REFUSES with GROUNDING_REJECTED** |"
)
text = text.replace(
    "| G13-15 | Exit Intent RAG | ENH | X-102 | the pixel triggers; the chat answers grounded (X-119) |",
    "| G13-15 | Exit Intent RAG | ENH | X-102 | the pixel triggers; the chat answers grounded (X-119) · ⛔ **REFUSES with GROUNDING_REJECTED** |"
)

text = text.replace(
    "| **G16-21** | **X-102** — rich media | carousels in the chat · **trigger:** an answer with structured results | action results | a card renders a broken image | a missing asset renders text, never a broken placeholder *(the never-fails image law)*, asserted | ⑥⑦ inherit |",
    "| **G16-21** | **X-102** — rich media | carousels in the chat · **trigger:** an answer with structured results | action results | a card renders a broken image | a missing asset renders text, never a broken placeholder *(the never-fails image law)*, asserted | ⑥⑦ inherit · ⛔ **REFUSES with RENDER_REJECTED** |"
)
text = text.replace(
    "| G16-21 | Rich Media Support | ENH | X-102 | carousels rendered in the chat |",
    "| G16-21 | Rich Media Support | ENH | X-102 | carousels rendered in the chat · ⛔ **REFUSES with RENDER_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
