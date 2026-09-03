with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G18-23 | Voicemail-to-Text | ENH | X-66 | transcription into the one Conversation |",
    "| G18-23 | Voicemail-to-Text | ENH | X-66 | transcription into the one Conversation · ⛔ **REFUSES with MULTIPLE_THREADS** |"
)

text = text.replace(
    "| **G18-23** | Voicemail-to-text | transcription lands in the **one** `Conversation` · **trigger:** voicemail left | the thread | it creates a new thread and the history splits | a voicemail from a known number appends to the existing thread, asserted; from an unknown number it creates the `Person` **and** the thread in one transaction | ⑥⑦ inherit |",
    "| **G18-23** | Voicemail-to-text | transcription lands in the **one** `Conversation` · **trigger:** voicemail left | the thread | it creates a new thread and the history splits | a voicemail from a known number appends to the existing thread, asserted; from an unknown number it creates the `Person` **and** the thread in one transaction | ⑥⑦ inherit · ⛔ **REFUSES with MULTIPLE_THREADS** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
