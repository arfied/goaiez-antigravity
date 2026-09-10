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
want_tests=0; want_doctor=0; census_only=0
# --census [name] runs ONLY §1a/§1b and exits. `name` is the argv[0] basename the census
# hunts for, default `agy`. It exists so the census has a POSITIVE CONTROL THAT IS SAFE
# WHEN THE DETECTOR IS ABSENT (2026-09-06): proving it by starting a real `agy` in this
# checkout would run an ungoverned coder to demonstrate that something notices — dangerous
# in exactly the case the census exists for. `--census sleep` against a backgrounded
# `sleep` proves the same mechanism (argv[0] basename × cwd) and is a harmless sleep if the
# detector is dead.
prev=""
for a in "$@"; do
  case "$a" in
    --tests) want_tests=1;;
    --full-doctor) want_doctor=1;;
    --census) census_only=1;;
    -*) ;;
    *) [ "$prev" = "--census" ] && CENSUS_NAME="$a";;
  esac
  prev="$a"
done
CENSUS_NAME=${CENSUS_NAME:-agy}
bar() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
fail=0

# SHARED GATE LOG (2026-09-06, shape agreed with the sibling project). One TSV line
# per tool run, appended at exit, so a death correlates against what else was running
# in that minute and the next kill is attributable instead of argued about.
# EIGHT columns, in this order:
#   start_iso  end_iso  gate_pid  tool_pid  rc  project  checkout  tool
# rc is the RAW code and is the whole point: 128+N — 143 SIGTERM, 137 SIGKILL, 124 is
# timeout(1)'s own. Never normalise it to 0/1.
# **tool_pid is the join key, gate_pid only groups a run's rows** (sibling project,
# 2026-09-06, from our own first sample: one gate_pid appeared on both the pint and
# the phpstan row, which is what proved it useless as a key). `coder-bin/kill` records
# the TARGET pid, and an agent killing a suite kills the *tool* — pest is what looks
# stray in `ps`, not the wrapper. Joining kill-log on gate_pid would fail silently in
# exactly the case this log exists to answer. Both pids cost one `$!` and neither is
# recoverable afterwards.
# `project` is the PROJECT, `checkout` is the working copy — this file wrote `grs-antig`
# in both until 2026-09-06 16:4x, which split its own traffic on any group-by. The seven
# lanes had it right from the first message; Track 1 did not. One stable label per
# project, and it is not the directory name.
# ⚠️ HISTORY IS MIXED. Rows before 16:4x come in two widths — the lanes wrote 7 columns
# (no `tool_pid`) because that is the shape Track 1 sent them, and Track 1 wrote 8 after
# the sibling project's correction without re-sending it. Nothing in a row announces its
# own width, so **a consumer must branch on NF before touching a field**: on a 7-column
# row `$4` is `rc`, on an 8-column row `$4` is `tool_pid`. Read `$4` as rc across the mix
# and every Track 1 row becomes a seven-digit failure code.
# No lock: appends under PIPE_BUF to an O_APPEND file are atomic on Linux, and a flock
# here would interact with the pest lock for nothing.
# Overridable so a TEST can never write into the shared log (sibling project, 28c52305,
# 2026-09-06): their pre-push test executes the real hook with instant no-op stubs, and
# once the hook logged, the test began appending rows for tools that never ran — twelve
# of them, well-formed, eight-column, correctly typed and invented. A diagnostic log that
# records its own harness is worse than no log: the fabrications have the shape of the
# evidence. No test here touches this script today (grepped across all eight checkouts);
# this is the preventive, because nothing in the schema would object if one did.
GATE_LOG=${GATE_LOG:-/home/goaiez/tmp/gate-runs.tsv}
log_gate() {                     # log_gate <tool> <start_iso> <rc> [tool_pid]
  printf '%s\t%s\t%s\t%s\t%s\t%s\t%s\t%s\n' \
    "$2" "$(date -Is)" "$$" "${4:--}" "$3" "goaiez-antigravity" "$(basename "$ROOT")" "$1" \
    >> "$GATE_LOG" 2>/dev/null || true
}
# Defined here, at the top, deliberately: a gate killed during sections 0-5 must
# still leave a start row. Defining it at section 6 recorded nothing for a gate
# killed two seconds in — measured, 2026-09-06 16:16.
# A KILLED GATE MUST NOT BE SILENT (sibling project, 2026-09-06). log_gate appends at
# tool exit, so a tool killed mid-run still lands its row with rc 143 — but a kill
# aimed at the WRAPPER leaves no row at all, indistinguishable from a gate that never
# started. Two sentinels fix it for the cost of two lines: a start row with rc `-`,
# and an end row from an EXIT trap. A start with no matching end is a killed gate.
GATE_STARTED=$(date -Is)
log_gate gate "$GATE_STARTED" - "$$"
trap 'log_gate gate "$GATE_STARTED" "${fail:-?}" "$$"' EXIT
# `trap - EXIT` first: without it the EXIT trap fires after the signal trap's `exit`
# and appends a SECOND end row carrying $fail — measured 2026-09-06 16:2x, a killed
# gate wrote rc=143 then rc=0, and a reader taking the last row would call a killed
# gate clean. That is the same defect class this log exists to catch.
trap 'trap - EXIT; log_gate gate "$GATE_STARTED" 143 "$$"; exit 143' TERM
trap 'trap - EXIT; log_gate gate "$GATE_STARTED" 130 "$$"; exit 130' INT


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

