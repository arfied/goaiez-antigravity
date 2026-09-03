with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G5-46** | **X-148** — retrieval | the right passages, inside the turn budget · **trigger:** any grounded compose | X-160's documents · X-119's facts | ⛔ **retrieval returns nothing and the agent fills the gap itself** | **an empty retrieval produces a REFUSAL, never a generated answer**, asserted with an empty index — *this is the same failure as G10-19 one layer down* | ⑥ none · ⑦ always |",
    "| **G5-46** | **X-148** — retrieval | the right passages, inside the turn budget · **trigger:** any grounded compose | X-160's documents · X-119's facts | ⛔ **retrieval returns nothing and the agent fills the gap itself** | **an empty retrieval produces a REFUSAL, never a generated answer**, asserted with an empty index — *this is the same failure as G10-19 one layer down* · ⛔ **REFUSES with RETRIEVAL_REJECTED** |"
)
text = text.replace(
    "| G5-46 | RAG Search | ENH | X-148 | the documents are X-160's |",
    "| G5-46 | RAG Search | ENH | X-148 | the documents are X-160's · ⛔ **REFUSES with RETRIEVAL_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
