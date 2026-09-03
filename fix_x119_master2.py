with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# I will replace `| ⑥⑦ inherit |` with `| ⑥⑦ inherit · ⛔ **REFUSES with SOMETHING** |`

text = text.replace(
    'the crawl is X-151\'s, the grounding store is X-119\'s. Pinecone is corpus vocabulary — one database (§22) · ⛔ **REFUSES with DOMAIN_MISMATCH** | ⑥⑦ inherit |',
    'the crawl is X-151\'s, the grounding store is X-119\'s. Pinecone is corpus vocabulary — one database (§22) | ⑥⑦ inherit · ⛔ **REFUSES with DOMAIN_MISMATCH** |'
)

text = text.replace(
    'the grounding law; retrieval is X-148\'s. Pinecone is corpus vocabulary · ⛔ **REFUSES with NO_SOURCE** | ⑥⑦ inherit |',
    'the grounding law; retrieval is X-148\'s. Pinecone is corpus vocabulary | ⑥⑦ inherit · ⛔ **REFUSES with NO_SOURCE** |'
)

text = text.replace(
    'a volunteered detail becomes a Fact with its source · ⛔ **REFUSES with IMPLICIT_INFERENCE** | ⑥⑦ inherit |',
    'a volunteered detail becomes a Fact with its source | ⑥⑦ inherit · ⛔ **REFUSES with IMPLICIT_INFERENCE** |'
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
