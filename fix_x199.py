with open('app/GOAIEZ-MASTER-PLAN.md', 'a') as f:
    f.write("""
## X-199 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G1-05** | Auto-Line Items | fails → one line at the total | `invoice_lines` | fails | fails → one line at the total | ⑥⑦ inherit · ⛔ **REFUSES with LINE_ITEM_FAILED** |
| **G1-31** | Invoice Customization | PDF fails → HTML | `invoices` | fails | PDF fails → HTML | ⑥⑦ inherit · ⛔ **REFUSES with CUSTOMIZATION_FAILED** |
| **G1-40** | Payment Link Generator | zero 404s | `invoices` | fails | zero 404s | ⑥⑦ inherit · ⛔ **REFUSES with LINK_FAILED** |
| **G1-51** | Stripe Auto-Charge | gateway-agnostic | `overflow_charges` | fails | gateway-agnostic | ⑥⑦ inherit · ⛔ **REFUSES with CHARGE_FAILED** |
| **G1-60** | WhatsApp Payment Links | a channel choice | `invoices` | fails | a channel choice | ⑥⑦ inherit · ⛔ **REFUSES with WHATSAPP_FAILED** |
""")
