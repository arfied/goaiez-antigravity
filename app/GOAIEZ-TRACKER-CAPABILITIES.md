# GOAIEZ — TRACKER · CAPABILITIES
**STATUS COLUMN REPAIRED 2026-08-27 — not one row had ever read `SPECCED`; 674 rows carry a seven-field spec in the plan and the column never said so. Measured by joining every G-id against the plan's capability tables, two-pass, classification rows subtracted.**
**T677 · turns 30 · 31 · 32 (C1 · C2 · C3) COMPLETE · generated from `GOAIEZ-905-ASSIGNED.md` (T604) · one row per register line · ⭐⭐ every domain classified; only G14's fenced 38 carry no BL**

⭐ **Legend — BL (Boundary Law):** `MODULE` owns its own primary table + broadcasts its own events + has a dedicated UI/settings surface → keeps/gets an X-number · `ENH→X-nn` adds a column/payload/component to an existing module → folds into that parent, no X-number · `UNMAPPED` needs a module that does not exist → flagged for minting · `KILLED` by a standing ruling (cite it) · `RE-HOME→Gn` belongs to another domain.
⭐ **STATUS:** `PENDING` · `CLASSIFIED` (C-phase done) · `SPECCED` (7 fields written) · `SUPERSEDED`

## G1 · BILLING & MONEY — 61
**Modules of record:** C-Billing · X-120 · X-82 · **Turn:** G1 ✅ (§153, 5-field) → BL audit T662

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G1-01 | 100-Credit Demo Sandbox | **ENH** | **C-Billing trial caps + X-161** | SPECCED | corpus says Twilio/tokens → **Infobip / AI credits**. Cap reached → **R25: alert + keep answering** · transcribed from the G1 audit 2026-08-27 refuses refuses |
| ⭐ (reclaimed) | Annual Upgrade Nudges | **RECLAIMED T677** | **X-210** *(issuer_scope=platform)* | CLASSIFIED | §215 — R26 revoked. ⛔ **R34 survives: the save-offer adds NO STEP — one screen, both choices, cancel always one tap.**  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| G1-03 | Auto-Categorization | **ENH** | **X-173 AccountingSync** | SPECCED | transcribed from the G1 audit 2026-08-27 |
| ~~G1-04~~ | Auto-Generation *(PO)* | ⛔ RETIRED | — | RETIRED | owner removed the register line at T677 (§170.0); it was classified RE-HOME to X-167 and that spec is void |
| G1-05 | Auto-Line Items | **ENH** | **X-199** | SPECCED | AI structures lines from the job; fails → one line at the total · transcribed from the G1 audit 2026-08-27 |
| G1-06 | Auto-Recharge | **KILLED** | — | KILLED | ⛔ killed by ruling: the auto-top-up matrix (T591) · transcribed from the G1 audit 2026-08-27 |
| G1-07 | Auto-Reply Simulation | **ENH** → X-223 | **G11 warm-up engine** → X-223 | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-08 | Automated Chargebacks & Dispute Handling | **MODULE** | ****X-201 DisputeDesk** *(EXISTS — minted §156.3; the audit predates it)*** | SPECCED | own table `disputes` · own events `dispute.opened/evidence.compiled/resolved` · operator queue UI — passes all three tests, no parent exists. ⛔ "instantly suspends" must ride **R83 · transcribed from the G1 audit 2026-08-27 |
| G1-09 | Automated Dunning Ladders | **KILLED** | — | KILLED | ⛔ killed by ruling: §45A — the 21-day timeline is the ONE ladder · transcribed from the G1 audit 2026-08-27 |
| G1-10 | Automated Reconciliation | **ENH** | **C-Billing** | SPECCED | CC-17 §3's nightly reconciler already exists — this adds the payout-report join. **Flags, never auto-corrects** · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-11 | Automated Reconciliation | **ENH** | **X-123** | SPECCED | this is **wiring, not a capability** — three subscribers on `invoice.paid`, each owned by its module. Matrix check proves it · transcribed from the G1 audit 2026-08-27 |
| G1-12 | Automated Reminders | **ENH** | **X-108 Scheduler** | SPECCED | appointment reminders belong to the appointment engine (§146); the only billing fact is *one segment = one credit*. Spec with X-108's group · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-13 | Billing Monitor | **ENH** | **C-Billing dunning** | SPECCED | gateway-outage detection: 20 failures/5 min → `gateway.outage_suspected`, dunning pauses globally · transcribed from the G1 audit 2026-08-27 |
| G1-14 | Billing Sweeps & Drops | **ENH** | **C-Billing** | SPECCED | `SettleBatchSweep` — a job over the existing charge-intents table; partial settlement never recorded · transcribed from the G1 audit 2026-08-27 |
| G1-15 | Buy Online, Return In-Store - BORIS | **ENH** | **X-117 (a return state)** | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-16 | Cash-Collected Validation | **ENH** | **X-170 Commissions** | SPECCED | commissions/agency payouts only on **settled** cash (§91 agency modes) · transcribed from the G1 audit 2026-08-27 |
| G1-17 | Countdown Dunning | **KILLED** | — | KILLED | ⛔ killed by ruling: §45A — the 21-day timeline is the ONE ladder · transcribed from the G1 audit 2026-08-27 |
| G1-18 | Credit Deduction Sync | **ENH** | **C-Billing** | SPECCED | merges with G1-57 — every AI call writes cent-precision cost; retail debit **derived at 8:1**, never typed · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-19 | Credit Expiry & Dormancy | **ENH** | **C-Billing** | SPECCED | `expires_at` — granted expires at the boundary, **purchased never** · transcribed from the G1 audit 2026-08-27 |
| G1-20 | Credit Ledger Check | **ENH** | **C-Billing (reads X-82)** | SPECCED | pre-spend estimate; unavailable → proceed, never block · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-21 | Dynamic Tax Calculations | **KILLED** | — | KILLED | ⛔ killed by ruling: §145.4 — no nexus, a rate per pricebook · transcribed from the G1 audit 2026-08-27 |
| G1-22 | Event Subscriptions | **ENH** | **X-123 + X-142** | SPECCED | tenant-facing subscribe UI over the bus; delivery is the bus's. **[AMENDED T677 — F-11]** *was `X-101`, which is not in the pinned roster (§164.1); X-142 `McpServer` owns `webhook_ · transcribed from the G1 audit 2026-08-27 |
| G1-23 | Fraud Prevention - Radar | **ENH** | **X-198** | SPECCED | gateway-side rules we **configure**, never implement · transcribed from the G1 audit 2026-08-27 |
| G1-24 | Frictionless Signup | **ENH** | **X-118** | SPECCED | magic-link, no card — onboarding's, not billing's · transcribed from the G1 audit 2026-08-27 |
| G1-25 | Ghost Account Detection | **ENH → RE-HOME G3** | **X-105** | SPECCED | dormant **prospect** detection before outbound spend — acquisition wearing a billing label · transcribed from the G1 audit 2026-08-27 |
| G1-26 | Hyper-Personalization | **ENH** | **X-105 outreach (research → intro line)** | SPECCED | transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-27 | Important Tagging | **ENH** → X-223 | **G11 warm-up engine** → X-223 | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-28 | In-App Lockouts | **ENH** | **C-Billing dunning** | SPECCED | day-10 **banner**, never a lockout — §45A · transcribed from the G1 audit 2026-08-27 |
| G1-29 | Interactive SOPs | **ENH** | **X-111 help / G15** | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-30 | Internal Chat | **ENH** | **the inbox thread (X-124 layer)** | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-31 | Invoice Customization | **ENH** | **X-199** | SPECCED | branding columns on the invoice template; PDF fails → HTML, never no invoice · transcribed from the G1 audit 2026-08-27 |
| G1-32 | Ledger & Free Limits | **MODULE** | **C-Billing (the ledger core)** | SPECCED | the one true module in the domain — owns `credit_ledger_entries`, emits `ledger.*`, has the Money screens. Already exists; nothing to mint · transcribed from the G1 audit 2026-08-27 |
| G1-33 | Micro-Dunning | **ENH (split)** | **C-Billing and X-199** | SPECCED | ⚠️ two halves: platform half = pre-dunning (specced); **tenant-invoice half** *(3 days before due · due · 3 late)* = X-199 receivables for the invoice-terms **minority** (§46A) — * · transcribed from the G1 audit 2026-08-27 |
| G1-34 | Mock Payment Gateway | **ENH** | **X-198 adapter roster** | SPECCED | a mock adapter with `is_mock: true`; `doctor` fails on any mock row in production · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-35 | MRR Dashboard | **ENH** | **X-111** | SPECCED | a report over the ledger, no table of its own · transcribed from the G1 audit 2026-08-27 |
| G1-36 | MRR Prediction | **ENH** | **X-07 Forecaster** | SPECCED | forecasting already has a module; this is one more series · transcribed from the G1 audit 2026-08-27 |
| G1-37 | Multi-Stripe Account Support | **KILLED** | — | KILLED | ⛔ killed by ruling: X-198 MerchantConnect (the feature exists, gateway-agnostic) · transcribed from the G1 audit 2026-08-27 |
| G1-38 | Niche Onboarding | **ENH** | **X-118** | SPECCED | the industry picker + template seeding **already does this** (§151); spec is a confirm-that-it-does · transcribed from the G1 audit 2026-08-27 |
| G1-39 | Payment Integration | **ENH** | **X-117** | SPECCED | Accept.js — the card never touches our page · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-40 | Payment Link Generator | **ENH** | **X-199** | SPECCED | links off an invoice/quote, on the short-linker (R14); **zero 404s, the agent never invents a URL** · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-41 | Ping Testing | **ENH** | **X-123** | SPECCED | the "Test Webhook" button. **[AMENDED T677 — F-11]** *was `X-101`; X-123's header names webhook brokering, the request inspector and "Test Webhook"* · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-42 | Pre-Dunning Alerts | **ENH** | **C-Billing (reads X-120 expiry)** | SPECCED | one email 15 days before a known expiry — **the cheapest save in the platform** · transcribed from the G1 audit 2026-08-27 |
| G1-43 | Preference Center | **ENH** | **C-Mail / consent layer** | SPECCED | granular unsubscribe; **transactional still sends** (§137) · transcribed from the G1 audit 2026-08-27 refuses refuses |
| G1-44 | Proration Handling | **KILLED** | — | KILLED | ⛔ killed by ruling: R171 — calendar anniversary, none · transcribed from the G1 audit 2026-08-27 |
| G1-45 | Revision Notes | **ENH** | **X-01 approval flow** | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-46 | Run-Rate Math | **ENH** | **X-07 Forecaster** | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-47 | Seasonality Adjustments | **ENH** | **X-07 Forecaster** | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-48 | Smart Dunning Pauses | **KILLED** | — | KILLED | ⛔ killed by ruling: §45A — the 21-day timeline is the ONE ladder · transcribed from the G1 audit 2026-08-27 |
| G1-49 | Smart Retries | **ENH** | **C-Billing dunning** | SPECCED | gateway ML timing, else fixed d1/3/5/7 · transcribed from the G1 audit 2026-08-27 |
| G1-50 | SMS Dunning | **KILLED** | — | KILLED | ⛔ killed by ruling: §45A — the 21-day timeline is the ONE ladder · transcribed from the G1 audit 2026-08-27 |
| G1-51 | Stripe Auto-Charge | **ENH** | **X-199 (via X-198)** | SPECCED | gateway-agnostic — "Stripe" is corpus vocabulary · transcribed from the G1 audit 2026-08-27 |
| G1-52 | Stripe Metered Billing Sync | **ENH** | **C-Billing** | SPECCED | **the ledger is the source**; the gateway receives period totals, never per-event usage · transcribed from the G1 audit 2026-08-27 |
| G1-53 | Subscription Overrides | **KILLED** | — | KILLED | ⛔ killed by ruling: T591 — no discounts · transcribed from the G1 audit 2026-08-27 |
| G1-54 | Tax Jurisdiction Mapping | **KILLED** | — | KILLED | ⛔ killed by ruling: §145.4 — no nexus, a rate per pricebook · transcribed from the G1 audit 2026-08-27 |
| ⛔ FENCED | ~~Time Tracking → timesheets half~~ (G1-55) | **⛔ FENCED T677** | **—** | FENCED | §220 — ⛔⛔ **NO PAYROLL. "The cleanest way to never produce a wrong wage is to never produce a wage."** The export ships HOURS and COMMISSION-EARNED only. |
| G1-56 | Trial Sandboxing | **ENH** | **C-Billing trial caps** | SPECCED | hard cap on the ledger, **R25 at the cap** · transcribed from the G1 audit 2026-08-27 |
| G1-57 | Usage-Based Billing for AI Tokens | **ENH** | **C-Billing** | SPECCED | = G1-18; one spec · transcribed from the G1 audit 2026-08-27 |
| G1-58 | Vendor Scoring | **ENH** | **X-167 Inventory** | SPECCED | transcribed from the G1 audit 2026-08-27 |
| G1-59 | Voice Assistant Dunning | **ENH** | **C-Billing → X-66 action** | SPECCED | stage 7 calls **one** `voice.call` action; no module of its own · transcribed from the G1 audit 2026-08-27 |
| G1-60 | WhatsApp Payment Links | **ENH** | **X-199 + C-Whatsapp** | SPECCED | a channel choice on an existing link · transcribed from the G1 audit 2026-08-27 refuses refuses |
| ⭐ (reclaimed) | Win-Back Drips | **RECLAIMED T677** | **X-210** | CLASSIFIED | §214 — the owner overruled the R26 fence: **R26 governs GOAIEZ's own pricing, not a tenant's.**  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||

