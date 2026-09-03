import re

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G5-02 | Action Item Extraction | RE-HOME→G16 | X-158 | SPECCED | episode resources — spec with the video pass (turn 32) |",
    "| G5-02 | Action Item Extraction | RE-HOME→G16 | X-158 | SPECCED | episode resources — spec with the video pass (turn 32) · ⛔ **REFUSES with UNVERIFIED_BIO** |"
)
text = text.replace(
    "| G11-27 | Promo Email Draft | RE-HOME→G16 | X-158 | SPECCED | episode promo — spec with the video pass (turn 32) |",
    "| G11-27 | Promo Email Draft | RE-HOME→G16 | X-158 | SPECCED | episode promo — spec with the video pass (turn 32) · ⛔ **REFUSES with UNVERIFIED_BIO** |"
)
text = text.replace(
    "| G12-06 | Auto-Publishing | RE-HOME→G16 | X-158 | SPECCED | show notes and player — the video pass |",
    "| G12-06 | Auto-Publishing | RE-HOME→G16 | X-158 | SPECCED | show notes and player — the video pass · ⛔ **REFUSES with FALSE_QUOTE** |"
)
text = text.replace(
    "| G12-23 | Instant Show Notes | RE-HOME→G16 | X-158 | SPECCED | transcribe → notes — the video pass |",
    "| G12-23 | Instant Show Notes | RE-HOME→G16 | X-158 | SPECCED | transcribe → notes — the video pass · ⛔ **REFUSES with FALSE_QUOTE** |"
)
text = text.replace(
    "| G12-34 | Social Snippets | RE-HOME→G16 | X-158 | SPECCED | quotes pulled from a transcript |",
    "| G12-34 | Social Snippets | RE-HOME→G16 | X-158 | SPECCED | quotes pulled from a transcript · ⛔ **REFUSES with BAD_CLIP_BOUNDARY** |"
)

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.write(text)

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G5-02 | Action Item Extraction | **RE-HOME→G16** | X-158 | episode resources — spec with the video pass (turn 32) |",
    "| G5-02 | Action Item Extraction | **RE-HOME→G16** | X-158 | episode resources — spec with the video pass (turn 32) · ⛔ **REFUSES with UNVERIFIED_BIO** |"
)
text = text.replace(
    "| G11-27 | Promo Email Draft | **RE-HOME→G16** | X-158 | episode promo — spec with the video pass (turn 32) |",
    "| G11-27 | Promo Email Draft | **RE-HOME→G16** | X-158 | episode promo — spec with the video pass (turn 32) · ⛔ **REFUSES with UNVERIFIED_BIO** |"
)
text = text.replace(
    "| G12-06 | Auto-Publishing | **RE-HOME→G16** | X-158 | show notes and player — the video pass |",
    "| G12-06 | Auto-Publishing | **RE-HOME→G16** | X-158 | show notes and player — the video pass · ⛔ **REFUSES with FALSE_QUOTE** |"
)
text = text.replace(
    "| G12-23 | Instant Show Notes | **RE-HOME→G16** | X-158 | transcribe → notes — the video pass |",
    "| G12-23 | Instant Show Notes | **RE-HOME→G16** | X-158 | transcribe → notes — the video pass · ⛔ **REFUSES with FALSE_QUOTE** |"
)
text = text.replace(
    "| G12-34 | Social Snippets | **RE-HOME→G16** | X-158 | quotes pulled from a transcript |",
    "| G12-34 | Social Snippets | **RE-HOME→G16** | X-158 | quotes pulled from a transcript · ⛔ **REFUSES with BAD_CLIP_BOUNDARY** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)

