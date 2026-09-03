import re
with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **N-028** | **X-210** | the margin guard NAMES every below-cost service |  |",
    "| **N-028** | **X-210** | the margin guard NAMES every below-cost service | ⛔ **REFUSES with BELOW_COST** |"
)
text = text.replace(
    "| **N-030** | **X-210** | the AI honours and never invents |  |",
    "| **N-030** | **X-210** | the AI honours and never invents | ⛔ **REFUSES with NO_FACT** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)
