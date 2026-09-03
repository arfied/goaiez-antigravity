import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@emits event.published · event.failed · event.dead_lettered`"
replacement = "`@emits event.published · event.failed · event.dead_lettered · any.event`"

text = text.replace(target, replacement)

# Wait, X-156 had this:
# `@emits ingested.normalised · ingest.rejected · `@consumes any.event ⭐ *(...)*
# It has a broken backtick on `@emits`!
# Let's fix that too.
t2 = "`@emits ingested.normalised · ingest.rejected · `@consumes any.event ⭐"
r2 = "`@emits ingested.normalised · ingest.rejected` · `@consumes any.event` ⭐"
text = text.replace(t2, r2)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
