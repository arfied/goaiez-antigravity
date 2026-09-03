import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@provides enrich.run · identity.resolve · enrich.export` · `@emits `enrichment.requested` ⭐ *(2026-08-27 — §235 group ④ GENUINELY MISSING: consumed and emitted by nobody; emitter derived from noun ownership)* · prospect.enriched · enrichment.failed · identity.resolved` · `"
replacement = "`@provides enrich.run · identity.resolve · enrich.export` · `@emits enrichment.requested · prospect.enriched · enrichment.failed · identity.resolved` · `@consumes capability.decided` · `@owns_table enrichment_fields · enrichment_runs`"
text = text.replace(target, replacement)

text = re.sub(
    r'(@agent_reachable\s+`identity\.resolve`)\s*⭐',
    r'\1 · `none` ⭐',
    text
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
