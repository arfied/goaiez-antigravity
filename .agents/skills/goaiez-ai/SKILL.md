---
name: goaiez-ai
description: Anything that calls a model. Use before writing a single AI call, a prompt, or a model name.
---

# THE AI CORE — ONE CALL PATH

## ⛔ R238: ONE CALL PATH, NO SECOND ENTRANCE

**Every AI call in this platform, from every module, goes through ONE path.**

```
module
  └─ $ai->run($moduleId, $jobClass, $input)
       ① GATE      X-126 — no grounding Fact → NO_FACT, BEFORE any spend
       ② BUDGET    C-Ai — tenant credit + token ceiling; REFUSE, never truncate
       ③ ASSEMBLE  prompt VERSION + context + tool contracts
       ④ REDACT    PII out before the payload leaves the building
       ⑤ CACHE     exact hit → return; semantic hit → return WITH the distance
       ⑥ ROUTE     R237 — PRIMARY, else BACKUP (different vendor), else COMPLEX
       ⑦ CALL      timeout · retry · circuit breaker
       ⑧ VALIDATE  schema · refusal codes · X-213 vision check on tenant-facing
       ⑨ RECORD    requested AND served · tokens · cost · latency · cache verdict
       ⑩ LEARN     corrections land in agent_instructions, never in a prompt edit
```

⭐ **Gate before budget before spend.** A second entrance is unbuildable once two
callers exist, which is why `C-Ai`, `X-219` and `X-220` are wave 3 — before
anything that calls a model.

## ⛔⛔⛔ `model_requested` AND `model_served` — THE MOST IMPORTANT COLUMN PAIR

**Without both, a silent fallback attributes the BACKUP's output to the
PRIMARY** — so every eval, every cost report and every *"which model is better"*
decision is measuring the wrong thing.

| `N-238-02` | `ai_calls` carries **`model_requested` · `model_served` · `fallback_reason`.** ⭐ **`doctor` fails a write that has one and not the other** |
| :--- | :--- |

## ⛔ A PROMPT IS A ROW, AND ITS VERSION IS RECORDED ON EVERY CALL

| `ai_prompts` | key · version · body · job_class · created_by · frozen_at |
| :--- | :--- |
| `N-238-03` | ⛔ **every `ai_calls` row records the prompt VERSION it used.** *"The output changed" is unanswerable without it* |
| `N-238-04` | ⭐ **a frozen prompt is immutable** — a change is a **new version**, never an edit |

## ⛔ NEVER NAME A VENDOR IN A MODULE

A hardcoded model string is a `boundary` violation and fails the COMMIT.
⛔ **`N-238-01` extends that to vendor CLIENT CLASSES** — a module that reaches a
vendor SDK directly is a build failure, not a shortcut.

⚠️ **Six of the twelve AI concerns were prose-only with no module owning them** —
prompt versioning, PII redaction, injection defence, tool calling, model
deprecation, and requested-vs-served. **The gap was never features; it was that
the brain was scattered across seven modules with no single contract.**
`source/GOAIEZ-AI-CORE.md` is the full account — read it before wave 3.

## R237: A MODEL IS A ROW, NOT A CHOICE IN CODE

Every model from every vendor — self-hosted included — is a **row**, assigned
**per module**:

| PRIMARY | |
| :--- | :--- |
| BACKUP | ⛔ **a different vendor** — a backup on the same vendor is not a backup |
| COMPLEX | ✅ **capped UNDER `C-Ai`'s tenant and platform ceilings** — decided 2026-08-29. *A slot that can escape the budget is not a slot, it is a leak.* ⭐ **The JOB CLASS decides what is "complex", declared as a ROW** — never a per-call heuristic |

⭐ Gemini-in-Antigravity is **one row**. It does not change the plan; it changes
an assignment.

## R239 / X-220: A PROMPT IS A ROW WITH A VERSION

**And its golden set is versioned with it.** A prompt edit that does not move its
golden set is an untested deploy of the thing most likely to change behaviour.

## ⛔ NO FACT, NO SKILL

`X-126` evaluates on every action. **The agent never invents a price, a time or a
link** — if `X-119` has no Fact for it, the skill is refused.

This is the whole of journey 3: *a quote comes from the pricebook or does not
come at all.* It is a refusal, and the refusal is the feature.

## THE STRENGTH SPLIT, RECORDED

| photorealism, lighting, brand assets | **image generation** |
| :--- | :--- |
| heavy-text layout composition | ⛔ **not image generation — that is HTML and CSS**, which `X-161`/`X-179` already produce |

## ⛔ AND THE REFUSALS ARE OWED, NOT OPTIONAL

**322 of 966 capability rows need a refusal written** — a refusal is required
only where refusal is *possible*, which is what scoped it down from 1,294.

✅ **They are YOURS to write — decided 2026-08-29.** Written **per module, during
that module's build**: a refusal is easiest to write while you are holding the
thing that refuses. It is not a separate project and not an owner task.

⛔ **Do not delete a capability id to clear the violation — the id going missing
IS the failure.**
