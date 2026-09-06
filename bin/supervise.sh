#!/usr/bin/env bash
#
# The supervisor's read-only gate.  Changes nothing.  Safe at any time.
#
#   bash bin/supervise.sh                # guard · tree · state · integrity · pint · phpstan
#   bash bin/supervise.sh --tests        # + pest, against phpunit.xml's database
#   bash bin/supervise.sh --full-doctor  # + all eight doctor stages
#
# Exit 2 = a database points at production. Exit 1 = a gate failed. Exit 0 =
# gates green, which is necessary and not sufficient: now read the diff.
#
set -uo pipefail
cd "$(dirname "$0")/.." || exit 1
ROOT="$PWD"; APP="$ROOT/app"
PROD_DB="goaiez_antig"
want_tests=0; want_doctor=0
for a in "$@"; do case "$a" in --tests) want_tests=1;; --full-doctor) want_doctor=1;; esac; done
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
hits=$(printf '%s\n' "$touched" | grep -E "$pat" | grep -v '\.env\.example$' || true)
if [ -n "$hits" ]; then
  printf '%s\n' "$hits" | sed 's/^/  ⛔ /'
  echo "  (manifest/capabilities are legal only via regeneration; supervisor files are legal only from the supervisor)"
  fail=1
else
  echo "  none"
fi

bar "2a. rewrite ledger (amends/rebases are recorded by the post-rewrite hook)"
if [ ! -x "$ROOT/.git/hooks/post-rewrite" ]; then
  echo "  ⛔ post-rewrite hook is MISSING — its absence is a finding"; fail=1
elif [ -s "$ROOT/.agents/supervisor/REWRITES.log" ]; then
  SEEN="/home/goaiez/tmp/rewrites-seen-$(basename "$ROOT")"
  cur=$(md5sum "$ROOT/.agents/supervisor/REWRITES.log" | cut -d' ' -f1)
  if [ -f "$SEEN" ] && [ "$(cat "$SEEN")" = "$cur" ]; then
    echo "  ledger unchanged since last review ($(grep -c '^==' "$ROOT/.agents/supervisor/REWRITES.log") historical entries, already quoted)"
  else
    tail -6 "$ROOT/.agents/supervisor/REWRITES.log" | sed 's/^/  ⛔ NEW: /'; fail=1
    echo "$cur" > "$SEEN"
  fi
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

bar "2c. debug debris in app code (dump/dd/var_dump)"
dbg=$(grep -rnE '\b(dump|dd|var_dump)\(' "$APP/app" --include='*.php' 2>/dev/null | grep -vE ':[0-9]+:\s*(\*|//)' | grep -v '@allow-dump' | head -5)
if [ -n "$dbg" ]; then printf '%s\n' "$dbg" | sed 's/^/  ⛔ /'; fail=1; else echo "  none"; fi

bar "2d. shared coder guard parses  (/home/goaiez/agents/coder-bin/git — all seven lanes' git)"
GUARD=/home/goaiez/agents/coder-bin/git
# The guard is a supervisor-maintained file (owner grant in .claude/settings.json) and it is
# on every coder's PATH in every checkout. A syntax error in it does not fail closed — it
# breaks `git` itself for every lane at once. Check it on every gate; it costs nothing.
if [ ! -f "$GUARD" ]; then
  echo "  ⛔ MISSING — coder runs would get the real git with no guard at all"; fail=1
elif bash -n "$GUARD" 2>/tmp/guard-parse.$$; then
  echo "  parses · $(wc -l <"$GUARD") lines · md5 $(md5sum "$GUARD" | cut -c1-12)"
  rm -f /tmp/guard-parse.$$
else
  sed 's/^/  ⛔ /' /tmp/guard-parse.$$; rm -f /tmp/guard-parse.$$; fail=1
fi

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
  php artisan doctor 2>&1 | tail -30 | sed 's/^/  /'
fi

