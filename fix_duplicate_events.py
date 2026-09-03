import re

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    content = f.read()

def remove_emit(mod, event):
    global content
    pattern = r'(`@module ' + mod + r'`.*?\n)(.*?)(?=\n`@module|\Z)'
    def repl(m):
        header = m.group(1)
        body = m.group(2)
        body = re.sub(r'(\s*·\s*@emits\s+.*?)\b' + re.escape(event) + r'\b(.*?)\n', r'\1\2\n', body)
        body = re.sub(r'(\s*·\s*@emits\s+.*?)\s*·\s*·\s*', r'\1 · ', body)
        body = re.sub(r'(\s*·\s*@emits\s+.*?)\s*·\s*\n', r'\1\n', body)
        body = re.sub(r'\s*·\s*@emits\s+\n', '\n', body)
        return header + body
    
    content = re.sub(pattern, repl, content, flags=re.DOTALL)

for mod in ['X-190', 'X-103', 'X-205', 'X-208']: remove_emit(mod, 'approval.requested')
for mod in ['C-Agent', 'X-207', 'X-186', 'X-127', 'X-217', 'X-218']: remove_emit(mod, 'send.requested')
remove_emit('X-118', 'win.first')
for event in ['campaign.sent', 'campaign.replied', 'sequence.stopped']: remove_emit('X-186', event)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(content)

