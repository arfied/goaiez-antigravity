with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G2-30** | **X-160** — dedup at ingest | SHA-256 on content · **trigger:** upload | the `Asset` *(X-121's)* | the same document ingests twice and retrieval returns it twice, doubling its weight | a re-upload creates **no second row and no second embedding**, asserted | ⑥⑦ inherit |",
    "| **G2-30** | **X-160** — dedup at ingest | SHA-256 on content · **trigger:** upload | the `Asset` *(X-121's)* | the same document ingests twice and retrieval returns it twice, doubling its weight | a re-upload creates **no second row and no second embedding**, asserted · ⛔ **REFUSES with DUPLICATE_DOCUMENT** | ⑥⑦ inherit |"
)

text = text.replace(
    "| **G9-25** | **X-160** — OCR | ⭐ **structure stays structured** — a table stays a table · **trigger:** a scanned document | the extracted structure | a price table becomes a wall of text and every number loses its column | a scanned pricebook round-trips with **rows and columns intact**, asserted against a fixture · ⛔ **an OCR'd price is `is_sample` until confirmed** *(P-092)* | ⑥⑦ inherit |",
    "| **G9-25** | **X-160** — OCR | ⭐ **structure stays structured** — a table stays a table · **trigger:** a scanned document | the extracted structure | a price table becomes a wall of text and every number loses its column | a scanned pricebook round-trips with **rows and columns intact**, asserted against a fixture · ⛔ **an OCR'd price is `is_sample` until confirmed** *(P-092)* · ⛔ **REFUSES with BAD_SHAPE** | ⑥⑦ inherit |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
