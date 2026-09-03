import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target1 = "`@emits research.completed · icebreaker.generated · signal.found`"
replacement1 = "`@emits research.completed · icebreaker.generated · signal.found · prospect.scored`"
text = text.replace(target1, replacement1)

target2 = "`@ingress prospect.scored <api>` ⭐ *(2026-08-27 — **§235's own prescription, applied for the first time.** §235 classified these and a later line CLAIMED \"13 @ingress · 5 @scheduled WITH OWNERS\" were done; **measured: 0 of 41 had ever reached a module header.** An ingress event has no emitter BY DESIGN; an unowned scheduled event is a cron job nobody notices has stopped.)*"
replacement2 = ""
text = text.replace(target2, replacement2)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
