#!/usr/bin/env bash
#
# place-files.sh — put the GOAIEZ package and runtime into the live Laravel tree.
#
# ⭐⭐⭐ WORKS FROM A FLAT FOLDER.
#
# When you download 117 files from a chat they arrive in ONE directory with no
# structure: BriefCommand.php sits next to GOAIEZ-MASTER-PLAN.md sits next to
# TwelveJourneysTest.php. There is no app/Console/Commands/ to copy.
#
# ⛔ The first version of this script assumed the directory tree and would have
#    failed on its first line against a real download. This version maps each
#    file BY NAME to its destination, so it works whether your folder is flat,
#    nested, or a mix.
#
#   Usage:
#     bash place-files.sh /path/to/laravel/tree
#     bash place-files.sh /path/to/laravel/tree ~/Downloads/goaiez
#
set -euo pipefail
ok()   { printf '  \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '  \033[33m!\033[0m %s\n' "$*"; }
die()  { printf '\n  \033[31m✗ %s\033[0m\n\n' "$*"; exit 1; }
say()  { printf '\n\033[1m%s\033[0m\n' "$*"; }

DEST="${1:-}"
SRC="${2:-$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)}"

[ -n "$DEST" ] || die "Usage: bash place-files.sh /path/to/laravel/tree [source-folder]"
[ -d "$DEST" ] || die "Not a directory: $DEST"
[ -f "$DEST/artisan" ] || die "No 'artisan' in $DEST — that is not a Laravel root."
[ -d "$SRC" ]  || die "Source folder not found: $SRC"

say "GOAIEZ — placing files"
echo "  from: $SRC"
echo "  into: $DEST"

# ⭐ The map: every runtime file BY NAME → where Laravel expects it.
#   Verified: every filename in the package is unique except ci.yml, which is
#   shipped twice on purpose and handled separately below.
declare -A MAP=(
  [BriefCommand.php]=app/Console/Commands
  [CapabilitiesScaffoldCommand.php]=app/Console/Commands
  [ContextCommand.php]=app/Console/Commands
  [DbBootstrapCommand.php]=app/Console/Commands
  [DeployCheckCommand.php]=app/Console/Commands
  [DoctorCommand.php]=app/Console/Commands
  [FindCommand.php]=app/Console/Commands
  [ImpactCommand.php]=app/Console/Commands
  [MakeModuleCommand.php]=app/Console/Commands
  [MapCommand.php]=app/Console/Commands
  [ModuleDoneCommand.php]=app/Console/Commands
  [ModuleScaffoldCommand.php]=app/Console/Commands
  [WhyCommand.php]=app/Console/Commands
  [DeclarationParser.php]=app/Doctor
  [InstructionLog.php]=app/Doctor
  [Manifest.php]=app/Doctor
  [ManifestReader.php]=app/Doctor
  [BoundaryStage.php]=app/Doctor/Stages
  [CapabilityStage.php]=app/Doctor/Stages
  [CitationStage.php]=app/Doctor/Stages
  [ContractStage.php]=app/Doctor/Stages
  [JourneyStage.php]=app/Doctor/Stages
  [SchemaStage.php]=app/Doctor/Stages
  [Stage.php]=app/Doctor/Stages
  [TestAnchorStage.php]=app/Doctor/Stages
  [GoaiezRuntimeServiceProvider.php]=app/Providers
  [JourneyHarness.php]=tests/Journeys
  [TwelveJourneysTest.php]=tests/Journeys
  [FourPatchesTest.php]=tests/Patches
  [RuntimeProofTest.php]=tests/Runtime
  [crontab]=deploy
  [supervisor.conf]=deploy
  [.env.ci]=.
  [Procfile]=.
  [install-step-0.sh]=.
)

# ── 1. package files (the law) ────────────────────────────────────────
# ⛔⛔ FIVE classes read these from base_path(): module:scaffold,
#    capabilities:scaffold, find, why, CitationStage. Without them the scaffold
#    generates ZERO manifests and reports "0 modules" as though the system were
#    empty. It does not error. It quietly means nothing.
say "1/4  Package files (the law)"
PKGN=0
while IFS= read -r f; do
  cp "$f" "$DEST/$(basename "$f")"; PKGN=$((PKGN+1))
done < <(find "$SRC" -name 'GOAIEZ-*.md' -type f)
[ "$PKGN" -gt 0 ] || die "No GOAIEZ-*.md found under $SRC"
ok "$PKGN package files → the Laravel root"

# ── 2. runtime code, by name ──────────────────────────────────────────
say "2/4  Runtime code"
PHPN=0; MISSING=()
for name in "${!MAP[@]}"; do
  found="$(find "$SRC" -name "$name" -type f 2>/dev/null | head -1)"
  if [ -z "$found" ]; then MISSING+=("$name"); continue; fi
  sub="${MAP[$name]}"
  mkdir -p "$DEST/$sub"
  target="$DEST/$sub/$name"
  # ⭐ Warn on a DIFFERING overwrite. A collision means the live tree and this
  #   package disagree about who owns that path — a finding, not a nuisance.
  if [ -f "$target" ] && ! cmp -s "$found" "$target"; then
    warn "OVERWRITING (differs): $sub/$name"
  fi
  cp "$found" "$target"; PHPN=$((PHPN+1))
done
ok "$PHPN runtime files placed"
if [ ${#MISSING[@]} -gt 0 ]; then
  for m in "${MISSING[@]}"; do warn "NOT FOUND in source: $m"; done
fi

# ── 3. ci.yml — the one duplicated name ───────────────────────────────
say "3/4  CI"
CI="$(find "$SRC" -name 'ci.yml' -type f 2>/dev/null | head -1)"
if [ -n "$CI" ]; then
  mkdir -p "$DEST/.github/workflows"; cp "$CI" "$DEST/.github/workflows/ci.yml"
  ok ".github/workflows/ci.yml"
else
  warn "ci.yml not found — CI will not run; nothing else is affected"
fi

# ── 4. verify — count AFTER the copy ──────────────────────────────────
# ⭐⭐ Every "claimed but never applied" failure in this programme looked
#    exactly like a successful command. So count what actually landed.
say "4/4  Verify"
P=$(ls "$DEST"/GOAIEZ-*.md 2>/dev/null | wc -l | tr -d ' ')
C=$(ls "$DEST"/app/Console/Commands/*.php 2>/dev/null | wc -l | tr -d ' ')
S=$(ls "$DEST"/app/Doctor/Stages/*.php 2>/dev/null | wc -l | tr -d ' ')
D=$(ls "$DEST"/app/Doctor/*.php 2>/dev/null | wc -l | tr -d ' ')
V=$([ -f "$DEST/app/Providers/GoaiezRuntimeServiceProvider.php" ] && echo 1 || echo 0)
T=$(find "$DEST/tests" -name '*.php' 2>/dev/null | wc -l | tr -d ' ')
printf '  package files      %3s\n' "$P"
printf '  commands           %3s  (expect 13)\n' "$C"
printf '  doctor stages      %3s  (expect 8)\n' "$S"
printf '  doctor core        %3s  (expect 4)\n' "$D"
printf '  service provider   %3s  (expect 1)\n' "$V"
printf '  test files         %3s  (expect 4)\n' "$T"

[ "$P" -ge 70 ] || die "Only $P package files landed. Five classes read these — without them the scaffold reports '0 modules'."
[ "$C" -eq 13 ] || die "Expected 13 commands, found $C."
[ "$S" -eq 8 ]  || die "Expected 8 stage files, found $S."
[ "$V" -eq 1 ]  || die "The service provider did not land — NOTHING registers and every artisan command will be missing."

cat <<'NEXT'

────────────────────────────────────────────────────────────────
  ✓ Files placed. Now:

      cd <your laravel tree>
      bash install-step-0.sh

  ⛔ Expect doctor to report violations. That is the system
     working. A first run reporting ZERO would mean the checks
     are not connected to anything.
────────────────────────────────────────────────────────────────
NEXT
