import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

text = text.replace(
    "`@emits post.published · comment.received · comment.escalated · `@consumes",
    "`@emits post.published · comment.received · comment.escalated` · `@consumes"
)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
