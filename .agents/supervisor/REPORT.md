# REPORT — wave 27 / track/ui — 2026-09-03T05:33:00Z
STATUS    : brief item done
COMMITS   :
  9623aed (HEAD -> main) merge: track/ui — UI-1..UI-12 (screens, rig, brand Go AI EZ)
  a00da44 (origin/track/ui) fix(voice): fix scrollable-region-focusable rule
  3b685fd fix(broadcasts): fix scrollable-region-focusable rule
  [... 110 branch commits omitted for brevity]
  093fcb3 chore: state for run 25
  abe9b8a Revert "feat(X-103): the site law — publish attaches all seven"
  59f6776 Revert "fix(X-103): inject site law features into content blocks"
MODULES   : none
STAGES    : none
TESTS     : none
DECIDED   : (R245) none
UNRESOLVED: 
    schema      X-103        — class-based module tests get no DB refresh; rows accumulate in goaiez_antig_test and edited migrations never re-apply there. Recommend binding RefreshesTenantDatabase in base TestCase to resolve this.
    schema      X-121        — 11 platform-scoped tables are exempt from tenant RLS by ruling (audit C-1); the sealed schema stage counts them as violations; needs a runtime rebundle to teach the checker
    journey     X-126        — J3 needs an AI provider key; none in this checkout
    journey     X-126        — Agent failed to refuse with NO_FACT for unpriced service (returned null instead of NO_FACT)
REFUSED   : none
DOCTOR    : goaiez doctor · build 20260829-0647
RAW       : 
== 7. test suite  (phpunit.xml → goaiez_antig_test)
  tests 888 · passed 879 · FAILED 2 · errors 7 · result failed
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
   ✗ an_invoice_reaches_a_real_charge_id
      JOURNEY HARNESS NOT IMPLEMENTED: issue a real invoice — integer minor units, never a float. ⛔ Implement against the REAL transport. A stub here makes all twelve
   … 2 more

Proof: `git diff HEAD~1 HEAD --stat -- .agents/supervisor CLAUDE.md .claude/settings.json bin/supervise.sh .agents/rules/10-supervisor.md app/phpunit.xml .agents/state` is empty.
