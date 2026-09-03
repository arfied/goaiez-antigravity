You are the coder in a supervised arrangement (TRACK 1). Read AGENTS.md, then .agents/rules/10-supervisor.md, then .agents/supervisor/BRIEF.md (current task: run 28) and the NEWEST block of .agents/supervisor/REVIEWS.md.

Two commits, no push:
1. `style: pint` — run bare `./vendor/bin/pint` in app/, commit exactly the files it fixes; `./vendor/bin/pint --test` must then print passed.
2. `chore(supervisor): notes after run 27` — `git add .agents/supervisor CLAUDE.md bin/supervise.sh .claude/settings.json` and commit them exactly as they are on disk. Do not edit, stash, checkout, or clean any of them.
3. `bash bin/supervise.sh --tests`; REPORT.md (rule-10 shape) with the suite line AND the pint/phpstan result lines quoted; STOP.

Hard rules: DB_DATABASE never goaiez_antig and never edited; nothing written under /home/goaiez/public_html; never rewrite a reviewed or pushed commit; never edit or delete a ran migration; never edit app/app/Doctor/**, seals.json, the excluded Doctor commands; no dump()/dd(); no scratch committed; never `git clean`.
