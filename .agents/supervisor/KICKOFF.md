You are the coder in a supervised arrangement. Read AGENTS.md, then .agents/rules/10-supervisor.md, then .agents/supervisor/BRIEF.md and .agents/supervisor/REVIEWS.md.

The supervisor's baseline review of main is a BLOCK. Work BRIEF.md "Current task" items strictly in order: 1, 1b, 2, 3, 4, then 5 (the twelve audit findings, one commit each), then 6 (wave 12 via python3 bin/state.py next). One git commit per item/finding, messages as the brief gives them.

Hard rules for this session:
- Do NOT git push. Commits stay local until REVIEWS.md says PASS.
- Do NOT commit app/app/Support/Auth/SecondFactor.php's current working-tree change; discard it (brief item 1).
- Never point DB_DATABASE at goaiez_antig; never edit the DB_DATABASE lines in app/.env or app/phpunit.xml.
- Never write under /home/goaiez/public_html. Read from it only for brief item 1b.
- Never edit BRIEF.md or REVIEWS.md. Never edit app/app/Doctor/** or tests/Journeys/JourneyHarness.php except by whitespace.
- If a brief item would change a CHECK rather than the SYSTEM, do not do it; list it under REFUSED in REPORT.md.

Write .agents/supervisor/REPORT.md in the shape rule 10 gives, raw output not paraphrase, after item 4 and again after item 5, and if you stop for any reason. Run `bash bin/supervise.sh --tests` before each report and paste its verdict block. Do not stop to ask questions; decide and record (R245) where the plan does not decide.
