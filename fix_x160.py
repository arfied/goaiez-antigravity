with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "`@provides document.ingest · document.review · document.confirm` · `@emits document.ingested · document.reviewed · `",
    "`@provides document.ingest · document.review · document.confirm` · `@emits document.ingested · document.reviewed · upload.received`"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