# ⛔⛔ PER-LANE TEST DATABASE (REV-119, 2026-09-09). Re-ported after run 114's
#     fast-forward took Track 1's copy of this file whole and dropped this lane's
#     version of §7. `main`'s app/phpunit.xml pins goaiez_antig_test for EVERY
#     lane, and app/phpunit.xml is a never-merge path — so after the fast-forward
#     nothing at all was left holding the separation. RefreshesTenantDatabase runs
#     migrate:fresh, which DROPS every table first, so one bare `--tests` from any
#     of the seven checkouts destroys the test database of the other six.
#
# ⭐ §7's clash guard below does not substitute for this. It SERIALISES suites that
#   share a database; it never SEPARATES them. Serialising seven lanes onto one
#   database makes the destruction orderly, not absent.
#
# ⭐⭐ Derived, and an already-exported DB_DATABASE WINS — a derived default is the
#    one value a brief must be able to override without editing a tracked file.
#    NOT exported globally on purpose: §4's doctor and its schema stage read the
#    LIVE database, and exporting here would silently repoint them off .env's
#    goaiez_antig_reviews. The value is applied at the pest call in §7 and nowhere
#    else, which is the only place whose blast radius is the suite.
lane_db=${DB_DATABASE:-goaiez_antig_$(basename "$ROOT" | sed 's/^grs-antig-*//')_test}
[ "$lane_db" = "goaiez_antig__test" ] && lane_db=goaiez_antig_test
echo "  effective (§7)   DB_DATABASE=$lane_db"
if [ "$lane_db" = "$PROD_DB" ]; then
  echo "  ⛔ the effective test database is PRODUCTION. Stop. Nothing below may run."; exit 2
fi

bar "1. working tree"
git status --short | head -40
echo "  $(git status --short | wc -l) uncommitted path(s)"
git log --oneline -5 | sed 's/^/  /'
git rev-list --left-right --count origin/main...HEAD 2>/dev/null \
  | awk '{print "  vs origin/main (local ref): behind " $1 ", ahead " $2 "  — refresh with: git fetch --no-write-fetch-head origin"}'

