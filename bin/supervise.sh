#!/usr/bin/env bash
#
# The supervisor's read-only gate.  Reads the tree and changes nothing in it.
# Safe at any time.  With --tests it writes exactly one artifact, the raw pest
# output, to scratch/pest-raw-last.log (gitignored) — see §7.
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
LAST_RC=0

# ── shared gate log ────────────────────────────────────────────────────────────
# Track 1, 2026-09-06 16:0x + 16:5x + 17:1x. Eight columns, FINAL shape:
#   start_iso  end_iso  gate_pid  tool_pid  rc  project  checkout  tool
# `rc` is RAW (128+N: 143 SIGTERM, 137 SIGKILL, 124 timeout(1)) — never 0/1, the
# signal is the whole point. `tool` is a FIXED vocabulary of five and nothing
# else: gate | pint | phpstan | pest | doctor. `gate` covers both sentinels; a
# consumer distinguishes them by rc, not by inventing two tool names.
# `project` is the project, not the directory. No lock: appends under PIPE_BUF to
# an O_APPEND file are atomic on Linux.
# ⚠️ GATE_LOG is OVERRIDABLE by design — the sibling project's gate test executed
# the real hook and began appending rows for tools that never ran, twelve rows
# with every schema property satisfied. A test that exercises this gate points
# GATE_LOG at a throwaway path. A diagnostic log that records its own harness is
# worse than no log, because the fabrications have the shape of the evidence.
GATE_LOG=${GATE_LOG:-/home/goaiez/tmp/gate-runs.tsv}
GATE_PROJECT=goaiez-antigravity
GATE_CHECKOUT=$(basename "$ROOT")
log_gate() {  # start_iso end_iso tool_pid rc tool
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$1" "$2" "$$" "$3" "$4" "$GATE_PROJECT" "$GATE_CHECKOUT" "$5" >> "$GATE_LOG" 2>/dev/null || true
}
# The sentinel is defined HERE, at the top, and not where the tools run: Track 1's
# own copy sat lower and recorded nothing at all for a gate killed two seconds in.
# `trap - EXIT` INSIDE each signal trap is the second half — without it the EXIT
# trap fires afterwards and writes a clean rc 0 row that hides the honest 143.
GATE_START_ISO=$(date -Is)
log_gate "$GATE_START_ISO" "-" "-" "-" gate
_gate_end() { log_gate "$GATE_START_ISO" "$(date -Is)" "-" "$1" gate; }
trap '_gate_end $?' EXIT
trap 'trap - EXIT; _gate_end 143; exit 143' TERM
trap 'trap - EXIT; _gate_end 130; exit 130' INT

# ── one tool run ───────────────────────────────────────────────────────────────
# §6 used to pipe each tool straight into `tail`, which with `pipefail` catches a
# style red and a SIGTERM identically: a KILLED pint prints a bare `Terminated`
# and was about to be recorded as "pint failed" (Track 1, 16:0x). A killed tool is
# NOT a verdict, and this says so out loud.
# ⚠️ `out=$(cmd)` gives you no pid, so the run is backgrounded — and the `pgrep -P`
# descent is not optional: `timeout 1800 pest` makes `timeout` the job and php the
# process a killer sees in `ps`, so logging the wrapper's pid fails the join with
# kill-log.tsv in exactly the case the log exists for, silently.
run_tool() {  # tool tail_n cmd...
  local tool=$1 n=$2; shift 2
  local s tmp jobpid tpid child
  s=$(date -Is); tmp=$(mktemp "${TMPDIR:-/tmp}/gate-XXXXXX")
  "$@" > "$tmp" 2>&1 &
  jobpid=$!; tpid=$jobpid
  for _ in 1 2 3 4 5; do
    child=$(pgrep -P "$jobpid" 2>/dev/null | head -1)
    [ -n "$child" ] && { tpid=$child; break; }
    sleep 0.2
  done
  wait "$jobpid"; LAST_RC=$?
  log_gate "$s" "$(date -Is)" "$tpid" "$LAST_RC" "$tool"
  tail -n "$n" "$tmp" | sed 's/^/  /'
  rm -f "$tmp"
  [ "$LAST_RC" -ge 128 ] && echo "  ⛔ $tool was KILLED or timed out (rc=$LAST_RC) — this is NOT a verdict, no test or file was judged"
  return "$LAST_RC"
}

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