## G2 · CRM & PIPELINE — 77
**Modules of record:** X-01 · X-07 · X-08 · X-10 · X-108 · X-162 · **Turn:** ✅ 30 C1 (T677 · §165)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G2-01 | "Find Near Me" | ENH | X-10 | SPECCED | proximity query over the polygon store; the field surface is X-171 refuses refuses |
| G2-02 | "If/Then" Routing | UNMAPPED | ApprovalDesk | SPECCED | asset approval routed by type — see §165.5 |
| G2-03 | 1-Click CRM Injection | ENH | X-196 | SPECCED | a button in the extension; the write is X-156's and carries an `attestation_id` (P-069) |
| G2-04 | 2-Way Sync | ENH | X-108 | SPECCED | Google/Outlook calendars; the header already owns Calendly/Eventbrite sync |
| G2-05 | API Sync | ENH | X-156 | SPECCED | Salesforce/HubSpot/GHL as ingest sources — nothing sits in a staging table |
| G2-06 | Appointment Scheduling | ENH | X-108 | SPECCED | the agent calls `availability.request`; time is looked up or refused (P-093) |
| G2-07 | Auto-Assignment | ENH | X-10 | SPECCED | geocode → polygon → assign; named in the header |
| G2-08 | Auto-Scheduling | RE-HOME→G12 | X-182 | SPECCED | social scheduling — spec with the social pass (turn 32) |
| G2-09 | Automated QA Scoring | ENH | X-200 | SPECCED | `qa_scorecards`; an AI seat is scored exactly like a human · refuses: to score an AI seat differently from a human seat |
| G2-10 | Automated Scheduling | RE-HOME→G15 | X-108 | SPECCED | the calendar half is X-108's; the hiring flow is G15's |
| G2-11 | Automated Trigger | ENH | X-183 | SPECCED | R36's case-study machine — double consent, never automatic publication |
| G2-12 | Blackout Dates | ENH (split) | X-108 | SPECCED | split: the blackout calendar is X-108's *("blackouts and holiday overrides")*; the PTO request-and-approval… |
| G2-13 | Calendar Auto-Block | ENH | X-108 | SPECCED | out-of-office is named in the header |
| G2-14 | Campaign ML Optimization | ENH | X-186 | SPECCED | best send time from the tenant's own history; still Marketing class, still inside the window (P-063) · refuses: a send time outside the tenant's marketing window (P-063), whatever the model proposes |
| G2-15 | Collision Detection | RE-HOME→G15 | X-113 | SPECCED | overlapping time-off requests |
| G2-16 | Collision Detection | ENH | X-01 | SPECCED | "Rep A is typing" presence on the shared thread |
| G2-17 | Conditional Logic | ENH | X-155 | SPECCED | multi-step form logic |
| G2-18 | Contact De-Duplication | ENH | X-01 | SPECCED | named in the header; one `Person` (P-163) |
| G2-19 | Cost Routing Engine | ENH | C-Ai | SPECCED | the dispatcher's whole job; cost is a routing input, never a quality excuse |
| G2-20 | CRM Binding | ENH | X-155 | SPECCED | forms write the twelve entities directly — no mapping screen |
| G2-21 | CRM Database Lookups | ENH | X-66 | SPECCED | the agent reads a grounded `Fact`; a balance is looked up or refused (P-092) |
| G2-22 | CRM Deal Sync | ENH | X-125 | SPECCED | a stage change fires a flow; a 40-step template is a canvas, not code |
| G2-23 | CRM Logging | ENH | X-01 | SPECCED | named in the header |
| G2-24 | CRM Record Popup | RE-HOME→FSM | X-167 | SPECCED | SKU scan — the FSM bucket C4 must add (G1's structural finding) |
| G2-25 | CRM UI - Frontend Module | ENH | X-01 | SPECCED | D1: MASTER = Honest Counter for the tenant app; God-Mode/glassmorphism is console-only |
| G2-26 | Custom Anthems | ENH | X-200 | SPECCED | the wallboard (an X-194 view of live queue state) |
| G2-27 | Custom Finetuning Pipelines | ENH | C-Ai | SPECCED | the plan's mechanism is grounding + lexicon + the teaching box (P-097); a per-tenant fine-tune is an owner… refuses refuses |
| G2-28 | Custom SLAs | ENH | X-113 | SPECCED | named in the header |
| G2-29 | Deal Slip Warnings | ENH | X-162 | SPECCED | named in the header |
| G2-30 | Deduplication | ENH | X-160 | SPECCED | SHA-256 at ingest; the `Asset` row is X-121's |
| G2-31 | Direct CRM Injection | ENH | X-156 | SPECCED | every contact write carries an `attestation_id` or is refused (P-069) |
| G2-32 | Engagement Scoring | ENH | X-01 | SPECCED | `lead_scores`; opens and clicks arrive from C-Mail refuses refuses |
| G2-33 | Expiry Timers | UNMAPPED | ApprovalDesk | SPECCED | escalation on an unanswered approval |
| G2-34 | Fraud Score Routing | ENH | X-10 | SPECCED | named in the header |
| G2-35 | Head-to-Head Battles | ENH | X-200 | SPECCED | the wallboard;  §150.4's scorecard is positive only |
| G2-36 | Hidden Lead Scoring | ENH | X-01 | SPECCED | named in the header; the UTM itself is X-138's |
| G2-37 | Historical Trophies | ENH | X-200 | SPECCED | the wallboard |
| G2-38 | ICP Scoring | ENH | X-01 | SPECCED | the grade is a `lead_score`; the data is X-134's and carries `confidence` (P-147) |
| G2-39 | Integrated CRM Forms | ENH | X-155 | SPECCED | named in X-103's and X-156's headers as well |
| G2-40 | Jargon Injection | ENH | X-154 | SPECCED | the lexicon governs every compose; R19 keeps medical out |
| G2-41 | Kanban & Predictability | ENH | X-162 | SPECCED | named in the header |
| G2-42 | Lead Caps | ENH | X-01 | SPECCED | named in the header |
| G2-43 | Lead Transparency | ENH | X-112 | SPECCED | the client sees the agency's price only (§17.5) |
| G2-44 | Management Alerts | RE-HOME→G15 | X-113 | SPECCED | a wellbeing alert to a manager |
| G2-45 | Meeting Booking AI | ENH | X-108 | SPECCED | the agent offers only a window the scheduler confirmed · refuses: a window the scheduler has not confirmed |
| G2-46 | Meta Lead Forms Webhook | ENH | X-156 | SPECCED | named in the header; the HMAC-verified door |
| G2-47 | Multi-Stage Routing | UNMAPPED | ApprovalDesk | SPECCED | a second mandatory sign-off above a threshold |
| G2-48 | Natural Language Routing | ENH | X-66 | SPECCED | ElevenLabs is corpus vocabulary — the stack is X-197 (§18F) |
| G2-49 | No-Show Reactivation | ENH | X-108 | SPECCED | named in the header |
| G2-50 | One-Click Installs | ENH | X-195 | SPECCED | an install is a manifest + config rows, never executable code |
| ~~G2-51~~ | Out of Hours Tracking | ⛔ **KILLED** | — | **KILLED T677 · §208** | ⛔⛔ **KILLED at §208, T677 — staff surveillance. The T677 ruling had already killed seven rows of this shape; these are the same instinct under an operations label. What survives is coaching-only and lives in X-200's positive-only scorecard, which has no `rank` field in its schema.** |
| G2-52 | Payload Customization | ENH | X-123 | SPECCED | the outbound payload map on the broker |
| G2-53 | Permission Routing | UNMAPPED | ApprovalDesk | SPECCED | client approve-by-magic-link before publish |
| G2-54 | Pipeline Automation | ENH | X-162 | SPECCED | named in the header; the sequence fires through X-186 |
| G2-55 | Pipeline Automations | ENH | X-162 | SPECCED | = G2-54; one spec. The Zapier hop is native here (X-123) |
| G2-56 | Pipeline Gap Analysis | ENH | X-07 | SPECCED | named in the header refuses refuses |
| G2-57 | Pre-Chat Lead Capture | ENH | X-102 | SPECCED | capture-first; the chat never dead-ends |
| G2-58 | Pre-Qualification Logic | ENH | X-108 | SPECCED | questions on the booking page refuses refuses |
| G2-59 | Quota Pacing | ENH | X-07 | SPECCED | a tile over the forecast |
| G2-60 | Rep Penalization | ENH | X-10 | SPECCED | the SLA reassignment already exists;  it re-routes work, it never ranks people (§150.4) |
| G2-61 | RFM Scoring | ENH (split) | X-01 | SPECCED | split: the score is a `lead_score`;  the lookalike-seed half is FENCED (§44 · P-128) |
| G2-62 | Round-Robin | ENH | X-10 | SPECCED | named in the header |
| G2-63 | Round-Robin Assignment | ENH | X-111 | SPECCED | support tickets, by presence |
| G2-64 | Round-Robin Routing | ENH | X-10 | SPECCED | demo bookings; conversion-based is named in the header |
| G2-65 | Route Optimization | ENH | X-162 | SPECCED | named in the header |
| G2-66 | Sandbagging Detection | ENH | X-07 | SPECCED | named in the header |
| G2-67 | Scheduled PDF Reports | ENH | X-112 | SPECCED | the agency's weekly client report; rendered by X-194 |
| G2-68 | Seasonal Adjustment | ENH | X-07 | SPECCED | named in the header |
| G2-69 | Seed Data Generator | ENH | X-161 | SPECCED | every seeded row carries `is_mock`; `doctor` fails on one in production (§153) |
| G2-70 | Skill-Based Routing | ENH | X-10 | SPECCED | named in the header |
| G2-71 | Smart Staff Routing | ENH | X-162 | SPECCED | nearest tech — the third state (EN ROUTE) is what makes the answer true |
| G2-72 | Speed to Lead SLA | ENH | X-10 | SPECCED | named in the header |
| G2-73 | Stale Deal Alerts | ENH | X-162 | SPECCED | named in the header |
| G2-74 | Sticky Routing | ENH | X-10 | SPECCED | a returning caller reaches the same owner; the carrier half is P-070 refuses refuses |
| G2-75 | Sticky Routing | ENH | X-10 | SPECCED | = G2-74; one spec |
| G2-76 | Universal UI | ENH | X-01 | SPECCED | the unified inbox is the header's first line |
| G2-77 | Weather Re-Routing | ENH | X-162 | SPECCED | named in the header;  the multi-warehouse framing is out of scope — *"a van and a storage unit, not a wareh… |

## G3 · SCRAPING & ENRICHMENT — 66
**Modules of record:** X-151 · X-134 · X-135 · X-136 · X-16 · X-196 · X-109 · **Turn:** ✅ 30 C1 (T677 · §165)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G3-01 | 1-Click Setup Magic | ENH | X-118 | SPECCED | X-118 needs a name and a number, not a URL — this is the same inference run with a weaker input |
| G3-02 | 60-Second Sitemap Scraper | ENH | X-119 | SPECCED | the crawl is X-151's, the grounding store is X-119's.  Pinecone is corpus vocabulary — one database (§22) refuses refuses |
| G3-03 | Ad Library Scraping | ENH | X-135 | SPECCED | ad intelligence; §44 fences ad *management*, not research |
| G3-04 | Algorithmic Pacing | ENH | X-200 | SPECCED | predictive pacing under the 3% abandonment ceiling (§160.1) |
| G3-05 | API Limit Evasion | ENH | X-196 | SPECCED | `FetchPolicy.authenticated=false` by default (P-078); the operator's own account safety outranks any scrape… |
| G3-06 | Asynchronous Auto-Submit | ENH | X-109 | SPECCED | named in the header; rung ① |
| G3-07 | Automated Drip Adjustment | ENH | X-105 | SPECCED | a reply stops the ladder (P-075); the nurture branch is X-186's |
| G3-08 | Automatic Refresh | ENH | X-134 | SPECCED | a 90-day re-ping; staleness is `fetched_at`, never an eviction (P-143) |
| G3-09 | Autonomous Follow-up | ENH | X-105 | SPECCED | named in the header |
| G3-10 | Bulk Extraction | ENH | X-16 | SPECCED | named in the header |
| G3-11 | Callback Tracking | ENH | X-137 | SPECCED | every visitor gets a call token;  CallTrackingMetrics is corpus vocabulary; with the pool exhausted, the page renders the **static fallback number** and the session is marked `unattributed` — ⛔ **never a reused token**, asserted by forcing exhaustion refuses refuses |
| G3-12 | Captcha Evasion | ENH | X-109 | SPECCED | P-144 — solved ONLY here, on the tenant's own key; a scrape that meets one retries 3× then skips |
| G3-13 | Competitor Backlink Poaching | ENH | X-191 | SPECCED | named in the header |
| G3-14 | Competitor Density Scoring | ENH | X-16 | SPECCED | a distress signal, never a send permit (P-068) |
| G3-15 | Competitor Hiring Spikes | ENH | X-136 | SPECCED | a signal |
| G3-16 | Competitor Mentions | ENH | X-135 | SPECCED | competitor intelligence; the transcript is a `Message`, the weekly report an X-194 view |
| G3-17 | Competitor Pivot | ENH | X-105 | SPECCED | the battle card lands in the reply · ⛔ **REFUSES with NO_FACT** refuses refuses |
| G3-18 | Content Spintax | RE-HOME→G11 | C-Mail | SPECCED | the warm-up engine — spec with the email pass (turn 31) refuses refuses |
| G3-19 | Deep Scanning | ENH | X-134 | SPECCED | tech-stack enrichment with `source` and `confidence` |
| G3-20 | Deep Scraping | ENH | X-135 | SPECCED | research fires only on distress (P-146) |
| G3-21 | Drip Integration | ENH | X-105 | SPECCED | rung ④, the voicemail drop |
| G3-22 | Dynamic Name Personalization | ENH | X-158 | SPECCED | the name is a `Fact`, never a guess |
| G3-23 | Event Badge Scanning | ENH | X-156 | SPECCED | a scan is a source; the QR rides the short-linker (P-072) |
| G3-24 | FB Group Scraping | ENH | X-196 | SPECCED | authenticated=false by default (P-078) — same caveat as G3-05 |
| G3-25 | Follow-Up Sync | ENH | X-158 | SPECCED | a watch threshold is a signal; the send is X-105's |
| G3-26 | Groq Node Integration | ENH | X-197 | SPECCED | the 7¢ stack (§18F) |
| G3-27 | High-Intent Alerts | ENH | X-136 | SPECCED | an alert, never a send (P-068) |
| G3-28 | Hiring Intent Prediction | ENH | X-136 | SPECCED | a signal |
| G3-29 | Historical Ingestion | ENH | X-154 | SPECCED | their words, not ours · ⛔ **REFUSES with NO_CLAIM_IMPORT** refuses refuses |
| G3-30 | Instant Halt | ENH | X-105 | SPECCED | named in the header (P-075) |
| G3-31 | Intelligent DOM Parsing | ENH | X-109 | SPECCED | form-field mapping |
| G3-32 | LinkedIn Scraping | ENH | X-196 | SPECCED | same authenticated-session caveat as G3-05 |
| G3-33 | Live Update Sync | ENH | X-136 | SPECCED | a 30-day re-scan raising a signal |
| G3-34 | LLMs.txt Injection | ENH | X-176 | SPECCED | named in the header |
| G3-35 | Local Directory Crawling | ENH | X-16 | SPECCED | named in the header |
| G3-36 | Local Form Auto-Fill | ENH | X-109 | SPECCED | named in the header |
| G3-37 | Localized Spintax Pages | KILLED → X-140 | X-140 | SPECCED | Q-012 — Google is the SEO source; spun city pages are the doorway-page pattern Google's spam policy names.… |
| G3-38 | Multi-Touch Orchestration | ENH | X-105 | SPECCED | named in the header |
| G3-39 | Node Scraper Integration | ENH | X-196 | SPECCED | the Gro Node |
| G3-40 | Omnipresence | KILLED → X-185 | X-185 | SPECCED | §44 · P-128 — sequential retargeting is ad MANAGEMENT |
| G3-41 | Photo Extraction | ENH | X-16 | SPECCED | the storefront image as an `Asset` |
| G3-42 | Pricing Monitor | ENH | X-136 | SPECCED | a signal; the alert names an action (X-111) |
| G3-43 | Prospect Poaching | ENH | X-105 | SPECCED | the battle card attaches to the reply |
| G3-44 | Proxy Rotation | ENH | X-151 | SPECCED | the proxy pool is X-151's;  P-145 sets a global concurrency and RPS ceiling — "never IP-banned" is not the… |
| G3-45 | Real-Time Interception | KILLED → X-136 | X-136 | SPECCED | §44 · P-128 — geofenced ad serving is ad management refuses refuses |
| G3-46 | Replay Scarcity | ENH | X-158 | SPECCED | an expiring short link (P-072) |
| G3-47 | Revenue Trigger | ENH | X-10 | SPECCED | an enrichment field used as a routing input |
| G3-48 | Review Scraping | ENH | X-135 | SPECCED | competitor-weakness research |
| G3-49 | Scraper Ad Audience Sync | KILLED → X-221 | X-221 | SPECCED | §44 · P-128 — audience building is ad management |
| G3-50 | Scraping & Enrichment | ENH | X-134 | SPECCED | the domain's own summary line; = X-151 + X-134 |
| G3-51 | Social Appending | ENH | X-134 | SPECCED | with `source` and `confidence` |
| G3-52 | Spam Filter Evasion | ENH | X-109 | SPECCED | personalisation is the mechanism; *"defeat Akismet"* is not a capability the plan can adopt without a rulin… |
| G3-53 | ~~Spintax at Scale~~ → **Lexicon Personalisation** | RE-POINTED | **X-154** | SPECCED | ⛔ **The EVASION sense is KILLED (T677). What survives is an AUTHORED variation set the tenant owns — their words, not per-send generation to defeat a filter.** |
| G3-54 | Spintax Evasion | ENH | C-Sms | SPECCED | spinning text to evade carrier A2P filtering conflicts with P-064's 10DLC path — owner question refuses refuses |
| G3-55 | Spintax Generation | KILLED → X-186 | X-186 | SPECCED | Q-012 — = G3-37; doorway pages refuses refuses |
| G3-56 | Tech Stack Correlation | ENH | X-136 | SPECCED | enrichment from a job posting — no scrape needed |
| G3-57 | Tech Stack Extraction | ENH | X-134 | SPECCED | Wappalyzer as one waterfall rung |
| G3-58 | Territory Poaching Alerts | ENH | X-10 | SPECCED | named in the header |
| G3-59 | The "Icebreaker" Generator | ENH | X-135 | SPECCED | every icebreaker names something TRUE (P-146) |
| G3-60 | Universal Form Hijacker | ENH | X-104 | SPECCED | NAME COLLISION — X-109's header also claims *"the universal form hijacker"*; the description here is the te… |
| G3-61 | Unlinked Mention Scraping | ENH | X-191 | SPECCED | named in the header |
| G3-62 | Unlinked Mention Scraping | ENH | X-191 | SPECCED | = G3-61; one spec |
| G3-63 | Visual Context | ENH | X-151 | SPECCED | a headless screenshot stored as an `Asset` (X-121) |
| G3-64 | VPN/Proxy Detection | ENH | X-155 | SPECCED | spam and bot filtering is named in the header |
| G3-65 | Waterfall Enrichment | ENH | X-134 | SPECCED | named in the header |
| G3-66 | Waterfall Enrichment | ENH | X-134 | SPECCED | = G3-65; one spec |

## G4 · INFRA / OPS / SECURITY — 51
**Modules of record:** X-111 · X-157 · X-113 · **Turn:** ✅ 30 C1 (T677 · §165)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G4-01 | Activity Monitoring | ENH | X-118 | SPECCED | TTFM and the no-login nudge |
| G4-02 | API Key Rotation | ENH | X-142 | SPECCED | `token.issue` · `token.revoke`, tenant-scoped |
| G4-03 | Audit Trail | ENH | X-122 | SPECCED | every invocation logged with timestamp, IP + geo, user and `actor_type` |
| G4-04 | Auto-Scaling | KILLED → X-203 | X-203 | SPECCED | §44 · P-128 — ad budget scaling is ad management |
| G4-05 | Automated Health Checks | ENH | X-111 | SPECCED | named in the header |
| G4-06 | Automated Restoration Testing | UNMAPPED | ResiliencyDesk | SPECCED | a rehearsed restore is turn 93's work and has no module to land in — see §165.5 |
| G4-07 | Automated Webhook Trigger | ENH | X-136 | SPECCED | a new-registration signal;  a signal never mints a `SendPermit` (P-068) — the send is X-105's on Lane 3 |
| G4-08 | Blacklist Monitoring | ENH | C-Mail | SPECCED | deliverability first, then everything else · refuses: any action that compromises deliverability — deliverability comes first |
| G4-09 | Config Inheritance | ENH | X-112 | SPECCED | named in the header; a sub-tenant may narrow, never widen |
| G4-10 | Dead Letter Queue | ENH | X-123 | SPECCED | named in the header — 10 consecutive failures, tenant emailed · refuses: retrying after 10 consecutive failures |
| G4-11 | Dependency Locking | ENH | X-162 | SPECCED | NAME COLLISION — X-195's header claims the term for manifests; this row is task dependencies |
| G4-12 | Eloquent Models & Caching | ENH | X-121 | SPECCED | Redis on high-read nouns, invalidated inline on write |
| G4-13 | Feature Toggling | ENH | X-195 | SPECCED | flags are blast-radius control (P-182) |
| G4-14 | Global Search | ENH | X-111 | SPECCED | the operator's; the tenant's conversational search is X-01's.  ElasticSearch is corpus vocabulary — one dat… |
| G4-15 | Granular Permissions | ENH | X-113 | SPECCED | named in the header |
| G4-16 | Hard Coded RLS | ENH | X-121 | SPECCED | RLS FORCEd on every noun table, never optional |
| G4-17 | Job Queue Auto-Scaling | ENH | X-123 | SPECCED | Horizon scales on queue depth |
| G4-18 | JWT Authentication | ENH | X-142 | SPECCED | tenant-scoped, permission-inherited tokens · ⛔ **REFUSES with BAD_TOKEN** refuses refuses |
| G4-19 | Link Placement Monitoring | ENH | X-191 | SPECCED | named in the header |
| G4-20 | Livewire Dynamic Frontend | ENH | X-194 | SPECCED | a house standard enforced by lint, not a capability row |
| G4-21 | Load Balancing | ENH | X-121 | SPECCED | read-replica routing at the entity layer; the topology is turn 95's |
| G4-22 | Magic Link SSO | ENH | X-112 | SPECCED | the agency's client sees results without a password |
| G4-23 | Multi-Region Redundancy | UNMAPPED | ResiliencyDesk | SPECCED | turn 93 |
| G4-24 | Multi-Tenant Rate Limiting | ENH | X-111 | SPECCED | throttling and hard limits;  the $99/$999 tiers are dead — two packages (P-001) |
| G4-25 | Naming Convention Enforcement | ENH | X-138 | SPECCED | UTM hygiene;  X-195's header claims the term for manifests |
| G4-26 | Offline Mode - Local-First | ENH | X-171 | SPECCED | offline-first is the premise  ⛔ **§254: the REGISTER text describes a RETAIL iPad POS “ringing up customers” — wrong business. §198's spec (two-truths reconciliation, field-wins-on-observed, queue-and-sync for a TECHNICIAN'S VAN) is the truth.** ||
| G4-27 | Offline Mode | KILLED → X-171 | X-171 | SPECCED | P-095 / R25 — nothing hard-stops at a cap: the phone keeps answering and auto top-up is universal. A widget… |
| G4-28 | Ops & Supervisor Watchdog | ENH | X-111 | SPECCED | named in the header |
| G4-29 | Permission Scopes | ENH | X-195 | SPECCED | an install declares its actions; the gate is the registry |
| G4-30 | Point-In-Time Recovery - PITR | UNMAPPED | ResiliencyDesk | SPECCED | turn 93 |
| G4-31 | Request Inspector | ENH | X-111 | SPECCED | the screen; the mechanism is X-123's |
| G4-32 | Request Throttling - Leaky Bucket | ENH | X-123 | SPECCED | queue the spike, never drop it |
| G4-33 | Resource Throttling | ENH | X-111 | SPECCED | named in the header |
| G4-34 | Retry Logic - Exponential Backoff | ENH | X-123 | SPECCED | 1 · 5 · 30 min |
| G4-35 | Role-Based Access | ENH | X-113 | SPECCED | named in the header |
| G4-36 | Runtime & Boot Layer | ENH | X-111 | SPECCED | the auto-healing supervisor |
| G4-37 | S3 Versioning | ENH | X-121 | SPECCED | `Asset` versioning; the ransomware case is turn 93's |
| G4-38 | Seamless Migration | ENH | X-118 | SPECCED | SAMPLE → real on conversion;  the $179.99 figure is dead (money-number law) refuses refuses |
| G4-39 | Smart Retry Logic | RE-HOME→G1 | C-Billing | SPECCED | = G1-49 Smart Retries — already specced at §153 |
| G4-40 | System Kill Switch | ENH | X-111 | SPECCED | `ops.ban` plus a mass `token.revoke` through X-142 |
| G4-41 | Tenant Isolation CLI | ENH | X-111 | SPECCED | the T443 delete-list runner |
| G4-42 | Tenant-Level Rollbacks | ENH | X-121 | SPECCED | version restore with a `compensable` reversal class refuses refuses |
| G4-43 | Universal Http Layer | ENH | X-123 | SPECCED | global retries on every outbound call refuses refuses |
| G4-44 | Universal Magic Login | ENH | X-118 | SPECCED | frictionless signup, magic link, no password refuses refuses |
| G4-45 | Universal OAuth Hub | ENH | X-118 | SPECCED | the hub is a generated index over every module's `*.connect` action (X-122) — not a module |
| G4-46 | Version Control | ENH | X-121 | SPECCED | `Asset` history and side-by-side compare |
| G4-47 | Version Control | RE-HOME→G15 | X-111 | SPECCED | SOP edit history; the same home as G1-29 Interactive SOPs |
| G4-48 | Webhook Brokering | ENH | X-123 | SPECCED | named in the header |
| G4-49 | Webhook Injection | ENH | X-195 | SPECCED | an install registers its subscriptions; the screen is X-142's |
| G4-50 | Zapier/Make Native Apps | ENH | X-123 | SPECCED | they ride the same catalogue; the catalogue is X-122's refuses refuses |
| G4-51 | Zero-Downtime Migrations | ENH | X-121 | SPECCED | expand/contract on the noun tables; the release switch is Step 8's refuses refuses |

## G5 · AGENT / AI CORE — 53
**Modules of record:** C-Agent · X-119 · X-148 · X-149 · X-154 · X-160 · C-Ai · **Turn:** ✅ 30 C1 (T677 · §165)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G5-01 | Action Item Extraction | ENH | C-Agent | SPECCED | named in the header; the task lands in X-01 refuses refuses |
| G5-02 | Action Item Extraction | RE-HOME→G16 | X-158 | SPECCED | episode resources — spec with the video pass (turn 32) · ⛔ **REFUSES with UNVERIFIED_BIO** refuses refuses |
| G5-03 | Agent A/B Testing | ENH | X-149 | SPECCED | the conscience measures it;  a persona split is a test, never a permit change |
| G5-04 | Agent Analytics | ENH | X-149 | SPECCED | ClickHouse is corpus vocabulary — one database (§22 · P-143) |
| G5-05 | AI Auto-Build | ENH | X-118 | SPECCED | the whole module is an inference run |
| G5-06 | AI Auto-Tagging | ENH | X-114 | SPECCED | image tagging on the `Asset` |
| G5-07 | AI Form Generator | ENH | X-155 | SPECCED | describing the form is the wizard |
| G5-08 | AI Generation | ENH | X-178 | SPECCED | named in the header |
| G5-09 | AI Roleplay | ENH | X-200 | SPECCED | coaching on the desk; the voice is X-197's  · ⛔ **REFUSES with BAD_STATE** |
| G5-10 | AI Safety & Prompt Injection Defense | ENH | C-Agent | SPECCED | untrusted text is DATA, never instruction; red evals are build-failing (P-102) |
| G5-11 | AI Sentiment Parsing | ENH | X-183 | SPECCED | a negative comment on a draft; the escalation is the *ApprovalDesk* question |
| G5-12 | AI Subject Line Split Testing | ENH | X-186 | SPECCED | still one mixed campaign (P-074) |
| G5-13 | AI Summarization | ENH | X-01 | SPECCED | the thread's three-bullet head |
| G5-14 | AI-Driven Reactivation Intake | ENH | X-186 | SPECCED | the Zapier/Sheets hop is corpus vocabulary; ours is native (X-123) |
| G5-15 | Assistant Multi-Lingual Auto-Detect | ENH | C-Agent | SPECCED | = G5-31/32; one spec |
| G5-16 | Assistant Proactive Outreach | ENH | X-185 | SPECCED | a gap in the calendar is a signal, not a permit (P-068) — the send runs Lane 2 through `ConsentService` · refuses: to treat a calendar gap as a permit |
| G5-17 | Auto-Documentation | ENH | X-111 | SPECCED | a resolved ticket drafts a help row;  the help registry generates itself from X-122 |
| G5-18 | Autonomous L1 Resolution | ENH | X-111 | SPECCED | the HELP path; reply HUMAN always escalates (R37) |
| G5-19 | Base Prompt Engine | ENH | C-Agent | SPECCED | named in the header |
| G5-20 | Battle Cards | ENH | X-105 | SPECCED | named in the header |
| G5-21 | Brand Kit Enforcement | ENH | X-178 | SPECCED | the design tokens; the words' equivalent is X-154 |
| G5-22 | Bring Your Own Key - BYOK | ENH (split) | C-Ai | SPECCED | split: the key is C-Ai's;  the MRR-discount half is KILLED (T591 — no discounts) |
| ~~G5-23~~ | Comms NLP Scanning | ⛔ **KILLED** | — | **KILLED T677 · §208** | ⛔⛔ **KILLED at §208, T677 — staff surveillance. The T677 ruling had already killed seven rows of this shape; these are the same instinct under an operations label. What survives is coaching-only and lives in X-200's positive-only scorecard, which has no `rank` field in its schema.** |
| G5-24 | Contextual Drafts | ENH | C-Agent | SPECCED | named in the header |
| G5-25 | Custom RAG Knowledge Base | ENH | X-119 | SPECCED | the grounding law; retrieval is X-148's.  Pinecone is corpus vocabulary refuses refuses |
| G5-26 | Deep Research Agent | ENH | X-135 | SPECCED | every cited fact carries its source and its date (P-120) |
| G5-27 | Dynamic Battle-Cards | ENH | X-105 | SPECCED | = G5-20; one spec |
| G5-28 | In-App Support AI | ENH | X-124 | SPECCED | the assistant that configures the platform; HUMAN escalates to X-111 |
| G5-29 | Intent Matching | ENH (split) | X-131 | SPECCED | split: the interest score is X-131's;  the lookalike-build half is FENCED (§44) |
| G5-30 | Interactive Interview Form | ENH | X-155 | SPECCED | adaptive questions |
| G5-31 | Multi-Language Auto-Detect | ENH | C-Agent | SPECCED | the web-chat door is X-102's |
| G5-32 | Multi-Language Auto-Detect | ENH | C-Agent | SPECCED | the voice door is X-66's; = G5-31 |
| G5-33 | Multi-Modal Agent Handoff | ENH | C-Agent | SPECCED | ONE `Conversation` across channels is why it works (X-121) refuses refuses |
| G5-34 | Multi-Provider Sync | ENH | X-156 | SPECCED | Housecall Pro / ServiceTitan / Jobber as sources; X-129 migrates them in |
| G5-35 | Narrative Generation | ENH | X-183 | SPECCED | R36 — drafts from REAL data only, double consent |
| G5-36 | Negative Constraints | ENH | X-154 | SPECCED | their words, not ours |
| G5-37 | Negative Sentiment Handoff | ENH | C-Agent | SPECCED | the takeover latch is X-01's (R21) |
| G5-38 | NLP Auto-Tagging | ENH | X-111 | SPECCED | ticket categorisation |
| G5-39 | NLP Translation | ENH | C-Agent | SPECCED | compose-time, both directions |
| G5-40 | Objection Extraction | ENH | X-200 | SPECCED | the QA scorecard |
| G5-41 | Objection Handling RAG | ENH | C-Agent | SPECCED | named in the header refuses refuses |
| G5-42 | Pain Point Matching | ENH | C-Agent | SPECCED | the research behind it is X-135's |
| G5-43 | Pre-Trained Agent Prompts | ENH | C-Agent | SPECCED | the 100 authored profiles are the fixture (P-126) |
| G5-44 | Prompt Caching & RAG Acceleration | ENH | C-Ai | SPECCED | cost is measured cent-precision in `ai_calls` |
| G5-45 | Provider Waterfall | ENH | C-Ai | SPECCED | NAME COLLISION — X-150 `ProviderWaterfall` is the DATA-provider waterfall; model failover is C-Ai's |
| G5-46 | RAG Search | ENH | X-148 | SPECCED | the documents are X-160's refuses refuses |
| G5-47 | Real-Time Agent Correction | ENH | X-154 | SPECCED | compose-time only — no LLM in the send path (P-071) |
| G5-48 | Reply Classification | ENH | C-Agent | SPECCED | named in the header refuses refuses |
| G5-49 | Seasonal Refresh Prompts | ENH | X-184 | SPECCED | approve a cadence, never a topic list; ad PACKS as content are not fenced (P-128) |
| ~~G5-50~~ | Sentiment AI | ⛔ **KILLED** | — | **KILLED T677 · §208** | ⛔⛔ **KILLED at §208, T677 — staff surveillance. The T677 ruling had already killed seven rows of this shape; these are the same instinct under an operations label. What survives is coaching-only and lives in X-200's positive-only scorecard, which has no `rank` field in its schema.** |
| G5-51 | Sentiment Analysis | ENH | C-Agent | SPECCED | named in the header; the minute-by-minute graph is an X-194 view |
| G5-52 | Sentiment Matching | RE-HOME→G16 | X-158 | SPECCED | video tone → written tone |
| G5-53 | Sentiment Pivot | ENH | C-Agent | SPECCED | STOP belongs to `ConsentService`, never the classifier (P-060) |