# A PIDFILE REPORTS AN INTENTION, NOT A STATE (2026-09-06 17:3x, ruled in REVIEWS).
# The tick's case (a) is "if coder.pid is alive, print `coder running` and stop" — and a
# HUNG run satisfies that forever: the pid exists, the lane reports healthy, and it idles
# every ten minutes with its work unpushed. Measured live that afternoon: sixty silent for
# 182 minutes, pricebook for 77, both "running". Liveness therefore has to be measured as
# PROGRESS (has the log grown) and not as existence, and the supervisor must be able to
# measure it with a command its own settings.json allows — `ps` and `kill -0` are not on
# that list, which is how a tick ends up reasoning about a pid instead of reading one.
# §2e is also the one-writer census (2026-09-03 incident): any process with cwd here that
# launch-coder.sh did not start is a BLOCK, and it must print before any dispatch or gate.
bar "1a. coder process  (progress, not existence)"
STALL_MIN=${STALL_MIN:-30}
pidfile="$ROOT/.agents/supervisor/coder.pid"
coder_pid=""
[ -f "$pidfile" ] && coder_pid=$(tr -dc '0-9' < "$pidfile")
if [ -z "$coder_pid" ]; then
  echo "  no coder.pid — no dispatch has been recorded in this checkout"
elif ! kill -0 "$coder_pid" 2>/dev/null; then
  echo "  coder.pid $coder_pid is DEAD — the slot is free"
else
  log=$(ls -1t /home/goaiez/tmp/*"$(basename "$ROOT")"-run*.log 2>/dev/null | head -1)
  now=$(date +%s)
  pstart=$(stat -c %Y "/proc/$coder_pid" 2>/dev/null || echo "$now")
  age=$(( (now - pstart) / 60 ))
  if [ -n "$log" ]; then
    lmt=$(stat -c %Y "$log"); silent=$(( (now - lmt) / 60 )); bytes=$(stat -c %s "$log")
    echo "  coder.pid $coder_pid ALIVE ${age}m · log $(basename "$log") ${bytes}B · silent ${silent}m"
    if [ "$silent" -ge "$STALL_MIN" ]; then
      echo "  ⚠ coder STALLED — no log growth in ${silent}m (threshold ${STALL_MIN}m)."
      echo "    The slot is NOT free: do not dispatch over it. Report pid/log/silence to the owner."
      fail=1
    fi
  else
    echo "  coder.pid $coder_pid ALIVE ${age}m · NO LOG FOUND for $(basename "$ROOT") — cannot measure progress"
    fail=1
  fi
fi

# MATCH `agy`, NOT `claude` — and this section's FIRST output is why the line is here.
# Written as *agy*|*claude*, it flagged five "stray writers" on a checkout that had none:
# the supervisor tick itself, its two snapshot shells and the owner's VS Code session. The
# 2026-09-03 incident was an interactive `agy` started by hand with no pidfile, no brief
# and no review; CLAUDE.md's own census one-liner therefore ends `| grep agy`, and widening
# it turns a BLOCK signal into one that fires on every human who opens the checkout — a
# gate that always fails is worth exactly as much as one that always passes. A `claude`
# started as the CODER is caught by the pidfile in §1a, which is where it belongs.
# (After building anything that measures, its first output is data you do not trust.)
#
# EXCLUDE THE DISPATCHED CODER'S WHOLE TREE, NOT ITS PID — third false positive in this
# section in one hour, and the costliest, because it fires only when a healthy coder is
# running. `coder.pid` holds the `nohup bash -c` WRAPPER's pid; `agy` is its child. Matching
# `pn = coder_pid` therefore never matches the process that is actually named agy, so the
# census reported our own dispatched coder as a stray and set fail=1 — inside a gate the
# CODER itself runs at item 6, which would have read its own existence as a failed gate.
# Walk PPid instead.
is_descendant_of() {              # is_descendant_of <pid> <ancestor>
  local q="$1" hops=0 pp
  while [ -n "$q" ] && [ "$q" != 0 ] && [ $hops -lt 12 ]; do
    [ "$q" = "$2" ] && return 0
    pp=$(awk '/^PPid:/{print $2}' "/proc/$q/status" 2>/dev/null)
    q="$pp"; hops=$((hops+1))
  done
  return 1
}
bar "1b. one-writer census  ($CENSUS_NAME with cwd here that launch-coder.sh did not start)"
strays=0
for p in /proc/[0-9]*; do
  pn=${p#/proc/}
  [ "$pn" = "$$" ] && continue
  [ -n "$coder_pid" ] && is_descendant_of "$pn" "$coder_pid" && continue
  case "$(readlink "$p/cwd" 2>/dev/null)" in
    "$ROOT")
      # ARGV[0], never the whole cmdline — MATCH COMMAND POSITION, NOT MENTION (7686da5c,
      # and this section earned the lesson a second time twenty minutes later). Grepping
      # the joined cmdline for `agy` flagged a plain `bash -c` whose command merely NAMED
      # agy — a previous tick's own census one-liner, `… | grep agy`. A detector that fires
      # on any shell that talks about the thing it hunts will fire on its own documentation.
      argv0=""; IFS= read -r -d '' argv0 < "$p/cmdline" 2>/dev/null
      case "${argv0##*/}" in
        "$CENSUS_NAME")
          cmd=$(tr '\0' ' ' < "$p/cmdline" 2>/dev/null)
          echo "  ✗ stray $CENSUS_NAME pid $pn: ${cmd:0:140}"; strays=$((strays+1));;
      esac;;
  esac
