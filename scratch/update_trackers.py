import json
import re

with open('.agents/supervisor/ASSIGNMENTS.json', 'r') as f:
    assignments = json.load(f)['assignments']

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    lines = f.readlines()

for i, line in enumerate(lines):
    if not line.startswith('|'):
        continue
    parts = line.split('|')
    if len(parts) < 7:
        continue
    row_id = parts[1].replace('⭐', '').strip().replace('**', '')
    
    match = next((a for a in assignments if a['id'] == row_id), None)
    if match:
        if match['kind'] in ['new module', 'folded into']:
            old_val = parts[4].strip()
            to_val = match['to']
            if old_val == '':
                parts[4] = f" {to_val} "
            elif old_val != to_val and old_val != '—':
                parts[4] = f" {old_val} → {to_val} "
            else:
                parts[4] = f" {to_val} "
        elif match['kind'] == 'deferred':
            parts[5] = ' DEFERRED '
            
        lines[i] = '|' + '|'.join(parts[1:])

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'w') as f:
    f.writelines(lines)

deferred_mods = ['X-200', 'X-158', 'X-159', 'X-114', 'X-144', 'X-197', 'X-147', 'X-143', 'X-141', 'X-145', 'X-213', 'X-208', 'X-215', 'X-214']

with open('app/GOAIEZ-TRACKER-MODULES.md', 'r') as f:
    mod_lines = f.readlines()

for i, line in enumerate(mod_lines):
    if not line.startswith('|'):
        continue
    parts = line.split('|')
    if len(parts) < 4:
        continue
    
    m = re.search(r'X-\d+', parts[1])
    if not m:
        continue
    mod_id = m.group(0)
    
    if mod_id in deferred_mods:
        old_val = parts[3].strip()
        if not old_val.startswith('⏸ DEFERRED'):
            parts[3] = f" ⏸ DEFERRED · {old_val} "
        mod_lines[i] = '|' + '|'.join(parts[1:])

# We already added X-221, X-222, X-223 in the previous run.
# Wait, did we restore GOAIEZ-TRACKER-MODULES.md with `git show HEAD~1...`?
# Yes, we did! So they are not added yet.

new_mods = [
    "| **X-221** | AdsAdvisor | GROW | G14 | SQ-12 · W3 | Finished **§257.1** | HDR ✅ | CAPS ☐ | SITE ☐ | OMNI ☐ |\n",
    "| **X-222** | LegalDesk | PROTECT | G10 | SQ-0 · W2 | Finished **§257.2** | HDR ✅ | CAPS ☐ | SITE ☐ | OMNI ☐ |\n",
    "| **X-223** | WarmupEngine | GROW | G11 | SQ-1 · W2 | Finished **§257.3** | HDR ✅ | CAPS ☐ | SITE ☐ | OMNI ☐ |\n"
]
mod_lines.extend(new_mods)

with open('app/GOAIEZ-TRACKER-MODULES.md', 'w') as f:
    f.writelines(mod_lines)
