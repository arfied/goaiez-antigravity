with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "| **G18-04** | Name insertion in a drop | the customer's name spoken in a voicemail drop · **trigger:** the drop | the name as a **`Fact`** | ⛔ **the name is guessed or taken from an email local-part** — *\"Hi, jsmith92\"* spoken aloud | a drop for a `Person` with no confirmed name **omits the name**, never improvises, asserted | ⑥⑦ inherit |"
replacement = "| **G18-04** | Name insertion in a drop | the customer's name spoken in a voicemail drop · **trigger:** the drop | the name as a **`Fact`** | ⛔ **the name is guessed or taken from an email local-part** — *\"Hi, jsmith92\"* spoken aloud | a drop for a `Person` with no confirmed name **omits the name**, never improvises, asserted · ⛔ **REFUSES with UNVERIFIED_FACT** | ⑥⑦ inherit |"

text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
