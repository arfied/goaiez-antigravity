with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G10-02 | A2P 10DLC Compliance Automation | ENH | X-188 | the brand is auto-submitted (P-064); ⚠️ Twilio/TCR are corpus vocabulary — Infobip |",
    "| G10-02 | A2P 10DLC Compliance Automation | ENH | X-188 | the brand is auto-submitted (P-064); ⚠️ Twilio/TCR are corpus vocabulary — Infobip · ⛔ **REFUSES with BRAND_REJECTED** |"
)

text = text.replace(
    "| G18-10 | Local Caller ID | ENH | X-188 | the tenant's own registered numbers by area code |",
    "| G18-10 | Local Caller ID | ENH | X-188 | the tenant's own registered numbers by area code · ⛔ **REFUSES with NUMBER_BURNED** |"
)

text = text.replace(
    "| **G10-02** | **X-188** — 10DLC | the brand is **auto-submitted** *(P-064)* · **trigger:** provisioning | brand + campaign registration | ⛔ **the tenant is asked to register a brand** — the single most common place a non-technical owner stops forever | registration is submitted from data already held, with **zero tenant fields**, asserted · a rejection raises an operator task, never a tenant one. ⚠️ *Twilio/TCR is corpus vocabulary — Infobip* | ⑥ nothing asked · ⑦ fully unattended |",
    "| **G10-02** | **X-188** — 10DLC | the brand is **auto-submitted** *(P-064)* · **trigger:** provisioning | brand + campaign registration | ⛔ **the tenant is asked to register a brand** — the single most common place a non-technical owner stops forever | registration is submitted from data already held, with **zero tenant fields**, asserted · a rejection raises an operator task, never a tenant one. ⚠️ *Twilio/TCR is corpus vocabulary — Infobip* | ⑥ nothing asked · ⑦ fully unattended · ⛔ **REFUSES with BRAND_REJECTED** |"
)

text = text.replace(
    "| **G18-10** · **G18-11** | **X-188** — local presence — **one spec** | the tenant's **own registered** numbers by area code · **trigger:** outbound | the pool | ⛔ **rotating numbers to outrun complaints** rather than to be local | a number over the P-065 complaint threshold is **retired from the pool and reported**, not rotated past, asserted | ⑥ area codes confirmed once · ⑦ picks the local one |",
    "| **G18-10** · **G18-11** | **X-188** — local presence — **one spec** | the tenant's **own registered** numbers by area code · **trigger:** outbound | the pool | ⛔ **rotating numbers to outrun complaints** rather than to be local | a number over the P-065 complaint threshold is **retired from the pool and reported**, not rotated past, asserted | ⑥ area codes confirmed once · ⑦ picks the local one · ⛔ **REFUSES with NUMBER_BURNED** |"
)


with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
