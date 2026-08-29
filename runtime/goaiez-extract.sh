#!/usr/bin/env bash
#
# goaiez-extract.sh — ONE command. Gathers everything, once.
#
# ⭐⭐⭐ WHY THIS EXISTS:
#
# I have asked for one number, then one sample, then forty lines, then one more
# command — turn after turn. Each answer produced another question. That is a
# loop, it wastes the owner's time, and it is my fault: I never asked for
# everything at once.
#
# This gathers EVERY fact I need to decide the sequence — the database, the code
# tree, what was recently done, and what doctor says — into ONE file.
#
#   Usage:
#     bash goaiez-extract.sh /path/to/laravel/tree
#
#   Then paste the contents of goaiez-extract.txt back. Nothing else.
#
set -uo pipefail          # ⭐ NOT -e: a failing section must not kill the report
DEST="${1:-$(pwd)}"
OUT="$DEST/goaiez-extract.txt"
cd "$DEST" || { echo "cannot cd to $DEST"; exit 1; }

exec > "$OUT" 2>&1

sec() { printf '\n═══ %s ═══\n' "$*"; }

echo "GOAIEZ EXTRACT · $(date -u +%Y-%m-%dT%H:%M:%SZ) · $DEST"

sec "1. ENVIRONMENT"
php -v 2>/dev/null | head -1
php artisan --version 2>/dev/null
echo "composer: $(command -v composer >/dev/null && composer --version 2>/dev/null | head -1 || echo 'phar or absent')"

sec "2. IS THE PLAN PRESENT"
ls -la GOAIEZ-*.md 2>/dev/null | awk '{print $5, $9}' || echo "NO GOAIEZ-*.md"
echo "--- master plan sha (first 16) ---"
sha256sum GOAIEZ-MASTER-PLAN.md 2>/dev/null | cut -c1-16 || echo "MASTER PLAN ABSENT"