done
[ "$strays" -eq 0 ] && echo "  none" || { echo "  ⛔ $strays $CENSUS_NAME process(es) this checkout did not launch — BLOCK until resolved"; fail=1; }
if [ $census_only -eq 1 ]; then
  echo; echo "  (--census: sections 1a/1b only; exit $fail)"; exit $fail
fi

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

bar "2e. a merge that REVERTED a lane's check  (harness vs the incoming side)"
# §2 above measures the last commit against OUR HEAD, so it is structurally blind to the
# one thing a merge can do wrong: silently DROP the incoming side's change to a forbidden
# path. Run 115 restored `app/tests/Journeys/JourneyHarness.php` to HEAD during the site
# merge — reverting site's gated three-line J11 EdgeZone fix — and §2 printed `none`,
# correctly, because against HEAD the merge changed nothing there. The baseline was wrong,
# not the check. For a merge, the harness's baseline is the SECOND PARENT.
# The shared guard already encodes the intent (coder-bin/git:66-75: a gated merge may carry
# the harness ONLY when the staged blob is byte-identical to MERGE_HEAD's — "take the
# incoming side whole"), so this section only reports what that clause is there to enforce.
# ⚠️ The clause has a PRECONDITION and the first version of this check omitted it (run 117,
# 2026-09-06, its first firing on a real merge): "take the incoming side whole" only has
# meaning when the incoming side CHANGED the harness. On 8bccc2c6 track/ui never touched it
# while main was 64/-16 ahead of the merge base (a9e6a25f, site's J11 fix), so HEAD^2 alone
# read main's own legitimate ahead-ness as a dropped incoming change — the row that is
# legitimate by construction, again. The baseline for "did the incoming side change it" is
# the MERGE BASE; the baseline for "did the merge take it" is the second parent.
# Arms: FIRES on c1849a75 (site changed it +3 vs base 3c60289d, and the merge result still
# differs from the incoming side by that +3); SILENT on 8bccc2c6 (incoming vs base is empty);
# SILENT on any non-merge HEAD.
p2=$(git rev-parse -q --verify 'HEAD^2' 2>/dev/null || true)
if [ -z "$p2" ]; then
  echo "  HEAD is not a merge — nothing to compare"
else
  mb=$(git merge-base HEAD^1 "$p2" 2>/dev/null || true)
  inc=$(git diff --name-only "$mb" "$p2" -- app/tests/Journeys/JourneyHarness.php 2>/dev/null)
  if [ -z "$inc" ]; then
    echo "  incoming side never touched the harness (vs merge base $(git rev-parse --short "$mb")) — nothing to take ✓"
  else
    drop=$(git diff --name-only HEAD "$p2" -- app/tests/Journeys/JourneyHarness.php 2>/dev/null)
    if [ -n "$drop" ]; then
      echo "  ⛔ the merge did NOT take the incoming harness — $(git diff --shortstat HEAD "$p2" -- app/tests/Journeys/JourneyHarness.php)"
      echo "     inspect: git diff HEAD $p2 -- app/tests/Journeys/JourneyHarness.php"
      fail=1
    else
      echo "  harness identical to the incoming side ✓"
    fi
  fi
