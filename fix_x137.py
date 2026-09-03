with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G8-13** · **G3-11** · **G13-19** | DNI — dynamic number swapping · callback tracking · keyword attribution — **one spec** | **every visitor gets a call token**; the number on the page maps back to the session · **trigger:** page load | the pool · the session | ⛔ **the pool exhausts and two live sessions share a number** — every call after that is attributed to the wrong source, silently and forever | with the pool exhausted, the page renders the **static fallback number** and the session is marked `unattributed` — ⛔ **never a reused token**, asserted by forcing exhaustion · ⚠️ *CallTrackingMetrics is corpus vocabulary* | ⑥ inferred from the site · ⑦ swaps unasked |",
    "| **G8-13** · **G3-11** · **G13-19** | DNI — dynamic number swapping · callback tracking · keyword attribution — **one spec** | **every visitor gets a call token**; the number on the page maps back to the session · **trigger:** page load | the pool · the session | ⛔ **the pool exhausts and two live sessions share a number** — every call after that is attributed to the wrong source, silently and forever | with the pool exhausted, the page renders the **static fallback number** and the session is marked `unattributed` — ⛔ **never a reused token**, asserted by forcing exhaustion · ⚠️ *CallTrackingMetrics is corpus vocabulary* · ⛔ **REFUSES with POOL_EXHAUSTED** | ⑥ inferred from the site · ⑦ swaps unasked |"
)

text = text.replace(
    "| **G13-24** | Offline tracking | a static number per offline campaign *(a billboard, a van, a flyer)* · **trigger:** provisioning | number → campaign | the number is reused for a second campaign and the history merges | a number cannot be assigned to a second live campaign — the assignment is refused, asserted | ⑥ one field per campaign · ⑦ n/a |",
    "| **G13-24** | Offline tracking | a static number per offline campaign *(a billboard, a van, a flyer)* · **trigger:** provisioning | number → campaign | the number is reused for a second campaign and the history merges | a number cannot be assigned to a second live campaign — the assignment is refused, asserted · ⛔ **REFUSES with ASSIGNMENT_REJECTED** | ⑥ one field per campaign · ⑦ n/a |"
)

text = text.replace(
    "| **G18-17** · **G18-24** | Whisper — **one spec** | ⭐ **the whisper names the SOURCE** — *\"Google Ads — bathroom remodel\"* — before the human says hello · **trigger:** connect | the attribution | the whisper plays **to the caller** | the whisper audio is asserted present on the agent leg and **absent on the caller leg**, in one test on a real bridge | ⑥ the phrase is confirmed once · ⑦ plays always |",
    "| **G18-17** · **G18-24** | Whisper — **one spec** | ⭐ **the whisper names the SOURCE** — *\"Google Ads — bathroom remodel\"* — before the human says hello · **trigger:** connect | the attribution | the whisper plays **to the caller** | the whisper audio is asserted present on the agent leg and **absent on the caller leg**, in one test on a real bridge · ⛔ **REFUSES with WHISPER_REJECTED** | ⑥ the phrase is confirmed once · ⑦ plays always |"
)

# And fix the single capability rows:
text = text.replace(
    "| G3-11 | Callback Tracking | ENH | X-137 | every visitor gets a call token; ⚠️ CallTrackingMetrics is corpus vocabulary |",
    "| G3-11 | Callback Tracking | ENH | X-137 | every visitor gets a call token; ⚠️ CallTrackingMetrics is corpus vocabulary · ⛔ **REFUSES with POOL_EXHAUSTED** |"
)
text = text.replace(
    "| G8-13 | Dynamic Number Swapping | ENH | X-137 | DNI — every visitor gets a call token |",
    "| G8-13 | Dynamic Number Swapping | ENH | X-137 | DNI — every visitor gets a call token · ⛔ **REFUSES with POOL_EXHAUSTED** |"
)
text = text.replace(
    "| G13-24 | Offline Tracking | ENH | X-137 | a static number per offline campaign |",
    "| G13-24 | Offline Tracking | ENH | X-137 | a static number per offline campaign · ⛔ **REFUSES with ASSIGNMENT_REJECTED** |"
)
text = text.replace(
    "| G18-17 | Telephony Call Whisper | ENH | X-137 | the whisper names the SOURCE — that is what call tracking is for |",
    "| G18-17 | Telephony Call Whisper | ENH | X-137 | the whisper names the SOURCE — that is what call tracking is for · ⛔ **REFUSES with WHISPER_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
