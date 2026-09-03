with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G2-27 | Custom Finetuning Pipelines | ENH | C-Ai | ⚠️ the plan's mechanism is grounding + lexicon + the teaching box (P-097); a per-tenant fine-tune is an **owner question** |",
    "| G2-27 | Custom Finetuning Pipelines | ENH | C-Ai | ⚠️ the plan's mechanism is grounding + lexicon + the teaching box (P-097); a per-tenant fine-tune is an **owner question** · ⛔ **REFUSES with FINE_TUNE_REJECTED** |"
)

text = text.replace(
    "the cache key includes the **prompt version**, asserted by bumping a version and observing a miss · *fine-tuning stays killed — the mechanism is grounding + lexicon + the teaching box (P-097)* | ⑥⑦ inherit |",
    "the cache key includes the **prompt version**, asserted by bumping a version and observing a miss · *fine-tuning stays killed — the mechanism is grounding + lexicon + the teaching box (P-097)* | ⑥⑦ inherit · ⛔ **REFUSES with FINE_TUNE_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
