import re

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()
text = text.replace(
    "| G4-18 | JWT Authentication | ENH | X-142 | SPECCED | tenant-scoped, permission-inherited tokens |",
    "| G4-18 | JWT Authentication | ENH | X-142 | SPECCED | tenant-scoped, permission-inherited tokens · ⛔ **REFUSES with BAD_TOKEN** |"
)
with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)


with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G4-18 | JWT Authentication | ENH | X-142 | tenant-scoped, permission-inherited tokens |",
    "| G4-18 | JWT Authentication | ENH | X-142 | tenant-scoped, permission-inherited tokens · ⛔ **REFUSES with BAD_TOKEN** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)