fi

bar "2f. per-track paths a merge would take from THEIRS silently  (merge=ours cannot fire)"
# ⛔⛔ REV-126 (2026-09-09). REV-119 §A ruled that "a fast-forward is a write to every
# per-track path", and blamed the fast-forward. THAT DIAGNOSIS WAS TOO NARROW and the
# narrowness is why run 122 was about to repeat run 114 through an ordinary merge.
#
# `merge=ours` is a CONFLICT-RESOLUTION driver. Git consults it only when it performs a
# three-way content merge for the path — i.e. only when BOTH sides moved the path since the
# merge base. When our side has NOT moved it and theirs has, there is no conflict to
# resolve: git takes theirs as a trivial file-level fast-forward and the driver is never
# called. A `.gitattributes` full of `merge=ours` is silent in exactly that case.
#
# ⭐ And the round trip makes that case the NORMAL one, not the exotic one: once Track 1
#   merges this lane into main, the merge base advances to a lane commit that contains the
#   lane's own copy of every per-track path. From then on the lane looks unchanged on all of
#   them and only main moves — so the lane silently adopts main's. `merge=ours` protects
#   whichever side is "ours" at merge time; it cannot protect a path across a
#   lane→main→lane round trip. Measured 2026-09-09: base e3aea7ff (this lane's own commit),
#   four of eight paths in the bypass state, including app/phpunit.xml (six lanes' test
#   databases) and .agents/rules/10-supervisor.md (the anti-push rule, REV-121 §1).
#
# ⭐⭐ The list is READ FROM .gitattributes, never restated here. REV-119 §A's own defect was
#    a stated list generalised by a sentence; REV-121 §A repeated it. The authoritative
#    statement of "per-track" is the `merge=ours` lines, so this check derives from them and
#    a path added there is covered the same run, with no edit to this file.
#
# No fetch: `origin/main` is read as the LOCAL remote-tracking ref. The gate never fetches
# (root-owned FETCH_HEAD, CLAUDE.md) — refreshing it is the caller's step.
if ! git rev-parse -q --verify origin/main >/dev/null 2>&1; then
  echo "  no local origin/main ref — nothing to compare (refresh with: git fetch --no-write-fetch-head origin)"
elif [ ! -f "$ROOT/.gitattributes" ]; then
  echo "  ⚠ no .gitattributes — the per-track list has no authoritative statement"