# ⛔ This track's OWN test database. `app/phpunit.xml` is a per-track file and
# never merges in either direction (owner ruling, 2026-09-04). A merge of
# origin/main put `goaiez_antig_test` — TRACK 1's — back on this checkout on
# 2026-09-05, and a suite run from here wiped Track 1's schema under their gate
# at 07:1x (32 spurious "relation phone_numbers does not exist" errors). The
# same check stands alone in .agents/supervisor/pin-check.sh, which is gitignored
# and therefore survives a merge that overwrites this file.
OWN_TEST_DB="goaiez_antig_sixty_test"
if [ "$xml_db" != "$OWN_TEST_DB" ]; then
  echo "  ⛔ app/phpunit.xml pins '${xml_db:-<unset>}', not this track's '$OWN_TEST_DB'"
  echo "     restore: git show cd30f19c:app/phpunit.xml > app/phpunit.xml"
  if [ $want_tests -eq 1 ]; then
    echo "     REFUSING --tests: a run on another track's database is false here and destructive there."
    want_tests=0
  fi
  fail=1
fi

bar "1. working tree"
git status --short | head -40
echo "  $(git status --short | wc -l) uncommitted path(s)"
git log --oneline -5 | sed 's/^/  /'
git rev-list --left-right --count origin/main...HEAD 2>/dev/null \
  | awk '{print "  vs origin/main (local ref): behind " $1 ", ahead " $2 "  — refresh with: git fetch --no-write-fetch-head origin"}'

bar "1b. state ledger not truncated  (BUILD-STATE.json vs its parents)"
# ⛔ TICK 151 / OWNER 66. A merge resolution staged BUILD-STATE.json at 218 lines where
# HEAD had 1524 and MERGE_HEAD 1411 — twelve of sixteen top-level keys gone, `modules`,
# `stages`, `waves`, `journeys` and `runtime_build` among them. NOTHING here caught it:
# the file stayed valid JSON, `grep -rn '<<<<<<<'` was empty, `php -l` does not apply,
# and §1 reported it only as "1 uncommitted path". One `git commit` — the supervisor's,
# the one merge commit made without `-- <paths>` — would have written the loss into
# history. The honest test is the KEY SET: a ledger may grow, and it may keep either
# side's rows, but its top-level key set is never a proper subset of a parent's.
bs_keys() {  # $1 = git ref, or WT for the working tree
  if [ "$1" = "WT" ]; then
    python3 -c "import json,sys;print(' '.join(sorted(json.load(open('$ROOT/.agents/state/BUILD-STATE.json')))))" 2>/dev/null
  else
    git show "$1:.agents/state/BUILD-STATE.json" 2>/dev/null \
      | python3 -c "import json,sys;print(' '.join(sorted(json.load(sys.stdin))))" 2>/dev/null
  fi
}
bs_now=$(bs_keys WT)
if [ -z "$bs_now" ]; then
  echo "  ⛔ .agents/state/BUILD-STATE.json is missing or not parseable JSON"
  fail=1
else
  echo "  working tree: $(printf '%s' "$bs_now" | wc -w) keys, $(wc -l < "$ROOT/.agents/state/BUILD-STATE.json") lines"
  for parent in HEAD MERGE_HEAD; do
    git rev-parse -q --verify "$parent" >/dev/null 2>&1 || continue
    bs_par=$(bs_keys "$parent")
    [ -n "$bs_par" ] || continue
    missing=""
    for k in $bs_par; do
      case " $bs_now " in *" $k "*) ;; *) missing="$missing $k";; esac
    done
    if [ -n "$missing" ]; then
      echo "  ⛔ keys present in $parent and LOST here:$missing"
      echo "     rebuild from both parents — never hand-write it; state.py owns this file"
      fail=1
    else
      echo "  ✓ every $parent key survives ($(printf '%s' "$bs_par" | wc -w) keys)"
    fi
  done
fi

bar "2. forbidden paths touched  (uncommitted + last commit)"
touched=$( { git diff --name-only HEAD~1 HEAD 2>/dev/null; } | sort -u)
sup_edits=$(git diff --name-only HEAD -- .agents/supervisor CLAUDE.md bin/supervise.sh 2>/dev/null)
[ -n "$sup_edits" ] && printf '%s\n' "$sup_edits" | sed 's/^/  ℹ supervisor working notes (uncommitted — leave them alone): /'
touched=$(printf '%s\n%s' "$touched" "$(git diff --name-only HEAD | grep -vE '^(\.agents/supervisor/|CLAUDE\.md$|bin/supervise\.sh$)')" | sort -u | grep -v '^$')
pat='^app/app/Doctor/|seals\.json$|tests/Journeys/JourneyHarness\.php$|^app/Modules/[^/]+/(manifest|capabilities)\.php$|(^|/)\.env(\.|$)|^app/phpunit\.xml$|^source/|^runtime/|^bin/state\.py$|^\.agents/supervisor/(BRIEF|REVIEWS)\.md$'
hits=$(printf '%s\n' "$touched" | grep -E "$pat" || true)

