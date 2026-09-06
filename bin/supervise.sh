#!/usr/bin/env bash
#
# The supervisor's read-only gate.  Changes nothing.  Safe at any time.
#
#   bash bin/supervise.sh                # guard · tree · state · integrity · pint · phpstan
#   bash bin/supervise.sh --tests        # + pest, against phpunit.xml's database
#   bash bin/supervise.sh --full-doctor  # + all eight doctor stages
#   bash bin/supervise.sh --tests --filter X-199   # narrow the suite (see 7b)
#
# Exit 2 = a database points at production. Exit 1 = a gate failed. Exit 0 =
# gates green, which is necessary and not sufficient: now read the diff.
#
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
ROOT="$PWD"; APP="$ROOT/app"
PROD_DB="goaiez_antig"
want_tests=0; want_doctor=0; pest_filter=""
while [ $# -gt 0 ]; do
  case "$1" in
    --tests) want_tests=1;;
    --full-doctor) want_doctor=1;;
    --filter) pest_filter="${2:-}"; shift;;
    --filter=*) pest_filter="${1#--filter=}";;
  esac
  shift
done
bar() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail=0

bar "0. database guard  (production is $PROD_DB — see NEXT-SESSION.md, 2026-08-31)"
env_db=$(grep -E '^DB_DATABASE=' "$APP/.env" 2>/dev/null | head -1 | cut -d= -f2- | tr -d "\"' ")
xml_db=$(grep -oE 'name="DB_DATABASE" value="[^"]*"' "$APP/phpunit.xml" 2>/dev/null | sed -E 's/.*value="([^"]*)"/\1/')
echo "  app/.env         DB_DATABASE=${env_db:-<unset>}"
echo "  app/phpunit.xml  DB_DATABASE=${xml_db:-<unset>}"
for db in "$env_db" "$xml_db"; do
  if [ "$db" = "$PROD_DB" ]; then
    echo "  ⛔ points at PRODUCTION. Stop. Nothing below may run."; exit 2
  fi
done
[ -z "$env_db" ] && echo "  ⚠ .env has no DB_DATABASE — anything reading config would use the framework default"

bar "1. working tree"
git status --short | head -40
echo "  $(git status --short | wc -l) uncommitted path(s)"
git log --oneline -5 | sed 's/^/  /'
git rev-list --left-right --count origin/main...HEAD 2>/dev/null \
  | awk '{print "  vs origin/main (local ref): behind " $1 ", ahead " $2 "  — refresh with: git fetch --no-write-fetch-head origin"}'

bar "2. forbidden paths touched  (uncommitted + last commit)"
touched=$( { git diff --name-only HEAD~1 HEAD 2>/dev/null; } | sort -u)
sup_edits=$(git diff --name-only HEAD -- .agents/supervisor CLAUDE.md bin/supervise.sh 2>/dev/null)
[ -n "$sup_edits" ] && printf '%s\n' "$sup_edits" | sed 's/^/  ℹ supervisor working notes (uncommitted — leave them alone): /'
touched=$(printf '%s\n%s' "$touched" "$(git diff --name-only HEAD | grep -vE '^(\.agents/supervisor/|CLAUDE\.md$|bin/supervise\.sh$)')" | sort -u | grep -v '^$')
pat='^app/app/Doctor/|seals\.json$|tests/Journeys/JourneyHarness\.php$|^app/Modules/[^/]+/(manifest|capabilities)\.php$|(^|/)\.env(\.|$)|^app/phpunit\.xml$|^source/|^runtime/|^bin/state\.py$|^\.agents/supervisor/(BRIEF|REVIEWS)\.md$'
hits=$(printf '%s\n' "$touched" | grep -E "$pat" || true)
if [ -n "$hits" ]; then
  printf '%s\n' "$hits" | sed 's/^/  ⛔ /'
  echo "  (manifest/capabilities are legal only via regeneration; supervisor files are legal only from the supervisor)"
  fail=1
else
  echo "  none"
fi

bar "2a. rewrite ledger (amends/rebases are recorded by the post-rewrite hook)"
if [ ! -x "$(git -C "$ROOT" rev-parse --git-path hooks)/post-rewrite" ]; then
  echo "  ⛔ post-rewrite hook is MISSING — its absence is a finding"; fail=1
elif [ -s "$ROOT/.agents/supervisor/REWRITES.log" ]; then
  tail -6 "$ROOT/.agents/supervisor/REWRITES.log" | sed 's/^/  ⛔ /'; fail=1
else
  echo "  empty — no history rewrites since the ledger began"
fi

bar "2b. php -l on every PHP file in that set"
bad=0
for f in $(printf '%s\n' "$touched" | grep -E '\.php$'); do
  [ -f "$ROOT/$f" ] || continue
  if ! php -l "$ROOT/$f" >/dev/null 2>&1; then echo "  ⛔ parse error: $f"; bad=1; fi