else
  ptbase=$(git merge-base HEAD origin/main 2>/dev/null || true)
  echo "  base $(git rev-parse --short "$ptbase")  ours HEAD  theirs origin/main ($(git rev-parse --short origin/main))"
  echo "  merge.ours.driver=$(git config --get merge.ours.driver || echo '⛔ UNSET — the driver never runs at all')"
  pt_n=0; pt_bypass=0
  while read -r pt_path pt_attr; do
    case "$pt_attr" in merge=ours) ;; *) continue;; esac
    [ -n "$pt_path" ] || continue
    pt_n=$((pt_n+1))
    pt_ours=$(git diff --name-only "$ptbase" HEAD -- "$pt_path" 2>/dev/null)
    pt_theirs=$(git diff --name-only "$ptbase" origin/main -- "$pt_path" 2>/dev/null)
    if [ -z "$pt_theirs" ]; then
      echo "     ✓ $pt_path — theirs unchanged since base; nothing incoming to take"
    elif [ -n "$pt_ours" ]; then
      echo "     ✓ $pt_path — both sides moved; the merge=ours driver FIRES and keeps ours"
    else
      pt_bypass=$((pt_bypass+1))
      echo "     ⛔ $pt_path — OURS UNCHANGED, THEIRS MOVED: a merge takes THEIRS with no conflict and no driver"
      echo "        $(git diff --shortstat "$ptbase" origin/main -- "$pt_path")"
      echo "        inspect: git diff HEAD origin/main -- $pt_path"
    fi
  done < "$ROOT/.gitattributes"
  if [ "$pt_bypass" -gt 0 ]; then
    echo "  ⛔ $pt_bypass of $pt_n per-track path(s) would be silently overwritten by a merge from origin/main."
    echo "     A merge wave must restore each one AFTER the merge, or move it on our side BEFORE the merge."
    echo "     ⚠ --allow-restore REFUSES CLAUDE.md, bin/supervise.sh, .agents/rules/** and .claude/** —"
    echo "       a bypass on one of those is the SUPERVISOR's to repair, and the coder cannot do it."
    fail=1
  else
    echo "  $pt_n per-track path(s) checked · none in the bypass state ✓"
  fi
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
# ⛔ A MAILBOX FILE THAT LANDED SOMEWHERE ELSE (REV-134 §3, REV-135 §4).
#
# Run 129 wrote REPORT.md to the repo ROOT. The block above reads only the
# mailbox copy, so it advertised run 128's report as current and would have gone
# on doing so indefinitely — the tick's own session-start step 2 ("is REPORT.md
# newer than the last REVIEWS block?") resolves against that same stale path.
# The damage is silent and it compounds every tick.
#
# ⭐ What was missing is the DISPLAY, not the file: a stray copy at the root is
#   now reported beside the real one, with both timestamps, so no reader can
#   mistake which is which. Run 130 left one behind — the positive control.
for f in BRIEF REPORT REVIEWS; do
  s="$ROOT/$f.md"
  if [ -f "$s" ]; then
    fail=1
    printf '    ⛔ %s at the REPO ROOT %s  %4s lines — the mailbox is .agents/supervisor/%s\n' \
      "$f.md" "$(date -r "$s" '+%F %T')" "$(wc -l <"$s")" "$f.md"
    echo "       a mailbox file outside the mailbox is read by nobody and goes stale silently"
  fi
done

# ⛔ A WAVE ARTEFACT THAT RECORDS A SHELL ERROR INSTEAD OF A MEASUREMENT (REV-137 §1).
#
# Run 132's `.agents/supervisor/r132-doctor.txt` was 40 bytes and read
# `bash: line 1: goaiez: command not found`. The stage dump never ran — and
# REPORT.md, written 41 seconds later, said "All stages clean. No stage moved."
# and cited that file. A redirect is only an instrument if the command that
# fills it actually runs; an empty-of-measurement artefact is worse than a
# missing one, because its existence reads as evidence.
#
# ⭐ The same read catches the other half: run 132's r132-seam.txt carried
#   `grep: app/routes/routes.generated.php: No such file or directory` — the
#   brief had named a path that does not exist — and the report converted that
#   error into "the file does not exist", a statement about the tree. A grep
#   error in an artefact is a REFUSED-shaped event, and the reviewer must see it
#   without opening every file.
#
# Scoped to the last 24h so yesterday's artefacts age out on their own, and it
# prints the count it scanned so a clean reading is legible — a check that only
# ever prints nothing is indistinguishable from a check that is not running.
#
# ⛔ AND IT FLAGGED ITS OWN OUTPUT ON ITS SECOND RUN (REV-138 §1). A gate log is
#   an artefact matching `r*-*`, and this block PRINTS the offending line
#   verbatim, so run 133's `r133-gate.log:102` carried
#   `bash: line 1: goaiez: command not found` — quoted from r132-doctor.txt by
#   this very loop. The count went 2 → 3 for a benign reason, which is the
#   failure mode of a permanent ⛔: it trains the reader to skip the section that
#   would show a real one.
#
#   The fix is NOT to skip `*-gate.log` — a gate log can carry a real error from
#   a command inside the gate, and excluding the file would blind the check to
#   exactly that. Instead the detector's OWN output block is blanked before the
#   scan: `s/.*//` over the range from its `carries a shell/grep error` line to
#   its `wave artefacts` footer, which preserves line numbering so the number it
#   reports stays truthful. Nothing but this loop's output lives in that range.
_art_n=0; _art_bad=0
for p in $(find "$ROOT/.agents/supervisor" -maxdepth 1 -name 'r*-*' -type f -mmin -1440 2>/dev/null | sort); do
  _art_n=$((_art_n + 1))
  hit=$(sed '/carries a shell\/grep error at line/,/wave artefacts (24h):/s/.*//' "$p" 2>/dev/null \
        | grep -m1 -nE 'command not found|No such file or directory|Permission denied|: syntax error|Could not open input file' || true)
  [ -n "$hit" ] || continue
  _art_bad=$((_art_bad + 1)); fail=1
  printf '    ⛔ %s carries a shell/grep error at line %s\n' "${p##*/}" "${hit%%:*}"
  printf '       %s\n' "$(printf '%s' "$hit" | cut -d: -f2- | cut -c1-100)"
