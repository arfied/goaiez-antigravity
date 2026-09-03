import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

# Fix X-186
target_x186 = "`@provides campaign.create · campaign.run · sequence.stop` · `@emits campaign.sent · campaign.replied · sequence.stopped · campaign.exhausted · send.requested`"
replacement_x186 = "`@provides campaign.exhaust · send.request` · `@emits campaign.exhausted · send.requested`"
text = text.replace(target_x186, replacement_x186)

# Fix X-185 missing ingress events in EventBus (X-123)
target_x123 = "`@emits any.event`"
replacement_x123 = "`@emits any.event · entity.state_changed · form.abandoned · message.received · tenant.created · signal.detected`"
text = text.replace(target_x123, replacement_x123)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
