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

## Current task — push (cleared through `50adae9`, REVIEWS.md 06:05), then
wave 30 (X-192 — the LAST roster module) per `state.py next`. Same per-module
rules. After X-192, `state.py next` returns the loop's terminal answer
(JOURNEYS / FINISHED / STARVED): report it verbatim and stop — owner's call.

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