## G6 · WEBSITE / BUILDER / FUNNEL / ECOM — 35
**Modules of record:** X-103 · X-116 · X-117 · X-178 · X-179 · X-194 · X-104 · **Turn:** ✅ 31 C2 (T677 · §166)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G6-01 | 1-Click Clone | ENH | X-161 | SPECCED | the sandbox schema; every seeded row carries `is_mock` (§153) |
| G6-02 | 1-Click Upsells | ENH | X-117 | SPECCED | post-charge upsell on the tokenised card (P-160 — tokens only) |
| G6-03 | 100-Industry Demo System | ENH | X-116 | SPECCED | P-126: 200 templates 1:1 with 200 industries; 100 PROFILES are a different object, both kept |
| G6-04 | Auto-Formatting | ENH | X-183 | SPECCED | the case-study render; R36's double consent stands |
| G6-05 | Checkout Overlay | ENH | X-178 | SPECCED | named in the header |
| G6-06 | Cloudflare Zero-Touch Provisioning | ENH | X-157 | SPECCED | named in the header |
| G6-07 | Cross-Sell Mapping | ENH | X-117 | SPECCED | one `Sellable`, six fulfilment types — not mini-Shopify (P-166) |
| G6-08 | Custom Label Generation | RE-HOME→FSM | X-167 | SPECCED | barcode generation — the FSM bucket C4 must add |
| G6-09 | Deep Funnel Persistence | ENH | X-110 | SPECCED | UTM survives the session; the attribution is X-138's |
| G6-10 | Dummy Data Injection | ENH | X-161 | SPECCED | `is_mock` on every row; `doctor` fails on one in production |
| G6-11 | Dynamic Redirection | ENH | X-103 | SPECCED | device routing on the short-linker (P-072) |
| G6-12 | Dynamic Service Menu | ENH | X-116 | SPECCED | named in the header; the agent upsells only from a grounded `Fact` (P-092) · refuses: to upsell from an ungrounded Fact |
| G6-13 | E-commerce Detection | ENH | X-179 | SPECCED | named in the header |
| G6-14 | Fractional Inventory | RE-HOME→FSM | X-167 | SPECCED | fractional units — *"a van and a storage unit, not a warehouse"* bounds it |
| G6-15 | Funnel / Website Builder | ENH | X-103 | SPECCED | the header's first line |
| G6-16 | Funnel Drop-Off Targeting | ENH | X-103 | SPECCED | named in the header;  the *"10% off"* is the tenant's offer, never ours (T591) |
| G6-17 | Funnel Visualization | ENH | X-103 | SPECCED | named in the header; rendered by X-194 |
| G6-18 | Geospatial Optimization | RE-HOME→FSM | X-167 | SPECCED | multi-warehouse shipping — same scope note as G2-77 |
| G6-19 | Global Update Propagation | ENH | X-195 | SPECCED | named in the header |
| G6-20 | Iframe Embedding | ENH | X-103 | SPECCED | named in the header; a marketplace app is a MANIFEST, never injected code (X-195) |
| G6-21 | Industry Builder AI | ENH | X-178 | SPECCED | the profile builder for an unmapped niche;  it never invents a price — `[fill-me]` only (P-092) |
| G6-22 | Inventory Syncing | RE-HOME→FSM | X-167 | SPECCED | Shopify/WooCommerce stock sync |
| G6-23 | Lead Magnet Funnel | ENH | X-116 | SPECCED | named in the header |
| G6-24 | Omni-Channel Sync | RE-HOME→FSM | X-167 | SPECCED | retail stock across locations |
| G6-25 | One-Way Sync | ENH | X-161 | SPECCED | the sandbox pulls, never pushes — the safety property, not a feature |
| G6-26 | Page Speed Optimization | ENH | X-104 | SPECCED | named in the header |
| G6-27 | Password Protection | ENH | X-103 | SPECCED | named in the header; the PIN rides C-Sms |
| G6-28 | Pre-Loaded Funnels | ENH | X-116 | SPECCED | named in the header — EMERGENCY · QUOTE · BOOK |
| G6-29 | Psychological Color Palettes | ENH | X-116 | SPECCED | the anti-generic rule: palette varies by industry and conversion data, never by seed |
| G6-30 | Registration Engine | ENH | X-116 | SPECCED | the webinar funnel shape; registrants are `Person` rows |
| G6-31 | Responsive Industry Adaptation | ENH | X-178 | SPECCED | named in the header |
| G6-32 | Up-Sell Injection | ENH | X-103 | SPECCED | named in the header; the invoice is X-199's |
| G6-33 | Vercel API Deployment | ENH | X-157 | SPECCED | Vercel is corpus vocabulary — the edge is Cloudflare (§33.1) |
| G6-34 | Widget Auto-Injector | ENH | X-104 | SPECCED | named in the header |
| G6-35 | WP Hostile Takeover | ENH | X-104 | SPECCED | the takeover path is named;  it runs on the tenant's OWN site with their key — the name is the register's,… |

