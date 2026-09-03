import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G2-01 | \"Find Near Me\" | ENH | X-10 | proximity query over the polygon store; the field surface is X-171 |",
    "| G2-01 | \"Find Near Me\" | ENH | X-10 | proximity query over the polygon store; the field surface is X-171 · ⛔ **REFUSES with ROUTE_REJECTED** |"
)
text = text.replace(
    "| G2-74 | Sticky Routing | ENH | X-10 | a returning caller reaches the same owner; the carrier half is P-070 |",
    "| G2-74 | Sticky Routing | ENH | X-10 | a returning caller reaches the same owner; the carrier half is P-070 · ⛔ **REFUSES with ROUTE_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
