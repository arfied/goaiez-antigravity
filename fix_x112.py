with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G7-12 | Client Bill-Backs | ENH | X-112 | named in the header (§91's three agency modes) |",
    "| G7-12 | Client Bill-Backs | ENH | X-112 | named in the header (§91's three agency modes) · ⛔ **REFUSES with BILLBACK_REJECTED** |"
)

text = text.replace(
    "| ⛔ **G7-30** · **G7-31** · **G7-37** · **G7-12** | **Margin · markup · sweeps · bill-backs** — **one spec** | ⛔⛔ **MONEY** | ⛔⭐ **CONFIRM AT THE ACTION** *(`R235`: the STEP, not the module)* | ⛔ **a sweep bills a client for something the ledger cannot show** | ⛔ **every bill-back line traces to a metered event with its own row**, asserted · **integer arithmetic** *(§18)* |",
    "| ⛔ **G7-30** · **G7-31** · **G7-37** · **G7-12** | **Margin · markup · sweeps · bill-backs** — **one spec** | ⛔⛔ **MONEY** | ⛔⭐ **CONFIRM AT THE ACTION** *(`R235`: the STEP, not the module)* | ⛔ **a sweep bills a client for something the ledger cannot show** | ⛔ **every bill-back line traces to a metered event with its own row**, asserted · **integer arithmetic** *(§18)* · ⛔ **REFUSES with BILLBACK_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
