import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G21-10 | Slack Integration | ENH | X-124 | the assistant answering in-thread from the generated help registry |",
    "| G21-10 | Slack Integration | ENH | X-124 | the assistant answering in-thread from the generated help registry · ⛔ **REFUSES with CHANNEL_REJECTED** |"
)
text = text.replace(
    "| **G21-10** | Answering in-thread from a channel | the same answer, delivered where the question was asked · **trigger:** a mention in a connected channel | the same registry | the channel path develops its own answer logic and the two diverge | the in-channel answer is byte-identical to the in-app answer for the same question, asserted | ⑥⑦ inherit |",
    "| **G21-10** | Answering in-thread from a channel | the same answer, delivered where the question was asked · **trigger:** a mention in a connected channel | the same registry | the channel path develops its own answer logic and the two diverge | the in-channel answer is byte-identical to the in-app answer for the same question, asserted | ⑥⑦ inherit · ⛔ **REFUSES with CHANNEL_REJECTED** |"
)

text = text.replace(
    "| G5-28 | In-App Support AI | ENH | X-124 | the assistant that configures the platform; HUMAN escalates to X-111 |",
    "| G5-28 | In-App Support AI | ENH | X-124 | the assistant that configures the platform; HUMAN escalates to X-111 · ⛔ **REFUSES with TICKET_REJECTED** |"
)
text = text.replace(
    "| G1-30 | Internal Chat | **ENH** | **the inbox thread (X-124 layer)** | transcribed from the G1 audit 2026-08-27 |",
    "| G1-30 | Internal Chat | **ENH** | **the inbox thread (X-124 layer)** | transcribed from the G1 audit 2026-08-27 · ⛔ **REFUSES with CHANNEL_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
