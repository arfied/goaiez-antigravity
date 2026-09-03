with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| ⭐⭐⭐ **G5-03** | **The eval gate** | ⭐ **a proposed prompt version is promoted ONLY if it beats the incumbent on a FIXED eval set** · **trigger:** any prompt proposal | `eval_runs` · the **frozen** set | ⛔⛔ **the eval set drifts with the prompt.** *Then every version beats the last one and the score means nothing — **this is test-to-green wearing an evaluation's clothes*** | **the eval set is content-hashed and the hash is recorded with every run**; a run against a changed set is **INVALID, not a pass**, asserted by mutating the set · a proposal that ties **is not promoted** | ⑥ none · ⑦ promotes and reverts unattended |",
    "| ⭐⭐⭐ **G5-03** | **The eval gate** | ⭐ **a proposed prompt version is promoted ONLY if it beats the incumbent on a FIXED eval set** · **trigger:** any prompt proposal | `eval_runs` · the **frozen** set | ⛔⛔ **the eval set drifts with the prompt.** *Then every version beats the last one and the score means nothing — **this is test-to-green wearing an evaluation's clothes*** | **the eval set is content-hashed and the hash is recorded with every run**; a run against a changed set is **INVALID, not a pass**, asserted by mutating the set · a proposal that ties **is not promoted** · ⛔ **REFUSES with EVAL_REJECTED** | ⑥ none · ⑦ promotes and reverts unattended |"
)

text = text.replace(
    "| ⭐⭐ **G5-04** | **Model comparison** | ⭐ **resolution rate per model, per job class — the thing that settles Sonnet-vs-Haiku with evidence instead of opinion** · **trigger:** continuous | `ai_calls` — ⚠️ *ClickHouse is corpus vocabulary; one database* | ⛔ **substituted calls are counted.** *The fallback fires, OpenAI answers, and the number is attributed to the model that was asked for* | ⛔ **calls with `model_requested ≠ model_served` are EXCLUDED**, asserted with a mixed fixture · the metric is **resolution** *(did it resolve, did a human take over, did the customer return)*, **never the model's self-assessment** | ⑥ none · ⑦ proposes a re-rank; ⛔ **a human accepts it** |",
    "| ⭐⭐ **G5-04** | **Model comparison** | ⭐ **resolution rate per model, per job class — the thing that settles Sonnet-vs-Haiku with evidence instead of opinion** · **trigger:** continuous | `ai_calls` — ⚠️ *ClickHouse is corpus vocabulary; one database* | ⛔ **substituted calls are counted.** *The fallback fires, OpenAI answers, and the number is attributed to the model that was asked for* | ⛔ **calls with `model_requested ≠ model_served` are EXCLUDED**, asserted with a mixed fixture · the metric is **resolution** *(did it resolve, did a human take over, did the customer return)*, **never the model's self-assessment** · ⛔ **REFUSES with EVAL_REJECTED** | ⑥ none · ⑦ proposes a re-rank; ⛔ **a human accepts it** |"
)

text = text.replace(
    "| G5-03 | Agent A/B Testing | ENH | X-149 | the conscience measures it; ⚠️ a persona split is a test, never a permit change |",
    "| G5-03 | Agent A/B Testing | ENH | X-149 | the conscience measures it; ⚠️ a persona split is a test, never a permit change · ⛔ **REFUSES with EVAL_REJECTED** |"
)
text = text.replace(
    "| G5-04 | Agent Analytics | ENH | X-149 | ⚠️ ClickHouse is corpus vocabulary — one database (§22 · P-143) |",
    "| G5-04 | Agent Analytics | ENH | X-149 | ⚠️ ClickHouse is corpus vocabulary — one database (§22 · P-143) · ⛔ **REFUSES with EVAL_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
