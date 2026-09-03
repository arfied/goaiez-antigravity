with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G11-36 | Spam Call Blocking | ENH | C-Telephony | carrier-side screening before we pay for the minute |",
    "| G11-36 | Spam Call Blocking | ENH | C-Telephony | carrier-side screening before we pay for the minute · ⛔ **REFUSES with SPAM_DETECTED** |"
)

text = text.replace(
    "| G11-39 | Trust & Spam Shield | ENH | C-Telephony | SHAKEN/STIR grading on inbound |",
    "| G11-39 | Trust & Spam Shield | ENH | C-Telephony | SHAKEN/STIR grading on inbound · ⛔ **REFUSES with INVALID_SIGNATURE** |"
)

text = text.replace(
    "| G18-18 | Telephony Router | ENH | C-Telephony | the router and the eight adapters are the header |",
    "| G18-18 | Telephony Router | ENH | C-Telephony | the router and the eight adapters are the header · ⛔ **REFUSES with NO_ADAPTER** |"
)

text = text.replace(
    "| G18-20 | VIP Skipping | ENH | C-Telephony | LTV read from C-Billing; the bypass is a routing rule |",
    "| G18-20 | VIP Skipping | ENH | C-Telephony | LTV read from C-Billing; the bypass is a routing rule · ⛔ **REFUSES with ROUTING_FAILED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
