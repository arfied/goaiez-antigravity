import subprocess

commits = subprocess.check_output(['cat', 'commits.txt']).decode('utf-8')

report = f"""**GATE**
  tests 2593 · passed 2590 · FAILED 0 · errors 3 · result failed
-rw-r--r-- 1 goaiez goaiez 8063 2026-09-17 19:43:47.366924828 -0500 .agents/supervisor/.gate-w511.txt
Errors to report:
- a_missed_call_becomes_a_consented_text_back
- two_fields_at_signup_put_a_live_agent_on_a_real_number
- a_completed_job_asks_for_a_review_once_inside_the_cadence (Authorize.Net request failed: E00017)

**PINT**
  283a1cc3 style(Architecture): pint after the wave-510 resolution
  {{"tool":"pint","result":"passed"}}

**PHPSTAN**
  {{"tool":"phpstan","result":"passed","errors":0}}

**DOCTOR**
Pre:
38: FAIL contract 28ms 53 violation(s) — fails the COMMIT
140: fix: one event, one emitter — the module that gave the capability away must stop declaring it
Post:
38: FAIL contract 29ms 53 violation(s) — fails the COMMIT
140: fix: one event, one emitter — the module that gave the capability away must stop declaring it

**CONFLICTS**
- app/app/Support/Account/OwnerNav.php (UU): Kept both sides' lines, our side first then theirs. OwnerNav label grep: 3.
- app/tests/Feature/Architecture/HeadingSeamTest.php (UU): Took incoming side.
- app/tests/Feature/Architecture/OwnerNavTest.php (UU): Took incoming side.
- app/tests/Feature/Architecture/SampleStateModuleTest.php (UU): Took incoming side.
- app/app/Console/DemoFill/Registry.php: auto-merged, grep new Fillers: 78.
- modules count: 110.

**RESTORE**
Paths restored:
- app/phpunit.xml
- bin/supervise.sh
- .agents/rules/10-supervisor.md
- .agents/state/BUILD-STATE.json
- .agents/state/JOURNAL.md
- .agents/supervisor/launch-coder.sh
- CLAUDE.md

Empty diff: (no output)
Stat: 13 files changed, 176 insertions(+), 41 deletions(-)

**CLASSMAP**
Control (X160Filler): 1
Before (X119Filler): 0
After (X119Filler): 1
-rw-r--r-- 1 goaiez goaiez 1927266 2026-09-17 19:37:38.167323551 -0500 app/vendor/composer/autoload_classmap.php

**PINS**
HeadingSeamTest: 180.179.1 -> 182.181.1
Identity: 181 (seam) + 1 (own) = 182 (total)

OwnerNavTest: 80.10.70.50.20 -> 78.10.68.48.20
Identity: 10 (withLayout) + 68 (withoutLayout) = 78 (invisible)
Identity: 48 (unbuilt) + 20 (built) = 68 (withoutLayout)

SampleStateModuleTest: 66.11.55 -> 64.11.53
Identity: 11 (legal) + 53 (illegal) = 64 (total)

w511-pins-2.txt result line:
{{"tool":"pest","result":"passed","tests":8,"passed":8,"assertions":641,"duration_ms":5495}}

**HARNESS**
Not run (harness gate closed for this run).

**COMMITS**
{commits}
"""

with open(".agents/supervisor/REPORT.md", "w") as f:
    f.write(report)
