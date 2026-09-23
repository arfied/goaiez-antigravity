#!/usr/bin/env bash
#
# goaiez-status.sh — WHERE ARE WE? One screen, five seconds, no agent needed.
#
# ⭐⭐⭐ WHY THIS EXISTS:
#
# The owner: "it's running in the background, no idea what it's doing, context
# window outgrew it."
#
# An agent's context overflowing should not cost you visibility. The plan, the
# trackers and doctor ARE the memory — this reads them directly, so you can see
# the state of the build without asking anything that has to remember.
#
#   Usage:  bash goaiez-status.sh /path/to/laravel/tree
#
set -uo pipefail          # ⛔ NOT -e: a failing section must not hide the rest
DEST="${1:-$(pwd)}"
cd "$DEST" 2>/dev/null || { echo "cannot cd to $DEST"; exit 1; }

hr()  { printf '\n\033[1m%s\033[0m\n' "$*"; }
row() { printf '  %-34s %s\n' "$1" "$2"; }

hr "GOAIEZ STATUS · $(date -u +%H:%M:%SZ)"

hr "1. IS ANYTHING RUNNING RIGHT NOW"
W=$(ps aux 2>/dev/null | grep '[q]ueue:work' | grep -vc goaiez-status || true)
A=$(ps aux 2>/dev/null | grep '[a]rtisan' | grep -v 'queue:work' | grep -vc goaiez-status || true)
row "queue:work processes" "${W:-0}"
row "other artisan processes" "${A:-0}"
if [ "${W:-0}" != "0" ]; then
  echo "     ⭐ a worker IS running — queued jobs will execute"
else
  echo "     ⛔ NO worker — queued jobs are written to a table and never run"
fi

hr "2. WHICH BUILD IS INSTALLED"
grep -o "BUILD = '[0-9-]*'" app/Console/Commands/DoctorCommand.php 2>/dev/null \
  || echo "  ⛔ DoctorCommand not found — the runtime is not installed"

hr "3. THE QUEUE — what the platform thinks it has done"
php artisan tinker --execute="
  try { echo '  pending jobs : '.DB::table('jobs')->count().PHP_EOL; } catch (\Throwable \$e) { echo '  no jobs table'.PHP_EOL; }
  try { echo '  failed jobs  : '.DB::table('failed_jobs')->count().PHP_EOL; } catch (\Throwable \$e) { echo '  no failed_jobs table'.PHP_EOL; }
" 2>/dev/null || echo "  tinker unavailable"

hr "4. THE SPEC LAYER"
row "modules in the plan" "$(grep -oE '@module [*]{0,2}(X|C)-[A-Za-z0-9]+' GOAIEZ-MASTER-PLAN.md 2>/dev/null | grep -v 'X-nnn' | sort -u | wc -l)"
row "manifests on disk" "$(find app/Modules -name manifest.php 2>/dev/null | wc -l)"
row "capability files" "$(find app/Modules -name capabilities.php 2>/dev/null | wc -l)"
row "GOAIEZ-*.md in the root" "$(ls GOAIEZ-*.md 2>/dev/null | wc -l)"

hr "5. DOCTOR — the seven stages"
timeout 120 php artisan doctor 2>&1 | grep -E "^\s+(ok|FAIL)|violation\(s\)\.$" || echo "  ⛔ doctor did not complete"

hr "6. BATCH 1 PROGRESS"
for m in X-126 X-119 X-188; do
  files=$(find "app/Modules/$m" -type f 2>/dev/null | wc -l)
  proof=$([ -f "storage/app/evidence/$m/runtime-proof.json" ] && echo "YES" || echo "no")
  row "$m" "$files file(s) · runtime proof: $proof"
done

hr "7. WHAT TO DO NEXT"
cat <<'NEXT'
  If doctor completed and contract is near zero:
      → build X-126. See GOAIEZ-BUILD-X-126.md

  If a number looks wrong:
      → php artisan doctor --stage=<name> | head -40
        and paste it RAW. Never summarise a doctor number.

  If the worker is not running:
      → php artisan queue:work --stop-when-empty
        (drains and EXITS — do NOT run it bare, it blocks the shell forever)
NEXT
