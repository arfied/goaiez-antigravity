with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "an estimate tile with no tenant-entered job value renders **violet-dashed and cannot toggle live**, asserted; a tile with no row in the metric-definitions ledger **cannot ship**",
    "an estimate tile with no tenant-entered job value renders **violet-dashed and cannot toggle live**, asserted; a tile with no row in the metric-definitions ledger **cannot ship** · ⛔ **REFUSES with DATA_BLUR**"
)

text = text.replace(
    "an end-of-day figure for two locations in different zones matches each location's own local day, asserted",
    "an end-of-day figure for two locations in different zones matches each location's own local day, asserted · ⛔ **REFUSES with TIMEZONE_MISMATCH**"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
