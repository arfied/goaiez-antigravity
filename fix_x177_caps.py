with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "| ⭐⭐⭐ **G8-27** · **G12-11** · **G8-18** · **G12-31** | **X-177 — local posts · bulk posts · Q&A seeding** — **one spec** | **CONFIRM** | ⛔ **L1 → L3 via §149.1's ladder** | ⛔⛔ **autonomous posting to a GBP that then gets SUSPENDED** — *and a suspended profile is the tenant's phone ringing stopping, not a feature degrading* | ⭐ **the trust ladder binds: N approved posts unedited unlocks unattended** · **the claim lint runs on every post** · ⛔ **Q&A seeding posts a QUESTION and answers it as the business — asserted labelled as the business, never as a customer** |"

replacement = "| ⭐⭐⭐ **G8-27** · **G12-11** · **G8-18** · **G12-31** | **X-177 — local posts · bulk posts · Q&A seeding** — **one spec** | **CONFIRM** | ⛔ **L1 → L3 via §149.1's ladder** | ⛔⛔ **autonomous posting to a GBP that then gets SUSPENDED** — *and a suspended profile is the tenant's phone ringing stopping, not a feature degrading* | ⭐ **the trust ladder binds: N approved posts unedited unlocks unattended** · **the claim lint runs on every post** · ⛔ **Q&A seeding posts a QUESTION and answers it as the business — asserted labelled as the business, never as a customer** · ⛔ **REFUSES with SUSPENDED_PROFILE** |"

text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
