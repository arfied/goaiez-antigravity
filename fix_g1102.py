import re

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G11-02 | Auto-Resend to Unopens | ENH | X-186 | SPECCED | named in the header |",
    "| G11-02 | Auto-Resend to Unopens | ENH | X-186 | SPECCED | named in the header · ⛔ **REFUSES with CEILING_EXCEEDED** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G11-02 | Auto-Resend to Unopens | ENH | X-186 | named in the header |",
    "| G11-02 | Auto-Resend to Unopens | ENH | X-186 | named in the header · ⛔ **REFUSES with CEILING_EXCEEDED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)

