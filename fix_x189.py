import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G16-19 | Quote Graphic Generation | ENH | X-189 | the branded card |",
    "| G16-19 | Quote Graphic Generation | ENH | X-189 | the branded card · ⛔ **REFUSES with LICENSE_REJECTED** |"
)
text = text.replace(
    "| G16-17 | Multi-Media Injection | ENH | X-189 | the personalised overlay; ⛔ the never-fails image law — client photo → generated → branded card |",
    "| G16-17 | Multi-Media Injection | ENH | X-189 | the personalised overlay; ⛔ the never-fails image law — client photo → generated → branded card · ⛔ **REFUSES with LICENSE_REJECTED** |"
)
text = text.replace(
    "| G17-29 | Visual Canvas Integration | ENH | X-189 | the overlay engine; sourcing is X-114's |",
    "| G17-29 | Visual Canvas Integration | ENH | X-189 | the overlay engine; sourcing is X-114's · ⛔ **REFUSES with LICENSE_REJECTED** |"
)
text = text.replace(
    "| G19-12 | MMS Picture Engine | ENH | X-189 | the overlay engine; the SMS+MMS pair is ONE debit (R9) |",
    "| G19-12 | MMS Picture Engine | ENH | X-189 | the overlay engine; the SMS+MMS pair is ONE debit (R9) · ⛔ **REFUSES with LICENSE_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
