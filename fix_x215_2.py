import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| **N-215-01** | Signature binds a hash | fails | `none` | fails | the signature binds a HASH of the rendered document — alter one character and it is invalid · ⛔ **REFUSES with SIGNATURE_REJECTED** | ⑥⑦ inherit |",
    "| **N-215-01** | Signature binds a hash | fails | `none` | fails | the signature binds a HASH of the rendered document — alter one character and it is invalid · ⛔ **REFUSES with SIGNATURE_REJECTED** |"
)
text = text.replace(
    "| **N-215-02** | A comment never edits | fails | `none` | fails | a comment NEVER edits a sent document; it raises an X-202 item and re-issues a version · ⛔ **REFUSES with SIGNATURE_REJECTED** | ⑥⑦ inherit |",
    "| **N-215-02** | A comment never edits | fails | `none` | fails | a comment NEVER edits a sent document; it raises an X-202 item and re-issues a version · ⛔ **REFUSES with SIGNATURE_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
