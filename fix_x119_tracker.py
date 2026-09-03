with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G3-02 | 60-Second Sitemap Scraper | ENH | X-119 | SPECCED | the crawl is X-151's, the grounding store is X-119's.  Pinecone is corpus vocabulary — one database (§22) |",
    "| G3-02 | 60-Second Sitemap Scraper | ENH | X-119 | SPECCED | the crawl is X-151's, the grounding store is X-119's.  Pinecone is corpus vocabulary — one database (§22) · ⛔ **REFUSES with DOMAIN_MISMATCH** |"
)

text = text.replace(
    "| G5-25 | Custom RAG Knowledge Base | ENH | X-119 | SPECCED | the grounding law; retrieval is X-148's.  Pinecone is corpus vocabulary |",
    "| G5-25 | Custom RAG Knowledge Base | ENH | X-119 | SPECCED | the grounding law; retrieval is X-148's.  Pinecone is corpus vocabulary · ⛔ **REFUSES with NO_SOURCE** |"
)

text = text.replace(
    "| G13-38 | Zero-Party Data Collection | ENH | X-119 | SPECCED | a volunteered detail becomes a `Fact` with its source |",
    "| G13-38 | Zero-Party Data Collection | ENH | X-119 | SPECCED | a volunteered detail becomes a `Fact` with its source · ⛔ **REFUSES with IMPLICIT_INFERENCE** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)