# ⛔ A PATH THAT *ARRIVED* IN A MERGE IS NOT A PATH THAT WAS *TOUCHED*.
# `git diff HEAD~1 HEAD` on a merge commit diffs against the FIRST parent (ours),
# so every file main brought reads as changed — JourneyHarness.php included, on
# every single merge. That false positive cost this track waves 60 and 61 (it is
# the same defect as the coder guard's, escalated as OWNER 59) before the merge
# was verified by hand and committed at tick 148. The honest test is against the
# MERGE parent: if the file is byte-identical to HEAD^2 it came from there
# untouched, and no assertion, refusal or seal moved.
merged_in=""
if [ -n "$(git rev-parse -q --verify HEAD^2 2>/dev/null)" ] && [ -n "$hits" ]; then
  kept=""
  for h in $hits; do
    if git diff --quiet HEAD^2 HEAD -- "$h" 2>/dev/null; then
      merged_in="$merged_in$h
"
    else
      kept="$kept$h
"
    fi
  done
  hits=$(printf '%s' "$kept" | grep -v '^$' || true)
fi
[ -n "$merged_in" ] && printf '%s' "$merged_in" | grep -v '^$' \
  | sed 's|^|  ✓ arrived unchanged from the merge parent (identical to HEAD^2, not touched): |'

if [ -n "$hits" ]; then
  printf '%s\n' "$hits" | sed 's/^/  ⛔ /'
  echo "  (manifest/capabilities are legal only via regeneration; supervisor files are legal only from the supervisor)"
  fail=1
elif [ -n "$merged_in" ]; then
  echo "  none touched"
else
  echo "  none"
fi

