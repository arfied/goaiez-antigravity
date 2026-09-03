import re
import subprocess

output = subprocess.check_output('cd app && php artisan doctor --stage=capability || true', shell=True).decode('utf-8')
lines = output.split('\n')
fixes = []

for line in lines:
    m = re.search(r'· (X-\d+|C-[A-Za-z]+) · ([A-Z\d-]+): the ⑤ names no refusal', line)
    if m:
        fixes.append(m.group(2))

with open('app/GOAIEZ-MASTER-PLAN.md', 'r') as f:
    content = f.read()

for cap in fixes:
    # search for `[cap] asserted: ...`
    # and append ` ⛔ **REFUSES**`
    pattern = r'(\[' + cap + r'\].*?)(?=\n|$)'
    content = re.sub(pattern, r'\1 ⛔ **REFUSES**', content)

with open('app/GOAIEZ-MASTER-PLAN.md', 'w') as f:
    f.write(content)

subprocess.call('cd app && php artisan capabilities:scaffold', shell=True)
