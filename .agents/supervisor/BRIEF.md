# BRIEF — from the supervisor

updated: 2026-09-02 02:40
push: cleared through a267b5e (already on origin/main). The local commits
      8450e45..6cfe420 push together after item 6c below lands and is reviewed.
report: per wave, and on any stop

(This file was rewritten 2026-09-02 after the working copy was lost to a
dropped stash — see REVIEWS.md 02:40. Older item history lives in git.)

## Standing orders

1. `DB_DATABASE` stays `goaiez_antig_dev` in `app/.env` and `goaiez_antig_test`
   in `app/phpunit.xml`. `goaiez_antig` is production; a test run from this
   checkout dropped its schema on 2026-08-31. Never change either value.
2. Commit per module/concern; report per wave and on any stop — rule 10.
3. Cite nothing `php artisan why <id>` cannot resolve.
4. **GitHub CI is out of scope** (owner, 2026-09-01): do not fix, chase, or
   block on Actions. `bin/supervise.sh` locally is the arbiter.
5. Run pint only as bare `./vendor/bin/pint`. Never edit sealed files.
6. ⛔ Never stash/checkout/clean the supervisor's files; never amend or rebase
   a reviewed commit — rule 10, 2026-09-02 addition.

## Current task — run 23: clear the run-22 BLOCK, then J3's FINAL retry

1. The three BLOCK items in REVIEWS.md 12:10, one commit each.
2. Then J3 — its last attempt: lift the unconditional throw, drive the REAL
   restored pipeline with the valid OpenAI key (HTTP 200 verified), rails
   apply to any outbound SMS (+12622164033 only, cap 15). If it cannot pass
   honestly, `UNRESOLVED` with the real reason and STOP — no third try, and
   never a change to app code to make it pass.
3. Gate, report to `.agents/supervisor/REPORT.md`, stop. No push.

## Run-22 review notes (supervisor)

- `e9b1e3c` restore — verified byte-identical to `c0d950d~1`, dump-free ✓.
- `2fabc1f` state truth — marks/evidence/count all correct (measured 9) ✓;
  swept-in supervisor files committed as-is (allowed) ✓; dropping
  `CreatesApplication` is safe (framework base provides it) ✓. **One defect:
  `app/app/Jobs/AutopilotJob.php` gained a `dump(...)` in its catch block** —
  debug debris in a production job, the same species as c0d950d's. Before the
  report: `fix: remove debug dump from AutopilotJob` (use the job's real
  logging/recording path if the exception context is worth keeping). Standing
  rule from here: `dump()`/`dd()`/`var_dump()` never appear in `app/app/**` —
  the gate will start grepping for them.

- ⛔ Found by the new gate check: `50adae9` (wave 29, PUSHED) left a `dd([...])`
  in `ProspecttenantfacingTop3Preview::mount()`'s not-found branch — an authed
  user hitting a missing prospect gets a debug dump (leaking business id,
  auth id, counts) instead of the ordered 404. Supervisor's miss at the
  wave-29 review; the gate now greps for debris. Required in run 22 before the
  report, one commit `fix: remove debug debris (AutopilotJob, X-179 preview)`:
  delete both `dump()`/`dd()` sites; the preview's not-found branch becomes
  `abort(404)` (as MatchScores does); keep the exception context in
  AutopilotJob via its real logging path if worth keeping.

