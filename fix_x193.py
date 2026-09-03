with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G10-31** · **G10-38** | **X-193** — classes + quiet hours — **one spec** | ⛔ **the class is decided from the CALLER, never the content** *(P-062)*; the window is data *(P-063)*; **MARKETING only** · **trigger:** every send | the class map | ⛔⛔ **a transactional message waits for quiet hours** — the missed-call text-back, the appointment reminder, the alert. *The exact failure P-061 and R80 exist to prevent* | a `transactional` send inside the quiet window **goes immediately**, and a `marketing` send in the same window **is deferred by X-204**, asserted in one test with both · web chat, missed-call and alerts **never wait**, asserted individually | ⑥ one window, confirmed once *(optional — P-063)* · ⑦ applied by class, unattended |",
    "| **G10-31** · **G10-38** | **X-193** — classes + quiet hours — **one spec** | ⛔ **the class is decided from the CALLER, never the content** *(P-062)*; the window is data *(P-063)*; **MARKETING only** · **trigger:** every send | the class map | ⛔⛔ **a transactional message waits for quiet hours** — the missed-call text-back, the appointment reminder, the alert. *The exact failure P-061 and R80 exist to prevent* | a `transactional` send inside the quiet window **goes immediately**, and a `marketing` send in the same window **is deferred by X-204**, asserted in one test with both · web chat, missed-call and alerts **never wait**, asserted individually · ⛔ **REFUSES with CLASS_REJECTED** | ⑥ one window, confirmed once *(optional — P-063)* · ⑦ applied by class, unattended |"
)

text = text.replace(
    "| G10-31 | Quiet Hours Enforcement | ENH | X-193 | ⛔ MARKETING class only; the window is data (P-063); web chat, missed-call and alerts never wait |",
    "| G10-31 | Quiet Hours Enforcement | ENH | X-193 | ⛔ MARKETING class only; the window is data (P-063); web chat, missed-call and alerts never wait · ⛔ **REFUSES with CLASS_REJECTED** |"
)
text = text.replace(
    "| G10-38 | Unified Guardrail Law | ENH | X-193 | = Quiet Hours Enforcement; one spec. The class is decided from the CALLER, never the content (P-062) |",
    "| G10-38 | Unified Guardrail Law | ENH | X-193 | = Quiet Hours Enforcement; one spec. The class is decided from the CALLER, never the content (P-062) · ⛔ **REFUSES with CLASS_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
