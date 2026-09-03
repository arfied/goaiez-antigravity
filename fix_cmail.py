with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Add explicit refusal rows at the end of the file
text += """
## C-Mail explicit refusals

| # | Capability | ① what | ③ data | ④ failure mode | ⑤ test — assertion · refusal | ⑥⑦ |
| :--- | :--- | :--- | :--- | :--- | :--- | :--- |
| **G1-43** | Preference centre | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G3-18** | Content Spintax | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G9-21** | Health Dashboard | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G10-28** | Policy Escalation | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-06** | BIMI Logo Setup | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-09** | Deliverability Testing | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-11** | DMARC Reporting | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-12** | Email Inbox Parsing | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-15** | Gmail Read-Only Watch | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-16** | Inbox Placement Ramping | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-17** | Inbox Rotation | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-18** | Inbox Rotation | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-29** | RSS-to-Email | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G11-38** | SPF Flattening | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
| **G15-31** | G15-31 | fails | `mail_events` | fails | fails | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |
"""

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
