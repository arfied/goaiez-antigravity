with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G10-40** | **C-Whatsapp** — opt-in engine | a scan or shortcode registers the opt-in · **trigger:** the inbound join | ⛔ **the permit is X-204's** — this module records the EVENT, not the decision | the opt-in is stored locally and becomes a second consent record | the opt-in writes an `ImportAttestation`-equivalent through X-204 and this module owns **no consent column**, asserted by schema | ⑥ the shortcode, once · ⑦ automatic |",
    "| **G10-40** | **C-Whatsapp** — opt-in engine | a scan or shortcode registers the opt-in · **trigger:** the inbound join | ⛔ **the permit is X-204's** — this module records the EVENT, not the decision | the opt-in is stored locally and becomes a second consent record | the opt-in writes an `ImportAttestation`-equivalent through X-204 and this module owns **no consent column**, asserted by schema · ⛔ **REFUSES with CONSENT_REJECTED** | ⑥ the shortcode, once · ⑦ automatic |"
)
text = text.replace(
    "| G10-40 | WhatsApp Opt-In Engine | ENH | C-Whatsapp | a scan or shortcode registers the opt-in; the permit itself is *ConsentService*'s |",
    "| G10-40 | WhatsApp Opt-In Engine | ENH | C-Whatsapp | a scan or shortcode registers the opt-in; the permit itself is *ConsentService*'s · ⛔ **REFUSES with CONSENT_REJECTED** |"
)

text = text.replace(
    "| **G19-22** | **C-Whatsapp** — Zernio WhatsApp + GBP chat sync | every channel lands on **ONE** `Conversation` *(§156.3)* · **trigger:** inbound | X-121's conversation | a GBP chat opens a second thread for a person who already has one | an inbound from a third channel for a known `Person` appends to the existing thread, asserted | ⑥⑦ inherit |",
    "| **G19-22** | **C-Whatsapp** — Zernio WhatsApp + GBP chat sync | every channel lands on **ONE** `Conversation` *(§156.3)* · **trigger:** inbound | X-121's conversation | a GBP chat opens a second thread for a person who already has one | an inbound from a third channel for a known `Person` appends to the existing thread, asserted · ⛔ **REFUSES with CHANNEL_REJECTED** | ⑥⑦ inherit |"
)
text = text.replace(
    "| G19-22 | Zernio WhatsApp & GBP Chat Sync | ENH | C-Whatsapp | ⭐ §156.3 — GBP runs through Zernio; every channel lands on ONE Conversation |",
    "| G19-22 | Zernio WhatsApp & GBP Chat Sync | ENH | C-Whatsapp | ⭐ §156.3 — GBP runs through Zernio; every channel lands on ONE Conversation · ⛔ **REFUSES with CHANNEL_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
