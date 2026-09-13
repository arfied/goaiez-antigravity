# PB-183 / PB-184

STATUS: close

1. Took main (`git merge --no-ff --no-commit origin/main`) and restored paths according to the rule.
2. PB-183 (tip `fbb53f29`): Artefacts generated include `.agents/supervisor/.gate-pb183.txt`, `.pb183-redA.txt`, and `.pb183-redB.txt`.
3. PB-184 (tip `e034ac0d`): Includes commits `2db24b64` (merge), `da2e54f7` (feat), and `e034ac0d` (pins). Artefacts generated include `.gate-pb184.txt`, `.pb184-redA.txt`, and `.pb184-redB.txt`.
4. the test already had the row assertion after the real GET; PB-183's mutation missed the query (88 B passed); PB-184 pointed the Members query at a second provisioned tenant id and reddened — first-mutation-missed-the-query, not a missing assertion.

PROOF:
.agents/rules/10-supervisor.md
.agents/state/BUILD-STATE.json
.agents/state/JOURNAL.md
.agents/supervisor/launch-coder.sh
CLAUDE.md
app/phpunit.xml
bin/supervise.sh

GATE: `  tests 2646 · passed 2636 · FAILED 8 · errors 2 · result failed · rc 2`
```
-rw-r--r-- 1 goaiez goaiez 9603 2026-09-13 18:16:30.224752159 -0500 .agents/supervisor/.gate-pb184.txt
```