done
if [ "$_art_n" -gt 0 ]; then
  printf '  wave artefacts (24h): %s scanned · %s carrying an error\n' "$_art_n" "$_art_bad"
  [ "$_art_bad" -gt 0 ] && echo "       a redirect whose command did not run is not a measurement — the report must not cite it"
fi

# ⭐ `schema` IS REPORTED WITH THE DATABASE IT WAS READ FROM, OR IT IS NOT REPORTED
#   (REV-119 §B) — AND THAT INSTRUCTION IS NOW EMITTED RATHER THAN RESTATED.
#
# SchemaStage reads .env's live database, not the tree, so its count is not a
# property of a sha and every lane measures a different number on the identical
# commit. The rule has been handed to the coder as a citation (REV-132 §3, missed),
# as a firmer citation (REV-134 §5, missed), as a paste-ready literal string
# (REV-135, emitted correctly — the rung that worked) and as that same literal
# again (REV-138 §2, missed, because "STAGES: none moved" needs no line at all).
#
# ⭐ The ladder this lane has now measured four times: a paste-ready string beats a
#   citation, a redirect beats a paste-ready string, and a CHECK beats a redirect —
#   because a check cannot be omitted by a report that summarises. The annotated
#   line is produced here, from the coder's own doctor dump, so the report quotes a
#   line that already carries the database and cannot paraphrase the annotation
#   away. A brief fix protects one run; a check protects every run (REV-135 §4).
_doc=$(find "$ROOT/.agents/supervisor" -maxdepth 1 -name 'r*-doctor.txt' -type f -mmin -1440 2>/dev/null | sort | tail -1)
if [ -n "$_doc" ]; then
  _sch=$(grep -m1 -E '(ok|FAIL) schema ' "$_doc" 2>/dev/null | sed 's/^[[:space:]]*//' || true)
  if [ -n "$_sch" ]; then
    printf '  schema, annotated for the report (REV-119 §B) — from %s:\n' "${_doc##*/}"
    printf '    %s   (read from %s, per app/.env)\n' "$_sch" "${env_db:-<unset>}"
  else
    printf '  ⚠ %s carries no schema line — the stage dump did not reach it\n' "${_doc##*/}"
  fi
