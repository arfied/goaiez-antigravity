import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

target = "`@provides research.run · icebreaker.generate` · `@emits research.completed · icebreaker.generated · signal.found` · `\n\n@agent_reachable"
replacement = "`@provides research.run · icebreaker.generate` · `@emits research.completed · icebreaker.generated · signal.found` · `@consumes capability.decided` · `@owns_table research_runs · icebreakers · prospect_signals`\n\n@agent_reachable"
text = text.replace(target, replacement)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
