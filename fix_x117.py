with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| ⭐ **G1-39** · **G1-15** · **G6-02** | **X-117 — payment on forms · BORIS · one-click upsell** — **one spec** | ⛔ **MONEY** | ⛔ **L1** | ⛔⛔ **a \"one-click upsell\" charges a stored card with no new authorisation** | ⛔ **every charge requires a fresh authorisation event** — asserted by attempting a second charge on a stored token with no new consent · *(P-160: tokens only)* |",
    "| ⭐ **G1-39** · **G1-15** · **G6-02** | **X-117 — payment on forms · BORIS · one-click upsell** — **one spec** | ⛔ **MONEY** | ⛔ **L1** | ⛔⛔ **a \"one-click upsell\" charges a stored card with no new authorisation** | ⛔ **every charge requires a fresh authorisation event** — asserted by attempting a second charge on a stored token with no new consent · *(P-160: tokens only)* · ⛔ **REFUSES with NO_CONSENT** |"
)

text += """
## X-117 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G1-73** | Milestone Billing | fails | `orders` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with MILESTONE_REJECTED** |
| **G1-75** | Paid Consultations | fails | `sellables` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with PRICE_REJECTED** |
| **G1-81** | Subscription Gifting | fails | `orders` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with GIFT_REJECTED** |
| **G1-82** | Subscription Pausing | fails | `orders` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with PAUSE_REJECTED** |
| **G17-31** | Multi-Currency Landed Cost | fails | `orders` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CURRENCY_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