fi

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
  local out rc started tmp tpid child jobpid
  started=$(date -Is)
  # Run in the background solely to capture the TOOL's pid. An agent killing a
  # suite kills the tool — that is the pid it sees in `ps` — so the tool pid is the
  # join key against kill-log.tsv; the gate pid only groups a run's rows. Neither is
  # recoverable after the fact (sibling project, 2026-09-06).
  tmp=$(mktemp "${TMPDIR:-/tmp}/gate-XXXXXX")
  "$@" > "$tmp" 2>&1 &
  jobpid=$!; tpid=$jobpid
  child=$(pgrep -P "$jobpid" 2>/dev/null | head -1)  # through a `timeout` wrapper
  [ -n "$child" ] && tpid=$child
  wait "$jobpid"; rc=$?
  out=$(cat "$tmp"); rm -f "$tmp"
  log_gate "$label" "$started" "$rc" "$tpid"
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
  bar "7. test suite  (effective DB_DATABASE → $lane_db; phpunit.xml pins $xml_db)"
  # REV-119: the clash guard must ask about the database pest will REALLY use, not
  # the one phpunit.xml pins — otherwise it scans siblings for a name nobody runs on.
  xml_db="$lane_db"
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
        # NAME THE HOLDER (sixty TRACK 1 ACTION 2, adopted by Track 1 2026-09-08). Until now
        # this line printed the path and the timeout and never said WHO held it. A lane that
        # cannot see what it is waiting on has a standing incentive to route around the wait,
        # and on sixty's tick 251 that cost the wave its entire mutation set — the ask was
        # filed as hygiene and was re-filed once it had cost a wave.
        #
        # Scanned from /proc, not from `fuser`/`lsof`: that is §1b's idiom, which is known to
        # work on this box, and it needs no tool whose presence this seat cannot even test
        # (`command -v` is denied here). All seven lanes run as one account, so the fds of the
        # process we are actually waiting on are readable.
        #
        # ⚠ OUR OWN fd 9 IS ON THIS FILE — the row that is legitimate by construction, which
        # every detector in this repo has been bitten by at least once. $$ is excluded by name,
        # exactly as §1b excludes it. Fails open in every arm: an unreadable /proc, a vanished
        # pid, or a holder on another account all fall back to the old message; nothing here
        # can break the gate or shorten the wait.
        echo "  … another suite holds $PEST_LOCK — waiting up to 40 min (never killing it)"
        held_by=0
        for lp in /proc/[0-9]*; do
          lpn=${lp#/proc/}
          [ "$lpn" = "$$" ] && continue
          for lfd in "$lp"/fd/*; do
            [ "$(readlink "$lfd" 2>/dev/null)" = "$PEST_LOCK" ] || continue
            lcwd=$(readlink "$lp/cwd" 2>/dev/null)
            lcmd=$(tr '\0' ' ' < "$lp/cmdline" 2>/dev/null)
            echo "      holder pid $lpn  cwd ${lcwd:-?}  ${lcmd:0:100}"
            held_by=$((held_by+1))
            break
          done
        done
        if [ "$held_by" -eq 0 ]; then
          echo "      (holder not identifiable from /proc — it may belong to another account)"
        fi
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
  pest_started=$(date -Is)
  ptmp=$(mktemp "${TMPDIR:-/tmp}/pest-XXXXXX"); DB_DATABASE="$lane_db" timeout 1800 ./vendor/bin/pest > "$ptmp" 2>&1 & pjob=$!; pest_pid=$(pgrep -P "$pjob" 2>/dev/null | head -1); pest_pid=${pest_pid:-$pjob}; wait "$pjob"; rc=$?; out=$(cat "$ptmp"); rm -f "$ptmp"
  log_gate pest "$pest_started" "$rc" "${pest_pid:--}"
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
# FAILURES ARE NEVER TRUNCATED SILENTLY (N137, 2026-09-08, wave 155). This loop
# read [:5] while the errors loop below it printed a "… N more" line, so a suite
# with FAILED 10 named five and said nothing about the other five — and the brief
# that run was graded on had "a FAILED name not in the expected list" as its STOP
# condition, a question this printer could not answer. An instrument that can only
# under-report is safe as a trigger and unsafe as a finding. Both lists now carry
# their own overflow line, and a FAILURE prints its message like an error does.
FCAP=40
fails=d.get("failures") or []
errs=d.get("error_details") or []
for f in fails[:FCAP]:
    print("   ✗ FAILURE %s" % f.get("test","?").split("::")[-1])
    fm=" ".join((f.get("message") or "").split())
    if fm: print("      %s" % fm[:200])
if len(fails)>FCAP: print("   … %d more FAILURE(s) not listed" % (len(fails)-FCAP))
for e in errs[:FCAP]:
    print("   ✗ %s\n      %s" % (e.get("test","?").split("::")[-1], (e.get("message") or "")[:160]))
if len(errs)>FCAP: print("   … %d more ERROR(s) not listed" % (len(errs)-FCAP))'
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