## G7 · AGENCY / WHITE-LABEL / AFFILIATE — 46
**Modules of record:** X-112 · X-113 · X-195 · **Turn:** ✅ 31 C2 (T677 · §166)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G7-01 | "Grace Period" Management | ENH | C-Billing | SPECCED | §45A — the 21-day timeline is the ONE ladder; a per-client grace is a date on it, never a second ladder |
| G7-02 | "Hide Billing" | ENH | X-112 | SPECCED | §17.5 — the client sees the agency's price only |
| G7-03 | "What-If" Sliders | ENH | X-170 | SPECCED | a projection over commission rules; rendered by X-194 |
| G7-04 | Affiliate ID Merging | UNMAPPED | AffiliateProgram | SPECCED | `ref` merged with `utm` — see §166.5 |
| G7-05 | Agency Announcements | ENH | X-112 | SPECCED | named in the header |
| G7-06 | Agency Impersonation Audit | ENH | X-112 | SPECCED | named in the header; the log is X-122's |
| G7-07 | Agency Whitelabeling | ENH | X-112 | SPECCED | named in the header |
| ⛔ FENCED | ~~API Sync (payroll)~~ (G7-08) | **⛔ FENCED T677** | **—** | FENCED | §220 — ⛔⛔ **NO PAYROLL. "The cleanest way to never produce a wrong wage is to never produce a wage."** The export ships HOURS and COMMISSION-EARNED only. |
| G7-09 | Automated Reminders | ENH | X-202 | SPECCED | the 48-hour nudge on an unopened asset — the first consumer of the minted desk |
| G7-10 | Bundling Logic | ENH | X-117 | SPECCED | bundle allocation on the `Sellable` |
| G7-11 | Clawback Automation | UNMAPPED | AffiliateProgram | SPECCED | a chargeback reverses a paid commission; X-201 raises the event |
| G7-12 | Client Bill-Backs | ENH | X-112 | SPECCED | named in the header (§91's three agency modes) refuses refuses |
| G7-13 | Client-Facing Visibility | ENH | X-112 | SPECCED | task visibility per client |
| G7-14 | Commission Injection | RE-HOME→G15 | X-169 | SPECCED | cleared commission into the payroll export |
| G7-15 | Cost Control | ENH | C-Billing | SPECCED | every AI call writes cent-precision cost; retail debits derive at 8:1, never typed |
| G7-16 | Custom Aliases | ENH | X-103 | SPECCED | custom slugs on the short-linker (P-072) |
| G7-17 | Custom Blacklists | KILLED → X-204 | X-204 | SPECCED | §44 · P-128 — ad-exclusion IP lists are ad management.  X-195's header claims the same words for REGISTRY b… |
| G7-18 | Custom Domain Gating | ENH | X-103 | SPECCED | named in the header; the review gateway subdomain is C-Reviews' |
| G7-19 | Embedded VSLs | ENH | X-112 | SPECCED | a Loom on the client dashboard; the asset is X-114's |
| G7-20 | Event Hijacking | KILLED → X-136 | X-136 | SPECCED | §44 · P-128 — pre-buying geo-fenced ad inventory is ad management refuses refuses |
| G7-21 | Franchise Filtering | ENH | X-16 | SPECCED | chains filtered out of the prospect set |
| G7-22 | Franchise Protection | ENH | X-10 | SPECCED | the polygon owns the lead; territories are named in the header |
| G7-23 | Fraud Detection | UNMAPPED | AffiliateProgram | SPECCED | self-clicking and stolen-card affiliates |
| G7-24 | Freelancer Auditing | ENH | X-183 | SPECCED | the pre-publish gate applied to a human's draft |
| G7-25 | Growth & Referral Engine | ENH | X-190 | SPECCED | customer referral links;  *"20% off"* is the TENANT's offer under R26, never ours |
| G7-26 | High-Volume Queuing | ENH | X-123 | SPECCED | RabbitMQ/SQS is corpus vocabulary — Horizon + Redis (§22) |
| G7-27 | Impersonation Engine | ENH | X-112 | SPECCED | named in the header — always logged |
| G7-28 | Impersonation Tracking | ENH | X-112 | SPECCED | = the row above; one spec |
| G7-29 | KPI Injection | RE-HOME→G15 | X-113 | SPECCED | the review's hard numbers — but §150.4's scorecard is POSITIVE ONLY (T677) |
| G7-30 | Margin Calculations | ENH | X-112 | SPECCED | gross margin per deal; the approval above a floor is X-202's |
| G7-31 | Margin Markup Interface | ENH | X-112 | SPECCED | named in the header;  the Twilio figures are corpus vocabulary — Infobip, rates from X-82 |
| G7-32 | Margin-Based Payouts | ENH | X-170 | SPECCED | commission on gross profit;  paid on cash COLLECTED, never invoiced |
| G7-33 | Multi-Tenant Hard Limits | ENH | X-111 | SPECCED | a spend ceiling ALERTS; it never stops the phone answering (P-095) |
| G7-34 | One-Click Impersonation | ENH | X-112 | SPECCED | = Impersonation Engine; one spec |
| G7-35 | Promo Code Sync | UNMAPPED | AffiliateProgram | SPECCED | a code credits the affiliate without a click |
| G7-36 | Refresh Automation | KILLED → X-151 | X-151 | SPECCED | §44 · P-128 — Meta seed-audience refresh is ad management |
| G7-37 | Reseller Margin Sweeps | ENH | X-112 | SPECCED | named in the header (§91) |
| G7-38 | Spiff Campaigns | ENH | X-170 | SPECCED | a time-boxed bonus rule refuses refuses |
| G7-39 | Split Commissions | ENH | X-170 | SPECCED | two payees on one deal |
| G7-40 | Spoofing Alerts | ENH | C-Mail | SPECCED | DMARC XML failure → alert; named in the header · refuses: to suppress a DMARC XML failure alert |
| G7-41 | Tiered Commissions | UNMAPPED | AffiliateProgram | SPECCED | referral tiers unlock by count |
| G7-42 | Tiered Logic | ENH | X-170 | SPECCED | staff commission tiers by revenue band |
| G7-43 | Unique Link Generation | ENH | X-190 | SPECCED | every customer gets a referral link; the short-linker is P-072 |
| G7-44 | White Labeling | ENH | X-112 | SPECCED | named in the header |
| G7-45 | White-Labeled Portal | UNMAPPED | AffiliateProgram | SPECCED | the partner's own login — clicks, pending earnings, swipe files |
| G7-46 | Whitelabeling | ENH | X-104 | SPECCED | the plugin wears the agency's name |

## G8 · SEO / AEO / GBP / LOCAL — 40
**Modules of record:** X-176 · X-177 · X-144 · X-140 · X-191 · **Turn:** ✅ 31 C2 (T677 · §166)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G8-01 | Automated Geo-Grid Tracking | ENH | X-177 | SPECCED | named in the header — metered, on the tenant's own key |
| G8-02 | Automated Internal Linking | ENH | X-176 | SPECCED | the internal linking graph is named in the header |
| G8-03 | Automated Sitemap Pinging | ENH | X-176 | SPECCED | named in the header |
| G8-04 | Breadcrumb Generation | ENH | X-176 | SPECCED | named in the header |
| G8-05 | Broken Link Building | ENH | X-191 | SPECCED | named in the header |
| G8-06 | Broken Link Building | ENH | X-191 | SPECCED | = the row above; one spec |
| G8-07 | Cannibalization Checks | ENH | X-140 | SPECCED | the pre-publish gate is named in the header |
| G8-08 | Citation Builder | ENH | X-192 | SPECCED | named in the header;  it recommends AGAINST a noindexed directory |
| G8-09 | Claimed Status Detection | ENH | X-177 | SPECCED | claimed-status is named in the header; the prospecting use of it is X-16's |
| G8-10 | Custom Fields & Schemas | ENH | X-194 | SPECCED | named in the header; the JSONB column is X-121's |
| G8-11 | Domain Authority Filtering | ENH | X-191 | SPECCED | named in the header |
| ~~G8-12~~ | Dynamic Keyword Insertion | KILLED | - | PURGED | cloaking risk - killed by the owner at T677 (section 190.3); X-116's distinct local pages carry the job |
| G8-13 | Dynamic Number Swapping | ENH | X-137 | SPECCED | DNI — every visitor gets a call token; with the pool exhausted, the page renders the **static fallback number** and the session is marked `unattributed` — ⛔ **never a reused token**, asserted by forcing exhaustion · ⚠️ *CallTrackingMetrics is corpus vocabulary* refuses refuses |
| G8-14 | Dynamic Product Schema | ENH | X-176 | SPECCED | product schema from the pricebook, invalidated in the same commit |
| G8-15 | Event Auto-Sync | ENH | X-176 | SPECCED | `Event` schema from X-108's calendar |
| G8-16 | FAQ Schema Extraction | ENH | X-176 | SPECCED | named in the header |
| G8-17 | GBP Auto-Sync | ENH | X-177 | SPECCED | named in the header — through Zernio (§156.3) |
| G8-18 | GBP Q&A Seeding | ENH | X-177 | SPECCED | named in the header |
| G8-19 | Google Maps Embedding | ENH | X-116 | SPECCED | a block in the local template |
| G8-20 | Guest Post AI Pitching | ENH | X-191 | SPECCED | named in the header — one follow-up only · refuses: a second follow-up |
| G8-21 | Guest Post AI Pitching | ENH | X-191 | SPECCED | = the row above; one spec · refuses: a second follow-up |
| G8-22 | Hyper-Local Schema | ENH | X-176 | SPECCED | named in the header |
| G8-23 | Instant Indexing | ENH | X-176 | SPECCED | named in the header |
| G8-24 | Internal Cannibalization Scan | ENH | X-140 | SPECCED | embedding similarity before publish |
| G8-25 | Internal Linking Graph | ENH | X-176 | SPECCED | named in the header |
| G8-26 | Keyword Spotting Alerts | ENH | X-153 | SPECCED | a risk word on a call raises an alert conversation; the claim expires at 30 min (P-077) · refuses: claims older than 30 min |
| G8-27 | Local Post Automation | ENH | X-177 | SPECCED | named in the header;  held while `gbp.suspended` · refuses: generating while `gbp.suspended` |
| G8-28 | Local Visibility Sync | ENH | X-192 | SPECCED | named in the header |
| G8-29 | Multi-Currency Natively | ENH | X-117 | SPECCED | §143–§144 — minor units, integers, no floats |
| G8-30 | PageRank Sculpting | ENH | X-176 | SPECCED | nofollow on low-value internal links |
| G8-31 | Places / Local SEO Maps | ENH | X-16 | SPECCED | NAP audit across directories; the Places key is the tenant's own (T469–T474) |
| G8-32 | Real-Time Validation | ENH | X-176 | SPECCED | schema validated in CI with zero errors |
| G8-33 | Semantic Entity Injection | ENH | X-176 | SPECCED | named in the header |
| G8-34 | SEO Backlink Monitoring | ENH | X-191 | SPECCED | placement monitoring is named in the header |
| G8-35 | SEO Billion-Dollar Auto-Pilot | ENH | X-116 | SPECCED | city×service pages from the template engine.  the volume is not the risk, the thinness is — every page carr… |
| G8-36 | SEO CAT Widget | ENH | X-102 | SPECCED | the widget's Shadow DOM; the page-reading half feeds X-119 |
| G8-37 | SEO Keyword Injection | RE-HOME→G16 | X-158 | SPECCED | show-notes rewriting — spec with the video pass (turn 32) |
| G8-38 | SEO Meta Sync | ENH | X-104 | SPECCED | named in the header |
| G8-39 | Structural SEO | ENH | X-183 | SPECCED | the AI writes gated by grounding; structure from the top-ranking analysis |
| G8-40 | Topic Clustering | ENH | X-140 | SPECCED | named in the header |

## G9 · ANALYTICS & REPORTING — 40
**Modules of record:** X-138 · X-130 · X-141 · X-111 · **Turn:** ✅ 31 C2 (T677 · §166)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G9-01 | Aggregated Dashboard | ENH | X-200 | SPECCED | objection roll-up from the QA scorecards; rendered by X-194 |
| G9-02 | Analytics Aggregation | ENH | X-110 | SPECCED | ClickHouse is corpus vocabulary — one database (§22 · P-143) |
| G9-03 | Analytics Audit | ENH | X-159 | SPECCED | an audit claim, measured and dated (P-132) |
| G9-04 | Analytics Injection | ENH | X-103 | SPECCED | the full-stack site law — pixel on every site by construction |
| G9-05 | Anomaly Alerts | ENH | X-111 | SPECCED | named in the header; the export row is X-122's log |
| G9-06 | Anomaly Detection | KILLED → X-111 | X-111 | SPECCED | §44 · P-128 — pausing a campaign on a CPC spike is ad management |
| G9-07 | App Analytics | ENH | X-195 | SPECCED | install and usage counts for a marketplace manifest |
| G9-08 | Audit Trail Export | ENH | X-202 | SPECCED | who approved what, exportable — the minted desk's own report |
| G9-09 | Automated Status Reports | ENH | X-112 | SPECCED | the agency's Friday client report; rendered and scheduled by X-194 |
| G9-10 | Bulk Actions & Exports | ENH | X-01 | SPECCED | named in the header *(moved there from X-121)* |
| G9-11 | Chart Generation | ENH | X-194 | SPECCED | named in the header |
| G9-12 | Competitor Benchmarks | ENH | X-161 | SPECCED | demo metrics are `is_mock`;  *"local industry averages"* must be measured or absent (P-120) |
| G9-13 | Consolidated Reporting | ENH | X-138 | SPECCED | attribution is a query, not a pipeline |
| G9-14 | Cross-Platform Dashboard | ENH | X-138 | SPECCED | reading spend for attribution is not ad MANAGEMENT (§44 fences management); the connection is X-139's · refuses: ad management |
| G9-15 | Decay Modeling | ENH | X-08 | SPECCED | tenant login decay = churn risk → an alert and a RECOMMEND, never an automatic offer |
| G9-16 | Decay Prediction | ENH | X-184 | SPECCED | creative fatigue → refresh the content pack; ad PACKS as content are not fenced (P-128) |
| G9-17 | Executive Summary | ENH | X-183 | SPECCED | the summary is content, gated by the pre-publish gate |
| G9-18 | Feature Parity Scatter Plot | ENH | X-144 | SPECCED | the parity scatter is named in the header |
| G9-19 | Gap Identification | ENH | X-111 | SPECCED | failed searches open a help topic;  the help registry generates itself from X-122 |
| G9-20 | Global Metric Aggregation | ENH | X-111 | SPECCED | fleet-wide operator roll-up |
| G9-21 | Health Dashboard | ENH | C-Mail | SPECCED | primary-vs-spam placement per network refuses refuses |
| G9-22 | Metric Extraction | ENH | X-183 | SPECCED | hard numbers pulled from the client's own words (R36 — real data only) |
| G9-23 | Metric Weighting | ENH | X-194 | SPECCED | named in the header |
| G9-24 | Network Graphing | ENH | X-190 | SPECCED | named in the header |
| G9-25 | Optical Character Recognition - OCR | ENH | X-160 | SPECCED | structure stays structured |
| G9-26 | PDF Export | ENH | X-194 | SPECCED | named in the header (`report.pdf`) |
| G9-27 | PDF Reporting | ENH | X-07 | SPECCED | the monthly forecast; rendered by X-194 |
| G9-28 | Real-Time Analytics | ENH | X-111 | SPECCED | API traffic per endpoint |
| G9-29 | Real-Time Rep Dashboard | ENH | X-170 | SPECCED | pending · cleared · clawed-back, in real time |
| G9-30 | Retention Analytics | ENH | X-158 | SPECCED | watch-depth as a signal, never a permit (P-068) |
| G9-31 | Revenue Recovery Dashboard | ENH | C-Billing | SPECCED | MRR saved by the one dunning ladder (§45A) |
| G9-32 | Risk Trajectory Graph | ENH | X-112 | SPECCED | client health on the agency dashboard; the score is X-08's |
| G9-33 | ROI Calculation | ENH | X-138 | SPECCED | campaign → closed revenue |
| G9-34 | ROI Dashboard | ENH | X-138 | SPECCED | named in the header — the flagship |
| G9-35 | ROI Visualization | ENH | X-194 | SPECCED | counts and estimates never blend; the estimate tile stays violet-dashed until job value is entered |
| G9-36 | Scorecard System | RE-HOME→G15 | X-113 | SPECCED | interview scorecards — hiring, not the platform |
| G9-37 | Timezone Awareness | ENH | X-194 | SPECCED | a report renders in the location's own timezone |
| G9-38 | TV Mode | ENH | X-200 | SPECCED | the wallboard |
| G9-39 | Volume Decay | KILLED → X-07 | X-07 | SPECCED | T677 (owner) · §150.4 — burnout scoring as surveillance; the metric survives in G15 as a coaching signal only |
| G9-40 | Win-Rate Correlation | KILLED → X-07 | X-07 | SPECCED | T677 (owner) · §150.4 — *"proving tired reps lose the company money"* is the punitive framing the ruling st… |

## G10 · COMPLIANCE & LEGAL — 41
**Modules of record:** X-133 (placeholder) · X-146 · **Turn:** ✅ 31 C2 (T677 · §166)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G10-01 | "Approved as Is" E-Sign | ENH | X-164 | SPECCED | the customer signature freezes the version it was sold at;  F-19 — a contract that is not an estimate has n… · refuses: to alter a version after signature |
| G10-02 | A2P 10DLC Compliance Automation | ENH | X-188 | SPECCED | the brand is auto-submitted (P-064);  Twilio/TCR are corpus vocabulary — Infobip refuses refuses |
| G10-03 | Abandonment Rate Compliance | ENH | X-200 | SPECCED | hard maximum 3%, lower only; the UI and API reject higher (§160.1) |
| G10-04 | AI Pipeline Scrubbing | ENH | X-07 | SPECCED | it scores the DEAL, not the rep — sandbagging detection is named in the header |
| G10-05 | Auto-Countersign | ENH | X-164 | SPECCED | the countersign step; see F-19 |
| G10-06 | Automated DMCA Takedown | KILLED → X-222 | X-222 | SPECCED | Q-061 / Law 122 · P-167 — the plan builds the switch, it never drafts a legal instrument.  X-191's content-… |
| G10-07 | Automated TCPA Scrubbing | UNMAPPED | ConsentService | SPECCED | the registers are provider slots, DEFAULT OFF, the tenant's own credentials (P-066) — see §166.5 |
| G10-08 | Bias and Compliance Filter | ENH | C-Agent | SPECCED | compose-time moderation;  Law 122 — the switch, never the rule |
| G10-09 | Boilerplate Exclusion | ENH | X-179 | SPECCED | named in the header |
| G10-10 | Competitor Inspiration | ENH | X-180 | SPECCED | ad PACKS as content are not fenced (P-128);  P-120 — every claim verifiable |
| G10-11 | Compliance Tracking | RE-HOME→G15 | X-113 | SPECCED | mandatory-training reminders |
| G10-12 | Consent & Opt-In Ledger | UNMAPPED | ConsentService | SPECCED | the permit as an immutable provenance record; an import is not consent (P-069) |
| G10-13 | Content Moderation & Guardrails | ENH | C-Agent | SPECCED | compose-time only —  no LLM in the send path (P-071) refuses refuses |
| G10-14 | Data Export - eDiscovery | ENH | X-122 | SPECCED | every invocation is already immutably logged |
| G10-15 | Document Vault | RE-HOME→G15 | X-113 | SPECCED | employee documents under RBAC |
| G10-16 | Expiry Escalation | ENH | X-202 | SPECCED | 72-hour escalation — exactly the clause that had no home before the mint |
| G10-17 | Global Compliance Scrubbing | UNMAPPED | ConsentService | SPECCED | = Automated TCPA Scrubbing; one spec |
| G10-18 | Global Guardrail Enforcement Engine | UNMAPPED | ConsentService | SPECCED | the middleware every send passes through; P-060's 20 refusal reasons are the entire set |
| G10-19 | Honest Empty-State | ENH | C-Agent | SPECCED | P-092 — a price is looked up or refused, never generated; a refusal without a code fails the build |
| G10-20 | Immutable Storage | ENH | X-164 | SPECCED | the executed document linked to the record; see F-19 |
| G10-21 | Immutable Storage | ENH | X-122 | SPECCED | append-only action log;  QLDB is corpus vocabulary — one database (§22) |
| G10-22 | Latency Guardrails | ENH | C-Ai | SPECCED | TTFT demotion in the model waterfall |
| G10-23 | Legal & Privacy Engine | KILLED → X-222 | X-222 | SPECCED | P-167 · Law 122 — eight EMPTY admin slots, nothing authored, no generated ToS or privacy copy |
| G10-24 | Legally Binding Signatures | ENH | X-172 | SPECCED | the signature pad lives in the customer portal; see F-19 · refuses: a redline is SURFACED with a diff, never accepted |
| G10-25 | List Scrubbing | ENH | X-186 | SPECCED | scrubbed against suppression fresh as of each send · refuses: to send without scrubbing against a fresh suppression list |
| G10-26 | Non-Standard Terms | ENH | X-202 | SPECCED | a term outside the standard routes for a decision |
| ⛔ FENCED | ~~Policy Enforcement (payroll)~~ (G10-27) | **⛔ FENCED T677** | **—** | FENCED | §220 — ⛔⛔ **NO PAYROLL. "The cleanest way to never produce a wrong wage is to never produce a wage."** The export ships HOURS and COMMISSION-EARNED only. |
| G10-28 | Policy Escalation | ENH | C-Mail | SPECCED | the DMARC journey `p=none` → quarantine → reject refuses refuses |
| G10-29 | Privacy Compliance | ENH | X-133 | SPECCED | the BANNER is a switch we build;  what it must say is not ours to write (Law 122). X-133 is not on the pinn… |
| G10-30 | Privacy Wall & HIPAA Scope | ENH | X-133 | SPECCED | P-103 / R19 — human-medical and dental are OUT until PHI isolation; there is no HIPAA mode to configure yet… |
| G10-31 | Quiet Hours Enforcement | ENH | X-193 | SPECCED | MARKETING class only; the window is data (P-063); web chat, missed-call and alerts never wait |
| G10-32 | Redline Negotiation | ENH | X-172 | SPECCED | a clause comment from the customer; the decision routes to X-202 |
| G10-33 | Scrubbing API | UNMAPPED | ConsentService | SPECCED | = Automated TCPA Scrubbing; one spec |
| G10-34 | Sequential Logic | ENH | X-202 | SPECCED | multi-stage sequential approval — the desk's core state machine |
| G10-35 | Signature Verification | ENH | X-122 | SPECCED | HMAC-SHA256 on every outbound webhook is named in the header · refuses: an outbound webhook without HMAC-SHA256 |
| G10-36 | Tax Compliance | UNMAPPED | AffiliateProgram | SPECCED | W-9 threshold freezes a payout;  Law 122 — the switch and the threshold as data, never the advice |
| G10-37 | Under-18 Guardrails | ENH | C-Agent | SPECCED | P-148 — under-18 rejected at ingest; the agent halts and hands off refuses refuses |
| G10-38 | Unified Guardrail Law | ENH | X-193 | SPECCED | = Quiet Hours Enforcement; one spec. The class is decided from the CALLER, never the content (P-062) |
| G10-39 | Variable Injection | ENH | X-164 | SPECCED | CRM variables into a template; see F-19 |
| G10-40 | WhatsApp Opt-In Engine | ENH | C-Whatsapp | SPECCED | a scan or shortcode registers the opt-in; the permit itself is *ConsentService*'s |
| G10-41 | Zapier/Make Payload Signatures | ENH | X-122 | SPECCED | per-tenant HMAC secret on the outbound payload · refuses: an outbound payload without the per-tenant HMAC secret |

## G11 · EMAIL & DELIVERABILITY — 41
**Modules of record:** C-Mail · **Turn:** ✅ 31 C2 (T677 · §166)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G11-01 | Abandonment Tracking | ENH | X-155 | SPECCED | the abandon point, with the pixel |
| G11-02 | Auto-Resend to Unopens | ENH | X-186 | SPECCED | named in the header · ⛔ **REFUSES with CEILING_EXCEEDED** refuses refuses |
| G11-03 | Automated DNS Setup | ENH | C-Mail | SPECCED | it SHOWS the exact missing record with a copy button — it never asks them to configure SPF |
| G11-04 | Automated Onboarding | RE-HOME→G15 | X-113 | SPECCED | new-hire provisioning |
| G11-05 | Automated Pausing | ENH | C-Mail | SPECCED | R17 halt seeds — 0.10% complaint or 250 bounces, the campaign family, never the thread |
| G11-06 | BIMI Logo Setup | ENH | C-Mail | SPECCED | named in the header refuses refuses |
| G11-07 | CMS Customization | ENH | X-179 | SPECCED | tech-stack extraction feeds the opener |
| G11-08 | Cross-Platform Sync | KILLED → X-223 | X-223 | SPECCED | §44 · P-128 — pushing a seed list to ad platforms is ad management |
| G11-09 | Deliverability Testing | ENH | C-Mail | SPECCED | a test send scored before the campaign refuses refuses |
| G11-10 | Deliverability Verification | ENH | C-Mail | SPECCED | bounce and spam-trap check before a cold send · refuses: a cold send without a bounce and spam-trap check |
| G11-11 | DMARC Reporting | ENH | C-Mail | SPECCED | named in the header refuses refuses |
| G11-12 | Email Inbox Parsing | ENH | C-Mail | SPECCED | named in the header; replies thread into the Conversation refuses refuses |
| G11-13 | Escalating Email Sequence | ENH | C-Billing | SPECCED | §45A — the 21-day timeline is the ONE ladder; day-10 is a BANNER, never a lockout |
| G11-14 | Field-Level History | ENH | X-121 | SPECCED | named in the header — with version restore refuses refuses |
| G11-15 | Gmail Read-Only Watch | ENH | C-Mail | SPECCED | named in the header refuses refuses |
| G11-16 | Inbox Placement Ramping | ENH | C-Mail | SPECCED | warm-up is a CALENDAR, not a setting refuses refuses |
| G11-17 | Inbox Rotation | ENH | C-Mail | SPECCED | named in the header refuses refuses |
| G11-18 | Inbox Rotation | ENH | C-Mail | SPECCED | = the row above; one spec refuses refuses |
| G11-19 | Lexicon Enforcement | ENH | X-154 | SPECCED | named in the header — their words, not ours |
| G11-20 | Mail Deliverability Engine | ENH | C-Mail | SPECCED | the header's first line;  SES-primary (R16), DPA before first send · refuses: to send before a DPA is in place |
| G11-21 | Newsletter Distillation | ENH | X-140 | SPECCED | named in the header |
| G11-22 | Omni-Channel Inbox Sync | ENH | X-01 | SPECCED | one polymorphic `Conversation` (X-121's) across every channel |
| G11-23 | Omni-Channel Messaging | ENH | X-01 | SPECCED | = the row above; one spec |
| G11-24 | Omnichannel Campaigns | ENH | X-186 | SPECCED | named in the header;  every send from there is Marketing class from the CALLER · refuses: a send whose caller did not declare Marketing class — the class comes from the caller, never from the channel |
| G11-25 | One-Click Dispositions | ENH | X-200 | SPECCED | named in the header — a closed set per campaign  · ⛔ **REFUSES with BAD_STATE** |
| G11-26 | Payload Validation | ENH | X-122 | SPECCED | strict JSON-schema validation; a missing field is refused, never defaulted |
| G11-27 | Promo Email Draft | RE-HOME→G16 | X-158 | SPECCED | episode promo — spec with the video pass (turn 32) · ⛔ **REFUSES with UNVERIFIED_BIO** refuses refuses |
| G11-28 | Reply Interception | ENH | X-186 | SPECCED | named in the header — any reply stops the sequence (P-075) |
| G11-29 | RSS-to-Email | ENH | C-Mail | SPECCED | named in the header refuses refuses |
| G11-30 | Seed Audience | KILLED → X-223 | X-223 | SPECCED | §44 · P-128 — LTV seed lists pushed to ad platforms is ad management |
| G11-31 | Send-Time Optimization | ENH | X-186 | SPECCED | named in the header; still inside the marketing window (P-063) · refuses: a send time outside the marketing window (P-063) |
| G11-32 | SMS Deliverability Fallback | ENH | C-Sms | SPECCED | T677 (owner): the spintax half is reframed as Lexicon Personalization (X-154) — we do not evade carrier fil… refuses refuses |
| G11-33 | SMS/Email Blackholing | ENH | X-161 | SPECCED | the sandbox intercepts every outbound; `is_mock` end to end · refuses: unintercepted outbound messages |
| G11-34 | Sniper Outreach | ENH | X-191 | SPECCED | the guest-post pitch that names something TRUE about the page · refuses: a pitch that names nothing TRUE about the page |
| G11-35 | Sniper Outreach | ENH | X-105 | SPECCED | the cold opener from X-135's research (P-146 — distress only) · refuses: an opener without distress (P-146) |
| G11-36 | Spam Call Blocking | ENH | C-Telephony | SPECCED | carrier-side screening before we pay for the minute refuses refuses |
| G11-37 | Spam Folder Rescue | ENH | C-Mail | SPECCED | named in the header.  the legitimate mechanism is the warm-up calendar and seed-list diversity — a seeded n… · refuses: any illegitimate warm-up mechanism outside the calendar and seed-list diversity |
| G11-38 | SPF Flattening | ENH | C-Mail | SPECCED | named in the header refuses refuses |
| G11-39 | Trust & Spam Shield | ENH | C-Telephony | SPECCED | SHAKEN/STIR grading on inbound refuses refuses |
| G11-40 | Unified Inbox | ENH | X-01 | SPECCED | the header's first line |
| G11-41 | VIP Prioritization | ENH | X-01 | SPECCED | sort order on the thread list; the LTV is C-Billing's |

## G12 · SOCIAL & CONTENT — 39
**Modules of record:** X-182 · X-183 · X-184 · X-185 · X-186 · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G12-01 | "Powered By" Viral Loop | ENH | X-190 | SPECCED | named in the header |
| G12-02 | AI Blog Publishing | ENH | X-183 | SPECCED | gated by grounding; the pre-publish gate is X-183's · refuses: publishing without grounding |
| G12-03 | Auto-Detection | ENH | X-176 | SPECCED | entity type inferred for schema, zero user input |
| G12-04 | Auto-Publish Sync | ENH | X-202 | SPECCED | approval granted → the publish action fires — the item's own floor governs; an item whose floor is unmet does not authorize a publish, and the desk never calls the publisher itself refuses refuses |
| G12-05 | Auto-Publishing | ENH | X-183 | SPECCED | to the builder or the plugin · ⛔ **REFUSES with UNATTENDED_LOCKED** refuses refuses |
| G12-06 | Auto-Publishing | RE-HOME→G16 | X-158 | SPECCED | show notes and player — the video pass · ⛔ **REFUSES with FALSE_QUOTE** refuses refuses |
| G12-07 | Automated Follow-Ups | ENH | X-191 | SPECCED | ONE follow-up only, per the header — not three |
| G12-08 | Automated LinkedIn Connection | ENH | X-196 | SPECCED | `FetchPolicy.authenticated=false` by default (P-078) |
| G12-09 | Batch Approvals | ENH | X-202 | SPECCED | thirty graphics, one decision |
| G12-10 | Best Time to Post | ENH | X-182 | SPECCED | from the tenant's own engagement history · ⛔ **REFUSES with GLOBAL_AVERAGE_FALLBACK** refuses refuses |
| G12-11 | Bulk Google Post Generation | ENH | X-177 | SPECCED | named in the header;  held while `gbp.suspended`.  DALL-E is corpus vocabulary — images are X-114/X-189 · refuses: generating while `gbp.suspended` |
| G12-12 | Comment Auto-Reply | ENH | X-182 | SPECCED | the reply threads into the Conversation (X-01); R20 — the agent takes every inbound |
| G12-13 | Competitor Content Inspiration | ENH | X-184 | SPECCED | a RECOMMEND, never an auto-post |
| G12-14 | Competitor Content Theft Alert | ENH | X-191 | SPECCED | named in the header |
| G12-15 | Content Generation on Acceptance | ENH | X-191 | SPECCED | a yes from the editor drafts through X-183's gate |
| G12-16 | Content Refreshing | ENH | X-140 | SPECCED | named in the header;  bumping the year is not a refresh — the gate rejects a page with no new substance |
| G12-17 | Cross-Platform Adaptation | ENH | X-182 | SPECCED | tone per channel, inside X-154's lexicon |
| G12-18 | Dynamic Content Blocks | ENH | X-186 | SPECCED | blocks swap on the Person's own tags |
| G12-19 | Emoji Density Control | ENH | X-154 | SPECCED | their words, their rules — and GSM-7 segmentation makes it a billing fact too · ⛔ **REFUSES with SEGMENT_WARNING** refuses refuses |
| G12-20 | Emoji Optimization | ENH | X-185 | SPECCED | it may test a label, never a price (§134.6) |
| G12-21 | Evergreen Recycling | ENH | X-140 | SPECCED | named in the header |
| G12-22 | Guest Bios | RE-HOME→G16 | X-158 | SPECCED | episode furniture — the video pass |
| G12-23 | Instant Show Notes | RE-HOME→G16 | X-158 | SPECCED | transcribe → notes — the video pass · ⛔ **REFUSES with FALSE_QUOTE** refuses refuses |
| G12-24 | LinkedIn Carousel Generator | RE-HOME→G16 | X-158 | SPECCED | a video summarised into slides |
| G12-25 | Live Human Interception | ENH | C-Agent | SPECCED | negative-sentiment handoff; the takeover latch is X-01's (R21) |
| G12-26 | Multi-Persona Profiles | ENH | X-182 | SPECCED | a persona per channel; the lexicon still binds (X-154) |
| G12-27 | Personalized Icebreakers | ENH | X-135 | SPECCED | every icebreaker names something TRUE (P-146) |
| G12-28 | Post-Purchase Exclusion | ENH | X-186 | SPECCED | a won deal stops the sequence (P-075);  the ad-audience purge half is FENCED (§44) · refuses: to continue the sequence after a won deal (P-075) |
| G12-29 | Pre-Publish Gate | ENH | X-183 | SPECCED | named in the header — cannibalisation · SAMPLE prices never rendered · nothing contradicts the pricebook |
| G12-30 | Proof A/B Testing | ENH | X-185 | SPECCED | fleet evidence, never four data points at one tenant |
| G12-31 | Q&A Seeding | ENH | X-177 | SPECCED | named in the header |
| G12-32 | Social Posting Autopilot | ENH | X-182 | SPECCED | the header's first line;  real job photos, never stock (P-131) |
| G12-33 | Social Proof Webhooks | ENH | X-190 | SPECCED | named in the header;  the toast states a real event or does not fire (P-120) |
| G12-34 | Social Snippets | RE-HOME→G16 | X-158 | SPECCED | quotes pulled from a transcript · ⛔ **REFUSES with BAD_CLIP_BOUNDARY** refuses refuses |
| G12-35 | Stale Posting Detection | ENH | X-136 | SPECCED | a hiring signal, never a permit (P-068) |
| G12-36 | Trend Riding | ENH | X-184 | SPECCED | a trend proposes a topic; the cadence is what the tenant approved |
| G12-37 | Twitter Thread Extraction | RE-HOME→G16 | X-158 | SPECCED | transcript → thread |
| G12-38 | Viral Social Proof | ENH | X-182 | SPECCED | the quote card is X-189's overlay; 1–3★ never reaches a public surface (P-110) |
| G12-39 | Website Carousel Injector | ENH | X-103 | SPECCED | the review widget on the site; the plugin path is X-104's |

## G13 · PIXEL / IDENTITY / TRACKING — 38
**Modules of record:** X-110 · X-132 · X-131 · X-137 · X-153 · X-156 · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G13-01 | 14KB Smart Pixel | ENH | X-110 | SPECCED | the header's own shape — first-party, on the tenant's subdomain |
| G13-02 | Activity Heatmaps | ENH | X-200 | SPECCED | the wallboard;  team-level operational state only — T677 bars the punitive read |
| G13-03 | AI Token Arbitrage | ENH | C-Billing | SPECCED | 8:1 over cent-precision true cost, DERIVED, never typed |
| G13-04 | Automatic Appending | ENH | X-138 | SPECCED | a pasted URL gets its UTM and its short link (P-072) |
| G13-05 | Bot Fingerprinting | ENH | X-155 | SPECCED | spam and bot filtering is named in the header; a rejected submission is STORED and flagged, never discarded — asserted by rejecting one and finding the row; the tenant can see and release it refuses refuses |
| G13-06 | Click-Level Attribution | ENH | X-138 | SPECCED | attribution is a query over the action log |
| G13-07 | Cold Storage Hashing | ENH | X-203 | SPECCED | the minted desk's first mechanism — a restore that cannot prove itself is not a backup |
| G13-08 | Competitor Tracking | ENH | X-144 | SPECCED | competitor benchmarks are named in the header; the geo-grid is X-177's, metered |
| G13-09 | Conversion Zone Tracking | KILLED → X-110 | X-110 | SPECCED | §44 · P-128 — geo-fenced ad serving; we have no device-location source and X-139 uploads completed JOBS, not store visits — ⛔ KILLED: a conversion zone is never asserted, and the system refuses to infer a store visit from a completed job refuses refuses |
| G13-10 | CRM Attribution | ENH | X-138 | SPECCED | the offline close mapped back to the click |
| G13-11 | Cross-Device Graphing | ENH | X-132 | SPECCED | knowing who someone is does not make them contactable (P-068) |
| G13-12 | Cross-Domain Tracking | ENH | X-110 | SPECCED | the chat's context updates from the page (X-102) |
| G13-13 | Dwell Time Filtering | KILLED → X-110 | X-110 | SPECCED | §44 · P-128 — a filter on ad delivery is ad management |
| G13-14 | Engagement Tracking | ENH | X-172 | SPECCED | when the customer opened the document, in the portal |
| G13-15 | Exit Intent RAG | ENH | X-102 | SPECCED | the pixel triggers; the chat answers grounded (X-119); an exit-intent answer containing an ungrounded price **refuses instead** *(P-092)* — asserted with the pricebook empty refuses refuses |
| G13-16 | First vs. Last Click | ENH | X-138 | SPECCED | both stored; the model is a query, not a pipeline |
| G13-17 | Heatmap Overlay | ENH | X-194 | SPECCED | revenue on the territory map; the polygons are X-10's |
| G13-18 | Identity Resolution | ENH | X-132 | SPECCED | Clearbit Reveal is corpus vocabulary;  P-068 — a company name is a signal, not a permit |
| G13-19 | Keyword-Level Attribution | ENH | X-137 | SPECCED | the number pool — every visitor gets a call token |
| G13-20 | Lifetime Attribution | ENH | X-205 | SPECCED | the minted engine's core clock — a 90-day cookie and a lifetime balance |
| G13-21 | Link Tracking | ENH | X-138 | SPECCED | every link rides the short-linker; no module can send an untracked one (P-072) |
| G13-22 | Offline Event Uploads | ENH | X-139 | SPECCED | named in the header —  the one-way push is explicitly NOT fenced (P-128) refuses refuses |
| G13-23 | Offline Link Tracking | ENH | X-138 | SPECCED | QR for print unpacks to a full UTM |
| G13-24 | Offline Tracking | ENH | X-137 | SPECCED | a static number per offline campaign; a number cannot be assigned to a second live campaign — the assignment is refused, asserted refuses refuses |
| G13-25 | Pixel Deanonymization | ENH | X-132 | SPECCED | the six-tier waterfall with per-field confidence (P-147) |
| G13-26 | Pixel Detection | ENH | X-134 | SPECCED | a prospect's installed pixels as an enrichment field |
| G13-27 | Pixel Diagnostics | ENH | X-110 | SPECCED | watching the tenant's OWN tags fire is not ad management |
| G13-28 | Pixel Firing | ENH | X-110 | SPECCED | the 50ms hop before redirect |
| G13-29 | Quote Attribution Verification | ENH | X-183 | SPECCED | the gate cites or rejects · refuses: accepting without citation |
| G13-30 | Real-Time Heatmaps | ENH | X-110 | SPECCED | rage-click and scroll depth, rendered by X-194 |
| G13-31 | S3 Cloudflare Storage | ENH | X-157 | SPECCED | R2, zero egress; the `Asset` row is X-121's |
| G13-32 | Session Replay | KILLED → X-110 | X-110 | SPECCED | T677 owner: E3 first-party-only is absolute. X-110's rage-click and scroll aggregates survive |
| G13-33 | ShortLink Attribution Engine | ENH | X-138 | SPECCED | the branded short domain; P-072 puts `shortLinkFor()` in the base driver |
| G13-34 | Sub-Page Tracking | ENH | X-131 | SPECCED | what they care about, with confidence |
| G13-35 | UTM Harvesting | ENH | X-155 | SPECCED | hidden fields write straight to the entities, no staging table |
| G13-36 | View Tracking | ENH | X-199 | SPECCED | invoice opened → the alert names an action |
| G13-37 | Widget Rage-Click Detection | ENH | X-102 | SPECCED | the widget offers help instead of watching them fail |
| G13-38 | Zero-Party Data Collection | ENH | X-119 | SPECCED | a volunteered detail becomes a `Fact` with its source · refuses: an inference |

## G14 · ADS & PAID — 38
**Modules of record:** ⛔ FENCED §44 · X-139 · **Turn:** ⛔ REMOVED (fenced §44)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G14-01 | Ad Blindness Prevention | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-02 | Ad Comment Moderation | | | SUPERSEDED | fenced §44 |
| G14-03 | Addressable Geo-Fencing | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-04 | Audience Decay Detection | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-05 | Audience Rotation | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-06 | Automated Pausing | | | SUPERSEDED | fenced §44 |
| G14-07 | Automated Rules Engine | | | SUPERSEDED | fenced §44 |
| G14-08 | Automated Split Testing | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-09 | Budget Detection | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-10 | Budget Shifting | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-11 | Click Farm Prevention | X-221 | X-221 | SUPERSEDED | fenced §44 refuses refuses |
| G14-12 | Competitor Blocking | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-13 | Conversion-Based Routing | | | SUPERSEDED | fenced §44 |
| G14-14 | Copy Generation | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-15 | Copy vs. Creative Analysis | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-16 | CRM Audience Sync | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-17 | Cross-Sell Retargeting | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-18 | CTR Tracking | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-19 | Dayparting Analysis | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-20 | Device Bid Adjustments | | | SUPERSEDED | fenced §44 |
| G14-21 | Dynamic Retargeting | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-22 | Impression Share Maximization | | | SUPERSEDED | fenced §44 |
| G14-23 | Instant Deployment | | | SUPERSEDED | fenced §44 |
| G14-24 | Localized Ad Variations | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-25 | LTV-Based Bidding | | | SUPERSEDED | fenced §44 |
| G14-26 | Meta/Facebook Ads Sync | | | SUPERSEDED | fenced §44 |
| G14-27 | Multi-Tier Generation | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-28 | Negative Lookalikes | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-29 | One-Click Campaigns | | | SUPERSEDED | fenced §44 |
| G14-30 | Price Testing | X-221 | X-221 | SUPERSEDED | fenced §44 refuses refuses |
| G14-31 | Revival Testing | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-32 | Sequential Retargeting | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-33 | Switch-and-Save Campaigns | X-221 | X-221 | SUPERSEDED | fenced §44 refuses refuses |
| G14-34 | Visual Pin Drop | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-35 | Wasted Spend Prevention | X-221 | X-221 | SUPERSEDED | fenced §44 refuses refuses |
| G14-36 | Weather Overlays | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-37 | Weather-Triggered Bids | X-221 | X-221 | SUPERSEDED | fenced §44 |
| G14-38 | Win-Back Lookalikes | X-221 | X-221 | SUPERSEDED | fenced §44 |

## G15 · HR / INTERNAL OPS — 11
**Modules of record:** X-168 · X-169 · X-170 · X-171 · X-113 · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G15-01 | "Just in Time" Webinars | KILLED → X-158 | X-158 | SPECCED | P-120 — the claim law. A recording presented as *"starting in 15 minutes"* is a statement that is not true.… |
| G15-02 | 360-Degree Feedback | ENH | X-113 | SPECCED | T677: coaching and positive capability tracking only (§150.4) |
| G15-03 | AI Capacity Planning | ENH | X-10 | SPECCED | assignment by open workload — operational balancing, not scoring people |
| G15-04 | Anniversary/Birthday Bot | ENH | X-113 | SPECCED | an `account`-class notification (P-062) |
| G15-05 | Goal Tracking - OKRs | ENH | X-113 | SPECCED | T677 — coaching framing; the quarterly nag is a reminder, not a ranking |
| G15-06 | Granular Invoice Receipts | ENH | X-199 | SPECCED | this is the G1 anomaly resolved — §153 flagged it as *specced but not a G1 register line*; it is G15-06, an… |
| G15-07 | Multi-State Taxation | KILLED → X-199 | X-199 | SPECCED | Law 122 / Q-061 · X-169's own boundary — *we export, we do not file*. Reciprocal state tax rules are not ou… |
| G15-08 | Out of Office Sync | ENH | X-108 | SPECCED | out-of-office is named in the header; X-10 skips an unavailable assignee |
| ⛔ FENCED | ~~Overtime Math~~ (G15-09) | **⛔ FENCED T677** | **—** | FENCED | §220 — ⛔⛔ **NO PAYROLL. "The cleanest way to never produce a wrong wage is to never produce a wage."** The export ships HOURS and COMMISSION-EARNED only. |
| ⛔ FENCED | ~~Payroll Export~~ (G15-10) | **⛔ FENCED T677** | **—** | FENCED | §220 — ⛔⛔ **NO PAYROLL. "The cleanest way to never produce a wrong wage is to never produce a wage."** The export ships HOURS and COMMISSION-EARNED only. |
| ⛔ FENCED | ~~Pro-Rated Final Pay~~ (G15-11) | **⛔ FENCED T677** | **—** | FENCED | §220 — ⛔⛔ **NO PAYROLL. "The cleanest way to never produce a wrong wage is to never produce a wage."** The export ships HOURS and COMMISSION-EARNED only. |

## G16 · VIDEO & MEDIA — 31
**Modules of record:** X-158 · X-159 · X-114 · X-189 · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G16-01 | AI Stock Sourcing | ENH | X-114 | SPECCED | stock for a DEMO page only;  P-131 — a job photo is never stock |
| G16-02 | Auto-Captions | ENH | X-158 | SPECCED | burned captions on the 90-second cut |
| G16-03 | Chapter Orchestration | ENH | X-183 | SPECCED | long-form structure through the gate |
| G16-04 | Direct File Uploads | ENH | X-114 | SPECCED | signed upload URL; the `Asset` row is X-121's · refuses: an upload without a signed URL |
| G16-05 | Dynamic Offers | ENH | X-117 | SPECCED | P-120 — a countdown must be true. A timer that resets on refresh is a manufactured claim |
| G16-06 | Dynamic Video Ads | ENH | X-158 | SPECCED | ad PACKS as content are not fenced (P-128) |
| G16-07 | Expiry Links | ENH | X-103 | SPECCED | an expiring short link (P-072) |
| G16-08 | Featured Image AI | ENH | X-114 | SPECCED | generated, compressed, never a broken placeholder |
| G16-09 | Figma Integration | ENH | X-114 | SPECCED | a design source feeding the asset library |
| G16-10 | Global Broadcasts | ENH | X-112 | SPECCED | agency announcements are named in the header;  an un-dismissible popup is not a notification class we have… |
| G16-11 | Highlight Reels | ENH | X-200 | SPECCED | the coaching library — positive examples (T677) |
| G16-12 | Hook Testing | ENH | X-185 | SPECCED | named in the header |
| G16-13 | Image Auto-Sourcing | ENH | X-114 | SPECCED | = AI Stock Sourcing; one spec |
| G16-14 | Interactive Branching | ENH | X-158 | SPECCED | the branch is a state on the Conversation |
| G16-15 | Milestone Unlockables | ENH | X-200 | SPECCED | the wallboard — positive by construction, which is what §150.4 asks for |
| G16-16 | Monthly Generation | ENH | X-184 | SPECCED | approve a cadence, never a topic list |
| G16-17 | Multi-Media Injection | ENH | X-189 | SPECCED | the personalised overlay;  the never-fails image law — client photo → generated → branded card |
| G16-18 | Photo EXIF Injection | KILLED → X-189 | X-189 | SPECCED | Q-012 — Google is the SEO source. The EXIF-geotag myth was settled in the corpus by citing Google's own eng… |
| G16-19 | Quote Graphic Generation | ENH | X-189 | SPECCED | the branded card refuses refuses |
| G16-20 | Rich Media Hub | ENH | X-114 | SPECCED | transcoding to each channel's limits · ⛔ **REFUSES with OVER_LIMIT** refuses refuses |
| G16-21 | Rich Media Support | ENH | X-102 | SPECCED | carousels rendered in the chat; a missing asset renders text, never a broken placeholder *(the never-fails image law)*, asserted refuses refuses |
| G16-22 | Short-Form Script Extraction | ENH | X-158 | SPECCED | three cuts from the long form |
| G16-23 | Timestamped Chapters | ENH | X-158 | SPECCED | topic changes detected in the audio |
| G16-24 | Video Frame Annotation | ENH | X-202 | SPECCED | a comment at a timestamp IS a pending decision |
| G16-25 | Video Object Schema | ENH | X-176 | SPECCED | `VideoObject` injected on publish |
| G16-26 | Video View Retargeting | KILLED → X-221 | X-221 | SPECCED | §44 · P-128 — retargeting a viewer is ad management.  The watch-depth SIGNAL survives (X-158 → P-068) |
| G16-27 | Video Walkthrough | ENH | X-172 | SPECCED | a recorded explanation above the signature line |
| G16-28 | Video-to-Blog | ENH | X-183 | SPECCED | transcript → post, through the gate |
| G16-29 | Visual Creative AI | ENH | X-114 | SPECCED | ad packs as content (P-128); the logo and palette come from the brand kit |
| G16-30 | VSL Script Generation | ENH | X-158 | SPECCED | five questions → a script;  no invented statistics (P-120) · refuses: to include invented statistics in the script (P-120) |
| G16-31 | Watermarking | ENH | X-114 | SPECCED | the recipient's address on every page |

## G17 · OTHER / CROSS-CUTTING — 29
**Modules of record:** cross-cutting — see §M1 assignments · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G17-01 | A/B Testing | ENH | X-185 | SPECCED | opening lines; fleet evidence before promotion |
| G17-02 | Alternative Pitching | ENH | X-105 | SPECCED | named in the header |
| G17-03 | Asset Assignment | RE-HOME→G15 | X-113 | SPECCED | hardware tied to a staff `Person` |
| G17-04 | Authorize.Net Idempotency | ENH | X-198 | SPECCED | the `refId` hash is named in X-122 — a duplicated ref charges once |
| G17-05 | Auto-Sync | ENH | X-156 | SPECCED | Drive and Notion as ingest sources |
| G17-06 | Automated Follow-Ups | ENH | X-191 | SPECCED | = G12-07; one spec, and  one follow-up only |
| G17-07 | Automatic IP Banning | ENH | X-111 | SPECCED | `ip_bans` with a TTL is named in the header |
| G17-08 | Automatic Resizing | ENH | X-114 | SPECCED | one master → every size, WebP |
| G17-09 | Cross-Language Plagiarism | ENH | X-191 | SPECCED | named in the header |
| G17-10 | Dynamic Pricing Engine | ENH | X-82 | SPECCED | the $99.99/$179.99 figures are DEAD (P-001 · T468 — two packages, prices never change, numbers live in the… |
| G17-11 | Expiry Enforcement | ENH | X-164 | SPECCED | a quote expires at the version it was sold at |
| G17-12 | Geolocation Mismatch | ENH | X-155 | SPECCED | IP-versus-timezone as a bot signal |
| G17-13 | High-Volume Processing | ENH | X-151 | SPECCED | async, under the global per-target RPS ceiling (P-145) |
| G17-14 | Historical Re-targeting | KILLED → X-221 | X-221 | SPECCED | §44 · P-128 — location-history ad targeting is ad management |
| G17-15 | Instant Decoding | RE-HOME→FSM | X-167 | SPECCED | in-browser barcode scan |
| G17-16 | IP & Geolocation | ENH | X-122 | SPECCED | every invocation logs IP + MaxMind geo — named in the header |
| G17-17 | IP Velocity & Fraud Prevention | ENH | X-111 | SPECCED | fraud velocity is named in the header |
| G17-18 | Location-Specific Pricing | ENH | X-163 | SPECCED | a rate per pricebook (§145.4 — no nexus, no jurisdiction math) |
| G17-19 | Manager Roll-Up | ENH | X-07 | SPECCED | named in the header |
| G17-20 | Nurture Drip Sync | ENH | X-186 | SPECCED | a download drops into a sequence; the lane is declared, `ConsentService` decides |
| G17-21 | One-Click Revisions | ENH | X-202 | SPECCED | a rejection creates the work item;  X-195's header claims the same words for TEMPLATE revisions |
| G17-22 | Polygon Drawing | KILLED → X-16 | X-16 | SPECCED | §44 · P-128 — drawing a fence around a competitor's building is geo-fenced ad targeting |
| G17-23 | Polygon Drawing | ENH | X-10 | SPECCED | territories drawn on a map — named in the header |
| G17-24 | QR Code Generation | ENH | X-138 | SPECCED | branded QR off every short link |
| G17-25 | Re-districting | ENH | X-10 | SPECCED | named in the header — a tech leaves, the polygon splits automatically |
| G17-26 | Rollover Logic | ENH | X-82 | SPECCED | rollover-or-expire is a rate-registry policy the owner sets;  the metering model of record has auto top-up… |
| G17-27 | Timezone Magic | ENH | X-108 | SPECCED | slots localised to the customer's browser |
| G17-28 | Version History | ENH | X-121 | SPECCED | field-level history with version restore — named in the header |
| G17-29 | Visual Canvas Integration | ENH | X-189 | SPECCED | the overlay engine; sourcing is X-114's |

## G18 · VOICE & TELEPHONY — 27
**Modules of record:** X-66 · X-188 · X-197 · X-137 · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G18-01 | AMD - Answering Machine Detection | ENH | X-200 | SPECCED | §18C.4 —  uncertain → treat as human, never drop a voicemail on a live person |
| G18-02 | Automated Course Correction | ENH | X-200 | SPECCED | T677 — the fix, stated as a next action; never a ranking |
| G18-03 | CRM Screen Pop | ENH | X-200 | SPECCED | the agent desktop shows the record; the thread is X-01's |
| G18-04 | Dynamic Name Insertion | ENH | X-197 | SPECCED | the name is a `Fact`; the voice is ours, self-hosted (§18F) · ⛔ **REFUSES with UNVERIFIED_FACT** refuses refuses |
| G18-05 | Feature Gating & FOMO | KILLED → X-210 | X-210 | SPECCED | P-001 · T468 — two packages, and everything we ship, as we ship it, at the price you joined at. There is no… |
| G18-06 | Gamification Breaks | ENH | X-200 | SPECCED | a wellbeing prompt on the desk — the positive side of §150.4 |
| G18-07 | Holiday Overrides | ENH | X-108 | SPECCED | blackouts and holiday overrides are named in the header |
| G18-08 | Live Call Coaching | ENH | X-200 | SPECCED | listen · whisper · barge on the seat  · ⛔ **REFUSES with BAD_STATE** |
| G18-09 | LiveKit Voice Engine | ENH | X-197 | SPECCED | named in the header;  ElevenLabs is corpus vocabulary — the 7¢ minute only works self-orchestrated (§18F) |
| G18-10 | Local Caller ID | ENH | X-188 | SPECCED | the tenant's own registered numbers by area code refuses refuses |
| G18-11 | Local Presence | ENH | X-188 | SPECCED | = Local Caller ID; one spec.  rotation is bounded by P-065's per-number complaint monitoring |
| G18-12 | Multi-Ring Simultaneous | ENH | X-153 | SPECCED | three staff alerted, first reply claims, the claim expires at 30 minutes (P-077) |
| G18-13 | Post-Call Autopsy | ENH | X-200 | SPECCED | T677 — feedback to the rep, not a scoreboard against them · refuses: to act as a scoreboard against the rep — it is feedback (T677) |
| G18-14 | Post-Call CSAT Survey | ENH | C-Reviews | SPECCED | CSAT on resolve is named in the header refuses refuses |
| G18-15 | Queue Position Announcements | ENH | X-200 | SPECCED | live queue state; the AI answers first (R11/R20) |
| G18-16 | Talk-to-Listen Ratio | ENH | X-200 | SPECCED | T677 — a coaching signal only |
| G18-17 | Telephony Call Whisper | ENH | X-137 | SPECCED | the whisper names the SOURCE — that is what call tracking is for; the whisper audio is asserted present on the agent leg and **absent on the caller leg**, in one test on a real bridge — ⛔ the whisper is never audible to the caller; a bridge that would play it on the caller leg refuses the whisper rather than play it, asserted absent on the caller leg refuses refuses |
| G18-18 | Telephony Router | ENH | C-Telephony | SPECCED | the router and the eight adapters are the header refuses refuses |
| G18-19 | Twilio Power-Dialing | ENH | X-200 | SPECCED | Twilio is corpus vocabulary — Infobip primary (§120–§122) · refuses: to treat Twilio as primary — Infobip is primary (§120–§122) |
| G18-20 | VIP Skipping | ENH | C-Telephony | SPECCED | LTV read from C-Billing; the bypass is a routing rule refuses refuses |
| G18-21 | Voice RAG | ENH | X-66 | SPECCED | real-time objection detection; retrieval is X-148's |
| G18-22 | Voice Top-Up | ENH | C-Billing | SPECCED | the numbers are DEAD. The metering model of record: 7¢/min · 100 minutes included · top-ups $100→$100 and $… |
| G18-23 | Voicemail-to-Text | ENH | X-66 | SPECCED | transcription into the one Conversation refuses refuses |
| G18-24 | Whisper Messages | ENH | X-137 | SPECCED | = Telephony Call Whisper; one spec |
| G18-25 | Whisper Mode | KILLED → X-66 | X-66 | SPECCED | X-200's own header — *uncertain → treat as human, never drop a voicemail on a live person*. A silent hang-u… |
| G18-26 | Zoom/LiveKit Sync | ENH | X-158 | SPECCED | live webinar rooms and tokens |
| G18-27 | Zoom/Meet Auto-Generation | ENH | X-108 | SPECCED | a booking generates its own conference link · refuses: generating a conference link without a booking |

## G19 · SMS / MMS / WHATSAPP — 22
**Modules of record:** C-Sms · X-147 · C-Whatsapp · X-188 · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G19-01 | Automated Waitlist | ENH | X-108 | SPECCED | named in the header — a cancellation fills itself refuses refuses |
| G19-02 | Carrier Rate Shopping | RE-HOME→FSM | X-167 | SPECCED | parcel rates;  *a van and a storage unit, not a warehouse* bounds it |
| G19-03 | Carrier Route Detection | KILLED → C-Telephony | C-Telephony | SPECCED | T677 (owner) — we do not route around carriers, and P-070: a thread keeps its carrier; migration happens on… |
| G19-04 | Complex Branching | ENH | X-186 | SPECCED | multi-day sequences; any reply stops them (P-075) |
| G19-05 | Dynamic Config Injection | ENH | X-195 | SPECCED | feature flags per tenant — blast-radius control (P-182) |
| G19-06 | Executive Role Alerts | ENH | X-136 | SPECCED | a hiring signal, never a permit (P-068) |
| G19-07 | Expiry Rules | ENH | X-103 | SPECCED | expiring and click-capped short links |
| G19-08 | Ghost Risk Detection | ENH | X-01 | SPECCED | ghost-risk is named in the header — flagged before a send is wasted |
| G19-09 | High-Water Mark Alerts | ENH | X-111 | SPECCED | a compromise halt is a SECURITY stop and is not the credit cap — P-095 still holds: nothing stops because a… |
| G19-10 | Incentive Blocking | ENH | C-Reviews | SPECCED | compose-time block on *"review for 10% off"* — review-gating incentives are banned on every class, and this… |
| G19-11 | Missed Call Text Back | ENH | C-Sms | SPECCED | R80 — it fires on the RING, not the carrier timeout; transactional, blocked by nothing but STOP (P-061) refuses refuses |
| G19-12 | MMS Picture Engine | ENH | X-189 | SPECCED | the overlay engine; the SMS+MMS pair is ONE debit (R9) |
| G19-13 | Mobile Approvals | ENH | X-202 | SPECCED | a deep link and one green button |
| G19-14 | Number Reputation Monitoring | ENH | X-188 | SPECCED | P-065 makes per-number complaint monitoring mandatory under a shared brand |
| G19-15 | Real-Time Frontend Sync | ENH | X-01 | SPECCED | the thread updates without a refresh |
| G19-16 | Shortcode Ecosystem | ENH | X-104 | SPECCED | named in the header |
| G19-17 | SMS Auto-Top-Up Matrix | ENH | C-Billing | SPECCED | the $50/5,000 figures are dead — auto top-up is universal and the amounts live in X-82 (T469–T474) refuses refuses |
| G19-18 | SMS Integration | ENH | C-Sms | SPECCED | every link rides the short-linker (P-072); 159-char discipline is the segment law refuses refuses |
| G19-19 | SMS Rebuttal Engine | ENH | X-105 | SPECCED | the battle card drafted into a reply the human sends · ⛔ **REFUSES with NO_FACT** refuses refuses |
| G19-20 | SMS Reminders | ENH | X-108 | SPECCED | 24h · 1h · 10min;  one segment = one credit, a meter and never a fee |
| G19-21 | VIP Alerts | ENH | X-112 | SPECCED | the account manager told before the client leaves; the score is X-08's |
| G19-22 | Zernio WhatsApp & GBP Chat Sync | ENH | C-Whatsapp | SPECCED | §156.3 — GBP runs through Zernio; every channel lands on ONE Conversation |

## G20 · REVIEWS & REPUTATION — 16
**Modules of record:** C-Reviews · X-181 · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G20-01 | AI Review Replies | ENH | C-Reviews | SPECCED | P-110 — 1–3★ never reaches a public reply path, which is exactly what makes auto-reply structurally safe |
| G20-02 | Automated Reminders | RE-HOME→G15 | X-113 | SPECCED | nagging managers about performance reviews — not customer reviews |
| G20-03 | Automated Review Replies | ENH | C-Reviews | SPECCED | the prompt lint: *"how did the repair go"*, never *"mention Dave"* (§37.3) |
| G20-04 | Competitor Review Mining | ENH | C-Reviews | SPECCED | named in the header;  a reviewer's name is a signal — contacting them needs a Lane-2 basis they have not gi… |
| G20-05 | CSAT Automation | ENH | C-Reviews | SPECCED | CSAT on resolve; a 1★ reopens the ticket in X-111 |
| G20-06 | Guest Review Links | ENH | C-Reviews | SPECCED | named in the header |
| G20-07 | NPS Polling | ENH | C-Reviews | SPECCED | day 60, <7 → triage (P-113) |
| G20-08 | Omni-Review Hub UI | ENH | C-Reviews | SPECCED | named in the header — Google via Zernio · Yelp · Facebook · BBB |
| G20-09 | Reputation Engine | ENH | C-Reviews | SPECCED | the owner's original ask, now the header |
| G20-10 | Reputation Targeting | ENH | X-105 | SPECCED | named in the header — under 3.5★ is a distress signal (P-146) · refuses: targeting over 3.5★ — under 3.5★ is the distress signal (P-146) |
| G20-11 | Review Gating | ENH | C-Reviews | SPECCED | OWNER §168A: the gate LIVES as a tenant preference (threshold 4 default, 5 max) and is a SEQUENCE — gated / ticket / resolved / re-ask / the link |
| G20-12 | Review Gating/Triage | ENH | C-Reviews | SPECCED | = G20-11; one spec. fix_then_ask flips ON, firing on ticket.resolved |
| G20-13 | Review Reactivation | ENH | C-Reviews | SPECCED | named in the header; the send is Marketing class and waits for the window |
| G20-14 | Review Response AI | ENH | C-Reviews | SPECCED | anything ambiguous is DRAFTED to the inbox, never published — sarcasm read as praise is a brand disaster (§… |
| G20-15 | Review Syncing | ENH | X-183 | SPECCED | a real review inside the case study (R36 — real data only) |
| G20-16 | Reviews & Reputation Expansion | ENH | X-181 | SPECCED | the triage routing that §37 made the owner law |

## G21 · SUPPORT & HELP — 14
**Modules of record:** X-111 · generated help library · **Turn:** ✅ 32 C3 (T677 · §167)

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
| G21-01 | Live Chat Injection | KILLED → X-102 | X-102 | SPECCED | P-120 — the claim law. Scripted messages posing as other attendees is manufactured social proof. *(Same cla… |
| G21-02 | Omni-Channel Merging | ENH | X-111 | SPECCED | fuzzy-merged tickets; one Person, one Conversation (P-163) |
| G21-03 | Runbook Automation | ENH | X-203 | SPECCED | the minted desk's second mechanism — the scripted response to a failure, with its own state |
| G21-04 | Self-Service Portal | ENH | X-199 | SPECCED | card, invoices, seats — and R34's cancel in under 60 seconds belongs on the same screen |
| G21-05 | SLA Engine | ENH | X-111 | SPECCED | `sla_due_at` with escalation; every alert names an ACTION, never a fact |
| G21-06 | Slack Alerts | ENH | X-123 | SPECCED | an outbound webhook destination;  the ad-fatigue trigger behind this example is FENCED (§44) |
| G21-07 | Slack Approvals | ENH | X-202 | SPECCED | two buttons, no login |
| G21-08 | Slack Approvals | RE-HOME→G15 | X-113 | SPECCED | `/pto` — the time-off workflow, not a platform decision |
| G21-09 | Slack Bot Creation | ENH | X-123 | SPECCED | a channel created by webhook on a project event |
| G21-10 | Slack Integration | ENH | X-124 | SPECCED | the assistant answering in-thread from the generated help registry refuses refuses |
| G21-11 | Slack Integration | ENH | X-202 | SPECCED | approve or deny without opening the CRM |
| G21-12 | Slack Integration | ENH | X-123 | SPECCED | a comment event pinging the right person |
| G21-13 | Slack Sync | ENH | X-200 | SPECCED | a closed deal on the wallboard and in the channel  · ⛔ **REFUSES with BAD_STATE** |
| G21-14 | Ticket Deflection | ENH | X-111 | SPECCED | the help card offered before the ticket is submitted |

---
**Totals:** ⭐ **+8 owner rows on C-Reviews (OWN-01…OWN-06 §168A · OWN-07 the photo · OWN-08 the two-cycle cap, §169.0); **738** rows to spec (G8-12 purged T677) (G1-04 retired by the owner) — 50 written at seven fields (§169).** 816 register lines across 21 domains · G1 61 (T662) · **G2–G5 247 (§165)** · **G6–G11 243 (§166)** · **G12–G21 227 (§167)** · ⭐⭐⭐ **778 CLASSIFIED · 0 PENDING** · the remaining 38 are G14, fenced at §44. **Step 2's classification phase is complete (T677).**
| ⭐ (reclaimed) | Coupon Engine | **RECLAIMED T677** | **X-210** | CLASSIFIED | §214 — owner overrule; R26 governs GOAIEZ's pricing, not a tenant's.  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Discount Thresholds | **RECLAIMED T677** | **X-210** | CLASSIFIED | §214 — owner overrule; R26 governs GOAIEZ's pricing, not a tenant's.  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Subscription Pausing | **RECLAIMED T677** | **X-117** | CLASSIFIED | §214 — owner overrule; R26 governs GOAIEZ's pricing, not a tenant's.  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Subscription Gifting | **RECLAIMED T677** | **X-117** | CLASSIFIED | §214 — owner overrule; R26 governs GOAIEZ's pricing, not a tenant's.  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Paid Consultations | **RECLAIMED T677** | **X-117** | CLASSIFIED | §214 — owner overrule; R26 governs GOAIEZ's pricing, not a tenant's.  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Milestone Billing | **RECLAIMED T677** | **X-117** | CLASSIFIED | §214 — owner overrule; R26 governs GOAIEZ's pricing, not a tenant's.  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Downgrade Offers | **RECLAIMED T677** | **X-210** *(issuer_scope=platform)* | CLASSIFIED | §215 — R26 revoked. ⛔ **R34 survives: the save-offer adds NO STEP — one screen, both choices, cancel always one tap.**  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Late Fee Automation | **RECLAIMED T677** | **X-211** | CLASSIFIED | §216 — AR  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Dynamic Payment Plans | **RECLAIMED T677** | **X-211** | CLASSIFIED | §216 — AR  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | ACH/Wire Integration | **RECLAIMED T677** | **X-211** | CLASSIFIED | §216 — AR  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Offline Payment Logging | **RECLAIMED T677** | **X-211** | CLASSIFIED | §216 — AR  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Collections Routing | **RECLAIMED T677** | **X-211** | CLASSIFIED | §216 — AR - the THIRD-PARTY handoff; the platform never pursues a debt  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Dispute Automation | **RECLAIMED T677** | **C-Reviews + X-177** | CLASSIFIED | §216 — ⛔ NOT a chargeback - a GOOGLE REVIEW REMOVAL request. ⛔ ~~L1 forever~~ **STRUCK by `R235`** — ⭐ the automation RUNS; a human confirms the ToS violation  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Trial Expiration Logic | **RECLAIMED T677** | **C-Billing** | CLASSIFIED | §216 — already law - §197.3 NOTICE BEFORE CHARGE  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Automated Uninstalls | **RECLAIMED T677** | **X-195** | CLASSIFIED | §216 — token revoked + webhooks removed on uninstall  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Automated Refund Requests | **RECLAIMED T677** | **⛔ FENCED** | CLASSIFIED | §216 — ad-fraud click credits - §44/P-128 |
| ⭐ (reclaimed) | Plaid Sync | **RECLAIMED T677** | **held** | CLASSIFIED | §216 — corporate-card EXPENSE capture - held with the G15 ruling |
| ⭐ (reclaimed) | Pre-Paid Credit Ledger | **RECLAIMED T677** | **C-Billing** | CLASSIFIED | §216 — already built - Flow B, the agency credit block |
| ⭐⭐⭐ (reclaimed) | Integration Marketplace | **RECLAIMED T677 · PROMOTED** | **X-212 `MigrationIn` + X-195** | CLASSIFIED | §218 — **COMPETITOR MIGRATION-IN: 5 years of customers, jobs, invoices, photos and agreements from ServiceTitan/Jobber/Housecall or a CSV.** ⛔ **An import EMITS NOTHING · DRY RUN first · not a permit · notes scanned to `secure` fields · and the EXIT works too (P-203).**  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Voice Cloning | **RECLAIMED T677** | **X-66 / C-Telephony** | CLASSIFIED | §217 — P-202 - tenant's OWN voice, explicit consent, and every message DISCLOSES it is AI-generated  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Voice Biometrics | **RECLAIMED T677** | **X-66** | CLASSIFIED | §217 — ⛔⛔ P-202 - "passively" STRUCK. OPT-IN, disclosed, auth-only. A voiceprint is a `secure` field  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Deep Fake Audio Protection | **RECLAIMED T677** | **X-66** | CLASSIFIED | §217 — P-202 - the legitimate USE of an opted-in voiceprint  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Grandfathering | **RECLAIMED T677** | **X-210 + C-Billing** | CLASSIFIED | §217 — ⭐ newly load-bearing now that P-001 allows a list-price rise: a cohort rate is a ROW, never an assumption  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Multi-Entity Nesting | **RECLAIMED T677** | **X-176** | CLASSIFIED | §217 — ⛔ 6th title-lie - SCHEMA nesting for the knowledge graph, not agency hierarchies  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Embedded Apps | **RECLAIMED T677** | **X-195** | CLASSIFIED | §217 — iframe dashboards in the client portal  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Private Apps | **RECLAIMED T677** | **X-195** | CLASSIFIED | §217 — a tenant-private connector hidden from the public store  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | Avatar Rendering | **RECLAIMED T677** | **X-158** | CLASSIFIED | §217 — ⛔ a synthetic spokesperson is DISCLOSED - P-202  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ (reclaimed) | ClickHouse Warehouse | **RECLAIMED T677** | **ARCHITECTURE** | CLASSIFIED | §217 — the analytical store - digest stack, wave 2. ONE store, not two |
| ⭐ (reclaimed) | ElasticSearch Indexing | **RECLAIMED T677** | **ARCHITECTURE** | CLASSIFIED | §217 — the same decision as ClickHouse - pick one |
| ⭐ (reclaimed) | GraphQL Optimization | **RECLAIMED T677** | **ARCHITECTURE** | CLASSIFIED | §217 — API-gateway option; REST + the action registry suffices until proven otherwise |
| ⭐ (reclaimed) | Time Travel | **RECLAIMED T677** | **STEP 8** | CLASSIFIED | §217 — dev sandbox - fast-forward the clock to test recurring billing |
| ⭐ (reclaimed) | Auto-Destruction | **RECLAIMED T677** | **STEP 8** | CLASSIFIED | §217 — dev sandbox - delete after 14 days idle |
| ⛔ FENCED | Hiring Manager Extraction | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Candidate Nurture Drips | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⭐ (reclaimed) | Knockout Questions | **RECLAIMED T677** | **HELD (ATS)** | CLASSIFIED | §217 — ⛔ an automated employment rejection on one numeric answer is a regulated automated decision - may FILTER, never be the sole basis |
| ⛔ FENCED | One-Click Job Board Syndication | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Multi-Currency Landed Cost | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Will-Call/Pickup Routing | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⭐ RESCUED | Expense Reimbursements | **⭐ RE-HOMED T677** | **X-173 + the pricebook COST side** | CLASSIFIED | §220 — ⭐ **a JOB COST, not HR.** *A part bought for a job and the miles driven to it are what make the margin guard honest — the cost of the JOB, not the compensation of a PERSON.*  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ RESCUED | Mileage Tracking | **⭐ RE-HOMED T677** | **X-173 + the pricebook COST side** | CLASSIFIED | §220 — ⭐ **a JOB COST, not HR.** *A part bought for a job and the miles driven to it are what make the margin guard honest — the cost of the JOB, not the compensation of a PERSON.*  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ RESCUED | AI Receipt OCR | **⭐ RE-HOMED T677** | **X-173 + the pricebook COST side** | CLASSIFIED | §220 — ⭐ **a JOB COST, not HR.** *A part bought for a job and the miles driven to it are what make the margin guard honest — the cost of the JOB, not the compensation of a PERSON.*  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ RESCUED | Peer Recognition | **⭐ T677** | **X-200** | CLASSIFIED | §219 — ⭐ RESCUED - the positive-only scorecard, where it already belongs  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ RESCUED | Reorder Point Math | **⭐ T677** | **X-167** | CLASSIFIED | §219 — ⭐ RESCUED from the warehouse fence - a VAN running out of a part is X-167 core; §197.1 lead-time horizon is the mechanism  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ RESCUED | Safety Stock Alerts | **⭐ T677** | **X-167** | CLASSIFIED | §219 — ⭐ RESCUED - same  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⭐ RESCUED | Cancellation Funnel | **⭐ T677** | **X-210 + C-Billing** | CLASSIFIED | §219 — ⛔ 7th TITLE-LIE - a RETENTION flow, not a warehouse row. R34 binds: it may add NO STEP  ⭐ **SPECCED AT SEVEN FIELDS — §226** ||
| ⛔ FENCED | 9-Box Grid Matrix | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | AI Resume Parsing | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Attendance Tracking | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Benefits Enrollment | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Bereavement/Jury Duty | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Compensation Modeling | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Diversity Tracking - EEO | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Exit Interview Funnel | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Exit Interview Negotiation | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Garnishment Handling | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Mandatory PTO Suggestion | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Missing Receipt Harassment | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Offer Letter Generation | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Org Chart Generator | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | PIP Enforcer | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Seed List Diversity | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Shift Swapping | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | Unlimited PTO Tracking | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | W-2 Pre-Flight | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **HRIS fence: "we do PAY, not PEOPLE OPS."** NOT NOW, reversible by one word. *(Knockout Questions, if ever built: may FILTER, never the sole basis for a rejection.)* |
| ⛔ FENCED | 3PL Integration | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Amazon FBA Sync | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Cycle Counting | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Dead Stock Detection | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Drop-Shipping Workflows | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | FIFO/LIFO Tracking | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Kitting/Assemblies | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Pick Path Optimization | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Pick-and-Pack Validation | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Returns Restocking | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Serial Number Tracking | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Split Fulfillment | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Store Transfers | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Weight/Box Math | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Blanket POs | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Partial Receiving | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |
| ⛔ FENCED | Receiving Mode | **⛔ T677** | **⛔ FENCED** | CLASSIFIED | §219 — **Warehouse fence: "we do VAN INVENTORY, not WAREHOUSES."** A plumber has a van, not a building with aisles. NOT NOW, reversible by one word. |

## ⭐⭐⭐ `N-` NAMESPACE — AUTHORED IN THE PLAN, NOT MINED FROM THE REGISTER *(§239 · T677)*

| id | module | ① what | ⑤ assertion · refusal |
| :--- | :--- | :--- | :--- |
| **N-001** | **X-204** | GRANT or a coded refusal | a refusal without one of P-060's twenty codes FAILS THE BUILD |
| **N-002** | **X-204** | every sender asks; no sender decides | grep finds no channel module deciding permission for itself |
| **N-003** | **X-204** | STOP is instant and absolute | halts every pending step for that Person within one cycle |
| **N-004** | **X-204** | an imported Person is UNPERMITTED | asserted on X-212's commit (P-203) |
| **N-005** | **X-204** | the cadence ceiling counts every class | a GROW and an INFORM in the same window both count |
| **N-006** | **X-204** | suppression is per DESTINATION, never per customer | a hard bounce on one address never silences a person everywhere |
| **N-007** | **X-201** | the evidence bundle assembles itself | call logs, transcripts, signatures, receipts, invoice AND consent record present before submission |
| **N-008** | **X-201** | accepts chargeback.received from ANY gateway | doctor asserts no gateway name appears in the dispute logic (P-197) |
| **N-009** | **X-201** | the exposure ledger | money taken vs work DELIVERED, per tenant |
| **N-010** | **X-201** | the verb does not exist here | a dispute is defended or conceded; money back is X-198's and it is L1 |
| **N-011** | **X-201** | deadlines are a clock, not a hope | a dispute inside its raise-window goes to a human regardless of state; the window is a CONFIG ROW per gateway and reason code |
| **N-012** | **X-207** | Web Push + APNs + FCM from one internal contract | no carrier between us and the device |
| **N-013** | **X-207** | a push is still a SEND | it passes X-204 and the cadence ceiling - asserted refuses |
| **N-014** | **X-207** | quiet hours apply | the owned channel is not an exemption |
| **N-015** | **X-207** | token death is normal | an expired token retires quietly, never a delivery failure against the tenant |
| **N-016** | **X-207** | the owner's three alerts arrive in MINUTES | a review naming an employee, a RECOVER escalation, a money event |
| **N-017** | **X-208** | the TENANT supplies the Lob key | doctor asserts NO platform account exists for this channel |
| **N-018** | **X-208** | Do-Not-Mail is checked AT GENERATION | by the time it is at the post office it is printed and paid for |
| **N-019** | **X-208** | ⛔ ~~L1 forever~~ **STRUCK by `R235`** — ⭐ **the module ships ON** | physical mail cannot be recalled, so `P-096`/`D5` confirms **THE SEND** — *the irreversible STEP, never the module's posture* |
| **N-020** | **X-208** | a piece carrying an offer carries its promotion_id | (section 200, P-201) |
| **N-021** | **X-209** | employee -> their own day | everything else in this platform acts tenant -> customer |
| **N-022** | **X-209** | its output NEVER reaches a customer | grep finds no send path to a non-staff Person |
| **N-023** | **X-209** | per-EMPLOYEE autonomy | a new hire starts at L1 on everything |
| **N-024** | **X-209** | the visibility seam | whatever it does appears in the tenant's own log |
| **N-025** | **X-209** | it may not see another employee's secure fields | P-198, role AND job scoped |
| **N-026** | **X-209** | it refuses to answer about pay | P-204: the platform does not pay people, so its assistant does not discuss what they are owed |
| **N-027** | **X-210** | no cap -> cannot save |  |
| **N-028** | **X-210** | the margin guard NAMES every below-cost service | ⛔ **REFUSES with BELOW_COST** |
| **N-029** | **X-210** | no stacking by default | best-single-discount wins |
| **N-030** | **X-210** | the AI honours and never invents | ⛔ **REFUSES with NO_FACT** |
| **N-031** | **X-210** | issuer_scope never crosses | a tenant promotion can never apply to a platform subscription |
| **N-032** | **X-210** | incrementality holdout mandatory | a promotion with no measurement window cannot be created |
| **N-033** | **X-211** | a fee with no matching TERM is refused | refuses |
| **N-034** | **X-211** | a plan past the threshold routes to a financing partner | doctor asserts no path where the platform holds the paper |
| **N-035** | **X-211** | offline payment needs a reference or a photo | and reconciles against the deposit |
| **N-036** | **X-211** | ⭐ **the package is BUILT autonomously**; ⛔ TRANSMISSION to an agency is a human action — *money and a debt leave the tenant's control* | `doctor` asserts no autonomous path to `ar.packaged` *(**`R235`-COMPLIANT** — `P-096`/`D5`'s irreversible step, **not a posture**)* |
| **N-037** | **X-211** | an open RECOVER blocks dunning entirely | P-205, structurally, not as a sort |
| **N-038** | **X-212** | 500 imported jobs -> ZERO outbound messages |  |
| **N-039** | **X-212** | a dry run writes NOTHING to live | asserted by diffing row counts |
| **N-040** | **X-212** | one weak identifier is rejected, never merged |  |
| **N-041** | **X-212** | notes scanned to secure fields | the burglary kit arriving in bulk |
| **N-042** | **X-212** | the EXPORT works too | P-203 - if we can import it, we can export it |

⭐⭐ **42 rows. Every one carries an id, an assertion and a refusal.** ⛔ **An `N` row is HIGHER status than a `G` row, not lower: a `G` row is a marketing sentence about a different company, corrected on the way in; an `N` row was written against a failure mode by someone who understood the system.**
| **N-043…N-048** | **X-206 · X-120 · X-166** | ⛔⛔ **THE SECRETS-AND-MONEY SET — BUILD FIRST** | **a credential is NEVER returned in plaintext to ANY caller (incl. its owner, an admin, the AI) · scoped by ownership, asserted cross-tenant · never in a log/trace/APM/dump · CVV NEVER persisted anywhere for any duration · no screen returns a decrypted PAN · a margin figure is NEVER computed from invoiced revenue, only COLLECTED** |
| **N-049…N-061** | **X-126 · X-128 · X-150 · X-145** | **the gates and registries** | **no `Fact` → no skill, every action, no bypass · the gate runs BEFORE the model sees the tool · X-128 IS gate 6 and fails the build on an event with no origin · the waterfall stops at the first VALID SHAPE, never the first 200 · the optimiser's candidate set is the registry FILTERED BY THE GATE and can never widen it** refuses |
| **N-062…N-086** | **X-129 · X-165 · X-173 · X-168 · X-175 · X-141 · X-130 · X-143 · X-147** | **the rest, as invariants** | **a tenant is never left on an empty domain · priority scheduling MUST BE REAL · a sync conflict goes UNKNOWN not STALE · ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · a price on site comes from X-163 or is refused · a replay NEVER emits · X-130 is AGGREGATE ONLY and refuses below N tenants · an RCS→SMS degrade never re-sends** refuses |

⭐⭐⭐ **86 `N-` rows total. ZERO empty briefs remain.** ⛔ **These sixteen are ORIGINAL — authored, not mined. The register never described a credential vault.**


## ⭐⭐ THE RECLAIMED — REGISTER LINES THAT NEVER REACHED THIS FILE *(2026-08-27)*
> **84 register lines had no row here. 49 were already dispositioned in the plan (§216 · §217 · §218 · §226) and 35 were dispositioned this session.**
> ⛔ **`KILL` was never one of the options — every removal below cites the ruling that made it.**

| # | Capability | BL | Parent / X | Status | Note |
| :--- | :--- | :--- | :--- | :--- | :--- |
|⭐ G1-61|ACH/Wire Integration|**ENH**|**X-211 Receivables**| SPECCED | §226.1 — the reason is stated in plain words; the threshold is a ROW (P-193) · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ past the threshold it ROUTES TO A FINANCING PARTNER; doctor asserts no tenant-owed instalment schedule is stored by us · the threshold is a ROW (P-193) |
|⭐ G1-62|Automated Refund Requests|**ENH**|**X-139 + the pixel**| SPECCED | R200 — click-fraud report from FIRST-PARTY data; the tenant downloads, files and claims · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ DETECT AND REPORT ONLY — doctor asserts no outbound call to any ad platform (R200) · refuses: any outbound call to an ad platform (R200) refuses |
|⭐ G1-63|Automated Uninstalls|**ENH**|**X-195 Marketplace**|CLASSIFIED| §226.4 — uninstall leaves ZERO orphaned subscriptions, asserted · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G1-64|Blanket POs|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a blanket PO draws down; doctor asserts no autonomous release |
|⭐ G1-65|Collections Routing|**ENH**|**X-211 Receivables**| SPECCED | §226.1 — ⭐ **the package is BUILT autonomously**; ⛔ transmission to an agency is a human action **because money and a debt leave the tenant's control** *(`P-096`/`D5`'s irreversible step — **`R235`-COMPLIANT**, not a posture)* · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ doctor asserts NO path to ar.packaged without a recorded human action · L1 forever · R211: a resolution attempt is recorded first |
|⭐ G1-66|Coupon Engine|**ENH**|**X-210 PromotionEngine**| SPECCED | §226.2 — a promotion with no cap CANNOT BE SAVED · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a promotion with NO CAP cannot be saved — asserted |
|⭐ G1-67|Discount Thresholds|**ENH**|**X-210 PromotionEngine**| SPECCED | §226.2 — the margin guard NAMES every service put below cost · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ the margin guard NAMES every service put below cost — asserted |
|⭐ G1-68|Dispute Automation|**ENH**|**C-Reviews + X-177**| SPECCED | §216.1 — a GOOGLE REVIEW REMOVAL, not a chargeback. ⛔ ~~L1 FOREVER~~ **STRUCK by `R235`** — ⭐ **the automation RUNS and prepares the request**; a human confirms the ToS violation because that is a JUDGEMENT the AI cannot make *(`R236`: not a permission gate — the AI genuinely cannot decide it)* · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a GOOGLE REVIEW REMOVAL, not a chargeback · ⛔ ~~L1 FOREVER~~ **STRUCK by `R235`** — ⭐ **the automation RUNS and prepares the request**; a human confirms the ToS violation because that is a JUDGEMENT the AI cannot make *(`R236`: not a permission gate — the AI genuinely cannot decide it)* |
|⭐ G1-69|Downgrade Offers|**ENH**|**X-210 (issuer_scope=platform)**| SPECCED | §226.2 — R34: the offer and CANCEL on one screen, cancel always one tap · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ R34: no interstitial exists between the cancel tap and the cancellation — asserted BY ABSENCE |
|⭐ G1-70|Dynamic Payment Plans|**ENH**|**X-211 Receivables**| SPECCED | §226.1 — beyond the threshold it ROUTES TO A FINANCING PARTNER; we never hold the paper · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ past the threshold it ROUTES TO A FINANCING PARTNER; we never hold the paper — asserted refuses |
|⭐ G1-71|Late Fee Automation|**ENH**|**X-211 Receivables**|CLASSIFIED| §226.1 — a fee with no matching TERM in the agreement is REFUSED (P-092) · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads refuses |
|⭐ G1-72|Mass Payouts|**ENH**|**the affiliate module (R189)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ R189: OUR affiliates only — doctor asserts no bulk payout path for tenant-owed money; a tenant gets an exportable REPORT |
|⭐ G1-73|Milestone Billing|**ENH**|**X-117 Commerce**| SPECCED | §226.3 — fires only on a milestone the CUSTOMER accepted, never an internal status change · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ fires ONLY on a milestone the CUSTOMER accepted, never an internal status change — asserted |
|⭐ G1-74|Offline Payment Logging|**ENH**|**X-211 Receivables**| SPECCED | §226.1 — a reference or photo is MANDATORY; reconciles against the deposit · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a reference or photo is MANDATORY and reconciles against the deposit; an unreconciled logged payment is REFUSED refuses |
|⭐ G1-75|Paid Consultations|**ENH**|**X-117 Commerce**| SPECCED | §226.3 — a pricing STRUCTURE, not a promotion · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a pricing STRUCTURE, not a promotion; the price is looked up or REFUSED (P-092) |
|⭐ G1-76|Partial Receiving|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a partial receipt leaves the PO OPEN with the remainder named; a silently closed PO is REFUSED |
|⭐ G1-77|Plaid Sync|**ENH**|**X-173 AccountingSync**| SPECCED | §216.1 — corporate-card expense capture, NOT bank verification (a title collision) · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ EXPENSE CAPTURE, not bank verification — a title collision, asserted by absence of any verification path |
|⭐ G1-78|Pre-Paid Credit Ledger|**COVERED**|**C-Billing**|CLASSIFIED| §226.3 — already built, Flow B's credit block; recorded as covered, not re-specced · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads refuses |
|⭐ G1-79|Receiving Mode|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ every received line reconciles to the PO; an unmatched receipt raises rather than posting |
| ⭐ G1-80 | Stripe Metered Billing Sync and authorize.net | **COVERED** | **— already tracked** → C-Billing | CLASSIFIED | a RENAME, not a gap: this line is present under the shorter name `Stripe Metered Billing Sync` · transcribed 2026-08-27 |
|⭐ G1-81|Subscription Gifting|**ENH**|**X-117 (issuer_scope=tenant)**| SPECCED | §226.3 · R190 — a tenant gifts their customer, not us gifting ours · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ issuer_scope=tenant (R190) — a tenant gifts their customer; doctor asserts no platform-scope path |
|⭐ G1-82|Subscription Pausing|**ENH**|**X-117 Commerce**|CLASSIFIED| §226.3 — pause STOPS the meter, asserted on the next cycle · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G1-83|Trial Expiration Logic|**ENH**|**C-Billing**| SPECCED | §226.3 — §197.3 NOTICE BEFORE CHARGE; a conversion with no prior notice FAILS · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ §197.3 NOTICE BEFORE CHARGE — a conversion with no preceding notice event FAILS, asserted with the notice suppressed |
|⭐ G2-78|Knockout Questions|**FENCED**| X-109 |CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads refuses |
|⭐ G3-67|Hiring Manager Extraction|**FENCED**|—| DEFERRED | R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G4-52|Embedded Apps|**ENH**|**X-195 Marketplace**| SPECCED | §226.4 — sandboxed iframe, no DOM access; a scope not in the manifest cannot be granted · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ no DOM access; a scope not in the manifest CANNOT be granted — asserted |
|⭐ G4-53|GraphQL Optimization|**ARCHITECTURE**| **— (noted)** → X-122 |CLASSIFIED| §217.4 — REST + the action registry suffices until proven otherwise · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G4-54|Integration Marketplace|**ENH**|**X-212 MigrationIn**| SPECCED | §217.1 — NOT an app store: one-click migration-in from ServiceTitan/Jobber/Housecall · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ P-203: the import writes rows and emits NOTHING — 500 jobs, ZERO outbound refuses |
|⭐ G4-55|Private Apps|**ENH**|**X-195 Marketplace**| SPECCED | §226.4 — a tenant-private connector, invisible cross-tenant. P-186: manifests, code NEVER · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ invisible cross-tenant, proven with two live tenants · P-186: manifests, code NEVER |
|⭐ G4-56|Time Travel|**ENH**| **Step 8 sandbox (turns 78–85)** → X-161 | SPECCED | §217.4 — fast-forward the clock to test recurring billing · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ doctor asserts NO time-travel path exists outside a sandbox |
|⭐ G6-36|3PL Integration|**OUT OF SCOPE**|—| DEFERRED | R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G6-37|Amazon FBA Sync|**OUT OF SCOPE**|—| DEFERRED | R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G6-38|Cancellation Funnel|**ENH**|**X-210 PromotionEngine**| SPECCED | §226.2 — with R34: no interstitial exists, by absence · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ R34: cancel stays one tap; the offer renders beside it, never in front of it refuses |
|⭐ G6-39|Cycle Counting|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ every level reconciles at job completion and is never trusted raw (§198) |
|⭐ G6-40|Dead Stock Detection|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ dead stock is REPORTED, never auto-disposed — asserted |
|⭐ G6-41|Drop-Shipping Workflows|**OUT OF SCOPE**|—| DEFERRED | R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G6-42|FIFO/LIFO Tracking|**OUT OF SCOPE**|—| DEFERRED | R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G6-43|Kitting/Assemblies|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ selling a kit decrements every component atomically or the sale is REFUSED |
|⭐ G6-44|Pick Path Optimization|**OUT OF SCOPE**|—| DEFERRED | R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G6-45|Pick-and-Pack Validation|**OUT OF SCOPE**|—| DEFERRED | R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G6-46|Reorder Point Math|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ doctor asserts NO autonomous ordering path — it PROPOSES; L1, MONEY |
|⭐ G6-47|Returns Restocking|**ENH**|**X-167 (inventory half)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a refund restocks exactly once; a replayed refund does not double-restock |
|⭐ G6-48|Safety Stock Alerts|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ alerts, never orders — asserted |
|⭐ G6-49|Serial Number Tracking|**ENH**|**X-167**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a serialised item with no serial cannot be closed — asserted |
|⭐ G6-50|Split Fulfillment|**OUT OF SCOPE**|—| DEFERRED | R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G6-51|Store Transfers|**ENH**|**X-167 (truck-to-truck, R203)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a transfer is atomic — killing it mid-move leaves the total unchanged · R201/R203: TRUCK-to-truck only, doctor asserts no location-to-location path |
|⭐ G6-52|Weight/Box Math|**OUT OF SCOPE**|—| DEFERRED | R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G7-47|Grandfathering|**ENH**|**X-210 + C-Billing**| SPECCED | §226.2 — a cohort rate is a ROW; an existing tenant's rate never changes without a NOTIFIED action · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ a cohort rate is a ROW; an existing rate never changes without a NOTIFIED action — asserted |
|⭐ G7-48|Multi-Entity Nesting|**ENH**|**X-176 SchemaEngine**| SPECCED | §217.5 — SIXTH TITLE-LIE: schema nesting for the knowledge graph, not agency hierarchies · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ schema nesting for the knowledge graph, not agency hierarchies; validates with ZERO errors or does not render |
|⭐ G8-41|Auto-Destruction|**ENH**| **Step 8 sandbox (turns 78–85)** → X-161 | SPECCED | §217.4 — delete an untouched sandbox after 14 days · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ destruction WARNS before it deletes; an untouched sandbox goes at 14 days |
|⭐ G8-42|ElasticSearch Indexing|**ARCHITECTURE**|**— (the DIGEST stack note)**| DEFERRED | §217.4 — the same decision as ClickHouse, made twice · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G9-41|ClickHouse Warehouse|**ARCHITECTURE**|**— (the DIGEST stack note)**| DEFERRED | §217.4 — one analytical store, chosen at wave 2. NOT a capability row · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G9-42|Monte Carlo Simulations|**ENH**|**X-07**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ every output is labelled a SIMULATION; a simulated figure never renders like a measured one |
|⭐ G12-40|One-Click Job Board Syndication|**FENCED**|—| DEFERRED | R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-12|9-Box Grid Matrix|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-13|AI Receipt OCR|**ENH**|**X-173**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ an uncertain OCR goes to a REVIEW QUEUE, never to a ledger |
|⭐ G15-14|AI Resume Parsing|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-15|Attendance Tracking|**ENH**|**X-105 (webinar watch time)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ webinar watch time, NOT staff attendance — attendance policy fences the SUBJECT; doctor asserts no staff-directed use |
|⭐ G15-16|Benefits Enrollment|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-17|Bereavement/Jury Duty|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-18|Compensation Modeling|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-19|Diversity Tracking - EEO|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-20|Exit Interview Funnel|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-21|Exit Interview Negotiation|**ENH**|**X-210 (the cancel save-offer)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ R34 overrides: cancel stays ONE TAP; the save-offer renders beside it · refuses: anything but one tap to cancel refuses |
|⭐ G15-22|Expense Reimbursements|**ENH**|**X-173**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ P-204: lands as a JOB COST; doctor asserts NO payment-to-a-person path in this module |
|⭐ G15-23|Garnishment Handling|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-24|Mandatory PTO Suggestion|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-25|Mileage Tracking|**ENH**|**X-173**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ mileage is a job cost with its route recorded; no payment path |
|⭐ G15-26|Missing Receipt Harassment|**ENH**|**X-173**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ the chase is a REMINDER, never a penalty (§150.4) |
|⭐ G15-27|Offer Letter Generation|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-28|Org Chart Generator|**ENH**|**X-111 (R188)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ R188 — roles not wages; doctor asserts no pay field |
|⭐ G15-29|Peer Recognition|**ENH**|**X-200**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ §150.4 — praise only; no per-person negative output exists in the schema |
|⭐ G15-30|PIP Enforcer|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-31|Seed List Diversity|**ENH**|**C-Mail (warm-up)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ no warm-up event reaches a tenant-facing metric · every quantity is a RANGE plus jitter — a constant is the signature · refuses: a constant quantity — a warm-up volume with no jitter is the signature of automation, and no warm-up event reaches a tenant-facing metric refuses |
|⭐ G15-32|Shift Swapping|**ENH**|**X-108 / X-171 (R188)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ R188 — work not pay; doctor asserts no pay field |
|⭐ G15-33|Unlimited PTO Tracking|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G15-34|W-2 Pre-Flight|**FENCED**|—|CLASSIFIED| R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G16-32|Avatar Rendering|**ENH**|**X-158 VideoEngine**| SPECCED | §226.4 · P-202 — the disclosure is rendered IN the frame, not only in metadata · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ the disclosure is rendered IN THE FRAME — asserted on the output file, not the request |
|⭐ G16-33|Deep Fake Audio Protection|**ENH**|**X-66 VoiceAgent**| SPECCED | §226.4 — the legitimate USE of enrolment: confirming the caller changing their own booking · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ enrolment confirms a caller changing their OWN booking; no other use path exists |
|⭐ G16-34|Voice Cloning|**ENH**|**X-66 VoiceAgent**| SPECCED | §226.4 · P-202 — the tenant's OWN voice only, recorded consent, AI disclosure on every message · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ the tenant OWN voice only, recorded consent; P-202 disclosure on every message, asserted per channel |
|⭐ G17-30|Candidate Nurture Drips|**FENCED**|—| DEFERRED | R184 / P-204 — pays or employs somebody · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads |
|⭐ G17-31|Multi-Currency Landed Cost|**ENH**|**X-117 (currency half; landed cost OUT, R204)**| SPECCED | reclaimed 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ R204: ONE currency — doctor asserts no conversion path; landed cost is OUT |
|⭐ G18-28|Voice Biometrics|**ENH**|**X-66 VoiceAgent**| SPECCED | §217.2 · P-202 — "PASSIVELY" STRUCK; opt-in enrolment, the voiceprint is a `secure` field · transcribed from the plan 2026-08-27 · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads · ⑤ "PASSIVELY" IS STRUCK — doctor asserts NO passive enrolment path exists; the voiceprint is a `secure` field |
|⭐ G18-29|Will-Call/Pickup Routing|**OUT OF SCOPE**| X-117 |CLASSIFIED| R197 / R201 — shipping & fulfilment; clients use another system · ⛔ register description STRIPPED (P-206) — it lives in the register, not in the file the brief reads refuses |

---

---

## ⭐⭐⭐ THE EIGHTEEN — TRANSCRIBED FROM THEIR OWN TEST ANCHORS *(2026-08-27)*

> ⛔⛔ **`capabilities:scaffold` refused 18 of 119 modules: no capability row, so `LAW 128`'s floor read ZERO and `brief` would emit nothing for them.**
> ⭐ *I expected their specs to exist as `N-` property rows. **17 of the 18 had none.*** ⭐⭐⭐ **But every one has a TEST ANCHOR — and a test anchor is a ⑤ in all but name.**
> ⛔ **Derived from each module's own anchor. Nothing below is new policy.**

| Capability | Name | Kind | Parent | Status | ⑤ THE ASSERTION |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **N-120-01** | Card vault PCI scope proof | PROPERTY | **X-120** | SPECCED | ⛔ a grep for card-number, PAN, CVV or CVC across database/migrations returns NOTHING, enforced by CI — the only component in PCI scope proves scope by ABSENCE |
| **N-120-02** | Expiry raised before failure | PROPERTY | **X-120** | SPECCED | a card expiring within 20 days raises BEFORE it fails a charge |
| **N-126-01** | No Fact, no skill | PROPERTY | **X-126** | SPECCED | ⭐⭐ an agent skill invoked with NO grounding Fact is REFUSED with reason NO_FACT — the refusal path's load-bearing assertion refuses |
| **N-126-02** | Grounded pass is logged | PROPERTY | **X-126** | SPECCED | a message with valid grounding passes, and the decision is logged either way |
| **N-129-01** | Never a dead site | PROPERTY | **X-129** | SPECCED | ⛔ a tenant is NEVER left on a dead site during migration (R130) — cutover reversible until DNS propagates |
| **N-141-01** | Replay emits nothing real | PROPERTY | **X-141** | SPECCED | a replay NEVER emits a real outbound — asserted at the DRIVER, not the caller |
| **N-143-01** | Declared surfaces only | PROPERTY | **X-143** | SPECCED | an action marked surfaces webmcp is reachable there AND REFUSED on every surface it does not declare |
| **N-145-01** | Gated candidates only | PROPERTY | **X-145** | SPECCED | the candidate set is the action registry FILTERED BY THE GATE — an ungated action is never a candidate |
| **N-147-01** | Degrade is recorded | PROPERTY | **X-147** | SPECCED | ⛔⛔ every RCS to SMS degrade writes rcs.degraded_to_sms — a paid-for RCS send never silently becomes an SMS |
| **N-150-01** | Junk value rejected | PROPERTY | **X-150** | SPECCED | ⭐⭐ a phone field of N/A from tier 1 is REJECTED and tier 2 is called — a junk value that satisfies NOT NULL is the expensive failure |
| **N-150-02** | Expensive tier bounded | PROPERTY | **X-150** | SPECCED | the expensive tier's call count over a month is bounded and asserted |
| **N-165-01** | Never authors a price | PROPERTY | **X-165** | SPECCED | a membership price taps through X-163's confirmation — ⛔ X-165 NEVER authors a price refuses |
| **N-166-01** | No costs, no margin | PROPERTY | **X-166** | SPECCED | ⚠️ actual-vs-expected from ledger rows only; a job with NO cost rows reports NO margin rather than 100% (INFERRED from WHAT, not the anchor) |
| **N-175-01** | The field works offline | PROPERTY | **X-175** | SPECCED | ⛔ the field surface works OFFLINE — asserted on a throttled fixture, because a basement has no signal |
| **N-206-01** | No key left in env | PROPERTY | **X-206** | SPECCED | ⛔⛔ a key still present in .env after wave 0's gate FAILS THE GATE |
| **N-206-02** | Reveal is scoped by ownership | PROPERTY | **X-206** | SPECCED | ⛔ **you may reveal what you OWN, never what you do not** *(§178)* — a reveal is a named, logged action carrying WHO · WHEN · WHICH KEY, and ⛔ **a secret is NEVER returned in plaintext to any caller, including its owner, including an admin, including the AI** |
| **N-207-01** | Push passes consent | PROPERTY | **X-207** | SPECCED | ⭐ every push origin passes ConsentService::decide with channel push; no push payload carries a secret field |
| **N-208-01** | Cost before approval | PROPERTY | **X-208** | SPECCED | the cost is shown BEFORE approval — ⛔ mail never sends without it |
| **N-209-01** | Per employee, not per tenant | PROPERTY | **X-209** | SPECCED | the employee's agent reaches only what THAT EMPLOYEE may reach — asserted per employee, not per tenant |
| **N-213-01** | A pass needs an artifact | PROPERTY | **X-213** | SPECCED | ⭐⭐⭐ every check stores its screenshot and checklist version — A PASS WITH NO ARTIFACT DID NOT HAPPEN |
| **N-213-02** | Flags, never blocks | PROPERTY | **X-213** | SPECCED | it FLAGS and never blocks publication (R219) |
| **N-214-01** | Debit is never surcharged | PROPERTY | **X-214** | SPECCED | ⛔⛔ debit is NEVER surcharged — BIN-asserted, and an UNKNOWN card type is treated as DEBIT |
| **N-214-02** | Disclosure precedes charge | PROPERTY | **X-214** | SPECCED | no surcharge applies without a preceding surcharge.disclosed |
| **N-215-01** | Signature binds a hash | PROPERTY | **X-215** | SPECCED | ⭐⭐ the signature binds a HASH of the rendered document — alter one character and it is invalid · refuses: an altered document refuses |
| **N-215-02** | A comment never edits | PROPERTY | **X-215** | SPECCED | a comment NEVER edits a signed document |

**25 specs · 18 modules · ⭐ all 18 now clear `LAW 128`'s floor.**
⚠️ **`N-166-01` is flagged as an INFERENCE** — derived from `X-166`'s WHAT, not stated in its anchor. *It is the right behaviour and it is mine, so it is marked rather than passed off as transcription.*

---

## ⭐⭐⭐ THE THREE NEW MODULES — CAPABILITY ROWS *(2026-08-27, §264J)*

> ⛔⛔ **`X-127` · `X-217` · `X-218` were minted with complete 13-field headers into `§264J` — and reached NEITHER tracker.** *The roster is **122**, both trackers said 119, and all three had **zero capability rows**, so `LAW 128`'s floor would have refused every brief.*
> ⭐ **Derived from each module's own TEST ANCHOR. Nothing below is new policy.**

| Capability | Name | Kind | Parent | Status | ⑤ THE ASSERTION |
| :--- | :--- | :--- | :--- | :--- | :--- |
| **N-127-01** | Claims match the live query | PROPERTY | **X-127** | SPECCED | ⛔⛔ a published claim is RE-COMPUTED from the live query and must MATCH TO THE DIGIT — a drifted claim is PULLED AUTOMATICALLY, not flagged (§5 Law 3: no invented statistics, ever) |
| **N-127-02** | Tenant zero gates the ship | PROPERTY | **X-127** | SPECCED | §5 Law 2 — a module that fails for tenant #0 does not ship |
| **N-127-03** | GOAIEZ scope is not a superuser | PROPERTY | **X-127** | SPECCED | ⛔ a GOAIEZ-scoped read returns ZERO rows belonging to any other tenant (R232 N-232-04) — tenant-zero does not relax the no-harvest fence |
| **N-217-01** | Offered terms equal program terms | PROPERTY | **X-217** | SPECCED | ⭐⭐ an accepted recruit lands in X-205 with the EXACT terms offered — offer row and program row asserted EQUAL, so a negotiated rate can never drift from what was promised |
| **N-217-02** | No offer exceeds the ceiling | PROPERTY | **X-217** | SPECCED | ⛔ no recruitment offer exceeds the confirmed commission ceiling |
| **N-217-03** | Recruitment is not the program | PROPERTY | **X-217** | SPECCED | X-217 owns affiliate_prospects/recruitment_offers ONLY — it never writes X-205's affiliates/payouts tables (P-163 boundary) |
| **N-218-01** | No payout without a verified artifact | PROPERTY | **X-218** | SPECCED | ⛔⛔⛔ a payout NEVER fires without a VERIFIED deliverable — a live URL returning 200, captured and HASHED, asserted present before C-Billing is called. A deal marked delivered with NO ARTIFACT is REFUSED refuses |
| **N-218-02** | Discovery is scoped by audience fit | PROPERTY | **X-218** | SPECCED | discovery returns only profiles matching audience fit and locality; an unscoped pull is refused |
| **N-218-03** | Both audiences, one engine | PROPERTY | **X-218** | SPECCED | ⭐ the same module serves tenant and GOAIEZ scope (R232) — a TENANT-scoped read returns ZERO GOAIEZ deals and the reverse |

**9 specs · 3 modules · ⭐ all three now clear `LAW 128`'s floor.**

## ⭐⭐⭐ X-219 · X-220 — THE AI CORE'S CAPABILITY ROWS *(R239, 2026-08-28)*

> ⛔ **I minted both modules with full nine-field headers and ZERO capability rows.** *`capabilities:scaffold` correctly REFUSED them — "brief will refuse it on `LAW 128`'s floor until it has ≥1 spec." **The refusal was right and the omission was mine.***

| id | capability | status | module | ⑤ assertion · refusal |
| :--- | :--- | :--- | :--- | :--- |
| **N-219-01** | Resolve a model for a module and job class | SPECCED | X-219 AiRouter | *asserted: `$ai->for(module, jobClass, slot)` returns a ROW, never a vendor string.* ⛔ **REFUSES with `NO_ASSIGNMENT` when no row exists — it does NOT fall back to a default model** |
| **N-219-02** | Backup must be a different provider | SPECCED | X-219 AiRouter | *asserted: an assignment whose PRIMARY and BACKUP share a provider FAILS the build (`N-237-03`).* ⛔ **REFUSES the write — a vendor outage takes every model it serves** |
| **N-219-03** | A self-hosted model is a first-class row | SPECCED | X-219 AiRouter | *asserted: `self_hosted = true` with a base url and no vendor key RESOLVES.* ⛔ **REFUSES any code path that assumes a commercial API** |
| **N-219-04** | Sunset models raise before they break | SPECCED | X-219 AiRouter | *asserted: `deprecates_at` within 30 days RAISES (`R219`).* ⛔ **REFUSES the build when a sunset model sits in PRIMARY with no BACKUP** |
| **N-220-01** | A prompt is a row with a version | SPECCED | X-220 PromptRegistry | *asserted: every `ai_calls` row records the prompt VERSION it used.* ⛔ **REFUSES a call whose prompt has no version — "the output changed" is unanswerable without it** |
| **N-220-02** | A frozen prompt cannot be edited | SPECCED | X-220 PromptRegistry | *asserted: a change to a frozen prompt creates a NEW VERSION.* ⛔ **REFUSES the edit — an experiment nobody can reproduce is not a prompt** |
| **N-220-03** | No PRIMARY promotion without the golden set | SPECCED | X-220 PromptRegistry | *asserted: `N-238-19` — a model enters PRIMARY only after passing that job class's golden set.* ⛔⛔ **REFUSES the promotion. Without this, "any model, any vendor" means ANY QUALITY, DISCOVERED IN PRODUCTION** |
| **N-220-04** | Every eval records model_served | SPECCED | X-220 PromptRegistry | *asserted: `model_requested` AND `model_served` on every eval row (`N-238-02`).* ⛔ **REFUSES a result carrying only one — a silent fallback would attribute the BACKUP's output to the PRIMARY** |

| **N-046** | CVV Never Persisted | PROPERTY | **X-120** | SPECCED | ⛔⛔ **CVV IS NEVER PERSISTED, ANYWHERE, FOR ANY DURATION** *(P-196)* — *asserted against the buffer, the trace and the dump* |
| **N-047** | No Decrypted PAN on Screen | PROPERTY | **X-120** | SPECCED | **no screen anywhere returns a decrypted PAN** *(P-199)* — **the SYSTEM has access; no PERSON does** |
| **N-078** | Replay Never Emits | PROPERTY | **X-141** | SPECCED | ⛔ **a replay NEVER emits** *(P-203's shape)* · *"would this rule have helped?" is answered from history, never from a model* · **session replay stays killed** *(G13-32)* |
| **N-079** | Replay Never Emits | PROPERTY | **X-141** | SPECCED | ⛔ **a replay NEVER emits** *(P-203's shape)* · *"would this rule have helped?" is answered from history, never from a model* · **session replay stays killed** *(G13-32)* |
| **N-080** | Replay Never Emits | PROPERTY | **X-141** | SPECCED | ⛔ **a replay NEVER emits** *(P-203's shape)* · *"would this rule have helped?" is answered from history, never from a model* · **session replay stays killed** *(G13-32)* |
| **N-069** | GL Categorisation Confidence | PROPERTY | **X-173** | SPECCED | GL categorisation carries a CONFIDENCE and below threshold it asks · a sync conflict goes UNKNOWN, never STALE (§192) · the payroll export ships hours and commission-earned ONLY (P-204) |
| **N-070** | GL Categorisation Confidence | PROPERTY | **X-173** | SPECCED | GL categorisation carries a CONFIDENCE and below threshold it asks · a sync conflict goes UNKNOWN, never STALE (§192) · the payroll export ships hours and commission-earned ONLY (P-204) |
| **N-071** | GL Categorisation Confidence | PROPERTY | **X-173** | SPECCED | GL categorisation carries a CONFIDENCE and below threshold it asks · a sync conflict goes UNKNOWN, never STALE (§192) · the payroll export ships hours and commission-earned ONLY (P-204) |
| **N-081** | Aggregate Floor Rule | PROPERTY | **X-130** | SPECCED | ⛔⛔ **aggregate only — a query that resolves to fewer than N tenants is REFUSED** · *no tenant's data is identifiable in a fleet number* · **it informs; it never routes** |
| **N-082** | Aggregate Floor Rule | PROPERTY | **X-130** | SPECCED | ⛔⛔ **aggregate only — a query that resolves to fewer than N tenants is REFUSED** · *no tenant's data is identifiable in a fleet number* · **it informs; it never routes** |
| **N-083** | Aggregate Floor Rule | PROPERTY | **X-130** | SPECCED | ⛔⛔ **aggregate only — a query that resolves to fewer than N tenants is REFUSED** · *no tenant's data is identifiable in a fleet number* · **it informs; it never routes** |
| **N-065** | Domain Move Leaves No Empty Tenant | PROPERTY | **X-129** | SPECCED | a tenant is never left on an empty domain — rankings, links and redirects MOVE (R130) · the drain is idempotent — replayed twice, one result · the old domain redirects, never 404s · the EXPORT works too |
| **N-063** | The Cross-Module Invariants | PROPERTY | **X-129 · X-130 · X-141 · X-143 · X-147 · X-165 · X-168 · X-173 · X-175** | SPECCED | **a tenant is never left on an empty domain · priority scheduling MUST BE REAL · a sync conflict goes UNKNOWN not STALE · ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · a price on site comes from X-163 or is refused · a replay NEVER emits · X-130 is AGGREGATE ONLY and refuses below N tenants · an RCS→SMS degrade never re-sends** |
| **N-064** | The Cross-Module Invariants | PROPERTY | **X-129 · X-130 · X-141 · X-143 · X-147 · X-165 · X-168 · X-173 · X-175** | SPECCED | **a tenant is never left on an empty domain · priority scheduling MUST BE REAL · a sync conflict goes UNKNOWN not STALE · ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · a price on site comes from X-163 or is refused · a replay NEVER emits · X-130 is AGGREGATE ONLY and refuses below N tenants · an RCS→SMS degrade never re-sends** |
| **N-067** | The Cross-Module Invariants | PROPERTY | **X-129 · X-130 · X-141 · X-143 · X-147 · X-165 · X-168 · X-173 · X-175** | SPECCED | **a tenant is never left on an empty domain · priority scheduling MUST BE REAL · a sync conflict goes UNKNOWN not STALE · ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · a price on site comes from X-163 or is refused · a replay NEVER emits · X-130 is AGGREGATE ONLY and refuses below N tenants · an RCS→SMS degrade never re-sends** |
| **N-073** | The Cross-Module Invariants | PROPERTY | **X-129 · X-130 · X-141 · X-143 · X-147 · X-165 · X-168 · X-173 · X-175** | SPECCED | **a tenant is never left on an empty domain · priority scheduling MUST BE REAL · a sync conflict goes UNKNOWN not STALE · ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · a price on site comes from X-163 or is refused · a replay NEVER emits · X-130 is AGGREGATE ONLY and refuses below N tenants · an RCS→SMS degrade never re-sends** |
| **N-076** | The Cross-Module Invariants | PROPERTY | **X-129 · X-130 · X-141 · X-143 · X-147 · X-165 · X-168 · X-173 · X-175** | SPECCED | **a tenant is never left on an empty domain · priority scheduling MUST BE REAL · a sync conflict goes UNKNOWN not STALE · ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · a price on site comes from X-163 or is refused · a replay NEVER emits · X-130 is AGGREGATE ONLY and refuses below N tenants · an RCS→SMS degrade never re-sends** |
| **N-085** | The Cross-Module Invariants | PROPERTY | **X-129 · X-130 · X-141 · X-143 · X-147 · X-165 · X-168 · X-173 · X-175** | SPECCED | **a tenant is never left on an empty domain · priority scheduling MUST BE REAL · a sync conflict goes UNKNOWN not STALE · ⛔ X-168 has no overtime/out-of-hours/attendance (P-204) · a price on site comes from X-163 or is refused · a replay NEVER emits · X-130 is AGGREGATE ONLY and refuses below N tenants · an RCS→SMS degrade never re-sends** |
| **N-044** | Credential Ownership Scoping | PROPERTY | **X-206** | SPECCED | a credential is scoped by OWNERSHIP — a tenant's key is unreachable from another tenant's seat, asserted cross-tenant |
| **N-045** | Credential Exhaust Redaction | PROPERTY | **X-206** | SPECCED | no credential appears in a log, a trace, an APM payload or a crash dump (P-196's exhaust rule, applied to keys) |
| **N-066** | Member Priority Scheduling Is Real | PROPERTY | **X-165** | SPECCED | priority scheduling MUST BE REAL — a member offered the same slot as a non-member fails the test · renewal gives notice before charge (§197.3) · member pricing is a pricebook tier, never a discount |
| **N-068** | Member Priority Scheduling Is Real | PROPERTY | **X-165** | SPECCED | priority scheduling MUST BE REAL — a member offered the same slot as a non-member fails the test · renewal gives notice before charge (§197.3) · member pricing is a pricebook tier, never a discount |
| **N-072** | Job Time Is Not A Timesheet | PROPERTY | **X-168** | SPECCED | refuses: X-168 JobTime; arrival, duration and completion on a JOB — not a timesheet · GPS clock-in is bound to job state ·  no overtime, no out-of-hours, no attendance (P-204 · §219) |
| **N-074** | Job Time Is Not A Timesheet | PROPERTY | **X-168** | SPECCED | refuses: X-168 JobTime; arrival, duration and completion on a JOB — not a timesheet · GPS clock-in is bound to job state ·  no overtime, no out-of-hours, no attendance (P-204 · §219) |
| **N-075** | On-Site Prices Come From X-163 | PROPERTY | **X-175** | SPECCED | a price on site comes from X-163 or is refused (P-092) · it is C-Agent's third deployment — no second agent exists · it never quotes a customer directly refuses |
| **N-077** | On-Site Prices Come From X-163 | PROPERTY | **X-175** | SPECCED | a price on site comes from X-163 or is refused (P-092) · it is C-Agent's third deployment — no second agent exists · it never quotes a customer directly refuses |
| **N-050** | The Fact Gate Invariants | PROPERTY | **X-126 · X-128 · X-145 · X-150** | SPECCED | **no `Fact` → no skill, every action, no bypass · the gate runs BEFORE the model sees the tool · X-128 IS gate 6 and fails the build on an event with no origin · the waterfall stops at the first VALID SHAPE, never the first 200 · the optimiser's candidate set is the registry FILTERED BY THE GATE and can never widen it** refuses |
| **N-048** | Margin Comes From Collected, Never Invoiced | PROPERTY | **X-166** | SPECCED | a margin figure is NEVER computed from invoiced revenue — only from COLLECTED (§201) |
| **N-051** | The Fact Gate Invariants | PROPERTY | **X-126 · X-128 · X-145 · X-150** | SPECCED | **no `Fact` → no skill, every action, no bypass · the gate runs BEFORE the model sees the tool · X-128 IS gate 6 and fails the build on an event with no origin · the waterfall stops at the first VALID SHAPE, never the first 200 · the optimiser's candidate set is the registry FILTERED BY THE GATE and can never widen it** refuses |
| **N-054** | The Fact Gate Invariants | PROPERTY | **X-126 · X-128 · X-145 · X-150** | SPECCED | **no `Fact` → no skill, every action, no bypass · the gate runs BEFORE the model sees the tool · X-128 IS gate 6 and fails the build on an event with no origin · the waterfall stops at the first VALID SHAPE, never the first 200 · the optimiser's candidate set is the registry FILTERED BY THE GATE and can never widen it** refuses |
| **N-057** | The Fact Gate Invariants | PROPERTY | **X-126 · X-128 · X-145 · X-150** | SPECCED | **no `Fact` → no skill, every action, no bypass · the gate runs BEFORE the model sees the tool · X-128 IS gate 6 and fails the build on an event with no origin · the waterfall stops at the first VALID SHAPE, never the first 200 · the optimiser's candidate set is the registry FILTERED BY THE GATE and can never widen it** refuses |
| **N-060** | The Fact Gate Invariants | PROPERTY | **X-126 · X-128 · X-145 · X-150** | SPECCED | **no `Fact` → no skill, every action, no bypass · the gate runs BEFORE the model sees the tool · X-128 IS gate 6 and fails the build on an event with no origin · the waterfall stops at the first VALID SHAPE, never the first 200 · the optimiser's candidate set is the registry FILTERED BY THE GATE and can never widen it** refuses |
| **N-052** | The Fact Gate Invariants | PROPERTY | **X-126** | SPECCED | NO Fact → NO SKILL, on EVERY action, with no bypass path · a refusal always carries one of P-060's codes · a rate ceiling is a config ROW, never a literal ·  the gate is evaluated BEFORE the model sees the tool, not after refuses |
| **N-053** | Gate 6 Reads And Never Writes | PROPERTY | **X-128** | SPECCED | it reads every module's declarations and FAILS THE BUILD on a consumed event with no origin (P-208/P-213 — this module IS gate 6) · it never writes to a module · its output is regenerated, never hand-edited |
| **N-055** | Gate 6 Reads And Never Writes | PROPERTY | **X-128** | SPECCED | it reads every module's declarations and FAILS THE BUILD on a consumed event with no origin (P-208/P-213 — this module IS gate 6) · it never writes to a module · its output is regenerated, never hand-edited |
| **N-056** | The Waterfall Stops At A Valid Shape | PROPERTY | **X-150** | SPECCED | it stops at the first VALID SHAPE, never the first 200 · a provider that returns a wrong shape is DEMOTED, not retried · cost order is a config row |
| **N-058** | The Waterfall Stops At A Valid Shape | PROPERTY | **X-150** | SPECCED | it stops at the first VALID SHAPE, never the first 200 · a provider that returns a wrong shape is DEMOTED, not retried · cost order is a config row |
| **N-059** | The Candidate Set Is Gate-Filtered | PROPERTY | **X-145** | SPECCED | the candidate set is the action registry FILTERED BY THE GATE — never the raw registry ·  it optimises within permitted actions and can never widen them · an outcome is a LedgerEntry, never a model's opinion |
| **N-061** | The Candidate Set Is Gate-Filtered | PROPERTY | **X-145** | SPECCED | the candidate set is the action registry FILTERED BY THE GATE — never the raw registry ·  it optimises within permitted actions and can never widen them · an outcome is a LedgerEntry, never a model's opinion |
| **N-084** | WebMCP Exposure Is Declared, And A Degrade Never Re-Sends | PROPERTY | **X-143** | SPECCED | only actions marked surfaces:[webmcp] are exposed, and the gate still applies ·  RCS→SMS degrade is silent to the customer and VISIBLE in the log · a degrade never re-sends refuses |
| **N-086** | WebMCP Exposure Is Declared, And A Degrade Never Re-Sends | PROPERTY | **X-143** | SPECCED | only actions marked surfaces:[webmcp] are exposed, and the gate still applies ·  RCS→SMS degrade is silent to the customer and VISIBLE in the log · a degrade never re-sends refuses |
