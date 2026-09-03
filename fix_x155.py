with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G13-05 | Bot Fingerprinting | ENH | X-155 | spam and bot filtering is named in the header |",
    "| G13-05 | Bot Fingerprinting | ENH | X-155 | spam and bot filtering is named in the header · ⛔ **REFUSES with BOT_REJECTED** |"
)
text = text.replace(
    "| ⭐ **G13-05** · **G3-64** · **G17-12** | Bot + VPN + geo-mismatch — **one spec** | spam and bot filtering · **trigger:** submit | signals | ⛔⛔ **a real customer is classified a bot and their enquiry vanishes** | ⛔ **a rejected submission is STORED and flagged, never discarded** — asserted by rejecting one and finding the row; **the tenant can see and release it** |",
    "| ⭐ **G13-05** · **G3-64** · **G17-12** | Bot + VPN + geo-mismatch — **one spec** | spam and bot filtering · **trigger:** submit | signals | ⛔⛔ **a real customer is classified a bot and their enquiry vanishes** | ⛔ **a rejected submission is STORED and flagged, never discarded** — asserted by rejecting one and finding the row; **the tenant can see and release it** · ⛔ **REFUSES with BOT_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
