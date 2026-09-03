import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@emits event.published · event.failed · event.dead_lettered · any.event`"
replacement = "`@emits event.published · event.failed · event.dead_lettered · any.event · message.received · entity.state_changed`"
text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
