with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    '@owns_table`fact_sources` · `fact_freshness`',
    '@owns_table `fact_sources` · `fact_freshness`'
)

# While I'm at it, X-119 has the same agent_reachable problem:
# `@provides fact.lookup · fact.confirm · fact.teach · knowledge.ingest_sync`
# `@agent_reachable `fact.lookup``
text = text.replace(
    '@agent_reachable `fact.lookup` ⭐',
    '@agent_reachable `fact.lookup · none` ⭐'
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
