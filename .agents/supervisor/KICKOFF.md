You are the coder in a supervised arrangement. Read AGENTS.md, then .agents/rules/10-supervisor.md, then .agents/supervisor/BRIEF.md and the NEWEST block of .agents/supervisor/REVIEWS.md.

State of play: brief items 1–5 are committed locally (20 commits ahead of origin/main). The supervisor reviewed item 5 at 12:15 in REVIEWS.md — verdict BLOCK. Your current task is brief item 5b: work the eight numbered BLOCK items in that 12:15 block, in order, one commit each. Then run `bash bin/supervise.sh --tests`; it must print pint passed, phpstan 0 errors, Feature failures 0, and only the twelve journey errors. Then write .agents/supervisor/REPORT.md in the exact shape rule 10 gives (COMMITS raw log, TESTS with grep -c before/after per test file, STAGES, DOCTOR stamp, REFUSED, and the supervise.sh verdict block pasted) and stop for review. Do not start wave 12 (item 6) until REVIEWS.md says PASS for item 5.

Hard rules for this session:
- git push origin main is allowed ONLY for the commits through 09e2660 (already cleared). Do not push anything after that until REVIEWS.md says PASS.
- Never point DB_DATABASE at goaiez_antig; never edit the DB_DATABASE lines in app/.env or app/phpunit.xml. Restore phpunit.xml memory_limit to 2048M (REVIEWS item 1b).
- Never write under /home/goaiez/public_html.
- Never edit BRIEF.md, REVIEWS.md, app/app/Doctor/**, or tests/Journeys/JourneyHarness.php except by whitespace.
- One concern per commit; the message says what it is. Never bury a fix in a style commit.
- Delete the stray scratch files (replace_script.sh, x121_tests.txt, error_log, app/error_log); never commit scratch.
- If a review item would change a CHECK rather than the SYSTEM, do not do it; list it under REFUSED in REPORT.md.
