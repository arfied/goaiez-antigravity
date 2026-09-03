with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "| G18-04 | Dynamic Name Insertion | ENH | X-197 | the name is a `Fact`; the voice is ours, self-hosted (§18F) |"
replacement = "| G18-04 | Dynamic Name Insertion | ENH | X-197 | the name is a `Fact`; the voice is ours, self-hosted (§18F) · ⛔ **REFUSES with UNVERIFIED_FACT** |"

text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
