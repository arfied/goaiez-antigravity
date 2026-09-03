import re
with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    text = f.read()

caps = ["G1-43", "G3-18", "G9-21", "G10-28", "G11-06", "G11-09", "G11-11", "G11-12", "G11-15", "G11-16", "G11-17", "G11-18", "G11-29", "G11-38", "G15-31"]

for cap in caps:
    # Let's find every line containing the cap and append the refusal if not there
    lines = text.split('\n')
    new_lines = []
    for line in lines:
        if cap in line and '|' in line and "REFUSES with" not in line:
            # Only if it looks like a table row
            if line.strip().startswith('|'):
                line = line.replace(' |', ' · ⛔ **REFUSES with CHANNEL_REJECTED** |')
        new_lines.append(line)
    text = '\n'.join(new_lines)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(text)