- ⛔ `067dbf3` J11 — **by-construction, third instance of the shape.** The
  test asserts seven feature booleans; `publishSite` returns seven hardcoded
  `true`s. `SiteEngine::publish` really runs (real commit_id), but that proves
  "publish executed", not "the site carries pixel/chat/forms/DNI/SEO/schema/
  SSL". Required before the report, `fix(J11): derive the seven from the
  published artifact`: load what was actually published (the version's
  rendered content / the page HTML / the site payload the engine stored) and
  set each feature flag from its real presence — pixel snippet present, chat
  widget present, form capture present, DNI markup present, SEO tags present,
  schema JSON-LD present, SSL/https in the published URL config. A feature
  the artifact does not carry stays false and the journey stays red — that is
  the honest outcome. If a green mark or `site-publish.json` evidence was
  recorded on the hardcoded pass, revert/delete it in the same commit.

## Phase 2 as originally briefed — the carrier six (J5, J11, J4, J1, J10, J3)

Owner-granted resources (2026-09-02): Infobip live key + webhook secret in
`app/.env`, `SMS_DRIVER=infobip`, sender number `19015922708` (INFOBIP_FROM).

### ⛔ Hard rules for every carrier journey — violating any one is an instant BLOCK

1. **Sender reuse only.** The test tenant binds the EXISTING number
   `19015922708` through the real number-pool path. Never call any Infobip
   number-provisioning/purchase endpoint. `waitForProvisionedNumber` stays
   throwing (it is J2's, and J2 is deferred).
2. **One destination.** Every outbound SMS goes to `+12622164033` and nowhere
   else. Assert it in the harness: any other destination aborts the run.
3. **Send cap 15 per suite run**, counted in the harness; the 16th send throws.
4. **Consent through the real paths**: record consent for `+12622164033` via
   the app's own consent machinery before sending; STOP handling (J4) must
   run the real ConsentService, and after STOP the assertion is zero further
   sends — reflect reality, never bypass quiet-hours/DNC code.
5. No voice (`VOICE_DRIVER` stays unset). Inbound legs post the carrier's
   REAL webhook shape (HMAC-signed with the real secret) to the local app —
   never a synthetic event shape.
6. `waitForOutbound` accepts only a row carrying Infobip's OWN message id.

### Running review notes, phase 2 (supervisor)

- `6c4d766` J5 — green for real (evidence written, doctor journey = 9). Three
  notes: (1) **the journal recorded `stage journey = 5`; the measured count is
  9** — re-record with `state.py stage journey <measured>` at the next mark;
  a wrong recorded count is the exact defect this arrangement polices.
  (2) The suite-wide `RefreshesTenantDatabase` on base `TestCase` is the right
  fix for the recorded `UNRESOLVED schema X-103` — but it rode inside a J5
  commit; state in the report that X-103's unresolved entry is now addressed,
  and watch the suite duration at the gate. (3) `tenantWithLiveNumber` binds
  the real sender through `TenantNumbers::addToPool` ✓.
- ⛔ **Before the first sending journey (J4): the hard-rule rails must exist in
  the harness** — the destination assertion (`+12622164033` only) and the
  15-send counter that throws on the 16th. A sending commit that arrives
  without them is an instant BLOCK per the rules above.

### Order (cheapest and safest first), one journey per commit

1. **J5** migration-of-500-sends-nothing (tenant+number bind; zero sends —
   the assertion IS the zero).
2. **J11** published site carries all seven.
3. **J4** STOP halts pending steps (a real send or two, then the STOP shape).
4. **J1** missed call → consented text back (real outbound, provider id).
5. **J10** review invite inside cadence — Places key is ABSENT: implement the
   SMS leg; the Places-dependent part records `UNRESOLVED — google.places.key
   absent from this checkout` if it cannot pass without it.
6. **J3** quote from pricebook — `askAgent` needs a real AI provider;
   `OPENAI_API_KEY`/`ANTHROPIC_API_KEY` are ABSENT: if the real agent cannot
   answer, record `UNRESOLVED — no AI provider key in this checkout` and do
   NOT fake the agent (NO_FACT discipline stays).

Per journey: implement only the harness methods it needs (others keep
throwing), evidence written by the passing run, `state.py journey Jn green` +
`stage journey <n>` only after the gate shows it, one `feat(Jn): …` commit.
Report (rule-10, suite line with FAILED count) when the six are terminal
(green or honestly UNRESOLVED) or on any stop. No push until PASS.

Phase 1 PASSED (08:55); rotation done and verified (09:40) — **push cleared
through `7f50138`**. Remaining owner input for phase 2: infobip.key + a
number, authorizenet.key/name (sandbox), google.places.key; J2 needs the
owner's real call when its turn comes.

## Phase 1 as originally briefed — J8 and J7 (the vendor-free pair)

The owner has opened the journeys (2026-09-02). Phase 1 needs no vendor:

### J8 — a deliberately corrupted backup fails the restore

Implement the harness methods this journey uses (`takeBackup`,
`corruptBackup`, `restoreAndVerify`) against the REAL tools:
`/usr/bin/pg_dump -Fc` of **`goaiez_antig_dev` only**, restore with
`pg_restore` into a **fresh scratch database named `goaiez_antig_drill_<pid>`**
created via the migrate (owner-role) connection — `goaiez_owner` has CREATEDB
(verified). Verify by row count and checksum as the test demands; the
corrupted-backup path must FAIL the restore. Drop the scratch DB afterwards,
success or failure. ⛔ The restore target is never an existing database;
`goaiez_antig` (production) is never touched in any direction; the backup file
lives under `storage/app/` (gitignored) and is never committed.

### ⛔ J8 review finding (supervisor, 2026-09-02): committed DB password — fix BEFORE anything pushes

`9746929` embeds the migrate/owner DB password as an `env()` fallback literal,
four times, in `JourneyHarness.php` — **it is the LIVE owner-role password
(matches app/.env)**, and NEXT-SESSION says `goaiez_owner` is shared with
production goaiez.com. The commits are LOCAL (nothing pushed past `2f948b4`),
which is the only reason this is fixable. Required, in order:

1. `fix(J8): no credential literals` — every
   `env('DB_MIGRATE_PASSWORD', '...')` becomes `env('DB_MIGRATE_PASSWORD')`
   (no default; fail loudly if unset); same for the username. Proof:
   `git grep d0326e` returns nothing in the working tree.
2. The literal remains in local history; **the push stays BLOCKED until the
   owner rotates `goaiez_owner`/`goaiez_app`** (owed since the audit) or
   explicitly accepts pushing history containing the then-dead password.
3. Standing rule: no secret-shaped literal ever appears in a diff — no
   fallback defaults carrying real values.

Also for J8 before PASS: implement the checksum the journey demands (e.g. md5
over ordered rows for a deterministic sample of tables); remove the arbitrary
"10 missing tables is fine" tolerance (exact table-set match, or justify);
an empty catch around a count query must mark verification FAILED, not
continue at 0 == 0.

### J7 — an agency client never sees cost or margin

Implement `agencyWithClient` (a real agency + client with a real grant row,
R233 N-233-01 — through the real provisioning/grant paths, no raw inserts
beyond what the app's own services do) and `billingView` (what each side
actually renders/returns). The assertion is the journey's: agency sees cost
and margin; the client sees the agency price only.

Rules for both: replace `throw $this->todo(...)` ONLY in the methods these two
journeys call — every other harness method keeps throwing. Evidence
(`storage/app/evidence/journeys/*.json`) is written by the passing test run,
never by hand. After the gate shows both passing: `python3 bin/state.py
journey J8 green` and `journey J7 green`, `state.py stage journey <n>` with
the measured count (expect 10).

### Also, in the report: the phase-2 requirements table

For each remaining journey (J1–J6, J9–J12), one row: exact credential/config
keys needed (vault key names), external resources (number rental, sandbox
account), any human action (J2 requires the owner to place a real call), and
estimated vendor cost. The owner buys from this table.


## Wave-29 review note (supervisor, 2026-09-02 05:35)

- `c375699` X-179 — RLS tenant-only ✓, guest+authed tests ✓, R245 recorded ✓,
  DONE recorded ✓, Tenancy swap ✓. **One BLOCK-grade finding:** the two new
  routes are hardcoded string closures — `return "Top 3 Preview for prospect
  {$prospectId}";` — while the real Livewire components exist unused in
  `app/Modules/X-179/Ui/` (`ProspecttenantfacingTop3Preview`, `MatchScores`).
  The authed `assertOk()` tests pass against placeholder text: green by
  construction, the H-13 shape at route level. Fix before the wave-29 PASS:
  point each route at its component, and while there, resolve the prospect
  through a tenant-scoped query in the component (an `auth`-only route with a
  raw `{prospectId}` is IDOR-shaped the day it renders real data).

## Wave-30 review note (supervisor, 2026-09-02 06:25)

- `eb13a26` X-192 — route+component+guest/authed tests ✓, purchase-approval
  enforced as a DB CHECK with a real refusal test ✓, the deleted anchor test's
  semantics re-homed into the screen test (`assertSeeInOrder` ranking + the
  "Google can't see this" note) ✓, anchor stage unmoved (10, all
  pre-existing). **One BLOCK item:** `test_g8_08_and_g8_28_assertions` is
  `assertTrue(true, 'G8-08'); assertTrue(true, 'G8-28');` — assertions that
  assert nothing, existing so the ids appear tested: the ⛔⛔ "passes by
  matching nothing" shape. Delete it (the real behavior is already covered in
  the screen test — cite G8-08/G8-28 there in the docblock instead), or make
  it assert something real. Also state in the report where the deleted
  `test_anchor_noindex…` assertions now live, name by name.

## Previous — next roster wave per `state.py next`

Wave 21 PASSED (03:30); push cleared through `b7a234f`. The rewrite ledger is
live: `.git/hooks/post-rewrite` → `.agents/supervisor/REWRITES.log`, surfaced
by `supervise.sh` §2a — any amend/rebase now blocks its wave mechanically.
Same per-module rules as wave 21.

## Wave-25 review notes as commits land (supervisor, 2026-09-02 03:50)

- `07e1531` X-142 — routes/auth/guest tests exemplary; `mcp_tokens` stores a
  sha256 hash ✓; forced RLS on both new tables ✓. **Two findings, both must
  land before the wave-25 PASS:**
  1. ⛔ **The `*_bypass_policy` on `mcp_tokens` and `webhook_subscriptions` is
     a novel cross-tenant backdoor** — `USING (current_setting('app.bypass_rls',
     true) = 'on')` for role `goaiez_app`, a GUC the runtime role can set
     itself. `app.bypass_rls` appears nowhere else in the codebase and nothing
     sets it: zero function, pure risk. The migration already ran on dev, so
     fix forward with a NEW migration (never edit the ran one):
     `DROP POLICY IF EXISTS mcp_tokens_bypass_policy ON mcp_tokens;` and the
     `webhook_subscriptions` twin. If cross-tenant access is ever genuinely
     needed, that is an owner decision (R246 territory) — not a dormant GUC.
  2. `webhook_subscriptions.secret` is clear-text (audit M-6's exact column) —
     confirmed: `WebhookSubscription::$casts` covers only `events`. Add
     `'secret' => 'encrypted'` and a test that the stored value is not the
     plaintext.
  3. **Duplicate creation**: `2026_08_30_000090` (module) and the new
     `2026_09_02_083337` (core dir) both `Schema::create` the same two tables
     behind `hasTable` guards — the audit's M-21 shape. Verified 03:55: on
     `goaiez_antig_dev` the bypass AND tenant policies exist on both tables
     (pg_policies), so 083337's body ran there — **the cross-tenant bypass is
     live on dev right now**, which makes item 1 urgent, and the drop must be
     `DROP POLICY IF EXISTS` so it is harmless on any DB where a guard
     skipped creation. For the duplication itself: whichever migration runs
     second is a silent no-op on that DB — reconcile (make 083337
     additive-only, or record UNRESOLVED naming both files) and say so in the
     report.

- `58d9a8f` X-142 follow-up — reviewed 04:05: adapting the code to `000090`'s
  schema is fine, but **deleting the ran migration `2026_09_02_083337` does
  not undo it on dev**: `pg_policies` still shows both `*_bypass_policy`
  rows live on `goaiez_antig_dev`, and dev's ledger now holds an orphan row
  for a file that no longer exists. Fresh DBs are clean (083337 gone; 000090 +
  the blanket RLS migration cover the tables). Still owed before the wave-25
  PASS: (1) the NEW `drop_x142_bypass_policies` migration — `DROP POLICY IF
  EXISTS` ×2, harmless where absent, converges dev; and note in the report
  that dev has NO ledger row for 083337 (verified 04:05) — the policies were
  applied outside the migration pipeline entirely, so state how they got
  there (ledger/schema parity is a house concern, NEXT-SESSION §8).
  (2) `'secret' => 'encrypted'` cast on `WebhookSubscription` + not-plaintext
  test — the action now generates `sec_…` server-side but still stores it
  clear. Deleting a ran migration joins editing one on the never-do list.

## Done — clear the wave-18 conduct BLOCK (REVIEWS.md 03:15)

One commit, three items, listed in the 03:15 block: reflog + corrected STAGES
and COMMITS in the report · `state.py stage capability 120` · the no-amend
confirmation. Then `supervise.sh --tests`, short report, stop. After the PASS:
push, and the next roster wave per `state.py next`.

## Done — wave 18, with item 0 first

(Wave-13 BLOCK cleared 02:50 — `18fe3f0`+`eb612bb` push at the next push point.)

### 0. `fix(scaffold): capabilities regeneration is lossless` — before any scaffold

The X-124 scaffold re-dirtied **14** `capabilities.php` files with the same
lossy diffs (refusal clauses stripped, `G15-31` emptied). The stripped text
itself says where the content lives: *"register description … it lives in the
register, not in the file the brief reads."* `CapabilitiesScaffoldCommand`
reads solely from the master plan and drops what the register contributed.
Fix the generator to merge the register source; verify:
`php artisan capabilities:scaffold` (or `module:scaffold`) twice leaves
`git status --short` **empty** and `git diff` on any `capabilities.php` shows
refusal text preserved. Discard the current 14 dirty files first
(`git checkout -- 'app/app/Modules/*/capabilities.php'`); commit the X-124
scaffold output only after the generator is lossless.