sec "3. DATABASE TABLES — decides whether X-121 is a BUILD or a MIGRATION"
php artisan tinker --execute="
  \$t = DB::select(\"select tablename from pg_tables where schemaname='public' order by tablename\");
  echo 'count: '.count(\$t).PHP_EOL;
  foreach(\$t as \$r){ echo \$r->tablename.PHP_EOL; }
" 2>/dev/null || echo "TINKER FAILED — no db connection?"

sec "4. THE TWELVE CANONICAL NOUNS — which already exist"
php artisan tinker --execute="
  \$want=['businesses','people','conversations','messages','jobs','reviews','campaigns','assets','ledger_entries','facts','sites','numbers'];
  foreach(\$want as \$w){
    \$n = DB::select(\"select count(*) c from information_schema.tables where table_schema='public' and table_name=?\", [\$w]);
    echo str_pad(\$w,18).(\$n[0]->c ? 'EXISTS' : 'absent').PHP_EOL;
  }
" 2>/dev/null || echo "TINKER FAILED"

sec "5. CODE TREE — what is actually in app/"
for d in Models Services Jobs Http/Controllers Livewire Enums Console/Commands Modules Doctor Providers Mail Notifications Support; do
  printf '%-24s %s\n' "app/$d" "$(find "app/$d" -name '*.php' 2>/dev/null | wc -l)"
done

sec "6. app/Modules — the 131 vs 122 question"
echo "directories: $(ls app/Modules 2>/dev/null | wc -l)"
echo "manifests:   $(find app/Modules -name manifest.php 2>/dev/null | wc -l)"
echo "--- any NOT matching X-nnn / C-Name ---"
ls app/Modules 2>/dev/null | grep -vE '^(X-[0-9]+|C-[A-Za-z]+)$' || echo "(none — all well-formed)"

sec "7. WHAT WAS RECENTLY DONE"
git -C "$DEST" log --oneline -25 2>/dev/null || echo "not a git repo"
echo "--- files changed in the last 20 commits ---"
git -C "$DEST" log --name-only --pretty=format: -20 2>/dev/null | sort -u | grep -v '^$' | head -40

sec "8. MIGRATIONS"
echo "count: $(find database/migrations -name '*.php' 2>/dev/null | wc -l)"
ls -t database/migrations 2>/dev/null | head -15

sec "9. DOCTOR — all seven stages"
php artisan doctor 2>&1 | tail -20

sec "10. CONTRACT — the first 30, verbatim"
php artisan doctor --stage=contract 2>&1 | head -32

sec "11. CITATION — the first 15"
php artisan doctor --stage=citation 2>&1 | head -17

sec "12. SCHEMA — all 10"
php artisan doctor --stage=schema 2>&1 | head -24

sec "13. A REAL MANIFEST — X-121, verbatim"
cat app/Modules/X-121/manifest.php 2>/dev/null | head -40 || echo "X-121 manifest ABSENT"

sec "14. DOES brief WORK YET"
php artisan brief X-121 2>&1 | head -25

sec "15. EXISTING AI LAYER — R237/R238 need to know"
for f in app/Services/Ai app/Models/AiProviderConfig.php config/ai.php app/Enums/AiModel.php app/Enums/AiProvider.php; do
  printf '%-36s %s\n' "$f" "$([ -e "$f" ] && echo PRESENT || echo absent)"
done
echo "--- app/Services/Ai contents ---"
ls app/Services/Ai 2>/dev/null || echo "(absent)"

sec "16. ⛔ THE QUEUE — M-95's DEFECT, AND NOTHING ELSE REVEALS IT"
# M-95: "nothing runs queue:work; Laravel's sync driver hides it."
# On QUEUE_CONNECTION=sync every queued job runs INLINE, so every test passes,
# no worker is needed, and the platform's asynchrony is a fiction.
echo "QUEUE_CONNECTION = $(grep -E '^QUEUE_CONNECTION' .env 2>/dev/null || echo 'NOT SET — defaults to sync')"
echo "CACHE_STORE      = $(grep -E '^CACHE_STORE|^CACHE_DRIVER' .env 2>/dev/null || echo 'unset')"
echo "--- is a worker actually running? ---"
# ⛔ the bracket trick stops grep matching ITSELF, but the pipeline still
#   counts this script's own command line. Filter the script out by name.
W=$(ps aux 2>/dev/null | grep "[q]ueue:work" | grep -v "goaiez-extract" | wc -l | tr -d ' ')
echo "queue:work processes: $W"
if [ "$W" = "0" ]; then echo "  NO WORKER RUNNING — queued jobs will never execute"; fi
echo "--- jobs table + pending ---"
php artisan tinker --execute="
  try { echo 'jobs pending: '.DB::table('jobs')->count().PHP_EOL; } catch (\\Throwable \$e) { echo 'no jobs table'.PHP_EOL; }
  try { echo 'failed_jobs: '.DB::table('failed_jobs')->count().PHP_EOL; } catch (\\Throwable \$e) { echo 'no failed_jobs table'.PHP_EOL; }
" 2>/dev/null || echo "TINKER FAILED"
echo "--- scheduler ---"
crontab -l 2>/dev/null | grep -c "schedule:run" | sed 's/^/schedule:run cron entries: /'

sec "17. ⛔⛔⛔ COLUMNS OF THE CANONICAL NOUNS — build vs MIGRATION"
# A table named `businesses` with the wrong columns is still a migration.
php artisan tinker --execute="
  foreach (['businesses','people','conversations','messages','jobs','reviews'] as \$t) {
    \$c = DB::select(\"select column_name, data_type from information_schema.columns where table_schema='public' and table_name=? order by ordinal_position\", [\$t]);
    if (!\$c) { echo \$t.': ABSENT'.PHP_EOL; continue; }
    echo \$t.' ('.count(\$c).' cols): ';
    echo implode(', ', array_map(fn(\$x)=>\$x->column_name, array_slice(\$c,0,14)));
    echo count(\$c)>14 ? ' …'.PHP_EOL : PHP_EOL;
  }
" 2>/dev/null || echo "TINKER FAILED"

sec "18. FILE NAMES — not counts. These are what gets triaged."
for d in Services Jobs Livewire Enums; do
  echo "--- app/$d ---"
  find "app/$d" -name '*.php' 2>/dev/null | sed "s|app/$d/||" | sort | head -45
done

sec "19. THE EXISTING AI LAYER — verbatim, because R237 may EXTEND it"
echo "--- config/ai.php ---"
head -60 config/ai.php 2>/dev/null || echo "(absent)"
echo "--- app/Services/Ai/*.php: class + public methods ---"
for f in app/Services/Ai/*.php; do
  [ -f "$f" ] || continue
  echo "  $f"
  grep -E "^\s*(final |abstract )?class |public function " "$f" 2>/dev/null | head -12 | sed 's/^/     /'
done

sec "20. RENDER SURFACE — decides whether phase 5 is new work or wiring"
echo "routes:      $(find routes -name '*.php' 2>/dev/null | wc -l)"
ls routes 2>/dev/null
echo "blade views: $(find resources/views -name '*.blade.php' 2>/dev/null | wc -l)"
echo "livewire:    $(find app/Livewire -name '*.php' 2>/dev/null | wc -l)"

sec "21. DEPENDENCIES — R237 forbids a vendor SDK inside a module"
php -r '
  $c = json_decode(file_get_contents("composer.json"), true);
  foreach (["require","require-dev"] as $k) {
    echo "--- $k ---".PHP_EOL;
    foreach (($c[$k] ?? []) as $p => $v) { echo "  $p $v".PHP_EOL; }
  }
' 2>/dev/null || echo "composer.json unreadable"

sec "END"
echo "Paste this whole file back. Nothing else is needed."