bar "6. style + static analysis"
# A KILLED TOOL IS NOT A FAILED TOOL (2026-09-06, from the sibling project's 13900).
# Piping straight into `tail` threw away the exit code and the shell's own report of
# the signal: their gate logged "Pint failed — push aborted" when Pint had been
# SIGTERMed, because a killed process prints a bare `Terminated` that no signal-9
# pattern matches. §7 below already reads rc for pest; §6 did not, and had the same
# hole. rc >= 124 is timeout (124) or a signal (128+n: 137 = KILL, 143 = TERM).
run_tool() {                     # run_tool <label> <cmd...>
  local label="$1"; shift
  local out rc
  out=$("$@" 2>&1); rc=$?
  printf '%s\n' "$out" | tail -4 | sed 's/^/  /'
  if [ $rc -ne 0 ]; then
    if [ $rc -ge 124 ] || printf '%s' "$out" | grep -qiE 'terminated|killed|signaled|signal "?[0-9]+"?'; then
      echo "  ⛔ $label was KILLED or timed out (rc=$rc) — this is NOT a $label verdict."
      echo "     No number from this run. Re-run it; if it repeats, find what is signalling."
    fi
    fail=1
  fi
}
run_tool pint    ./vendor/bin/pint --test
run_tool phpstan ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (phpunit.xml → $xml_db)"
  # Refuse while another pest runs on THIS database from any checkout whose
  # phpunit.xml pins it (2026-09-05 07:1x: the sixty checkout wiped the schema
  # under a Track 1 gate — 32 spurious "relation does not exist" errors).
  shared=""
  for co in /home/goaiez/agents/grs-antig*; do
    grep -q "DB_DATABASE\" value=\"$xml_db\"" "$co/app/phpunit.xml" 2>/dev/null && shared="$shared $co"
  done
  clash=0
  for p in $(pgrep -x php); do
    if tr '\0' ' ' < /proc/$p/cmdline 2>/dev/null | grep -q "bin/pes""t"; then
      c=$(readlink /proc/$p/cwd 2>/dev/null)
      for co in $shared; do case "$c" in "$co"/*) clash=$((clash+1)); echo "  ✗ pest pid $p running on $xml_db from $c";; esac; done
    fi
  done
  if [ $clash -gt 0 ]; then
    echo "  ✗ REFUSED: $clash other pest process(es) on $xml_db (checkouts pinning it:$shared) — a gate now would be false"
    echo '{"tool":"pest","result":"refused-shared-db"}' > /home/goaiez/tmp/last-pest.json
    fail=1; want_tests=0
  fi
fi
if [ $want_tests -eq 1 ]; then
  # SHARED SUITE LOCK (2026-09-06). This box runs eight checkouts of this project
  # plus a sibling project's agents on the same account. Two suites at once is not
  # only slow — it is what gives an agent a reason to reap a "stray" pest, and on
  # 2026-09-06 that cost this repo a gate to `killall -9` (rc 137) and two more to
  # SIGTERM (rc 143), while the sibling project lost a suite and a Pint run the same
  # day and blamed a neighbour. Nobody could prove who killed what.
  #
  # The lock removes the reason. It is ADVISORY and cross-project by design: any
  # script on this box that wraps its suite in the same flock serialises with this
  # one. It is not a DB guard — §7 above still refuses a clash on the pinned
  # database, which is a correctness problem, not a scheduling one.
  #
  # Never kill a suite you did not start. Wait for the lock, or report and stop.
  PEST_LOCK=/home/goaiez/tmp/pest.lock
  lock_held=0
  if command -v flock >/dev/null 2>&1; then
    exec 9>>"$PEST_LOCK" 2>/dev/null && {
      if ! flock -n 9; then
        echo "  … another suite holds $PEST_LOCK — waiting up to 40 min (never killing it)"
      fi
      flock -w 2400 9 && lock_held=1
    }
    if [ $lock_held -eq 0 ]; then
      echo "  ✗ pest NOT RUN — $PEST_LOCK held for 40 minutes. Not a red suite: no test ran."
      echo '{"tool":"pest","result":"lock-timeout"}' > /home/goaiez/tmp/last-pest.json
      fail=1; want_tests=0
    fi
  fi
fi
if [ $want_tests -eq 1 ]; then
  # timeout: a hung suite is a red line, never a 26-minute wait (ruling 2026-09-05 07:0x)
  out=$(timeout 1800 ./vendor/bin/pest 2>&1); rc=$?
  [ "${lock_held:-0}" -eq 1 ] && flock -u 9 2>/dev/null
  if [ $rc -eq 124 ]; then
    echo "  ✗ pest TIMEOUT after 1800s — the suite hung (a lock wait or a prompt); treat as red"
    out="$out"$'\n''{"tool":"pest","result":"timeout"}'
  elif [ -z "$out" ]; then
    # 2026-09-05 07:2x: a gate printed a blank §7 and an empty last-pest.json.
    # Zero bytes is never a result: rc 137/143 = killed from outside (a
    # `pkill -f pest` in another session); 255 = PHP died before the formatter
    # (memory, Vite manifest — narrow with --filter); 0 with no output = the
    # formatter never ran.
    echo "  ✗ pest printed ZERO BYTES (rc=$rc) — no test ran to completion; not a number, a silence. Re-run; if it repeats, --filter one file to surface the exception"
    out='{"tool":"pest","result":"silent","rc":'"$rc"'}'
    fail=1
  fi
  printf '%s' "$out" | tail -1 > /home/goaiez/tmp/last-pest.json
  [ $rc -ne 0 ] && fail=1
  if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
print("  tests %s · passed %s · FAILED %s · errors %s · result %s" % (d.get("tests"),d.get("passed"),d.get("failed",0),d.get("errors"),d.get("result")))
for f in (d.get("failures") or [])[:5]:
    print("   ✗ FAILURE %s" % f.get("test","?").split("::")[-1])
for e in (d.get("error_details") or [])[:5]:
    print("   ✗ %s\n      %s" % (e.get("test","?").split("::")[-1], (e.get("message") or "")[:160]))
n=len(d.get("error_details") or [])
if n>5: print("   … %d more" % (n-5))'
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
