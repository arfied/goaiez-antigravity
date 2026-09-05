import json

with open('.agents/supervisor/ASSIGNMENTS.json', 'r') as f:
    assignments = json.load(f)['assignments']

with open('app/GOAIEZ-TRACKER-CAPABILITIES.md', 'r') as f:
    lines = f.readlines()

deferred_ids = [a['id'] for a in assignments if a['kind'] == 'deferred']
print("Deferred IDs:", deferred_ids)

for i, line in enumerate(lines):
    if not line.startswith('|'):
        continue
    parts = line.split('|')
    if len(parts) < 7:
        continue
    row_id = parts[1].strip().replace('**', '')
    if row_id in deferred_ids:
        print(f"Found row: {row_id}")
