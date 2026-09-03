with open('app/GOAIEZ-MASTER-PLAN.md', 'a') as f:
    f.write("""
## 169.Y X-119 `TenantLexicon` — CAPABILITY TABLE

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G3-02** | 60-Second Sitemap Scraper | ... | ... | ... | the crawl is X-151's, the grounding store is X-119's. Pinecone is corpus vocabulary — one database (§22) · ⛔ **REFUSES with DOMAIN_MISMATCH** | ⑥⑦ inherit |
| **G5-25** | Custom RAG Knowledge Base | ... | ... | ... | the grounding law; retrieval is X-148's. Pinecone is corpus vocabulary · ⛔ **REFUSES with NO_SOURCE** | ⑥⑦ inherit |
| **G13-38** | Zero-Party Data Collection | ... | ... | ... | a volunteered detail becomes a Fact with its source · ⛔ **REFUSES with IMPLICIT_INFERENCE** | ⑥⑦ inherit |
""")