bar "2a. rewrite ledger (amends/rebases are recorded by the post-rewrite hook)"
# This checkout is a linked worktree, so $ROOT/.git is a FILE and $ROOT/.git/hooks
# can never exist. Git runs hooks from core.hooksPath, else the COMMON git dir —
# never the per-worktree one. Resolve it the way git does. (Fixed 2026-09-05: the
# literal path made this gate report MISSING for several waves while the hook the
# supervisor wrote on 2026-09-02 was present and live all along.)
hooksdir=$(git -C "$ROOT" config --get core.hooksPath 2>/dev/null)
[ -n "$hooksdir" ] || hooksdir="$(git -C "$ROOT" rev-parse --git-common-dir)/hooks"
case "$hooksdir" in /*) ;; *) hooksdir="$ROOT/$hooksdir" ;; esac
if [ ! -x "$hooksdir/post-rewrite" ]; then
  echo "  ⛔ post-rewrite hook is MISSING at $hooksdir — its absence is a finding"; fail=1
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
run_tool doctor 4 php artisan doctor:selftest || { fail=1; echo "  ⛔ RUNTIME — the checker, not the code"; }
run_tool doctor 4 php artisan doctor --stage=integrity || { fail=1; echo "  ⛔ SEAL/integrity red"; }
echo "  runtime_build in BUILD-STATE: $(python3 -c "import json;print(json.load(open('$ROOT/.agents/state/BUILD-STATE.json'))['runtime_build'])" 2>/dev/null) — compare with the doctor build stamp above"

if [ $want_doctor -eq 1 ]; then
  bar "5. all eight stages  (non-zero exit on any red stage is by design)"
  run_tool doctor 30 php artisan doctor
fi

bar "6. style + static analysis"
run_tool pint 3 ./vendor/bin/pint --test || fail=1
run_tool phpstan 4 ./vendor/bin/phpstan analyse --memory-limit=1G --no-progress || fail=1

if [ $want_tests -eq 1 ]; then
  bar "7. test suite  (phpunit.xml → $xml_db)"
  # Refuse while another pest runs on THIS database from any checkout whose
  # phpunit.xml pins it (adopted from Track 1's 9b65e1e5; the incident is the
  # one recorded in §0 above).
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
    fail=1; want_tests=0
  fi
fi
if [ $want_tests -eq 1 ]; then
  # ── the shared suite lock (Track 1, 2026-09-06 14:1x) ────────────────────────
  # Two suites at once on this box is not just slow: it is what gives an agent a
  # reason to reap a "stray" pest. Advisory and CROSS-PROJECT by design — anything
  # on this box wrapping its suite in the same flock serialises with us.
  # It is ORTHOGONAL to §7's shared-database refusal above: that is a correctness
  # guard, this is a scheduling one, and both stay.
  # ⚠️ A lock-timeout is NOT a red suite. It means no test ran. Never take a number
  # from a run that did not happen, and never kill the holder to get the lock.
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
      echo '{"tool":"pest","result":"lock-timeout"}' > "$ROOT/scratch/pest-raw-last.log"
      fail=1; want_tests=0
    fi
  fi
fi
if [ $want_tests -eq 1 ]; then
  # timeout: a hung suite is a red line, never a 26-minute wait (owner ruling
  # relayed 2026-09-05 08:0x).
  pest_tmp=$(mktemp "${TMPDIR:-/tmp}/gate-pest-XXXXXX")
  pest_start=$(date -Is)
  timeout 1800 ./vendor/bin/pest > "$pest_tmp" 2>&1 &
  pest_job=$!; pest_pid=$pest_job
  for _ in 1 2 3 4 5; do
    pest_child=$(pgrep -P "$pest_job" 2>/dev/null | head -1)
    [ -n "$pest_child" ] && { pest_pid=$pest_child; break; }
    sleep 0.2
  done
  wait "$pest_job"; rc=$?
  [ "${lock_held:-0}" -eq 1 ] && flock -u 9
  log_gate "$pest_start" "$(date -Is)" "$pest_pid" "$rc" pest
  out=$(cat "$pest_tmp"); rm -f "$pest_tmp"
  if [ $rc -eq 124 ]; then
    echo "  ✗ pest TIMEOUT after 1800s — the suite hung (a lock wait or a prompt); treat as red"
    out="$out"$'\n''{"tool":"pest","result":"timeout"}'
  elif [ -z "$out" ]; then
    # Zero bytes is never a result: rc 137/143 = killed from outside; 255 = PHP
    # died before the formatter existed (memory, or a missing Vite manifest —
    # narrow with --filter); 0 with no output = the formatter never ran.
    echo "  ✗ pest printed ZERO BYTES (rc=$rc) — no test ran to completion; not a number, a silence. Re-run; if it repeats, --filter one file to surface the exception"
    out='{"tool":"pest","result":"silent","rc":'"$rc"'}'
    fail=1
  fi
  [ $rc -ne 0 ] && fail=1
  # ⚠️ PERSIST THE RAW OBJECT. Until 2026-09-05 (tick 167) this script captured
  # `out` into a variable, printed a 12-row window of it, and dropped the rest —
  # while telling the reader "the complete list is the JSON object on the LAST
  # LINE of the raw pest output", which by then existed nowhere on disk. Wave 75
  # was asked for that object and had to run a SECOND 95-second pest to get it.
  # Two pest runs is the collision shape this checkout keeps paying for, and the
  # brief that demanded it was the supervisor's. One file removes the reason.
  mkdir -p "$ROOT/scratch"
  printf '%s\n' "$out" > "$ROOT/scratch/pest-raw-last.log"
  if printf '%s' "$out" | tail -1 | grep -q '^{"tool":"pest"'; then
    # ⚠️ THIS BLOCK LIED FOR TWO WAVES AND THE FIX IS WHY IT LOOKS LIKE THIS.
    # Until 2026-09-05 (tick 161) the summary printed only tests/passed/errors and
    # the list walked `error_details` ALONE — so a *failure* could not appear here
    # at all. Wave 69's clean run printed `tests 1710 · passed 1703 · errors 4` and
    # was silent about three real failures, one of them the very test that wave was
    # sent to fix. Two consequences, both now built in: `failed` is printed, and the
    # arithmetic is checked out loud, because `passed + failed + errors != tests` is
    # the tell that a reporter is holding something back. The window is still a
    # window — 12, not 5 — so the last line says where the whole list actually lives.
    printf '%s' "$out" | tail -1 | python3 -c '
import json,sys
d=json.loads(sys.stdin.read())
t,p=d.get("tests"),d.get("passed")
f=d.get("failed") or 0
e=d.get("errors") or 0
print("  tests %s · passed %s · failed %s · errors %s · result %s" % (t,p,f,e,d.get("result")))
if isinstance(t,int) and isinstance(p,int) and p+f+e != t:
    print("  ⚠️ %d passed + %d failed + %d errors = %d, not %d — this line is not telling you everything" % (p,f,e,p+f+e,t))
rows=[("FAIL ",x) for x in (d.get("failures") or [])]+[("ERROR",x) for x in (d.get("error_details") or [])]
for kind,x in rows[:12]:
    print("   %s %s\n      %s" % (kind, (x.get("test") or "?").split("::")[-1], ((x.get("message") or "").splitlines() or [""])[0][:160]))
if len(rows)>12:
    print("   … %d more — the complete list is the JSON object on the LAST LINE of the raw pest output" % (len(rows)-12))'
  else
    printf '%s\n' "$out" | tail -12 | sed 's/^/  /'
  fi
  echo "  raw pest output → scratch/pest-raw-last.log — its LAST LINE is the complete object."
  echo "  Paste from that file. Do NOT run a second pest to obtain it."
fi

bar "verdict"
if [ $fail -eq 0 ]; then
  echo "  gates green. Necessary, not sufficient — now read the diff and REPORT.md."
else
  echo "  ⛔ a gate failed above."
fi
exit $fail
