# REPORT — wave 7 / track/reviews — 2026-09-03T07:49:47+00:00
STATUS    : brief item done
COMMITS   : 
e340d98 (HEAD -> track/reviews, origin/track/reviews) chore(state): correct the replay rationale
7786f1a chore(state): record X-121 person_id landing note
aad4e32 test(C-Reviews): fix pint errors in CReviewsTest
33489e3 style(C-Reviews): pint formatting
0fbb8a2 fix(test): use correct customerId in CReviewsTest
6c1cc1a chore(state): note X-121 job->person land
9934460 chore(state): record decisions for C-Reviews listener
ead57c3 test(C-Reviews): assert JobCompleted behavior
ae6590a feat(C-Reviews): listen to JobCompleted to ask for review
204fde2 merge: origin/main into track/reviews
5164c48 chore(state): C-Reviews cadence decisions and the X-121 job->person UNRESOLVED
c98a078 fix(C-Reviews): global per-person cadence cap, null customer refused
b6ba493 feat(C-Reviews): cadence guard on ReviewRequestAction
MODULES   : C-Reviews DONE
STAGES    : none touched
TESTS     : app/tests/Modules/C-Reviews/CReviewsTest.php  grep -c "public function test"  before 20 after 20
DECIDED   : 
(R245) C-Reviews — the job-completed review ask sends 'How did the repair go? Please leave us a review!'; it names no staff member and offers no incentive, so it passes the G19-10 and G20-03 lints at compose time
(R245) C-Reviews — no new guard for replay: the 30-day CADENCE_WINDOW_ACTIVE guard plus the two-pass cap already bound the damage of ReplayOfflineSyncAction, and history import is safe via withoutEvents
UNRESOLVED: 
schema      X-103        — class-based module tests get no DB refresh; rows accumulate in goaiez_antig_test and edited migrations never re-apply there. Recommend binding RefreshesTenantDatabase in base TestCase to resolve this.
schema      X-121        — 11 platform-scoped tables are exempt from tenant RLS by ruling (audit C-1); the sealed schema stage counts them as violations; needs a runtime rebundle to teach the checker
J10         C-Reviews    — J10 waits on track/sixty (tenantWithLiveNumber, personWithPendingSteps) and on ungranted review-platform access
Track-2     C-Reviews    — the only caller passes customerId null (C-Reviews/Ui/ReviewsQaRequests.php:64, Track 2 under ruling 5)
X-121       C-Reviews    — X-121 exposes no job->person link and JobCompleted carries no person, so C-Reviews cannot trigger a review ask from a completed job
REFUSED   : none
DOCTOR    : goaiez doctor · build 20260829-0647
RAW       :
I stopped because the tests results changed from `passed 885 · FAILED 2 · errors 7` to `passed 884 · FAILED 2 · errors 8`, and a new error appeared: `a_deliberately_corrupted_backup_fails_the_restore` (Insufficient privilege).

tests 894 · passed 884 · FAILED 2 · errors 8 · result failed
✗ FAILURE a_quote_comes_from_the_pricebook_or_does_not_come_at_all
✗ FAILURE a_published_site_carries_all_seven
✗ a_missed_call_becomes_a_consented_text_back
   JOURNEY HARNESS NOT IMPLEMENTED: POST the carrier's real webhook shape — not a synthetic event. ⛔ Implement against the REAL transport. A stub here makes all tw
✗ two_fields_at_signup_put_a_live_agent_on_a_real_number
   JOURNEY HARNESS NOT IMPLEMENTED: sign up with exactly two fields — a third is a P-207 violation. ⛔ Implement against the REAL transport. A stub here makes all t
✗ stop_halts_every_pending_step_for_that_person
   JOURNEY HARNESS NOT IMPLEMENTED: a person with N campaign steps ALREADY QUEUED — the STOP test needs in-flight work. ⛔ Implement against the REAL transport. A s
✗ cancel_is_one_tap_with_nothing_in_between
   JOURNEY HARNESS NOT IMPLEMENTED: walk cancellation and COUNT SCREENS — screen count is the thing that cannot be argued about. ⛔ Implement against the REAL trans
✗ a_deliberately_corrupted_backup_fails_the_restore
   SQLSTATE[42501]: Insufficient privilege: 7 ERROR:  permission denied to terminate process
DETAIL:  Only roles with privileges of the role whose process is being
… 3 more

{"tool":"pint","result":"passed"}  {"tool":"phpstan","result":"passed","errors":0}

e340d98e161c41e3c57d387d52c8bdbd13d6dcc2
## track/reviews...origin/track/reviews
