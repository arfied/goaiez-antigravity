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
# Track 6 (reviews): dev DB goaiez_antig_reviews, tests goaiez_antig_reviews_test (exported above pest).
# The `php` on PATH here is the cgi-fcgi SAPI; laravel/pao refuses it, which is why
# §6 dies with "may only be invoked from a command line". Point GOAIEZ_PHP at a real
# CLI binary to fix §6; everything else tolerates the CGI SAPI.
#
# 2026-09-02 (REV-2): the coder found real CLI builds under /opt/cpanel. Pin one by
# probing PHP_SAPI rather than trusting a path — a `php` that prints cgi-fcgi makes
# §6 print two blank lines, which reads exactly like a passing gate.
PHP="${GOAIEZ_PHP:-}"
if [ -z "$PHP" ]; then
  for cand in /opt/cpanel/ea-php84/root/usr/bin/php /opt/cpanel/ea-php83/root/usr/bin/php \
              /opt/cpanel/ea-php82/root/usr/bin/php /usr/local/bin/php php; do
    command -v "$cand" >/dev/null 2>&1 || continue
    [ "$("$cand" -r 'echo PHP_SAPI;' 2>/dev/null)" = "cli" ] || continue
    PHP="$cand"; break
  done
  PHP="${PHP:-php}"
fi
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
hits=$(printf '%s\n' "$touched" | grep -E "$pat" || true)
if [ -n "$hits" ]; then
  printf '%s\n' "$hits" | sed 's/^/  ⛔ /'
  echo "  (manifest/capabilities are legal only via regeneration; supervisor files are legal only from the supervisor)"
  fail=1
else
  echo "  none"
fi

bar "2a. rewrite ledger (amends/rebases are recorded by the post-rewrite hook)"
hook=$(git -C "$ROOT" rev-parse --git-path hooks/post-rewrite 2>/dev/null); [ "${hook#/}" = "$hook" ] && hook="$ROOT/$hook"
if [ ! -x "$hook" ]; then
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

bar "2c. debug debris in app code (dump/dd/var_dump)"
dbg=$(grep -rnE '\b(dump|dd|var_dump)\(' "$APP/app" --include='*.php' 2>/dev/null | grep -vE ':[0-9]+:\s*(\*|//)' | grep -v '@allow-dump' | head -5)
if [ -n "$dbg" ]; then printf '%s\n' "$dbg" | sed 's/^/  ⛔ /'; fail=1; else echo "  none"; fi

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
# Through $PHP, not bare `php`: on the cgi-fcgi SAPI artisan emits a `Content-type:`
# header and the `goaiez doctor · build <stamp>` line gets pushed out of the tail —
# and that stamp is the only proof the counts came from the live checker.
"$PHP" artisan doctor:selftest 2>&1 | tail -4 | sed 's/^/  /' || { fail=1; echo "  ⛔ RUNTIME — the checker, not the code"; }
"$PHP" artisan doctor --stage=integrity 2>&1 | tail -6 | sed 's/^/  /' || { fail=1; echo "  ⛔ SEAL/integrity red"; }
echo "  runtime_build in BUILD-STATE: $(python3 -c "import json;print(json.load(open('$ROOT/.agents/state/BUILD-STATE.json'))['runtime_build'])" 2>/dev/null) — compare with the doctor build stamp above"

if [ $want_doctor -eq 1 ]; then
  bar "5. all eight stages  (non-zero exit on any red stage is by design)"
  "$PHP" artisan doctor 2>&1 | tail -30 | sed 's/^/  /'
fi

bar "6. style + static analysis"
echo "  php: $(command -v "$PHP") — $("$PHP" -v 2>&1 | head -1)"
"$PHP" ./vendor/bin/pint --test 2>&1 | tail -3 | sed 's/^/  /' || fail=1
"$PHP" ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress 2>&1 | tail -4 | sed 's/^/  /' || fail=1

