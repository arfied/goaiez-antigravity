# QUESTIONS ONLY THE OWNER CAN ANSWER

**Written to be forwarded as-is.** No id numbers, no jargon — each one says what
is being asked, what the options cost, and what happens meanwhile.

⛔ **None of these stops the build.** Each one parks the modules that depend on
it; everything else carries on. The agent adds to this file and keeps working.

---

## 1. What does the word "job" mean in this system?

**The problem:** the plan uses "job" to mean a **piece of work for a customer** —
a boiler replacement, a callout. The application uses "job" to mean an item on
the **background queue**, which is a technical thing customers never see. Both
want the same database table name.

**Two ways out:**

- Call the customer-facing one a **work order**, leave the queue alone. Clearest
  to read; every screen, report and message that says "job" has to say "work
  order" instead.
- Keep calling it a **job** and rename the queue's table. Nothing customer-facing
  changes; the rename touches infrastructure, and anything that assumes the
  standard name has to be found.

**Until this is answered:** the entity layer can be built additively but cannot be
reconciled. It is the largest single blocker on the list.

**Recommendation: work order.** The queue's name is a convention with tooling
built on it, and the customer-facing noun is the one people will read every day.

---

## 2. One word for a customer's customer, and one for a fact

The plan carries **three** words for the same thing — *businesses*, *companies*,
*customers* — and **two** for stored facts, *facts* and *business_facts*.

**What is needed:** which one is canonical in each pair, and what happens to the
other — deleted, or kept as a synonym.

**Why it matters:** every module owns tables, and one table has one owner. Two
names for one thing means two owners, and the boundary check cannot tell a
duplicate from a legitimate second table.

---

## 3. The 322 refusals

Every capability the AI can use needs to know **when to say no**. There are 966
capabilities; 322 of them can meaningfully refuse — the rest cannot refuse
anything, so they need no rule.

**The 322 refusal rules have not been written.** They are business policy, not
engineering: *when should the assistant decline to quote a price? To book
outside hours? To promise a callout time?*

⛔ **This is deliberately not an agent task.** An agent writing them would be
inventing policy and then testing its own invention.

**What would unblock it:** the rules, in any form — a list, a conversation, a
worked example per category is enough to derive the rest.

---

## 4. Is there a ceiling on the expensive AI setting?

Each module picks three AI models: a **main** one, a **backup** from a different
vendor, and a **complex** one for hard requests.

**The complex slot has no spending limit.** The main one does. So a module that
routes a lot of work to "complex" can spend past the ceiling that exists,
because that ceiling sits beside it rather than above it.

**What is needed:** confirmation that the complex slot sits **under** the same
ceiling as everything else. If it should be allowed to exceed it, by how much,
and who is told when it does.

**Recommendation: put it under the same ceiling.** A limit that a setting can
step around is not a limit, and this one is reachable by ordinary use rather
than by abuse.

---

## 5. Two things that are already settled — recorded so they are not re-opened

**The old code.** The instruction was a fresh build, and this package builds
fresh. The previous two trees are read-only prior art and nothing here touches
them.

**Outbound AI calling.** Ruled permitted, both kinds — sales calls to businesses
and the invoice-chasing call to a customer — with AI disclosure and every number
checked against the do-not-call list first.

⚠️ **Two things follow from that ruling that are themselves owner asks**, and
they will surface when that wave is reached:

- **The wording an AI caller uses to disclose itself has not been written**, and
  it is not the texting wording — that says "reply STOP", which nobody can do on
  a phone call. It needs whoever reviews the legal copy.
- **Calling hours are not the same law as texting hours.** The table this system
  has is a texting-hours table. The two often coincide, which is what makes the
  wrong one look usable. A calling-hours table is a separate piece of work.