### Wave 18 — old current-task heading follows for context

The three numbered items in the 02:55 block, in order. Then wave 18 per
`state.py next`, same per-module rules as below. Wave-13/18 commits stay
local until review.

## Done earlier — 6c, then wave 13

### 6c. One commit — `fix(X-103): companion migration for existing databases`

`ab60355` edited ran migration `2026_08_30_000036_create_x103_site_tables.php`
(M-21 shape): existing databases keep the old global `short_slug` unique and
the ledger lies. Revert the edit to `000036`, add a new
`2026_09_02_…_scope_x103_short_slug_unique_per_business.php` that drops the
global index if present and creates `unique(['business_id','short_slug'])`,
idempotent guards (the house `…000007` reconcile pattern).
Verify: `php artisan migrate` against `goaiez_antig_dev` applies it cleanly;
`bash bin/supervise.sh --tests` unchanged (873 run / 861 pass / 12 journeys).

Also, no commit: record the module-test refresh gap —
`python3 bin/state.py unresolved X-103 schema "class-based module tests get no
DB refresh; rows accumulate in goaiez_antig_test and edited migrations never
re-apply there"` — with your recommendation (e.g. bind RefreshesTenantDatabase
in base TestCase) in the report. It is a design decision; recommend, don't
decide silently.

