with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **G1-23** · **G1-34** · **G17-04** | **X-198 — fraud · mock gateway · idempotency** — **one spec** | ⛔ MONEY | **L1** | *(§201.3)* | **the idempotency key is asserted per adapter**, both gateways |",
    "| **G1-23** · **G1-34** · **G17-04** | **X-198 — fraud · mock gateway · idempotency** — **one spec** | ⛔ MONEY | **L1** | *(§201.3)* | **the idempotency key is asserted per adapter**, both gateways · ⛔ **REFUSES with IDEMPOTENCY_LOCKED** |"
)

text += """
## X-198 explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **N-010** | refund verb does not exist here | fails | `payments` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with NO_REFUND_VERB** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
