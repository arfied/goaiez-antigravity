with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "| G5-01 | Action Item Extraction | ENH | C-Agent | named in the header; the task lands in X-01 |",
    "| G5-01 | Action Item Extraction | ENH | C-Agent | named in the header; the task lands in X-01 · ⛔ **REFUSES with TASK_EXTRACTION_FAILED** |"
)

text = text.replace(
    "| G5-33 | Multi-Modal Agent Handoff | ENH | C-Agent | ONE `Conversation` across channels is why it works (X-121) |",
    "| G5-33 | Multi-Modal Agent Handoff | ENH | C-Agent | ONE `Conversation` across channels is why it works (X-121) · ⛔ **REFUSES with CONTEXT_LOST** |"
)

text = text.replace(
    "| G5-41 | Objection Handling RAG | ENH | C-Agent | named in the header |",
    "| G5-41 | Objection Handling RAG | ENH | C-Agent | named in the header · ⛔ **REFUSES with UNGROUNDED_OBJECTION** |"
)

text = text.replace(
    "| G5-48 | Reply Classification | ENH | C-Agent | named in the header |",
    "| G5-48 | Reply Classification | ENH | C-Agent | named in the header · ⛔ **REFUSES with UNKNOWN_CLASS** |"
)

text = text.replace(
    "| G10-13 | Content Moderation & Guardrails | ENH | C-Agent | compose-time only — ⛔ no LLM in the send path (P-071) |",
    "| G10-13 | Content Moderation & Guardrails | ENH | C-Agent | compose-time only — ⛔ no LLM in the send path (P-071) · ⛔ **REFUSES with MODERATION_FAILED** |"
)

text = text.replace(
    "| G10-37 | Under-18 Guardrails | ENH | C-Agent | ⛔ P-148 — under-18 rejected at ingest; the agent halts and hands off |",
    "| G10-37 | Under-18 Guardrails | ENH | C-Agent | ⛔ P-148 — under-18 rejected at ingest; the agent halts and hands off · ⛔ **REFUSES with MINOR_REJECTED** |"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
