with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G3-54 | Spintax Evasion | ENH | C-Sms | ⚠️ spinning text to evade carrier A2P filtering conflicts with P-064's 10DLC path — **owner question** |",
    "| G3-54 | Spintax Evasion | ENH | C-Sms | ⚠️ spinning text to evade carrier A2P filtering conflicts with P-064's 10DLC path — **owner question** · ⛔ **REFUSES with SPAM_DETECTED** |"
)

text = text.replace(
    "| G11-32 | SMS Deliverability Fallback | ENH | C-Sms | ⭐ **T677 (owner): the spintax half is reframed as Lexicon Personalization (X-154) — we do not evade carrier filtering.** Stripping the URL on a 30007 and degrading honestly is the real mechanism (P-073) |",
    "| G11-32 | SMS Deliverability Fallback | ENH | C-Sms | ⭐ **T677 (owner): the spintax half is reframed as Lexicon Personalization (X-154) — we do not evade carrier filtering.** Stripping the URL on a 30007 and degrading honestly is the real mechanism (P-073) · ⛔ **REFUSES with RETRY_EXHAUSTED** |"
)

text = text.replace(
    "| G19-11 | Missed Call Text Back | ENH | C-Sms | ⛔ R80 — it fires on the RING, not the carrier timeout; transactional, blocked by nothing but STOP (P-061) |",
    "| G19-11 | Missed Call Text Back | ENH | C-Sms | ⛔ R80 — it fires on the RING, not the carrier timeout; transactional, blocked by nothing but STOP (P-061) · ⛔ **REFUSES with DNC_LISTED** |"
)

text = text.replace(
    "| G19-18 | SMS Integration | ENH | C-Sms | every link rides the short-linker (P-072); 159-char discipline is the segment law |",
    "| G19-18 | SMS Integration | ENH | C-Sms | every link rides the short-linker (P-072); 159-char discipline is the segment law · ⛔ **REFUSES with CHAR_LIMIT_EXCEEDED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
