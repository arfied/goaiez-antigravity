with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G4-38 | Seamless Migration | ENH | X-118 | SAMPLE → real on conversion; ⚠️ the $179.99 figure is dead (money-number law) |",
    "| G4-38 | Seamless Migration | ENH | X-118 | SAMPLE → real on conversion; ⚠️ the $179.99 figure is dead (money-number law) · ⛔ **REFUSES with SIGNUP_FAILED** |"
)

text = text.replace(
    "| G4-44 | Universal Magic Login | ENH | X-118 | frictionless signup, magic link, no password |",
    "| G4-44 | Universal Magic Login | ENH | X-118 | frictionless signup, magic link, no password · ⛔ **REFUSES with SIGNUP_FAILED** |"
)

text = text.replace(
    "| **G4-44** · **G4-45** · **G4-38** · **G4-01** | **X-118 — magic login · OAuth hub · migration · activity** — **one spec** | — | **L3** | an OAuth connection stores a raw secret | ⛔ **credentials land in `X-206`, tenant-scoped, revealable to them** *(§184B)* |",
    "| **G4-44** · **G4-45** · **G4-38** · **G4-01** | **X-118 — magic login · OAuth hub · migration · activity** — **one spec** | — | **L3** | an OAuth connection stores a raw secret | ⛔ **credentials land in `X-206`, tenant-scoped, revealable to them** *(§184B)* · ⛔ **REFUSES with SIGNUP_FAILED** |"
)


with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
