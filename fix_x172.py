with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G10-24 | Legally Binding Signatures | ENH | X-172 | the signature pad lives in the customer portal; see F-19 |",
    "| G10-24 | Legally Binding Signatures | ENH | X-172 | the signature pad lives in the customer portal; see F-19 · ⛔ **REFUSES with SIGNATURE_REJECTED** |"
)

text = text.replace(
    "| ⛔ **G10-24** · **G10-32** | **X-172 — binding signatures + redline negotiation** | ⛔⛔ **MONEY** | ⛔⛔ **L1** | ⛔⛔ **the AI negotiates a redline.** *Accepting a customer's edit to a contract clause is a legal decision* | **a redline is SURFACED with a diff, never accepted**; `doctor` asserts no acceptance path exists in the module *(Q-061 · Law 122)* |",
    "| ⛔ **G10-24** · **G10-32** | **X-172 — binding signatures + redline negotiation** | ⛔⛔ **MONEY** | ⛔⛔ **L1** | ⛔⛔ **the AI negotiates a redline.** *Accepting a customer's edit to a contract clause is a legal decision* | **a redline is SURFACED with a diff, never accepted**; `doctor` asserts no acceptance path exists in the module *(Q-061 · Law 122)* · ⛔ **REFUSES with SIGNATURE_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