done
[ $bad -eq 0 ] && echo "  all parse" || fail=1

bar "3. build state"
python3 "$ROOT/bin/state.py" status 2>&1 | head -30 | sed 's/^/  /'
python3 "$ROOT/bin/state.py" next 2>&1 | head -20 | sed 's/^/  /'
echo "  journal tail:"; tail -6 "$ROOT/.agents/state/JOURNAL.md" | sed 's/^/    /'
echo "  mailbox:"
for f in BRIEF REPORT REVIEWS; do
  p="$ROOT/.agents/supervisor/$f.md"
  [ -f "$p" ] && printf '    %-10s %s  %4s lines\n' "$f.md" "$(date -r "$p" '+%F %T')" "$(wc -l <"$p")"
done

if [ ! -f "$APP/artisan" ]; then echo; echo "no app/artisan — nothing more to check"; exit $fail; fi
cd "$APP" || exit 1
[ -d /home/goaiez/tmp ] && export TMPDIR=/home/goaiez/tmp

bar "4. checker soundness + seal"
php artisan doctor:selftest 2>&1 | tail -4 | sed 's/^/  /' || { fail=1; echo "  ⛔ RUNTIME — the checker, not the code"; }
php artisan doctor --stage=integrity 2>&1 | tail -4 | sed 's/^/  /' || { fail=1; echo "  ⛔ SEAL/integrity red"; }
echo "  runtime_build in BUILD-STATE: $(python3 -c "import json;print(json.load(open('$ROOT/.agents/state/BUILD-STATE.json'))['runtime_build'])" 2>/dev/null) — compare with the doctor build stamp above"

if [ $want_doctor -eq 1 ]; then
  bar "5. all eight stages  (non-zero exit on any red stage is by design)"
  # `tail -30` alone drops the eight per-stage lines off the top — measured
  # 2026-09-06 04:4x, when the only stage numbers left in the output were
  # `integrity` and `journey`. The stage summary IS the thing being gated, so
  # pull those lines out by name first, then the tail for the detail.
  doc=$(php artisan doctor 2>&1)
  printf '%s\n' "$doc" | grep -E '^\s*(ok|FAIL|WARN)\s+\w+' | sed 's/^/  /'
  printf '%s\n' "$doc" | grep -E '[0-9]+ violation\(s\)\.' | sed 's/^/  /'
  echo "  --- detail (last 30 lines) ---"
  printf '%s\n' "$doc" | tail -30 | sed 's/^/  /'
fi