if [ $want_tests -eq 1 ]; then
  # The DB_DATABASE= export on the next line overrides phpunit.xml's $xml_db pin
  # (owner ruling 3: the pin stays, this track exports over it). Label the DB the
  # run actually used — the pin's name here once read as "we hit Track 1's DB".
  bar "7. test suite  (DB_DATABASE=goaiez_antig_reviews_test, over phpunit.xml's $xml_db pin)"
  # Two pests on ONE database truncate each other's tables mid-run and the loser
  # reads as a code failure. Refuse the step while any checkout that pins
  # goaiez_antig_reviews_test has a pest live (Track 1 commit 9b65e1e5).
  #
  # THIS checkout counts too (REV-60, 2026-09-06). The old loop skipped $ROOT on
  # the theory that a second run here is the caller's own business. It is not: a
  # pest orphaned by a killed supervise.sh — the tick harness SIGTERMs a
  # foreground call at 600s and leaves the pest running with no timeout parent —
  # then fights the next run over the same tables. Three gates were lost that way
  # in one tick, the third dying on the 1800s timeout with zero bytes. No pest of
  # ours has started yet at this point in the script, so any pest whose cwd is
  # under $ROOT is a stray and the run must not begin.
  #
  # Report the AGE and the PARENT of every pest we refuse over (REV-62,
  # 2026-09-06). The first cut of this guard printed a bare pid, which reads as a
  # permanent wall: run 57 closed with no number at all because its brief had no
  # way to say "this clears by itself in six minutes". A pest whose PPid is 1 was
  # orphaned by a killed gate, and if its parent process is `timeout` it is still
  # inside the 1800s budget that will reap it. That is a WAIT, not a kill.
  busy=""
  reap=""
  nokill=""
  now_s=$(date +%s)
  for other in /home/goaiez/agents/*/app/phpunit.xml /home/goaiez/public_html/*/app/phpunit.xml; do
    [ -f "$other" ] || continue
    oroot=$(dirname "$(dirname "$other")")
    grep -q 'goaiez_antig_reviews_test' "$other" 2>/dev/null || continue
    for pid in $(pgrep -f 'vendor/bin/pest' 2>/dev/null); do
      cwd=$(readlink -f "/proc/$pid/cwd" 2>/dev/null) || continue
      case "$cwd" in "$oroot"*) ;; *) continue;; esac
      # /proc/<pid> carries the process start time as its own mtime.
      comm=$(cat "/proc/$pid/comm" 2>/dev/null)
      ppid=$(awk '/^PPid:/{print $2}' "/proc/$pid/status" 2>/dev/null)
      st=$(stat -c %Y "/proc/$pid" 2>/dev/null)
      age="?"; [ -n "$st" ] && age=$(( now_s - st ))
      orph=""; [ "$ppid" = "1" ] && orph=" ORPHANED"
      busy="$busy
       $oroot pid $pid ($comm, age ${age}s, ppid ${ppid:-?})$orph"
      if [ "$ppid" = "1" ] && [ "$age" != "?" ]; then
        if [ "$comm" = "timeout" ]; then
          reap="$reap
       pid $pid self-reaps in $(( 1800 - age ))s (its own timeout 1800 budget)"
        else
          nokill="$nokill $pid"
        fi
      fi
    done
  done
  if [ -n "$busy" ]; then
    echo "  ⛔ REFUSED — a checkout pinned on goaiez_antig_reviews_test has pest live:$busy"
    echo "     A second run truncates the tables under both. This step will not start."
    if [ -n "$reap" ]; then
      echo "     ORPHANED but still parented by \`timeout\` — it reaps ITSELF. Do not kill it:$reap"
      echo "     Wait that long, re-run this gate, and say in the report that it refused and for how long."
    fi
    if [ -n "$nokill" ]; then
      echo "     Orphaned with NO timeout parent (pid(s):$nokill) — nothing will ever reap these."
      echo "     Killing a pest is the SUPERVISOR's call, never the coder's. Report the pid and stop."
    fi
    if [ -z "$reap" ] && [ -z "$nokill" ]; then
      echo "     Not orphaned — this is a live gate in another checkout. Wait for it."
    fi
    fail=1
    bar "verdict"
    echo "  ⛔ a gate failed above."
    exit $fail
  fi
  out=$(timeout 1800 env DB_DATABASE=goaiez_antig_reviews_test "$PHP" ./vendor/bin/pest 2>&1); rc=$?
  # Track-scoped: /home/goaiez/tmp is shared by every worktree, and an unscoped
  # last-pest.json means one track reads another track's run as its own.
  printf '%s' "$out" | tail -1 > "/home/goaiez/tmp/last-pest-$(basename "$ROOT").json"
  [ $rc -ne 0 ] && fail=1
  [ $rc -eq 124 ] && echo "  ⛔ TIMEOUT — pest exceeded 1800s (rc=124). The numbers below, if any, are partial."
  if [ -z "$out" ]; then
    # A blank block used to read as "nothing to say". It is a crash: memory_limit,
    # a missing Vite manifest, or a suite that died before its formatter existed.
    echo "  ⛔ ZERO BYTES — pest printed nothing (rc=$rc). Narrow it with --filter before"
    echo "     reading the code; check memory_limit and that npm run build has run."
    fail=1
  elif printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
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
    echo "  no JSON summary (rc=$rc) — last 12 lines:"
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