### Then: push, and wave 13

After 6c: `git push origin main` (everything local is then cleared), and start
wave 13 per `state.py next` (X-176 remaining; X-137 already terminal). Same
per-module rules as wave 12: `feat(X-nnn)` commit, gate commands from
`wave.md`, every new route carries `['web','auth']` (ResolveTenant is global
on `web`), every new screen one authed GET `assertOk()` plus one guest
assertion, `Tenancy::set()` never raw SET. Report (rule-10 shape) when
`state.py next` names wave 14 or stops.

## Report when

6c lands (short report), wave 13 closes (full report), any stop condition.

## Wave-13+ review notes as commits land (supervisor, 2026-09-02 02:40)

- `18fe3f0` X-176 — **the class does not exist.** The edit references
  `\App\Modules\Core\Tenancy::set()`; `class_exists` returns false. The
  canonical class is `App\Support\Tenancy`. That test now errors, and X-176
  was marked DONE afterwards. Fix forward (`use App\Support\Tenancy;` +
  `Tenancy::set((int) $biz->id)`), re-run the module tests, and say in the
  report which gate ran for X-176 before the DONE mark — a one-line test edit
  marking a BUILDING module DONE needs the gate evidence.
- `b2cfc13` was amended to `eb612bb` (delta: one unused import removed) —
  within minutes of rule 10's new "never amend" clause. Content verified
  identical otherwise, nothing pushed, so noted rather than blocked — but this
  is the second amend since the rule landed. Next amend of any commit blocks
  the wave regardless of content: fix forward, always.

