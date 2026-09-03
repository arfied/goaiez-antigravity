import re

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G3-29 | Historical Ingestion | ENH | X-154 | SPECCED | their words, not ours |",
    "| G3-29 | Historical Ingestion | ENH | X-154 | SPECCED | their words, not ours · ⛔ **REFUSES with NO_CLAIM_IMPORT** |"
)
text = text.replace(
    "| G12-19 | Emoji Density Control | ENH | X-154 | SPECCED | their words, their rules — and GSM-7 segmentation makes it a billing fact too |",
    "| G12-19 | Emoji Density Control | ENH | X-154 | SPECCED | their words, their rules — and GSM-7 segmentation makes it a billing fact too · ⛔ **REFUSES with SEGMENT_WARNING** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G3-29 | Historical Ingestion | ENH | X-154 | their words, not ours |",
    "| G3-29 | Historical Ingestion | ENH | X-154 | their words, not ours · ⛔ **REFUSES with NO_CLAIM_IMPORT** |"
)
text = text.replace(
    "| G12-19 | Emoji Density Control | ENH | X-154 | their words, their rules — and GSM-7 segmentation makes it a billing fact too |",
    "| G12-19 | Emoji Density Control | ENH | X-154 | their words, their rules — and GSM-7 segmentation makes it a billing fact too · ⛔ **REFUSES with SEGMENT_WARNING** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)