bar "6. style + static analysis"
./vendor/bin/pint --test 2>&1 | tail -3 | sed 's/^/  /' || fail=1
./vendor/bin/phpstan analyse --memory-limit=1G --no-progress 2>&1 | tail -4 | sed 's/^/  /' || fail=1

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (phpunit.xml → $xml_db)"

  # 7a. Refuse a SECOND suite against this one database.
  #
  # Run 67 (2026-09-06 02:5x) started a second `supervise.sh --tests` while the
  # first was still going; the two deadlocked on $xml_db and wrote nothing for
  # 76 minutes, while the coder's log said it was waiting on the shot rig. A
  # brief saying "one test process at a time" is not enough — the tool has to
  # refuse it.
  #
  # ⚠️ Scoped by /proc cwd, NEVER by name. Sibling tracks (grs-antig-sixty,
  # grs-antig-stages) run their own pest on this box and match every name
  # pattern there is; comparing the process's cwd to *this* $APP cannot reach
  # them. This only ever reports — killing anything is the owner's.
  # ⛔ NOT `pgrep -f 'vendor/bin/pest'`. Measured 2026-09-06 04:4x: that matched the
  # coder's own agy process, because the coder's command line IS the whole KICKOFF
  # and BRIEF, and this brief names `./vendor/bin/pest` in its own text. The gate
  # would then have refused every suite the coder ever tried to run. Three
  # conditions together, all cheap and none of them a name match:
  #   comm is php*  — excludes agy, node, timeout, bash, and any prompt text
  #   cwd is $APP   — excludes every sibling track
  #   cmdline names pest — excludes `php artisan` in this same checkout
  live=""
  for d in /proc/[0-9]*; do
    p=${d#/proc/}
    [ "$p" = "$$" ] && continue
    # ⚠️ `read … < "$d/comm" 2>/dev/null` does NOT silence this: bash reports a
    # failed *redirect* itself, before the command's stderr exists. A pid that
    # exits mid-scan then prints "No such file or directory" into the gate
    # output (seen 2026-09-06 08:1x). Test the file first.
    [ -r "$d/comm" ] || continue
    read -r comm < "$d/comm" || continue
    case "$comm" in php*) ;; *) continue ;; esac
    [ "$(readlink "$d/cwd" 2>/dev/null)" = "$APP" ] || continue
    grep -qa 'vendor/bin/pest' "$d/cmdline" 2>/dev/null && live="$live $p"
  done
  if [ -n "$live" ]; then
    echo "  ⛔ REFUSED — a pest is already running in this checkout against $xml_db:$live"
    echo "     Two suites on one database deadlock and write nothing (run 67, 2026-09-06)."
    echo "     Wait for it, or have the owner kill exactly those pids. ⛔ Never pkill -f pest:"
    echo "     sibling tracks match the same pattern. Their cwd is what identifies them."
    exit 1
  fi

  # 7b. A hard deadline. agy's own --print-timeout is COOPERATIVE and cannot
  # fire while it is blocked in a child that never returns — the run 67 shape.
  # Override for a wave that genuinely needs longer, declared in its brief.
  pest_timeout=${GOAIEZ_PEST_TIMEOUT:-30m}

  # ⛔ NEVER `out=$(timeout … pest)`. A command substitution only yields its
  # value when the child exits normally; when `timeout` kills pest, the shell
  # discards everything pest had already written and `$out` comes back EMPTY.
  # That is the whole of the "ZERO BYTES" epidemic — runs 67, 68 and 69 plus
  # three supervisor gates all read `rc=124/137/143 · 0 bytes` and were read as
  # a silent suite, when in fact the suite had been printing for thirty minutes
  # and the capture threw it away. Measured 2026-09-06 08:4x on 87027f75 with
  # nothing else on the box. Write to a file, then read the file back: the
  # bytes survive the kill, and the last line before the deadline names the
  # test that is actually slow.
  # ⚠️ Inside the checkout, NOT $TMPDIR. `/home/goaiez/tmp` is unreadable to the
  # supervisor session (ls/find/tail are all blocked there), so a log written
  # into it is a log nobody who needs it can open. Gitignored via .tick-*.
  pest_log="$ROOT/.agents/supervisor/.tick-pest.log"
  : > "$pest_log"
  if [ -n "$pest_filter" ]; then
    echo "  narrowed: --filter $pest_filter"
    timeout -k 30 "$pest_timeout" ./vendor/bin/pest --filter "$pest_filter" > "$pest_log" 2>&1; rc=$?
  else
    timeout -k 30 "$pest_timeout" ./vendor/bin/pest > "$pest_log" 2>&1; rc=$?
  fi
  out=$(cat "$pest_log")
  [ $rc -ne 0 ] && fail=1

  # 7c. Say the rc out loud, and name zero bytes for what it is rather than
  # letting a silent run read as a pass.
  bytes=$(wc -c < "$pest_log" | tr -d ' ')
  echo "  pest rc=$rc · ${bytes} bytes of output"
  if [ "$rc" -eq 124 ] || [ "$rc" -eq 137 ] || [ "$rc" -eq 143 ]; then
    echo "  ⛔ TIMED OUT after $pest_timeout (rc=$rc) — killed, not failed."
    echo "     Partial output survives in $pest_log — its last lines name the slow test."
    echo "     Narrow it: bash bin/supervise.sh --tests --filter <expr>"
  fi
  if [ "$bytes" -eq 0 ]; then
    echo "  ⛔ ZERO BYTES. Do not debug the code — narrow it with --filter and the real"
    echo "     exception appears immediately. Usual causes: memory_limit, a missing Vite"
    echo "     manifest (npm run build), or the run was killed from outside."
  fi

  if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · errors %s · result %s" % (d.get("tests"),d.get("passed"),d.get("errors"),d.get("result")))
for e in (d.get("error_details") or [])[:5]:
    print("   ✗ %s\n      %s" % (e.get("test","?").split("::")[-1], (e.get("message") or "")[:160]))
n=len(d.get("error_details") or [])
if n>5: print("   … %d more" % (n-5))'
    # A pest run can end `result failed` with `errors: None` — an ASSERTION that
    # did not hold is a failure, not an error, and carries no error_details. The
    # 08:0x version printed only the one-line summary there and said nothing
    # about which assertion, which is how run 69 reached review unmeasured.
    if printf '%s' "$out" | grep -q '^ *FAILED\|Failed asserting'; then
      echo "  --- failures ---"
      printf '%s\n' "$out" | grep -A4 'FAILED\|Failed asserting' | head -24 | sed 's/^/  /'
    fi
  else
    printf '%s\n' "$out" | tail -12 | sed 's/^/  /'
  fi
fi

bar "verdict"
if [ $fail -eq 0 ]; then
  echo "  gates green. Necessary, not sufficient — now read the diff and REPORT.md."
else
  echo "  ⛔ a gate failed above."
fi
exit $fail
