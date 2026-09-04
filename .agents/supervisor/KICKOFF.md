You are the coder in a supervised arrangement (TRACK 2 — UI, worktree
/home/goaiez/agents/grs-antig-ui, branch track/ui). Read
.agents/rules/10-supervisor.md, then .agents/supervisor/BRIEF.md (UI-28 fix) IN
FULL, then the NEWEST block of .agents/supervisor/REVIEWS.md (the 17:3x "BLOCK —
run 47" block) IN FULL.

READ THIS BEFORE ANYTHING ELSE. This is the FIX run for run 47 and it is
dispatch 2 of 2. Run 47 built three real screens and then died in the foreground
waiting for a test run, leaving NO REPORT.md, its blade fix uncommitted, the
mailbox untrack unfinished, and one previously-green test red. Nothing below
asks you to rebuild a screen.

push: OPEN ONCE, at the very END of the run, as the LAST command of item 6 —
after items 1 through 5 are committed. origin/track/ui is d7fa6382. A push
before item 6 is a BLOCK. A second push is a BLOCK.

Run 46's cap is spent: do NOT touch X-124, X-199 or X-110 except for the single
setup line item 2 names, and do not touch app/tests/Modules/X-110/TodayTest.php
or app/tests/Modules/X-124/TodaysRecommendationStripTest.php at all.

Execute BRIEF items 1 through 9 literally and in order.

1. COMMIT THE TWO BLADE FIXES YOU ALREADY MADE — first, before anything else.
   git status shows app/app/Modules/X-01/Ui/views/customers-list.blade.php and
   views/person.blade.php modified and uncommitted. Those edits are CORRECT and
   HEAD is broken without them: lead_scores.score was renamed to lead_rating by
   app/app/Modules/X-121/Database/migrations/2026_08_31_000003_rename_ranking_columns_to_fact_attributes.php:16,
   so the committed ->score does not exist; and
   app/resources/views/components/ui/status-pill.blade.php:31 renders $label and
   IGNORES its slot, so the committed pill would read "Ok" rather than a score.
   Read them, satisfy yourself, and commit them AS THEY STAND:
     git commit -m "fix(X-01): read lead_rating and pass the score as the pill's label" -- app/app/Modules/X-01/Ui/views/customers-list.blade.php app/app/Modules/X-01/Ui/views/person.blade.php
   Verify in RAW: git status --porcelain -- app/app/Modules/X-01/Ui/views/
   prints nothing.

2. test_money_paid_today IS RED — DIAGNOSE, THEN FIX AT THE SETUP. My
   measurement at 1928f095: 895 · passed 884 · FAILED 0 · errors 11. Baseline at
   d7fa6382 was 892 · passed 882 · FAILED 0 · errors 10. Your three tests all
   pass; one previously-green test broke:
     ✗ test_money_paid_today
       SQLSTATE[23503]: Foreign key violation: 7 ERROR: insert or update on
       table "invoices" violates foreign key constraint
       "invoices_customer_id_foreign"
   app/tests/Modules/X-199/MoneyPaidTodayTest.php:27 hardcodes
   'customer_id' => 1 and has been passing on a customers row with id 1 that
   happened to sit in goaiez_antig_ui_test, not on anything it seeds. Diagnose
   with ONE php artisan tinker --execute (NEVER tinker <file> — it hangs on
   stdin) and paste the output in RAW; say in DECIDED whether run 47's code or
   DB state caused it. Then fix at the SETUP: create a real customer and use its
   id. CHANGE ONLY THE SETUP — do not touch one assertion in that file; run 46's
   two evidence gaps in it are the owner's. NEVER re-run until green.

3. MUTATION PROOF, ONE PER SCREEN — the item runs 45 and 47 both skipped. All
   three screens are committed so it is safe. Mutate ONE SYSTEM file (the query,
   the filter, the grouping), NEVER the test and NEVER a caption; run that one
   test alone with --filter; quote its RED line VERBATIM in REPORT.md under the
   mutation that produced it; then git checkout-index -f -- <file>. The RED line
   must name a NUMBER or a MISSING ROW, not a label.
     CustomersList: drop ->where('business_id', $this->businessId) from the
       Person query.
     Person: return an empty collection from the Message::whereIn(...) query.
     Calendar: drop whereDate('start_time', $selectedDate) from the day query.
   Three mutations, three RED lines, or this run is a BLOCK again.

4. TWO ACTIONS ARE CALLED AND NOTHING IS ASSERTED AFTER THEM.
   CustomersList::readConversation() assigns $action->handle(...) to a local and
   discards it — assert its effect in CustomersListTest, or say in DECIDED why
   it has no observable effect worth asserting. CalendarTest:60-62 calls
   cancelAppointment and its own comment says "let's just assert nothing
   crashed" — assert the appointment's status after the call, or that the row
   leaves the day list. Also fix CustomersListTest.php:34 and :37, which both
   set 'lead_rating' => 95 in one array literal. Commit as
   "test(UI-28): assert what the row actions actually do", paths named.

5. PINT AND THE DUPLICATE FACTORIES. pint --test fails on all eight of run 47's
   files: X-01/Ui/CustomersList.php, X-01/Ui/Person.php, X-108/Ui/Calendar.php,
   database/factories/AppointmentFactory.php,
   database/factories/Modules/X108/AppointmentFactory.php and the three test
   files. Fix and commit as "style: pint". Separately,
   app/database/factories/Modules/X108/AppointmentFactory.php and
   Modules/X121/PersonFactory.php are UNTRACKED and duplicate the committed
   app/database/factories/AppointmentFactory.php / PersonFactory.php; no test
   uses either pair. Pick ONE location and commit it, or delete both and commit
   the removal of the committed pair. Say which in DECIDED.

6. THE MAILBOX UNTRACK, AND THEN THE ONE PUSH — run 47's unfinished item 0. You
   edited .gitignore and never committed it, then pushed d7fa6382 WITHOUT the
   untrack commit, so origin/track/ui is now exactly the tip Track 1 says
   destroys its mailbox on merge. The two rules are already in your working
   .gitignore — check with grep -n agents .gitignore and add them only if gone:
     .agents/supervisor/*
     !.agents/supervisor/launch-coder.sh
   Then, AFTER items 1-5 are committed:
     git rm --cached -q -- .agents/supervisor/BRIEF.md .agents/supervisor/REVIEWS.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md .agents/supervisor/REWRITES.log
     git commit -m "chore(supervisor): untrack the mailbox" -- .gitignore .agents/supervisor/BRIEF.md .agents/supervisor/REVIEWS.md .agents/supervisor/KICKOFF.md .agents/supervisor/REPORT.md .agents/supervisor/REWRITES.log
     git push -u origin track/ui
   git rm --cached, NEVER git rm — all five .md files must still be on disk
   afterwards. This is the ONE explicit exception to "a commit touching
   .agents/supervisor is a BLOCK": it removes paths from the index and changes
   no file's contents. If you are about to EDIT any supervisor file other than
   REPORT.md, stop. Verify and paste all four in RAW: git ls-files
   .agents/supervisor (must print launch-coder.sh or nothing — it is untracked
   here, so nothing is correct); ls -la .agents/supervisor/ (all five .md still
   there); git show --stat HEAD; git log --oneline origin/track/ui..HEAD (empty
   after the push). A refused push is UNRESOLVED with the refusal quoted
   verbatim — NEVER retry with --no-verify.

7. CAPTURES. node scripts/ui-shots.mjs --only='account-customers.*' — --only is
   mandatory, one rig process at a time. Corpus is 155 PNGs and 155 axe JSONs;
   ls app/storage/app/ui-review/*.png | wc -l before and after, both in RAW; if
   it drops, say so plainly. Commit everything first, then npm run build if any
   CSS class changed, then capture, and prove the ordering with
   git log -1 --format='%h %ad' --date=iso,
   stat -c '%y' app/public/build/manifest.json and
   stat -c '%y' app/storage/app/ui-review/account-customers.png — every capture
   mtime later than the commit and later than the manifest. I OPENED RUN 47's
   TWO CAPTURES MYSELF: they are the pre-existing App\Livewire\Account\Customers
   directory, healthy and unregressed, and they contain NOTHING OF YOURS. The
   rig's targets account-customers and account-customer-profile
   (scripts/ui-shots.mjs:302, :704) are the existing routed screens. DO NOT
   INVENT A ROUTE to get a screenshot — OWNER.md:11 and :23 forbid hand-written
   routes; mounting is generated on Track 1 by php artisan surfaces:generate and
   arrives with the next merge. If the rig cannot reach your three screens,
   write ONE PLAIN SENTENCE in REPORT.md saying so, name the app/routes/web.php
   line you checked, and put it in UNRESOLVED — that is an acceptable answer.
   Passing off somebody else's screen as evidence is not. Recapture
   account-customers regardless and paste both axe rows from
   app/storage/app/ui-review/axe/SUMMARY.txt; critical and serious stay zero.
   Then open any PNG you produced and say what you see: text on same-tone
   background, clipped or overlapping text, empty where content is expected, raw
   translation keys, Laravel/placeholder copy, wrong shell, error page, tap
   targets under 40px, missing app fonts.

8. THE REPORT — run 47 did not write one and that alone was a BLOCK.
   .agents/supervisor/REPORT.md, overwritten whole, rule 10's shape, heading
   exactly "# REPORT — UI-28 fix / Track 2 (UI) — <date -Is>", then STATUS,
   COMMITS, MODULES, STAGES, TESTS, DECIDED, UNRESOLVED, REFUSED, DOCTOR, RAW.
   Every key present, "none" if empty. WRITE IT EVEN IF YOU RUN OUT OF ROOM TO
   FINISH AN ITEM — a half-done run with an honest report is reviewable; a
   finished run with no report is not.
   DECIDED must carry: item 2's diagnosis (code or DB state); the people vs
   customers answer run 47 never wrote down, in three sentences (does anything
   join the two stores; do they hold the same contacts; is there a screen that
   already shows people rows); whether you narrowed or knowingly kept the
   blanket catch (Throwable) { $this->failed = true; } in the three render()
   methods; which factory location you kept.
   UNRESOLVED must carry: the two contact stores with file:line for both; any
   screen the rig cannot reach with the app/routes/web.php line you checked;
   anything a factory needed that the schema lacks; config/features.php.
   RAW, each under its own command line: item 1's git status --porcelain; item
   2's tinker output and re-run result; ALL THREE MUTATION RED LINES VERBATIM;
   item 6's four verifications; git show --stat per commit; the
   grep -c 'test(\|it(\|function test_' pairs; the corpus counts; item 7's three
   timestamps; the two axe rows; and bash bin/supervise.sh --tests's §7 line
   verbatim.
   Baseline measured by me at 1928f095 plus the uncommitted blades: tests 895 ·
   passed 884 · FAILED 0 · errors 11, corpus 155, phpstan passed 0 errors, pint
   FAIL on eight files, stages integrity 0 · boundary 2 · contract 102 ·
   citation 0 · schema 13 · capability 120 · anchor 10 · journey 12. Ten of the
   eleven errors are Track 1's journey placeholders; the eleventh is item 2.
   When you are done I expect errors 10 and pint passing. supervise.sh's closing
   "⛔ a gate failed" line is expected and not yours (§2 flags app/phpunit.xml in
   df4e214, §2a holds two owner-only amends). Do not touch REWRITES.log.

9. LEAVE NOTHING IN THE FOREGROUND AND NO DEBRIS. No tail -f, no php artisan
   serve by hand, no npm run dev — the rig starts and stops its own server.
   Never php artisan tinker <file>; use --execute. No command that waits. RUN 47
   DIED IN THE FOREGROUND WAITING FOR A TEST RUN AND LOST ITS WHOLE REPORT; do
   not repeat that. Scratch goes in /home/goaiez/tmp, never in the worktree.
   .agents/supervisor/.tick-gate.txt and .tick-tests.txt are the supervisor's —
   leave them. Your closing message is ONE LINE: the path you wrote and its line
   count. Then STOP. Do not start UI-29.

HARD RULES. This worktree only. DB_DATABASE stays goaiez_antig_ui (_ui_test for
pest) — goaiez_antig is PRODUCTION and goaiez_antig_dev/goaiez_antig_test are
Track 1's; NEVER edit app/phpunit.xml or .env's DB_ lines. Never edit or
stash/checkout/clean supervisor files other than REPORT.md, which is yours;
never edit BRIEF.md or REVIEWS.md; never amend/reset/rebase; never git clean,
never stash; no dump()/dd(). One concern per commit, paths named, always
git commit -m "…" -- <paths>, NEVER -a, NEVER git add -A. A commit that touches
.agents/supervisor, CLAUDE.md, .claude or bin is a BLOCK — item 6 is the single
explicit git rm --cached-only exception and it changes no file's contents. Never
touch app/app/Doctor, seals.json, tests/Journeys/JourneyHarness.php, another
module's manifest.php by hand, or any notPath()/exclusion; if a brief item would
require changing a CHECK, REFUSE it and say so in REFUSED — that refusal stands
and I will not overrule it. Anything needing a migration, an X-121 noun-table
change, a Doctor/seals/harness change, or a screen outside this lane is
UNRESOLVED with file:line and a note for Track 1; UNRESOLVED names a MISSING
DEPENDENCY, not an unmade decision. Never pipe php artisan test — a hook refuses
the WHOLE compound command. Write files in their own tool call.