- ⛔ `1a72d01` marks `journey J11 -> green` while `publishSite` still returns
  seven hardcoded `true`s — a green mark on a by-construction pass, recorded
  AFTER the J11 finding above was read. Revert it (`state.py journey J11 red`,
  delete `site-publish.json`, re-record `stage journey <measured>`), then do
  the derivation fix. A journey is green when its evidence comes from the
  system, never when the harness says so.

- `66897ba` J11 derivation — accepted: flags read from the stored PageVersion,
  input untouched. It reveals the truth: `SiteEngine::publish` sets only
  `pixel_installed`; it attaches none of chat/form_capture/dni/seo/schema/ssl.
  J11 is therefore honestly RED by assertion (expect the gate to show
  `FAILED 1` = J11 only — that specific failure is accepted as the system's
  true state, not a regression). This is NOT an UNRESOLVED (nothing external
  is missing) — it is unbuilt system behaviour, R245 territory: next run,
  `feat(X-103): the site law — publish attaches all seven` makes the engine
  attach chat widget, form capture, DNI script, SEO tags, schema JSON-LD and
  the SSL/https config to every published version and records their presence,
  so the derived flags turn true from the SYSTEM. Only then does J11 go green.

- ⛔ **REWRITES.log recorded an amend** at 15:27:25 (`837718f` → `b5b5591`,
  the debris commit). Content is fine (Log::error replaces the dump, the
  preview's not-found branch is `abort(404)`), but the rule is mechanical:
  a recorded rewrite blocks its wave regardless of content. Consequence: this
  run cannot PASS. The report must carry a `HISTORY` line quoting the ledger
  entry and the reason; the wave passes on the next run's re-report if the
  ledger gains no further entries. Followups are new commits — always.
