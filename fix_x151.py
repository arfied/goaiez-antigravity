import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| ⭐ **G3-44** · **G17-13** | **X-151** — the pool + concurrency — **one spec** | the proxy pool and async fetch under a **global** ceiling · **trigger:** any fetch | pool · RPS budget | ⛔⛔ **\"never IP-banned\" is treated as the goal.** *It is not — **polite is.** A fetcher tuned to avoid bans is a fetcher tuned to look like something else* | ⛔ **P-145's global concurrency and per-target RPS are enforced in ONE place and asserted by exceeding them in a test**; a 429 or a warning **hard-aborts** rather than rotating onward | ⑥ operator-side · ⑦ throttles itself |",
    "| ⭐ **G3-44** · **G17-13** | **X-151** — the pool + concurrency — **one spec** | the proxy pool and async fetch under a **global** ceiling · **trigger:** any fetch | pool · RPS budget | ⛔⛔ **\"never IP-banned\" is treated as the goal.** *It is not — **polite is.** A fetcher tuned to avoid bans is a fetcher tuned to look like something else* | ⛔ **P-145's global concurrency and per-target RPS are enforced in ONE place and asserted by exceeding them in a test**; a 429 or a warning **hard-aborts** rather than rotating onward · ⛔ **REFUSES with SPAM_REJECTED** | ⑥ operator-side · ⑦ throttles itself |"
)
text = text.replace(
    "| G3-44 | Proxy Rotation | ENH | X-151 | the proxy pool is X-151's; ⛔ P-145 sets a global concurrency and RPS ceiling — **\"never IP-banned\" is not the goal; polite is** |",
    "| G3-44 | Proxy Rotation | ENH | X-151 | the proxy pool is X-151's; ⛔ P-145 sets a global concurrency and RPS ceiling — **\"never IP-banned\" is not the goal; polite is** · ⛔ **REFUSES with SPAM_REJECTED** |"
)
text = text.replace(
    "| G17-13 | High-Volume Processing | ENH | X-151 | async, under the global per-target RPS ceiling (P-145) |",
    "| G17-13 | High-Volume Processing | ENH | X-151 | async, under the global per-target RPS ceiling (P-145) · ⛔ **REFUSES with SPAM_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
