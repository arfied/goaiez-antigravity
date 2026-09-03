with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G2-58 | Pre-Qualification Logic | ENH | X-108 | questions on the booking page |",
    "| G2-58 | Pre-Qualification Logic | ENH | X-108 | questions on the booking page · ⛔ **REFUSES with QUALIFICATION_FAILED** |"
)

text = text.replace(
    "| G19-01 | Automated Waitlist | ENH | X-108 | named in the header — a cancellation fills itself |",
    "| G19-01 | Automated Waitlist | ENH | X-108 | named in the header — a cancellation fills itself · ⛔ **REFUSES with SLOT_TAKEN** |"
)

text = text.replace(
    "| ⭐ **G19-01** | The waitlist | a cancellation pulls the next person forward · **trigger:** a cancel | the waitlist | ⛔ **it texts nine people the same slot and eight are disappointed by a race** | **the slot is HELD for the first person for the claim window** *(P-077 — 30 min)* **before the next is offered**, asserted with a queue of three |",
    "| ⭐ **G19-01** | The waitlist | a cancellation pulls the next person forward · **trigger:** a cancel | the waitlist | ⛔ **it texts nine people the same slot and eight are disappointed by a race** | **the slot is HELD for the first person for the claim window** *(P-077 — 30 min)* **before the next is offered**, asserted with a queue of three · ⛔ **REFUSES with SLOT_TAKEN** |"
)

text = text.replace(
    "| **G2-58** | Pre-qualification | the questions before the booking · **trigger:** the request | the question set | it interrogates a ready buyer and loses them | the set is asserted **capped and skippable**; an emergency intent **bypasses it entirely** *(R11's shape)* |",
    "| **G2-58** | Pre-qualification | the questions before the booking · **trigger:** the request | the question set | it interrogates a ready buyer and loses them | the set is asserted **capped and skippable**; an emergency intent **bypasses it entirely** *(R11's shape)* · ⛔ **REFUSES with QUALIFICATION_FAILED** |"
)

text = text.replace(
    "| **G1-12** | *(the deposit at booking)* | a deposit taken at book time · **trigger:** a booking that requires one | X-117 · tokens only | card data touches our DOM | ⛔ **tokens only** *(P-160)*, the iframe boundary asserted |",
    "| **G1-12** | *(the deposit at booking)* | a deposit taken at book time · **trigger:** a booking that requires one | X-117 · tokens only | card data touches our DOM | ⛔ **tokens only** *(P-160)*, the iframe boundary asserted · ⛔ **REFUSES with TOKEN_MISSING** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
