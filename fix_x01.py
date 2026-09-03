with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G2-32 | Engagement Scoring | ENH | X-01 | `lead_scores`; opens and clicks arrive from C-Mail |",
    "| G2-32 | Engagement Scoring | ENH | X-01 | `lead_scores`; opens and clicks arrive from C-Mail · ⛔ **REFUSES with SCORE_INVALID** |"
)

text = text.replace(
    "| ⭐ **G2-32** · **G2-36** · **G2-38** · **G2-61** | Scoring — engagement · hidden · ICP · RFM — **one spec** | one `lead_scores` row, several inputs · **trigger:** any signal | opens *(C-Mail)* · UTMs *(X-138)* · enrichment *(X-134)* | ⛔ **a score becomes a send trigger** | ⛔ **`P-068` — a score RAISES; `X-204` decides**, asserted by absence of a permit write · ⛔ **the RFM lookalike-seed half stays FENCED** *(§44)* · **an enriched input carries `source` + `confidence`, and confidence zero is excluded from the score**, asserted *(§187.5)* |",
    "| ⭐ **G2-32** · **G2-36** · **G2-38** · **G2-61** | Scoring — engagement · hidden · ICP · RFM — **one spec** | one `lead_scores` row, several inputs · **trigger:** any signal | opens *(C-Mail)* · UTMs *(X-138)* · enrichment *(X-134)* | ⛔ **a score becomes a send trigger** | ⛔ **`P-068` — a score RAISES; `X-204` decides**, asserted by absence of a permit write · ⛔ **the RFM lookalike-seed half stays FENCED** *(§44)* · **an enriched input carries `source` + `confidence`, and confidence zero is excluded from the score**, asserted *(§187.5)* · ⛔ **REFUSES with SCORE_INVALID** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
